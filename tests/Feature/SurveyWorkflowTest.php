<?php

namespace Tests\Feature;

use App\Models\LikertScale;
use App\Models\Survey;
use App\Models\SurveyColumn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The whole StatEase workflow, run against a temporary in-memory database
 * (your real statease_db is never touched). Run with: php artisan test
 */
class SurveyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** Every test runs as a logged-in user. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * 4 respondents. Q1 = (4 + 3 + 4 + 2) / 4 = 3.25 · Q2 = (3 + 3 + 2) / 3 = 2.67 (one blank).
     */
    private const CSV = "Timestamp,Name,Sex,Q1,Q2\n"
        ."2026/09/01 8:00,Ana,Female,4,3\n"
        ."2026/09/01 8:05,Ben,Male,3,3\n"
        ."2026/09/01 8:10,Cara,Female,4,2\n"
        ."2026/09/01 8:15,Dan,Male,2,\n";

    private function scaleId(string $name = '4-point Agreement'): int
    {
        return LikertScale::where('name', $name)->value('id');
    }

    private function upload(string $content = self::CSV, string $filename = 'survey.csv'): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('surveys.store'), [
            'title' => 'Test survey',
            'likert_scale_id' => $this->scaleId(),
            'survey_file' => UploadedFile::fake()->createWithContent($filename, $content),
        ]);
    }

    /** Upload the sample CSV and save the guessed column types. */
    private function readySurvey(): Survey
    {
        $this->upload();
        $survey = Survey::firstOrFail();

        $this->put(route('surveys.columns.update', $survey), [
            'likert_scale_id' => $this->scaleId(),
            'columns' => $survey->columns->pluck('type', 'id')->all(),
        ])->assertRedirect(route('surveys.results', $survey));

        return $survey->fresh();
    }

    public function test_uploading_a_csv_saves_the_survey_and_guesses_column_types(): void
    {
        $response = $this->upload();

        $survey = Survey::firstOrFail();
        $response->assertRedirect(route('surveys.columns.edit', $survey));

        $this->assertSame(4, $survey->respondent_count);
        $this->assertSame(
            ['Timestamp' => 'ignore', 'Name' => 'ignore', 'Sex' => 'profile', 'Q1' => 'likert', 'Q2' => 'likert'],
            $survey->columns->pluck('type', 'name')->all()
        );
    }

    public function test_an_excel_file_can_be_uploaded(): void
    {
        $path = base_path('test-files/library-satisfaction-40.xlsx');
        $this->assertFileExists($path);

        $this->post(route('surveys.store'), [
            'title' => 'Library survey',
            'likert_scale_id' => $this->scaleId('5-point Satisfaction'),
            'survey_file' => new UploadedFile($path, 'library.xlsx', null, null, true),
        ])->assertSessionHasNoErrors();

        $survey = Survey::firstOrFail();
        $this->assertSame(40, $survey->respondent_count);
        $this->assertSame(6, $survey->columns->where('type', SurveyColumn::TYPE_LIKERT)->count());
    }

    public function test_the_upload_form_requires_a_title_a_scale_and_a_file(): void
    {
        $this->post(route('surveys.store'), [])
            ->assertSessionHasErrors(['title', 'likert_scale_id', 'survey_file']);

        $this->assertSame(0, Survey::count());
    }

    public function test_files_that_are_not_csv_or_excel_are_rejected(): void
    {
        $this->upload('%PDF-1.4', 'notes.pdf')->assertSessionHasErrors('survey_file');
        $this->upload('not a zip', 'fake.xlsx')->assertSessionHasErrors('survey_file');

        $this->assertSame(0, Survey::count());
    }

    public function test_a_file_with_headers_but_no_responses_is_rejected(): void
    {
        $this->upload("Sex,Q1\n\n")->assertSessionHasErrors([
            'survey_file' => 'The file has a header row but no responses.',
        ]);
    }

    public function test_a_column_with_text_answers_cannot_be_saved_as_a_likert_item(): void
    {
        $this->upload();
        $survey = Survey::firstOrFail();
        $types = $survey->columns->pluck('type', 'id')->all();
        $sex = $survey->columns->firstWhere('name', 'Sex');
        $types[$sex->id] = SurveyColumn::TYPE_LIKERT;

        $this->put(route('surveys.columns.update', $survey), ['likert_scale_id' => $this->scaleId(), 'columns' => $types])
            ->assertSessionHasErrors("columns.{$sex->id}");

        $this->assertFalse($survey->fresh()->is_mapped);
    }

    public function test_results_show_the_weighted_mean_and_interpretation(): void
    {
        $survey = $this->readySurvey();

        $this->get(route('surveys.results', $survey))
            ->assertOk()
            ->assertSee('3.25')                       // Q1 = 13 / 4
            ->assertSee('2.67')                       // Q2 = 8 / 3, the blank is left out
            ->assertSee('Overall Weighted Mean')
            ->assertSee('Table 2 presents the weighted mean');  // Table 1 = Sex, then the Likert table
    }

    public function test_analysis_pages_wait_until_the_column_types_are_confirmed(): void
    {
        $this->upload();
        $survey = Survey::firstOrFail();

        foreach (['surveys.results', 'surveys.reliability', 'surveys.tests.index', 'surveys.report'] as $route) {
            $this->get(route($route, $survey))->assertRedirect(route('surveys.columns.edit', $survey));
        }
    }

    public function test_reliability_and_word_report_are_available_once_ready(): void
    {
        $survey = $this->readySurvey();

        $this->get(route('surveys.reliability', $survey))->assertOk()->assertSee("Cronbach's alpha");

        $this->get(route('surveys.report', $survey))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/msword; charset=UTF-8')
            ->assertSee('Weighted Mean, Verbal Interpretation and Rank of the Items');
    }

    public function test_a_statistical_test_runs_and_invalid_variables_are_refused(): void
    {
        $survey = $this->readySurvey();
        $sex = $survey->columns->firstWhere('name', 'Sex');

        $this->get(route('surveys.tests.run', [$survey, 'test' => 'anova', 'a' => "col:{$sex->id}", 'b' => 'overall', 'alpha' => '0.05']))
            ->assertOk()
            ->assertSee('One-way ANOVA');

        // "overall" is a score, not a group, so it cannot be ANOVA's grouping variable.
        $this->get(route('surveys.tests.run', [$survey, 'test' => 'anova', 'a' => 'overall', 'b' => "col:{$sex->id}", 'alpha' => '0.05']))
            ->assertRedirect(route('surveys.tests.index', $survey))
            ->assertSessionHasErrors('a');
    }

    public function test_a_scale_used_by_a_survey_cannot_be_deleted(): void
    {
        // The user's own 4-point scale, used by an uploaded survey
        $scale = LikertScale::create(['user_id' => auth()->id(), 'name' => 'My 4-point', 'points' => 4]);
        foreach (['SD', 'D', 'A', 'SA'] as $i => $label) {
            $scale->levels()->create(['value' => $i + 1, 'label' => $label]);
        }
        $this->post(route('surveys.store'), [
            'title' => 'Test survey',
            'likert_scale_id' => $scale->id,
            'survey_file' => UploadedFile::fake()->createWithContent('survey.csv', self::CSV),
        ])->assertSessionHasNoErrors();

        $this->delete(route('scales.destroy', $scale))->assertSessionHas('error');

        $this->assertModelExists($scale);
    }

    public function test_deleting_a_survey_also_deletes_its_columns_and_responses(): void
    {
        $survey = $this->readySurvey();

        $this->delete(route('surveys.destroy', $survey))->assertRedirect(route('surveys.index'));

        $this->assertSame(0, Survey::count());
        $this->assertDatabaseCount('survey_columns', 0);
        $this->assertDatabaseCount('respondents', 0);
    }

    public function test_slovin_calculator_shows_the_sample_size_and_validates_input(): void
    {
        $this->post(route('slovin.calculate'), ['population' => 1000, 'margin_of_error' => 5])
            ->assertOk()
            ->assertSee('286 respondents');

        $this->post(route('slovin.calculate'), ['population' => -5, 'margin_of_error' => 80])
            ->assertSessionHasErrors(['population', 'margin_of_error']);
    }
}
