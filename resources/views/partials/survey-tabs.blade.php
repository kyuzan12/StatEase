{{--
    Header shared by every page of one survey: breadcrumb, title, details, and a tab bar
    that stays visible under the navbar while scrolling.
    Usage: @include('partials.survey-tabs', ['survey' => $survey, 'printable' => true])
--}}
@php
    $tabs = [
        ['surveys.show', 'surveys.show', 'Data', 'bi-table', true],
        ['surveys.results', 'surveys.results', 'Results', 'bi-bar-chart-line', $survey->is_mapped],
        ['surveys.reliability', 'surveys.reliability', 'Reliability', 'bi-shield-check', $survey->is_mapped],
        ['surveys.tests.index', 'surveys.tests.*', 'Statistical Tests', 'bi-diagram-3', $survey->is_mapped],
    ];
    $printable = $printable ?? false;
@endphp

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}">My Surveys</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $survey->title }}</li>
    </ol>
</nav>

<h1 class="page-title">{{ $survey->title }}</h1>
<div class="survey-meta">
    <span><i class="bi bi-people"></i>{{ number_format($survey->respondent_count) }} respondents</span>
    <span><i class="bi bi-sliders"></i>{{ $survey->scale->name }} scale</span>
    <span><i class="bi bi-file-earmark-spreadsheet"></i>{{ $survey->original_filename }}</span>
    <span><i class="bi bi-calendar3"></i>Uploaded {{ $survey->created_at->format('M j, Y') }}</span>
</div>
@if ($survey->description)
    <p class="text-secondary-se mt-2 mb-0">{{ $survey->description }}</p>
@endif

<div class="survey-subnav">
    <div class="survey-subnav-inner">
        <nav class="survey-tabs" aria-label="Survey sections">
            @foreach ($tabs as [$route, $pattern, $label, $icon, $enabled])
                @if ($enabled)
                    <a class="tab-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($route, $survey) }}"
                       @if (request()->routeIs($pattern)) aria-current="page" @endif>
                        <i class="bi {{ $icon }}"></i> {{ $label }}
                    </a>
                @else
                    <span class="tab-link disabled" title="Confirm the column types first">
                        <i class="bi {{ $icon }}"></i> {{ $label }}
                    </span>
                @endif
            @endforeach
        </nav>
        <div class="survey-subnav-actions">
            <a href="{{ route('surveys.columns.edit', $survey) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-list-columns me-1"></i> Column Types
            </a>
            @if ($printable)
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            @endif
            @if ($survey->is_mapped)
                <a href="{{ route('surveys.report', $survey) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-file-earmark-word me-1"></i> Word Report
                </a>
            @endif
        </div>
    </div>
</div>
