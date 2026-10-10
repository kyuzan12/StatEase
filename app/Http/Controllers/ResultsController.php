<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Services\NarrativeService;
use App\Services\StatisticsService;

class ResultsController extends Controller
{
    /**
     * Compute and show the survey results, with an auto-written interpretation under each table.
     * (The "column types confirmed" check is done by the EnsureSurveyIsMapped middleware.)
     */
    public function show(Survey $survey, StatisticsService $statistics, NarrativeService $narrative)
    {
        $survey->load('scale.levels');
        $results = $statistics->fullResults($survey);

        // Interpretation paragraphs, numbered like the tables (Table 1, Table 2, ...)
        $narratives = [
            'profiles' => collect($results['profiles'])->map(fn ($profile, $i) => $narrative->profile($i + 1, $profile))->all(),
            'likert' => $narrative->likert($results['likert_table_number'], $results['likert']),
        ];

        return view('surveys.results', $results + compact('survey', 'narratives'));
    }
}
