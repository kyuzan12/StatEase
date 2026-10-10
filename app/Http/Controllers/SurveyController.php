<?php

namespace App\Http\Controllers;

use App\Exceptions\SurveyImportException;
use App\Http\Requests\StoreSurveyRequest;
use App\Models\LikertScale;
use App\Models\Respondent;
use App\Models\Survey;
use App\Services\SurveyImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    /**
     * List all uploaded surveys.
     */
    public function index(Request $request)
    {
        // Only the logged-in user's own surveys
        $surveys = $request->user()->surveys()->with('scale')->latest()->paginate(10);

        return view('surveys.index', compact('surveys'));
    }

    /**
     * Show the "New Survey" upload form.
     */
    public function create(Request $request)
    {
        $scales = LikertScale::visibleTo($request->user())->with('levels')->orderBy('points')->orderBy('name')->get();

        // Pre-select the most common scale; the user can change it.
        $defaultScaleId = $scales->firstWhere('name', '5-point Agreement')?->id ?? $scales->first()?->id;

        return view('surveys.create', compact('scales', 'defaultScaleId'));
    }

    /**
     * Read the uploaded CSV or Excel file and save the survey, its columns and its respondents.
     */
    public function store(StoreSurveyRequest $request, SurveyImportService $importer)
    {
        $file = $request->file('survey_file');

        try {
            $csv = $importer->parse($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (SurveyImportException $e) {
            return back()->withInput()->withErrors(['survey_file' => $e->getMessage()]);
        }

        $scale = LikertScale::with('levels')->findOrFail($request->validated('likert_scale_id'));

        // Everything inside the transaction is saved together, or not at all.
        $survey = DB::transaction(function () use ($request, $file, $csv, $importer, $scale) {
            $survey = Survey::create([
                'user_id' => $request->user()->id,
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'likert_scale_id' => $scale->id,
                'original_filename' => $file->getClientOriginalName(),
                'respondent_count' => count($csv['rows']),
            ]);

            // One SurveyColumn per CSV header, with a guessed type.
            foreach ($csv['headers'] as $position => $header) {
                $columnValues = array_map(fn ($row) => $row['values'][$position], $csv['rows']);

                $survey->columns()->create([
                    'position' => $position,
                    'name' => $header,
                    'type' => $importer->guessColumnType($header, $columnValues, $scale),
                ]);
            }

            // Insert respondents 500 at a time; much faster than one query per person.
            $now = now();
            foreach (array_chunk($csv['rows'], 500) as $chunk) {
                Respondent::insert(array_map(fn ($row) => [
                    'survey_id' => $survey->id,
                    'row_number' => $row['row_number'],
                    'answers' => json_encode($row['values'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }

            return $survey;
        });

        return redirect()
            ->route('surveys.columns.edit', $survey)
            ->with('success', "Uploaded {$survey->respondent_count} responses. StatEase guessed each column's type. Check them, then save.");
    }

    /**
     * Show one survey: its columns and all uploaded data, 20 rows per page.
     */
    public function show(Survey $survey)
    {
        $survey->load(['columns', 'scale']);
        $respondents = $survey->respondents()->orderBy('row_number')->paginate(20)->fragment('data');

        return view('surveys.show', compact('survey', 'respondents'));
    }

    /**
     * Delete a survey. Its columns and respondents are deleted by the database (cascade).
     */
    public function destroy(Survey $survey)
    {
        $survey->delete();

        return redirect()
            ->route('surveys.index')
            ->with('success', "Deleted \"{$survey->title}\".");
    }
}
