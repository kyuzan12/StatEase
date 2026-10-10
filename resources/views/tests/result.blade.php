@extends('layouts.app')

@use('App\Services\InferentialService', 'T')

@php
    $names = [
        T::TEST_CHI_SQUARE => ['Chi-square test of independence', 'χ²'],
        T::TEST_PEARSON => ["Pearson's r (correlation)", 'r'],
        T::TEST_ANOVA => ['One-way ANOVA', 'F'],
    ];
    [$testName, $symbol] = $names[$result['test']];
    $alphaText = rtrim(rtrim(number_format($alpha, 2), '0'), '.');
    $effectName = [T::TEST_CHI_SQUARE => "Cramér's V", T::TEST_PEARSON => 'r² (shared variation)', T::TEST_ANOVA => 'η² (eta squared)'][$result['test']];
    $statisticText = $result['test'] === T::TEST_PEARSON ? $narrative->apa($result['statistic'], 3) : number_format($result['statistic'], 3);
    $pText = $result['p'] < 0.001 ? '< .001' : $narrative->apa($result['p'], 3);
    $sign = $significant ? '<' : '≥';
    $CATEGORY_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];
@endphp

@section('title', $testName.' · '.$survey->title)

@section('content')
    @include('partials.survey-tabs', ['survey' => $survey, 'printable' => true])

    <a href="{{ route('surveys.tests.index', $survey) }}" class="text-decoration-none small d-print-none">
        <i class="bi bi-arrow-left"></i> Run another test
    </a>

    {{-- Headline --}}
    <div class="card mt-2 mb-4 test-headline {{ $significant ? 'is-significant' : 'is-not-significant' }}">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small text-secondary-se text-uppercase fw-semibold">{{ $testName }}</div>
                <div class="h5 mb-0">{{ ucfirst($a['label']) }} <span class="text-secondary-se">vs.</span> {{ $b['label'] }}</div>
            </div>
            <div class="text-end">
                @if ($significant)
                    <span class="badge text-bg-success fs-6"><i class="bi bi-check-circle-fill me-1"></i> Significant</span>
                @else
                    <span class="badge text-bg-secondary fs-6"><i class="bi bi-dash-circle me-1"></i> Not significant</span>
                @endif
                <div class="small text-secondary-se mt-1">p {{ $pText }} at α = {{ $alphaText }}</div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        {{-- Key numbers --}}
        <div class="col-lg-5">
            <div class="row g-3 print-keep-row">
                <div class="col-6">
                    <div class="card stat-card stat-card-primary h-100"><div class="card-body">
                        <div class="stat-label">{{ $symbol }} statistic</div>
                        <div class="stat-value">{{ $statisticText }}</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card stat-card h-100"><div class="card-body">
                        <div class="stat-label">p-value</div>
                        <div class="stat-value">{{ $pText }}</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card stat-card h-100"><div class="card-body">
                        <div class="stat-label">Degrees of freedom</div>
                        <div class="stat-value">{{ $result['df'] }}</div>
                        <div class="small text-secondary-se">N = {{ $result['n'] }}</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card stat-card h-100"><div class="card-body">
                        <div class="stat-label">{{ $effectName }}</div>
                        <div class="stat-value">{{ number_format($result['effect_size'], 3) }}</div>
                        <div class="small text-secondary-se">{{ ucfirst($result['effect_label']) }}{{ $result['test'] === T::TEST_PEARSON ? ' '.$result['direction'] : '' }} {{ $result['test'] === T::TEST_PEARSON ? 'correlation' : 'effect' }}</div>
                    </div></div>
                </div>
            </div>
        </div>

        {{-- Hypotheses and decision --}}
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-body">
                    <dl class="hypotheses mb-3">
                        <dt>H₀ (null)</dt><dd>{{ $hypotheses['null'] }}</dd>
                        <dt>H₁ (alternative)</dt><dd>{{ $hypotheses['alternative'] }}</dd>
                    </dl>
                    <div class="decision-rule {{ $significant ? 'is-significant' : '' }}">
                        <strong>Decision:</strong> p {{ $pText }} {{ $sign }} α = {{ $alphaText }}, so
                        <strong>{{ $significant ? 'reject H₀' : 'do not reject H₀' }}</strong>.
                    </div>
                    <div class="mt-3">
                        @include('partials.narrative', ['text' => $conclusion])
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($result['warnings'] as $warning)
        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i> {{ $warning }}</div>
    @endforeach

    {{-- ===================== Test details ===================== --}}
    @if ($result['test'] === T::TEST_CHI_SQUARE)
        <div class="card mb-4">
            <div class="card-header">
                <div class="fw-semibold">Contingency table</div>
                <div class="small text-secondary-se">Observed count, with the expected count in parentheses: E = (row total × column total) ÷ N</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 results-table text-center">
                    <thead>
                        <tr>
                            <th class="text-start">{{ $a['column']->name }} \ {{ \Illuminate\Support\Str::limit($b['column']->name, 40) }}</th>
                            @foreach ($result['col_labels'] as $colLabel)
                                <th>{{ $colLabel }}</th>
                            @endforeach
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result['row_labels'] as $rowLabel)
                            <tr>
                                <th class="text-start">{{ $rowLabel }}</th>
                                @foreach ($result['col_labels'] as $colLabel)
                                    @php $e = $result['expected'][$rowLabel][$colLabel]; @endphp
                                    <td class="{{ $e < 5 ? 'small-expected' : '' }}">
                                        <strong>{{ $result['observed'][$rowLabel][$colLabel] }}</strong>
                                        <span class="text-secondary-se small">({{ number_format($e, 2) }})</span>
                                    </td>
                                @endforeach
                                <td class="fw-semibold">{{ $result['row_totals'][$rowLabel] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="text-start">Total</th>
                            @foreach ($result['col_labels'] as $colLabel)
                                <th>{{ $result['col_totals'][$colLabel] }}</th>
                            @endforeach
                            <th>{{ $result['n'] }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-body">
                <div class="small text-secondary-se mb-2">Percentage of each row ({{ $a['column']->name }}) per answer. Similar bars = no relationship.</div>
                <div class="chart-box" style="height: {{ max(160, count($result['row_labels']) * 42 + 70) }}px">
                    <canvas id="testChart" role="img" aria-label="Stacked bar chart of row percentages"></canvas>
                </div>
            </div>
        </div>
    @elseif ($result['test'] === T::TEST_ANOVA)
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">Mean per group</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 results-table">
                            <thead><tr><th>{{ $a['column']->name }}</th><th class="text-center">n</th><th class="text-center">Mean</th><th class="text-center">SD</th></tr></thead>
                            <tbody>
                                @foreach ($result['groups'] as $group)
                                    <tr class="{{ $group['label'] === $result['highest_group']['label'] ? 'row-highest' : '' }}">
                                        <td>{{ $group['label'] }}</td>
                                        <td class="text-center">{{ $group['n'] }}</td>
                                        <td class="text-center fw-semibold">{{ number_format($group['mean'], 2) }}</td>
                                        <td class="text-center">{{ $group['sd'] === null ? '—' : number_format($group['sd'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th>All</th><th class="text-center">{{ $result['n'] }}</th><th class="text-center">{{ number_format($result['grand_mean'], 2) }}</th><th></th></tr></tfoot>
                        </table>
                    </div>
                    <div class="card-body">
                        <div class="chart-box" style="height: {{ max(160, count($result['groups']) * 40 + 50) }}px">
                            <canvas id="testChart" role="img" aria-label="Bar chart of the mean per group"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">ANOVA table</div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 results-table text-center">
                            <thead><tr><th class="text-start">Source</th><th>SS</th><th>df</th><th>MS</th><th>F</th><th>p</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td class="text-start">Between groups</td>
                                    <td>{{ number_format($result['ss_between'], 3) }}</td>
                                    <td>{{ $result['df_between'] }}</td>
                                    <td>{{ number_format($result['ms_between'], 3) }}</td>
                                    <td class="fw-semibold">{{ number_format($result['statistic'], 3) }}</td>
                                    <td class="fw-semibold">{{ $pText }}</td>
                                </tr>
                                <tr>
                                    <td class="text-start">Within groups</td>
                                    <td>{{ number_format($result['ss_within'], 3) }}</td>
                                    <td>{{ $result['df_within'] }}</td>
                                    <td>{{ number_format($result['ms_within'], 3) }}</td>
                                    <td></td><td></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th class="text-start">Total</th>
                                    <th>{{ number_format($result['ss_between'] + $result['ss_within'], 3) }}</th>
                                    <th>{{ $result['df_between'] + $result['df_within'] }}</th>
                                    <th></th><th></th><th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="card-body small text-secondary-se">
                        SS = sum of squares, MS = SS ÷ df, F = MS<sub>between</sub> ÷ MS<sub>within</sub>.
                        A large F means the groups differ more than people within the same group do.
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="card mb-4">
            <div class="card-header">
                <div class="fw-semibold">Scatter plot</div>
                <div class="small text-secondary-se">Each bubble is one combination of answers; bigger = more respondents. Points rising to the right = positive correlation.</div>
            </div>
            <div class="card-body">
                <div class="row g-4 align-items-center">
                    <div class="col-lg-8">
                        <div class="chart-box" style="height: 360px">
                            <canvas id="testChart" role="img" aria-label="Scatter plot of the two ratings"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-4 small">
                        <table class="table table-sm mb-3">
                            <tr><td>Mean of first rating</td><td class="text-end fw-semibold">{{ number_format($result['mean_x'], 2) }}</td></tr>
                            <tr><td>Mean of second rating</td><td class="text-end fw-semibold">{{ number_format($result['mean_y'], 2) }}</td></tr>
                            <tr><td>t ({{ $result['df'] }} df)</td><td class="text-end fw-semibold">{{ is_infinite($result['t']) ? '∞' : number_format($result['t'], 3) }}</td></tr>
                        </table>
                        <div class="fw-semibold mb-1">Strength of r</div>
                        <div class="text-secondary-se">±0.80–1.00 very strong · ±0.60–0.79 strong · ±0.40–0.59 moderate · ±0.20–0.39 weak · below ±0.20 very weak</div>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#52514e';
    const GRID = '#e1e0d9';
    const canvas = document.getElementById('testChart');

    @if ($result['test'] === T::TEST_CHI_SQUARE)
        @php
            $levelColors = $b['column']->type === 'likert'
                ? collect($survey->scale->levels)->mapWithKeys(fn ($level) => [$level->label => $survey->scale->colors()[$level->value]['background']])
                : collect($result['col_labels'])->values()->mapWithKeys(fn ($label, $i) => [$label => $CATEGORY_COLORS[$i] ?? '#8a929a']);
            $chartData = [
                'rows' => $result['row_labels'],
                'datasets' => collect($result['col_labels'])->map(fn ($col) => [
                    'label' => (string) $col,
                    'color' => $levelColors[$col] ?? '#8a929a',
                    'data' => collect($result['row_labels'])->map(fn ($row) => round($result['observed'][$row][$col] / $result['row_totals'][$row] * 100, 2))->all(),
                ])->all(),
            ];
        @endphp
        const data = @json($chartData);
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.rows,
                datasets: data.datasets.map(d => ({ label: d.label, data: d.data, backgroundColor: d.color, borderColor: '#fff', borderWidth: { left: 1, right: 1 }, borderSkipped: false, maxBarThickness: 28 })),
            },
            options: {
                indexAxis: 'y', maintainAspectRatio: false, animation: false,
                plugins: { legend: { position: 'top', labels: { boxWidth: 12 } }, tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${c.raw.toFixed(2)}%` } } },
                scales: { x: { stacked: true, max: 100, ticks: { callback: v => v + '%' }, grid: { color: GRID } }, y: { stacked: true, grid: { display: false } } },
            },
        });
    @elseif ($result['test'] === T::TEST_ANOVA)
        const groups = @json($result['groups']);
        new Chart(canvas, {
            type: 'bar',
            data: { labels: groups.map(g => g.label), datasets: [{ data: groups.map(g => g.mean), backgroundColor: '#2a78d6', borderRadius: 4, borderSkipped: 'start', maxBarThickness: 24 }] },
            options: {
                indexAxis: 'y', maintainAspectRatio: false, animation: false, layout: { padding: { right: 40 } },
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` Mean: ${c.raw.toFixed(2)} (n = ${groups[c.dataIndex].n})` } } },
                scales: { x: { min: 1, max: {{ $survey->scale->points }}, grid: { color: GRID } }, y: { grid: { display: false } } },
            },
            plugins: [{
                id: 'meanLabels',
                afterDatasetsDraw(chart) {
                    const { ctx } = chart;
                    ctx.save();
                    ctx.font = '600 12px ' + Chart.defaults.font.family;
                    ctx.fillStyle = '#0b0b0b';
                    ctx.textBaseline = 'middle';
                    chart.getDatasetMeta(0).data.forEach((bar, i) => ctx.fillText(groups[i].mean.toFixed(2), bar.x + 6, bar.y));
                    ctx.restore();
                },
            }],
        });
    @else
        @php
            // Group identical (x, y) pairs into bubbles so overlapping Likert answers stay visible.
            $bubbles = collect($result['pairs'])
                ->groupBy(fn ($pair) => round($pair[0], 2).'|'.round($pair[1], 2))
                ->map(fn ($group) => ['x' => round($group[0][0], 2), 'y' => round($group[0][1], 2), 'count' => $group->count()])
                ->values();
        @endphp
        const bubbles = @json($bubbles);
        // Show only the scale values (1, 2, 3, ...) on both axes.
        const wholeTicks = axis => { axis.ticks = Array.from({ length: {{ $survey->scale->points }} }, (_, i) => ({ value: i + 1 })); };
        const maxCount = Math.max(...bubbles.map(b => b.count));
        new Chart(canvas, {
            type: 'bubble',
            data: { datasets: [{ data: bubbles.map(b => ({ x: b.x, y: b.y, r: 4 + 14 * Math.sqrt(b.count / maxCount), count: b.count })), backgroundColor: 'rgba(42, 120, 214, 0.55)', borderColor: '#ffffff', borderWidth: 1.5 }] },
            options: {
                maintainAspectRatio: false, animation: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` (${c.raw.x}, ${c.raw.y}): ${c.raw.count} respondent(s)` } } },
                scales: {
                    x: { min: 0.5, max: {{ $survey->scale->points }} + 0.5, title: { display: true, text: @json(\Illuminate\Support\Str::limit(ucfirst($a['label']), 60)) }, grid: { color: GRID }, afterBuildTicks: wholeTicks },
                    y: { min: 0.5, max: {{ $survey->scale->points }} + 0.5, title: { display: true, text: @json(\Illuminate\Support\Str::limit(ucfirst($b['label']), 60)) }, grid: { color: GRID }, afterBuildTicks: wholeTicks },
                },
            },
        });
    @endif
</script>
@endpush
