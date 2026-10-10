<?php

namespace App\Http\Controllers;

use App\Http\Requests\LikertScaleRequest;
use App\Models\LikertScale;
use App\Services\StatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Settings page: create, edit and delete Likert scales.
 */
class LikertScaleController extends Controller
{
    public function index(Request $request, StatisticsService $statistics)
    {
        $user = $request->user();

        // Built-in scales first, then the user's own; "in use" counts only this user's surveys.
        $scales = LikertScale::visibleTo($user)
            ->with('levels')
            ->withCount(['surveys' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByRaw('user_id IS NOT NULL')
            ->orderBy('points')
            ->orderBy('name')
            ->get();

        // Interpretation ranges of each scale, so users can see how a mean will be read.
        $ranges = $scales->mapWithKeys(fn ($scale) => [$scale->id => $statistics->interpretationScale($scale)]);

        return view('scales.index', compact('scales', 'ranges'));
    }

    public function create()
    {
        return view('scales.form', ['scale' => new LikertScale(['points' => 5])]);
    }

    public function store(LikertScaleRequest $request)
    {
        $scale = DB::transaction(function () use ($request) {
            $scale = LikertScale::create($request->safe()->only(['name', 'description', 'points']) + ['user_id' => $request->user()->id]);
            $scale->levels()->createMany($request->levels());

            return $scale;
        });

        return redirect()->route('scales.index')->with('success', "Created the \"{$scale->name}\" scale.");
    }

    public function edit(LikertScale $scale)
    {
        $scale->load('levels');

        return view('scales.form', compact('scale'));
    }

    public function update(LikertScaleRequest $request, LikertScale $scale)
    {
        DB::transaction(function () use ($request, $scale) {
            $scale->update($request->safe()->only(['name', 'description', 'points']));

            // Simplest correct approach: replace all levels with the submitted ones.
            $scale->levels()->delete();
            $scale->levels()->createMany($request->levels());
        });

        return redirect()->route('scales.index')->with('success', "Saved the \"{$scale->name}\" scale.");
    }

    public function destroy(LikertScale $scale)
    {
        // Surveys depend on their scale to compute results, so a scale in use cannot be deleted.
        if ($scale->surveys()->exists()) {
            return back()->with('error', "\"{$scale->name}\" is used by {$scale->surveys()->count()} survey(s) and cannot be deleted.");
        }

        $scale->delete();

        return redirect()->route('scales.index')->with('success', "Deleted the \"{$scale->name}\" scale.");
    }
}
