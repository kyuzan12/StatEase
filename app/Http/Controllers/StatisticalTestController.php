<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunTestRequest;
use App\Models\Survey;
use App\Services\InferentialService;
use App\Services\NarrativeService;
use App\Services\ResponseMatrix;
use InvalidArgumentException;

class StatisticalTestController extends Controller
{
    /**
     * The test advisor: the user describes their research question, StatEase recommends the test.
     */
    public function index(Survey $survey)
    {
        $survey->load('columns');

        return view('tests.index', [
            'survey' => $survey,
            'profiles' => $survey->columns->where('type', 'profile')->values(),
            'items' => $survey->columns->where('type', 'likert')->values(),
        ]);
    }

    /**
     * Run the chosen test and show the result with its interpretation.
     */
    public function run(RunTestRequest $request, Survey $survey, InferentialService $inferential, NarrativeService $narrative)
    {
        $test = $request->validated('test');
        $alpha = (float) $request->validated('alpha');
        $a = $request->variable('a');
        $b = $request->variable('b');
        $matrix = ResponseMatrix::for($survey);

        try {
            $result = match ($test) {
                InferentialService::TEST_CHI_SQUARE => $inferential->chiSquare($matrix, $a['column'], $b['column']),
                InferentialService::TEST_ANOVA => $inferential->anova($matrix->categories($a['column']), $this->scores($matrix, $b)),
                InferentialService::TEST_PEARSON => $inferential->pearson($this->scores($matrix, $a), $this->scores($matrix, $b)),
            };
        } catch (InvalidArgumentException $e) {
            // The data cannot be tested this way (e.g. only one group). Explain it on the advisor page.
            return redirect()
                ->route('surveys.tests.index', $survey)
                ->withInput()
                ->withErrors(['test' => $e->getMessage()]);
        }

        return view('tests.result', [
            'survey' => $survey,
            'result' => $result,
            'a' => $a,
            'b' => $b,
            'alpha' => $alpha,
            'significant' => $result['p'] < $alpha,
            'hypotheses' => $narrative->hypotheses($test, $a['label'], $b['label']),
            'conclusion' => $narrative->testConclusion($result, $a['label'], $b['label'], $alpha),
            'narrative' => $narrative,
        ]);
    }

    /**
     * Numeric scores of a variable: one Likert item, or each respondent's overall average.
     */
    private function scores(ResponseMatrix $matrix, array $variable): array
    {
        return $variable['kind'] === 'overall'
            ? $matrix->overallScores()
            : $matrix->scores($variable['column']);
    }
}
