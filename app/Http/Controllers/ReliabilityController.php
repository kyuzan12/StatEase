<?php

namespace App\Http\Controllers;

use App\Models\Survey;
use App\Services\NarrativeService;
use App\Services\ReliabilityService;
use App\Services\ResponseMatrix;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReliabilityController extends Controller
{
    /**
     * Cronbach's alpha of the selected Likert items (all of them by default).
     * The item checkboxes send ?items[]=5&items[]=6, so the result can be bookmarked or shared.
     */
    public function show(Request $request, Survey $survey, ReliabilityService $reliability, NarrativeService $narrative)
    {
        $likertColumns = $survey->likertColumns()->get();

        // Keep only IDs that really are this survey's Likert items (ignore anything else in the URL).
        $requested = collect($request->query('items', $likertColumns->pluck('id')->all()))->map(fn ($id) => (int) $id);
        $selected = $likertColumns->whereIn('id', $requested)->values();

        $result = $paragraph = $error = null;
        try {
            $result = $reliability->analyze(ResponseMatrix::for($survey), $selected);
            $paragraph = $narrative->reliability($result);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage(); // shown on the page instead of a crash
        }

        return view('surveys.reliability', compact('survey', 'likertColumns', 'selected', 'result', 'paragraph', 'error'));
    }
}
