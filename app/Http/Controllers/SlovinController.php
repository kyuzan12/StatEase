<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlovinRequest;
use App\Services\StatisticsService;

class SlovinController extends Controller
{
    /**
     * Show the empty calculator.
     */
    public function index()
    {
        return view('slovin.index', ['result' => null]);
    }

    /**
     * Compute the sample size and show it with the step-by-step solution.
     */
    public function calculate(SlovinRequest $request, StatisticsService $statistics)
    {
        $result = $statistics->slovin(
            (int) $request->validated('population'),
            (float) $request->validated('margin_of_error') / 100, // 5 (%) -> 0.05
        );

        return view('slovin.index', compact('result'));
    }
}
