<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Services\NarrativeService;
use App\Services\ReliabilityService;
use App\Services\ResponseMatrix;
use App\Services\StatisticsService;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReportController extends Controller
{
    /**
     * Download the results as a Word document (.doc): every table with its interpretation,
     * ready to paste into the results chapter.
     *
     * Word can open an HTML file saved with a .doc extension, so the report is a Blade view
     * sent with the Word content type. No extra package is needed.
     */
    public function word(Survey $survey, StatisticsService $statistics, NarrativeService $narrative, ReliabilityService $reliability)
    {
        $survey->load('scale.levels');
        $results = $statistics->fullResults($survey);

        $narratives = [
            'profiles' => collect($results['profiles'])->map(fn ($profile, $i) => $narrative->profile($i + 1, $profile))->all(),
            'likert' => $narrative->likert($results['likert_table_number'], $results['likert']),
        ];

        // Reliability of all Likert items, if it can be computed
        $reliabilityResult = $reliabilityParagraph = null;
        try {
            $reliabilityResult = $reliability->analyze(ResponseMatrix::for($survey), $survey->likertColumns()->get());
            $reliabilityParagraph = $narrative->reliability($reliabilityResult);
        } catch (InvalidArgumentException) {
            // Not enough items or respondents: the report simply leaves this section out.
        }

        $html = view('reports.word', $results + [
            'survey' => $survey,
            'narratives' => $narratives,
            'reliability' => $reliabilityResult,
            'reliabilityParagraph' => $reliabilityParagraph,
        ])->render();

        $filename = Str::slug($survey->title).'-results.doc';

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
