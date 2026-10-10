@extends('layouts.app')

@section('title', 'Likert Scales')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Likert Scales</h1>
            <p class="page-lead">The answer choices StatEase understands, and how each weighted mean is interpreted.</p>
        </div>
        <a href="{{ route('scales.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Scale
        </a>
    </div>

    <div class="alert alert-info d-flex gap-2 mb-4">
        <i class="bi bi-info-circle-fill mt-1"></i>
        <div>
            <strong>Which scales can I change?</strong>
            <span class="badge rounded-pill text-bg-light border text-secondary-se">Built-in</span> scales are shared by all users,
            so they are read-only. Create your own scale to use different labels.
            Your own scales can be edited at any time, but a scale <strong>in use</strong> by one of your surveys cannot be deleted
            or change its number of points, because that survey's results are computed with it.
        </div>
    </div>

    <div class="row g-4">
        @foreach ($scales as $scale)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="card-title-se">{{ $scale->name }}</h2>
                            <p class="card-subtitle-se">
                                {{ $scale->points }} points
                                @if ($scale->description)
                                    &middot; {{ $scale->description }}
                                @endif
                            </p>
                            <div class="mt-2 d-flex flex-wrap gap-1">
                                @if ($scale->isBuiltIn())
                                    <span class="badge rounded-pill text-bg-light border text-secondary-se">
                                        <i class="bi bi-bookmark-star me-1"></i>Built-in
                                    </span>
                                @else
                                    <span class="badge rounded-pill type-likert">
                                        <i class="bi bi-person me-1"></i>My scale
                                    </span>
                                @endif
                                @if ($scale->surveys_count > 0)
                                    <span class="badge rounded-pill text-bg-light border text-secondary-se">
                                        <i class="bi bi-lock-fill me-1"></i>In use by {{ $scale->surveys_count }} {{ \Illuminate\Support\Str::plural('survey', $scale->surveys_count) }}
                                    </span>
                                @else
                                    <span class="badge rounded-pill text-bg-light border text-secondary-se">Not used yet</span>
                                @endif
                            </div>
                        </div>
                        @can('update', $scale)
                            <div class="d-flex gap-1 flex-shrink-0">
                                <a href="{{ route('scales.edit', $scale) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                                @if ($scale->surveys_count === 0)
                                    <form action="{{ route('scales.destroy', $scale) }}" method="POST"
                                          data-confirm="The &quot;{{ $scale->name }}&quot; scale will be permanently deleted. None of your surveys uses it."
                                          data-confirm-title="Delete this scale?" data-confirm-button="Delete scale">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete scale">
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip"
                                          title="In use by {{ $scale->surveys_count }} {{ \Illuminate\Support\Str::plural('survey', $scale->surveys_count) }}, so it cannot be deleted">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                            <i class="bi bi-trash"></i><span class="visually-hidden">Cannot delete</span>
                                        </button>
                                    </span>
                                @endif
                            </div>
                        @else
                            <span class="small text-secondary-se flex-shrink-0"><i class="bi bi-lock me-1"></i>Read-only</span>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small">
                            <thead>
                                <tr>
                                    <th>Value</th>
                                    <th>Answer label</th>
                                    <th class="text-center">Mean range</th>
                                    <th>Interpretation</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_reverse($ranges[$scale->id]) as $range)
                                    <tr>
                                        <td class="num">{{ $range['value'] }}</td>
                                        <td>{{ $range['label'] }}</td>
                                        <td class="text-center text-nowrap num">{{ number_format($range['min'], 2) }} – {{ number_format($range['max'], 2) }}</td>
                                        <td>@include('partials.level-chip', ['level' => $range])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
