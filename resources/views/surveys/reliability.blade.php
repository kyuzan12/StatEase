@extends('layouts.app')

@section('title', 'Reliability · '.$survey->title)

@php
    // Bootstrap color per interpretation, always shown with its label (never color alone)
    $statusClass = [
        'Excellent' => 'text-bg-success', 'Good' => 'text-bg-success', 'Acceptable' => 'text-bg-primary',
        'Questionable' => 'text-bg-warning', 'Poor' => 'text-bg-warning', 'Unacceptable' => 'text-bg-danger',
    ];
    $selectedIds = $selected->pluck('id')->all();
    $decimal = fn ($value, $places = 3) => $value === null ? '—' : number_format($value, $places);
@endphp

@section('content')
    @include('partials.survey-tabs', ['survey' => $survey, 'printable' => true])

    <div class="row g-4">
        {{-- Item selection (a control, so it is not printed) --}}
        <div class="col-lg-4 d-print-none">
            <div class="card">
                <div class="card-header fw-semibold">Items to test</div>
                <form method="GET" action="{{ route('surveys.reliability', $survey) }}">
                    <div class="card-body">
                        <p class="small text-secondary-se">
                            Test all items together, or only the items of one part (construct) of your questionnaire.
                        </p>
                        <div class="d-flex gap-2 mb-2 small">
                            <button type="button" class="btn btn-link btn-sm p-0" id="selectAll">Select all</button>
                            <span class="text-secondary-se">&middot;</span>
                            <button type="button" class="btn btn-link btn-sm p-0" id="selectNone">Select none</button>
                        </div>
                        <div class="item-checklist">
                            @foreach ($likertColumns as $column)
                                <div class="form-check">
                                    <input class="form-check-input item-check" type="checkbox" name="items[]" value="{{ $column->id }}"
                                           id="item{{ $column->id }}" @checked(in_array($column->id, $selectedIds))>
                                    <label class="form-check-label small" for="item{{ $column->id }}">
                                        <span class="text-secondary-se">{{ $loop->iteration }}.</span> {{ $column->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-calculator me-1"></i> Compute Cronbach's Alpha
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Result --}}
        <div class="col-lg-8">
            @if ($error)
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $error }}
                </div>
            @endif

            @if ($result)
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <div class="stat-label">Cronbach's alpha (α)</div>
                                <div class="d-flex align-items-baseline gap-2">
                                    <div class="stat-value">{{ number_format($result['alpha'], 3) }}</div>
                                    <span class="badge {{ $statusClass[$result['interpretation']] }} fs-6">{{ $result['interpretation'] }}</span>
                                </div>
                                <div class="small text-secondary-se">
                                    {{ $result['k'] }} items &middot; {{ $result['n'] }} respondents
                                    @if ($result['excluded'] > 0)
                                        <br>{{ $result['excluded'] }} respondent(s) skipped an item and were excluded
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="formula-box">
                                    <div class="small text-secondary-se mb-1">Solution</div>
                                    <div class="solution-step">α = (k / (k − 1)) × (1 − Σσᵢ² / σₜ²)</div>
                                    <div class="solution-step">
                                        α = ({{ $result['k'] }} / {{ $result['k'] - 1 }}) × (1 − {{ number_format($result['sum_item_variances'], 4) }} / {{ number_format($result['total_variance'], 4) }})
                                    </div>
                                    <div class="solution-step fw-bold">α = {{ number_format($result['alpha'], 3) }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            @include('partials.narrative', ['text' => $paragraph])
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <div class="fw-semibold">Item analysis</div>
                        <div class="small text-secondary-se">
                            Corrected item-total correlation = how well an item agrees with the other items (r ≥ {{ \App\Services\ReliabilityService::WEAK_ITEM_CORRELATION }} is good).
                            "Alpha if deleted" shows what α would be without that item.
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 results-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Mean</th>
                                    <th class="text-center">Variance</th>
                                    <th class="text-center">Item-total r</th>
                                    <th class="text-center">Alpha if deleted</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($result['items'] as $item)
                                    <tr class="{{ $item['flag'] ? 'row-flagged' : '' }}">
                                        <td>
                                            {{ $item['column']->name }}
                                            @if ($item['flag'])
                                                <div class="small text-danger-emphasis"><i class="bi bi-exclamation-triangle-fill"></i> {{ $item['flag'] }}</div>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ number_format($item['mean'], 2) }}</td>
                                        <td class="text-center">{{ $decimal($item['variance']) }}</td>
                                        <td class="text-center">{{ $decimal($item['item_total_correlation']) }}</td>
                                        <td class="text-center {{ $item['alpha_if_deleted'] !== null && $item['alpha_if_deleted'] > $result['alpha'] + 0.001 ? 'fw-bold text-danger-emphasis' : '' }}">
                                            {{ $decimal($item['alpha_if_deleted']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header fw-semibold">How to read Cronbach's alpha (George &amp; Mallery, 2003)</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 small">
                        @foreach (['α ≥ 0.9' => 'Excellent', '0.8 – 0.89' => 'Good', '0.7 – 0.79' => 'Acceptable', '0.6 – 0.69' => 'Questionable', '0.5 – 0.59' => 'Poor', 'α < 0.5' => 'Unacceptable'] as $range => $label)
                            <span class="badge {{ $statusClass[$label] }}">{{ $range }}: {{ $label }}</span>
                        @endforeach
                    </div>
                    <p class="small text-secondary-se mt-2 mb-0">An alpha of at least 0.70 is the usual minimum for a reliable research instrument.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('selectAll').addEventListener('click', () => document.querySelectorAll('.item-check').forEach(c => c.checked = true));
    document.getElementById('selectNone').addEventListener('click', () => document.querySelectorAll('.item-check').forEach(c => c.checked = false));
</script>
@endpush
