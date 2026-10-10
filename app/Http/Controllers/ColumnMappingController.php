<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateColumnMappingRequest;
use App\Models\LikertScale;
use App\Models\Survey;
use Illuminate\Support\Facades\DB;

class ColumnMappingController extends Controller
{
    /**
     * Show every column with a dropdown to choose its type.
     */
    public function edit(Survey $survey)
    {
        $survey->load('columns');
        $respondents = $survey->respondents()->get(['answers']);

        // A few example answers per column, to help the user decide its type.
        $samples = [];
        foreach ($survey->columns as $column) {
            $samples[$column->id] = $respondents
                ->map(fn ($respondent) => $respondent->answerFor($column))
                ->filter(fn ($answer) => $answer !== null && $answer !== '')
                ->unique()
                ->take(4)
                ->values();
        }

        $scales = LikertScale::visibleTo(auth()->user())->with('levels')->orderBy('points')->orderBy('name')->get();

        return view('surveys.columns', compact('survey', 'samples', 'scales'));
    }

    /**
     * Save the chosen column types and mark the survey as ready for analysis.
     */
    public function update(UpdateColumnMappingRequest $request, Survey $survey)
    {
        $types = $request->validated('columns');

        DB::transaction(function () use ($request, $survey, $types) {
            foreach ($survey->columns as $column) {
                $column->update(['type' => $types[$column->id]]);
            }

            $survey->update([
                'likert_scale_id' => $request->validated('likert_scale_id'),
                'is_mapped' => true,
            ]);
        });

        return redirect()
            ->route('surveys.results', $survey)
            ->with('success', 'Column types saved. Here are your results.');
    }
}
