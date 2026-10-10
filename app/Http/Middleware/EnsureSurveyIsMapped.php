<?php

namespace App\Http\Middleware;

use App\Models\Survey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before the results, reliability, test and report pages.
 * Those pages need to know which columns are Likert items, so the user must confirm the column types first.
 */
class EnsureSurveyIsMapped
{
    public function handle(Request $request, Closure $next): Response
    {
        $survey = $request->route('survey');

        if ($survey instanceof Survey && ! $survey->is_mapped) {
            return redirect()
                ->route('surveys.columns.edit', $survey)
                ->with('error', 'Please confirm the column types first.');
        }

        return $next($request);
    }
}
