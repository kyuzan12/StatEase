<?php

namespace Tests\Feature;

use App\Models\LikertScale;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Accounts and privacy: logging in, and making sure users only reach their own data.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "Sex,Q1,Q2\nFemale,4,3\nMale,3,3\nFemale,4,2\n";

    /** Upload a survey as the given user and return it. */
    private function surveyOf(User $user): Survey
    {
        $this->actingAs($user)->post(route('surveys.store'), [
            'title' => "Survey of {$user->name}",
            'likert_scale_id' => LikertScale::where('name', '4-point Agreement')->value('id'),
            'survey_file' => UploadedFile::fake()->createWithContent('survey.csv', self::CSV),
        ])->assertSessionHasNoErrors();

        return $user->surveys()->latest('id')->firstOrFail();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/surveys')->assertRedirect(route('login'));
        $this->get('/scales')->assertRedirect(route('login'));
        $this->get('/slovin')->assertRedirect(route('login'));
    }

    public function test_a_visitor_can_register_and_is_logged_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('surveys.index'));

        $this->assertAuthenticated();
        $this->assertNotSame('secret123', User::first()->password); // stored as a hash, never as typed
    }

    public function test_registration_is_validated(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('weakPasswords')]
    public function test_weak_or_invalid_passwords_are_refused(string $password, string $confirmation): void
    {
        $this->post(route('register'), [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public static function weakPasswords(): array
    {
        return [
            'shorter than 8 characters' => ['abc123', 'abc123'],
            'no numbers' => ['onlyletters', 'onlyletters'],
            'no letters' => ['12345678', '12345678'],
            'longer than 72 characters' => [str_repeat('a1', 37), str_repeat('a1', 37)],
            'confirmation does not match' => ['secret123', 'secret124'],
        ];
    }

    public function test_emails_are_not_case_sensitive(): void
    {
        $this->post(route('register'), [
            'name' => 'Ana Reyes',
            'email' => '  Ana@Example.COM ',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
        $this->assertSame('ana@example.com', User::first()->email); // stored in lowercase, spaces removed
        $this->post(route('logout'));

        // The same address in other letters cannot register again...
        $this->post(route('register'), [
            'name' => 'Someone Else',
            'email' => 'ANA@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('email');

        // ...but can log in.
        $this->post(route('login'), ['email' => 'ANA@EXAMPLE.com', 'password' => 'secret123'])
            ->assertRedirect(route('surveys.index'));
        $this->assertAuthenticated();
    }

    public function test_login_is_limited_to_6_attempts_per_minute(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        foreach (range(1, 6) as $attempt) {
            $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123'])
            ->assertStatus(429); // Too Many Requests, even with the right password
        $this->assertGuest();
    }

    public function test_errors_show_friendly_pages(): void
    {
        $ana = User::factory()->create();
        $survey = $this->surveyOf($ana);

        $this->actingAs(User::factory()->create())
            ->get(route('surveys.show', $survey))
            ->assertForbidden()
            ->assertSee('Not your survey')
            ->assertSee('Back to My Surveys');

        $this->get('/surveys/999999')->assertNotFound()->assertSee('Page not found');
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('surveys.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'The email or password is incorrect.']);

        $this->assertGuest();
    }

    public function test_users_only_see_their_own_surveys(): void
    {
        $ana = User::factory()->create(['name' => 'Ana']);
        $ben = User::factory()->create(['name' => 'Ben']);
        $this->surveyOf($ana);

        $this->actingAs($ben)->get(route('surveys.index'))
            ->assertOk()
            ->assertDontSee('Survey of Ana');
    }

    public function test_a_user_cannot_open_or_delete_another_users_survey(): void
    {
        $ana = User::factory()->create();
        $ben = User::factory()->create();
        $survey = $this->surveyOf($ana);

        $this->actingAs($ben);
        $this->get(route('surveys.show', $survey))->assertForbidden();
        $this->get(route('surveys.columns.edit', $survey))->assertForbidden();
        $this->get(route('surveys.results', $survey))->assertForbidden();
        $this->get(route('surveys.report', $survey))->assertForbidden();
        $this->delete(route('surveys.destroy', $survey))->assertForbidden();

        $this->assertModelExists($survey);
    }

    public function test_built_in_scales_are_read_only_and_custom_scales_are_private(): void
    {
        $ana = User::factory()->create();
        $ben = User::factory()->create();
        $builtIn = LikertScale::where('name', '5-point Agreement')->firstOrFail();
        $anasScale = LikertScale::create(['user_id' => $ana->id, 'name' => "Ana's scale", 'points' => 2]);

        $this->actingAs($ben);
        $this->get(route('scales.edit', $builtIn))->assertForbidden();
        $this->delete(route('scales.destroy', $builtIn))->assertForbidden();
        $this->get(route('scales.edit', $anasScale))->assertForbidden();
        $this->get(route('scales.index'))->assertDontSee("Ana's scale");

        // Ben cannot sneak Ana's scale into his own upload either
        $this->post(route('surveys.store'), [
            'title' => 'Sneaky',
            'likert_scale_id' => $anasScale->id,
            'survey_file' => UploadedFile::fake()->createWithContent('survey.csv', self::CSV),
        ])->assertSessionHasErrors('likert_scale_id');
    }

    public function test_the_first_account_receives_the_data_made_before_accounts_existed(): void
    {
        $oldScale = LikertScale::create(['name' => '10-point Satisfaction', 'points' => 2]);
        $oldSurvey = Survey::create(['title' => 'Old survey', 'likert_scale_id' => $oldScale->id, 'original_filename' => 'old.csv']);

        $this->post(route('register'), [
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $first = User::where('email', 'first@example.com')->firstOrFail();
        $this->assertSame($first->id, $oldSurvey->fresh()->user_id);
        $this->assertSame($first->id, $oldScale->fresh()->user_id);
        $this->assertNull(LikertScale::where('name', '5-point Agreement')->value('user_id')); // built-ins stay shared
    }
}
