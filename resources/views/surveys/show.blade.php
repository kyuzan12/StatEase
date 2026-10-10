@extends('layouts.app')

@section('title', $survey->title)

@php
    $typeCounts = $survey->columns->countBy('type');
@endphp

@section('content')
    @include('partials.survey-tabs', ['survey' => $survey])

    @if (! $survey->is_mapped)
        @include('partials.stepper', ['current' => 2])

        <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex gap-2">
                <i class="bi bi-exclamation-circle-fill mt-1"></i>
                <div><strong>One more step:</strong> confirm the column types so StatEase can compute the results.</div>
            </div>
            <a href="{{ route('surveys.columns.edit', $survey) }}" class="btn btn-sm btn-warning">Confirm Column Types</a>
        </div>
    @endif

    {{-- Column summary --}}
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="card-title-se">Columns</h2>
                <p class="card-subtitle-se">
                    {{ $survey->columns->count() }} columns found in the file{{ $survey->is_mapped ? '' : ' (types not confirmed yet)' }}.
                </p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                @foreach (\App\Models\SurveyColumn::TYPES as $type => $label)
                    <span class="badge type-{{ $type }}">{{ $typeCounts[$type] ?? 0 }} {{ $label }}{{ ($typeCounts[$type] ?? 0) === 1 ? '' : 's' }}</span>
                @endforeach
                <button class="btn btn-sm btn-outline-secondary ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#columnList"
                        aria-expanded="false" aria-controls="columnList">
                    <i class="bi bi-chevron-down me-1"></i> Show list
                </button>
            </div>
        </div>
        <div class="collapse" id="columnList">
            <ul class="list-group list-group-flush">
                @foreach ($survey->columns as $column)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-3 px-4">
                        <span><span class="text-secondary-se num me-2">{{ $column->position + 1 }}.</span>{{ $column->name }}</span>
                        <span class="badge type-{{ $column->type }}">{{ \App\Models\SurveyColumn::TYPES[$column->type] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- All uploaded data, 20 rows per page --}}
    <div class="card" id="data">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="card-title-se">Uploaded responses</h2>
                <p class="card-subtitle-se">Rows {{ $respondents->firstItem() }}–{{ $respondents->lastItem() }} of {{ number_format($respondents->total()) }}</p>
            </div>
            <div class="chart-legend mb-0">
                @foreach (\App\Models\SurveyColumn::TYPES as $type => $label)
                    <span><span class="type-dot type-{{ $type }}"></span>{{ $label }}</span>
                @endforeach
                <span><span class="blank-cell px-1 rounded">blank</span> no answer</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-striped preview-table">
                <thead>
                    <tr>
                        <th style="min-width: 64px">Row</th>
                        @foreach ($survey->columns as $column)
                            <th class="col-{{ $column->type }}">
                                <span class="type-dot type-{{ $column->type }}" title="{{ \App\Models\SurveyColumn::TYPES[$column->type] }}"></span>{{ $column->name }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($respondents as $respondent)
                        <tr>
                            <td class="text-secondary-se num">{{ $respondent->row_number }}</td>
                            @foreach ($survey->columns as $column)
                                @php($answer = $respondent->answerFor($column))
                                @if ($answer === null || $answer === '')
                                    <td class="blank-cell">blank</td>
                                @else
                                    <td class="col-{{ $column->type }}">{{ \Illuminate\Support\Str::limit($answer, 40) }}</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($respondents->hasPages())
            <div class="card-footer">
                {{ $respondents->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
    // Switch the "Show list" / "Hide list" label of the column list.
    const columnList = document.getElementById('columnList');
    const toggle = document.querySelector('[data-bs-target="#columnList"]');
    columnList.addEventListener('show.bs.collapse', () => toggle.innerHTML = '<i class="bi bi-chevron-up me-1"></i> Hide list');
    columnList.addEventListener('hide.bs.collapse', () => toggle.innerHTML = '<i class="bi bi-chevron-down me-1"></i> Show list');
</script>
@endpush
