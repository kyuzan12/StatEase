@extends('layouts.app')

@section('title', 'Results · '.$survey->title)

@php
    $scale = $survey->scale;
    $ranges = collect($likert['ranges'])->keyBy('value');
    // Mark the highest and lowest items, but only when the means are not all the same.
    $allTied = ($likert['highest']['mean'] ?? null) === ($likert['lowest']['mean'] ?? null);
    $highestId = $allTied ? null : ($likert['highest']['column']->id ?? null);
    $lowestId = $allTied ? null : ($likert['lowest']['column']->id ?? null);
    $hasProfiles = count($profiles) > 0;
    $sectionNumber = ['likert' => $hasProfiles ? 'II' : 'I', 'distribution' => $hasProfiles ? 'III' : 'II'];

    $formatRank = fn ($rank) => $rank === null ? '—' : (floor($rank) == $rank ? (int) $rank : number_format($rank, 1));
    $mean = fn ($value) => $value === null ? '—' : number_format($value, 2);

    // Data for the charts (JavaScript)
    $chartItems = collect($likert['items'])->map(fn ($item) => [
        'label' => $item['column']->name,
        'mean' => $item['mean'],
        'interpretation' => $item['level']['interpretation'] ?? null,
        'color' => $item['level']['background'] ?? '#cfcec8',
        'frequencies' => $item['frequencies'],
        'percentages' => $item['percentages'],
    ])->values();
    $chartProfiles = collect($profiles)->map(fn ($profile) => $profile['rows'])->values();
@endphp

@section('content')
    @include('partials.survey-tabs', ['survey' => $survey, 'printable' => true])

    <div class="row g-4">
        {{-- Table of contents: stays visible and highlights the section on screen --}}
        <div class="col-xl-2 d-none d-xl-block results-toc-col">
            <nav class="results-toc" id="resultsToc" aria-label="Results sections">
                <div class="toc-title">On this page</div>
                <div class="nav flex-column">
                    <a class="nav-link" href="#summary">Summary</a>
                    @if ($hasProfiles)
                        <a class="nav-link" href="#profile">I. Profile</a>
                        @foreach ($profiles as $index => $profile)
                            <a class="nav-link toc-sub" href="#profile-{{ $index }}">{{ \Illuminate\Support\Str::limit($profile['column']->name, 22) }}</a>
                        @endforeach
                    @endif
                    <a class="nav-link" href="#likert">{{ $sectionNumber['likert'] }}. Weighted Mean</a>
                    <a class="nav-link" href="#distribution">{{ $sectionNumber['distribution'] }}. Distribution</a>
                </div>
            </nav>
        </div>

        <div class="col-xl-10">
            {{-- ===================== SUMMARY ===================== --}}
            <section id="summary" class="result-section">
                <div class="row g-3 print-keep-row">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="stat-label"><i class="bi bi-people"></i>Respondents</div>
                                <div class="stat-value">{{ number_format($survey->respondent_count) }}</div>
                                <div class="stat-note">{{ count($likert['items']) }} Likert items &middot; {{ count($profiles) }} profile questions</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card stat-card stat-card-primary">
                            <div class="card-body">
                                <div class="stat-label"><i class="bi bi-bullseye"></i>Overall weighted mean</div>
                                <div class="stat-value">{{ $mean($likert['overall_mean']) }}</div>
                                @if ($likert['overall_level'])
                                    <div>@include('partials.level-chip', ['level' => $likert['overall_level']])</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @foreach (['highest' => ['Highest-rated item', 'bi-arrow-up-circle'], 'lowest' => ['Lowest-rated item', 'bi-arrow-down-circle']] as $key => [$caption, $icon])
                        <div class="col-sm-6 col-lg-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="stat-label"><i class="bi {{ $icon }}"></i>{{ $caption }}</div>
                                    @if ($likert[$key])
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <span class="stat-value">{{ $mean($likert[$key]['mean']) }}</span>
                                            @include('partials.level-chip', ['level' => $likert[$key]['level']])
                                        </div>
                                        <div class="stat-note text-truncate-2" title="{{ $likert[$key]['column']->name }}">{{ $likert[$key]['column']->name }}</div>
                                    @else
                                        <div class="stat-value">—</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ===================== I. PROFILE ===================== --}}
            @if ($hasProfiles)
                <section id="profile" class="result-section">
                    <h2 class="section-title"><span class="section-number">I</span> Profile of the Respondents</h2>
                    <p class="section-lead">Frequency and percentage of respondents in each group. Percentage = f ÷ N × 100.</p>

                    @foreach ($profiles as $index => $profile)
                        <div class="card {{ $loop->last ? '' : 'mb-4' }}" id="profile-{{ $index }}">
                            <div class="card-header">
                                <div class="table-caption">Table {{ $index + 1 }}</div>
                                <h3 class="card-title-se">Distribution of respondents by {{ $profile['column']->name }}</h3>
                            </div>
                            <div class="card-body">
                                <div class="row g-4 align-items-center">
                                    <div class="col-lg-5">
                                        <table class="table table-sm align-middle results-table mb-0">
                                            <thead>
                                                <tr><th>{{ $profile['column']->name }}</th><th class="text-center">Frequency</th><th class="text-center">Percentage</th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($profile['rows'] as $row)
                                                    @php($isMode = $profile['mode'] && $row['label'] === $profile['mode']['label'])
                                                    <tr class="{{ $isMode ? 'row-emphasis' : '' }}">
                                                        <td>
                                                            <span class="legend-swatch" data-profile="{{ $index }}" data-row="{{ $loop->index }}"></span>{{ $row['label'] }}
                                                            @if ($isMode)
                                                                <span class="badge rounded-pill bg-light text-secondary border ms-1">most</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-center num">{{ $row['frequency'] }}</td>
                                                        <td class="text-center num">{{ number_format($row['percentage'], 2) }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th>Total</th>
                                                    <th class="text-center num">{{ $profile['answered'] }}</th>
                                                    <th class="text-center num">100.00%</th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        @if ($profile['blank'] > 0)
                                            <p class="small text-secondary-se mt-2 mb-0">
                                                <i class="bi bi-info-circle me-1"></i>{{ $profile['blank'] }} respondent(s) left this blank and are not included.
                                            </p>
                                        @endif
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="chart-box" style="height: {{ max(130, count($profile['rows']) * 38 + 40) }}px">
                                            <canvas id="profileChart{{ $index }}" role="img" aria-label="Bar chart of respondents by {{ $profile['column']->name }}"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    @include('partials.narrative', ['text' => $narratives['profiles'][$index]])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </section>
            @endif

            {{-- ===================== II. WEIGHTED MEAN ===================== --}}
            <section id="likert" class="result-section">
                <h2 class="section-title"><span class="section-number">{{ $sectionNumber['likert'] }}</span> Weighted Mean per Item</h2>
                <p class="section-lead">Weighted Mean = Σ(f × w) ÷ N, where f is the frequency, w the scale value, and N the number of respondents who answered the item.</p>

                <div class="card mb-4">
                    <div class="card-header">
                        <div class="table-caption">Table {{ $likert_table_number }}</div>
                        <h3 class="card-title-se">Weighted mean, interpretation and rank of each item</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle results-table likert-table">
                            <thead class="text-center">
                                <tr>
                                    <th rowspan="2" class="text-start">Item</th>
                                    <th colspan="{{ $scale->points }}">Frequency</th>
                                    <th rowspan="2">N</th>
                                    <th rowspan="2">Weighted<br>Mean</th>
                                    <th rowspan="2">Interpretation</th>
                                    <th rowspan="2">Rank</th>
                                </tr>
                                <tr>
                                    @foreach (range($scale->points, 1) as $value)
                                        <th class="freq-head" title="{{ $ranges[$value]['label'] }}">
                                            <span class="freq-bar" style="background: {{ $ranges[$value]['background'] }}"></span>{{ $value }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($likert['items'] as $item)
                                    @php($isHighest = $item['column']->id === $highestId)
                                    @php($isLowest = $item['column']->id === $lowestId && ! $isHighest)
                                    <tr class="{{ $isHighest ? 'row-highest' : ($isLowest ? 'row-lowest' : '') }}">
                                        <td style="min-width: 240px">
                                            <span class="text-secondary-se num me-1">{{ $loop->iteration }}.</span>{{ $item['column']->name }}
                                            @if ($isHighest)
                                                <span class="badge rounded-pill text-bg-primary ms-1"><i class="bi bi-arrow-up"></i> Highest</span>
                                            @elseif ($isLowest)
                                                <span class="badge rounded-pill text-bg-secondary ms-1"><i class="bi bi-arrow-down"></i> Lowest</span>
                                            @endif
                                        </td>
                                        @foreach ($item['frequencies'] as $frequency)
                                            <td class="text-center num {{ $frequency === 0 ? 'text-secondary-se' : '' }}">{{ $frequency }}</td>
                                        @endforeach
                                        <td class="text-center num">{{ $item['n'] }}</td>
                                        <td class="text-center mean-cell">{{ $mean($item['mean']) }}</td>
                                        <td class="text-center">
                                            @if ($item['level'])
                                                @include('partials.level-chip', ['level' => $item['level']])
                                            @else
                                                <span class="text-secondary-se small">No answers</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-semibold num">{{ $formatRank($item['rank']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="{{ $scale->points + 2 }}" class="text-end">Overall Weighted Mean</th>
                                    <th class="text-center mean-cell">{{ $mean($likert['overall_mean']) }}</th>
                                    <th class="text-center">
                                        @if ($likert['overall_level'])
                                            @include('partials.level-chip', ['level' => $likert['overall_level']])
                                        @endif
                                    </th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="card-body border-top">
                        @include('partials.narrative', ['text' => $narratives['likert']])
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title-se">Weighted mean per item</h3>
                                <p class="card-subtitle-se">Bar color = interpretation. Dashed lines = where one interpretation ends and the next begins.</p>
                            </div>
                            <div class="card-body">
                                <div class="chart-box" style="height: {{ max(240, count($likert['items']) * 46 + 70) }}px">
                                    <canvas id="likertChart" role="img" aria-label="Bar chart of the weighted mean per item"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h3 class="card-title-se">Interpretation scale</h3>
                                <p class="card-subtitle-se">Interval = ({{ $scale->points }} − 1) ÷ {{ $scale->points }} = {{ number_format(($scale->points - 1) / $scale->points, 2) }}</p>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle small">
                                    <thead>
                                        <tr><th>Value</th><th class="text-center">Mean range</th><th>Interpretation</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($ranges->reverse() as $range)
                                            <tr>
                                                <td>{{ $range['value'] }} <span class="text-secondary-se">· {{ $range['label'] }}</span></td>
                                                <td class="text-center text-nowrap num">{{ number_format($range['min'], 2) }} – {{ number_format($range['max'], 2) }}</td>
                                                <td>@include('partials.level-chip', ['level' => $range])</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ===================== III. DISTRIBUTION ===================== --}}
            <section id="distribution" class="result-section">
                <h2 class="section-title"><span class="section-number">{{ $sectionNumber['distribution'] }}</span> Response Distribution</h2>
                <p class="section-lead">How the respondents answered each item, in percent. Red = negative side, gray = neutral, blue = positive side.</p>

                <div class="card">
                    <div class="card-body">
                        <div class="chart-legend" aria-label="Legend">
                            @foreach ($ranges as $range)
                                <span class="d-inline-flex align-items-center gap-1">
                                    <span class="legend-box" style="background: {{ $range['background'] }}"></span>{{ $range['value'] }} · {{ $range['label'] }}
                                </span>
                            @endforeach
                        </div>
                        <div class="chart-box" style="height: {{ max(240, count($likert['items']) * 46 + 50) }}px">
                            <canvas id="distributionChart" role="img" aria-label="Stacked bar chart of the percentage of each answer per item"></canvas>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Highlight the current section in the table of contents while scrolling.
    if (document.getElementById('resultsToc')) {
        new bootstrap.ScrollSpy(document.body, { target: '#resultsToc', rootMargin: '0px 0px -60%' });
    }

    const INK = '#1b2733';
    const INK_SECONDARY = '#4b5866';
    const GRID = '#e6e9ed';
    // Categorical colors for profile groups, always in this order (never cycled).
    const CATEGORY_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = INK_SECONDARY;

    const ranges = @json(array_values($likert['ranges']));
    const items = @json($chartItems);
    const profiles = @json($chartProfiles);
    const POINTS = {{ $scale->points }};

    // Split long question text into lines of about 38 characters.
    function wrap(text, width = 38) {
        const lines = [];
        let line = '';
        text.split(' ').forEach(word => {
            if ((line + ' ' + word).trim().length > width) { lines.push(line); line = word; }
            else { line = (line + ' ' + word).trim(); }
        });
        lines.push(line);
        return lines;
    }

    // Plugin: writes a value at the end of each bar (e.g. "3.33" or "12 (40%)").
    const endLabels = {
        id: 'endLabels',
        afterDatasetsDraw(chart, args, options) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = '600 12px ' + Chart.defaults.font.family;
            ctx.fillStyle = INK;
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((bar, i) => {
                const text = options.format(i);
                if (text) ctx.fillText(text, bar.x + 6, bar.y);
            });
            ctx.restore();
        },
    };

    // Plugin: dashed vertical lines at the boundaries between interpretation ranges.
    const rangeLines = {
        id: 'rangeLines',
        beforeDatasetsDraw(chart) {
            const { ctx, chartArea, scales } = chart;
            ctx.save();
            ctx.strokeStyle = '#a8a69e';
            ctx.setLineDash([4, 4]);
            ctx.lineWidth = 1;
            ranges.slice(0, -1).forEach(range => {
                const x = scales.x.getPixelForValue(range.max);
                ctx.beginPath();
                ctx.moveTo(x, chartArea.top);
                ctx.lineTo(x, chartArea.bottom);
                ctx.stroke();
            });
            ctx.restore();
        },
    };

    // Plugin: writes the percentage inside each stacked segment that is wide enough.
    const segmentLabels = {
        id: 'segmentLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.font = '600 11px ' + Chart.defaults.font.family;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            chart.data.datasets.forEach((dataset, d) => {
                chart.getDatasetMeta(d).data.forEach((segment, i) => {
                    const value = dataset.data[i];
                    if (value >= 8 && Math.abs(segment.x - segment.base) > 30) {
                        ctx.fillStyle = dataset.textColor;
                        ctx.fillText(Math.round(value) + '%', (segment.x + segment.base) / 2, segment.y);
                    }
                });
            });
            ctx.restore();
        },
    };

    const baseOptions = {
        indexAxis: 'y',
        maintainAspectRatio: false,
        animation: false,
        plugins: { legend: { display: false } },
    };

    // 1. Weighted mean per item, colored by interpretation
    new Chart(document.getElementById('likertChart'), {
        type: 'bar',
        data: {
            labels: items.map(i => wrap(i.label)),
            datasets: [{
                data: items.map(i => i.mean),
                backgroundColor: items.map(i => i.color),
                borderRadius: 4,
                borderSkipped: 'start',
                maxBarThickness: 24,
            }],
        },
        options: {
            ...baseOptions,
            layout: { padding: { right: 44 } },
            plugins: {
                ...baseOptions.plugins,
                endLabels: { format: i => items[i].mean === null ? '' : items[i].mean.toFixed(2) },
                tooltip: {
                    callbacks: {
                        title: ctx => items[ctx[0].dataIndex].label,
                        label: ctx => ` Weighted mean: ${ctx.raw.toFixed(2)} (${items[ctx.dataIndex].interpretation})`,
                    },
                },
            },
            scales: {
                x: { min: 1, max: POINTS, ticks: { stepSize: 1 }, grid: { color: GRID } },
                y: { grid: { display: false }, ticks: { color: INK } },
            },
        },
        plugins: [rangeLines, endLabels],
    });

    // 2. Response distribution: one stacked bar per item, one segment per scale value
    new Chart(document.getElementById('distributionChart'), {
        type: 'bar',
        data: {
            labels: items.map(i => wrap(i.label)),
            datasets: ranges.map(range => ({
                label: `${range.value} · ${range.label}`,
                data: items.map(i => i.percentages[range.value]),
                counts: items.map(i => i.frequencies[range.value]),
                backgroundColor: range.background,
                textColor: range.text,
                borderColor: '#ffffff',
                borderWidth: { left: 1, right: 1 },
                borderSkipped: false,
                maxBarThickness: 26,
            })),
        },
        options: {
            ...baseOptions,
            plugins: {
                ...baseOptions.plugins,
                tooltip: {
                    callbacks: {
                        title: ctx => items[ctx[0].dataIndex].label,
                        label: ctx => ` ${ctx.dataset.label}: ${ctx.dataset.counts[ctx.dataIndex]} (${ctx.raw.toFixed(2)}%)`,
                    },
                },
            },
            scales: {
                x: { stacked: true, min: 0, max: 100, ticks: { callback: v => v + '%' }, grid: { color: GRID } },
                y: { stacked: true, grid: { display: false }, ticks: { color: INK } },
            },
        },
        plugins: [segmentLabels],
    });

    // 3. Profile questions: one bar per group, labeled with count and percentage
    profiles.forEach((rows, index) => {
        const colors = rows.map((_, i) => rows.length <= CATEGORY_COLORS.length ? CATEGORY_COLORS[i] : CATEGORY_COLORS[0]);

        document.querySelectorAll(`.legend-swatch[data-profile="${index}"]`).forEach(swatch => {
            swatch.style.background = colors[swatch.dataset.row];
        });

        new Chart(document.getElementById('profileChart' + index), {
            type: 'bar',
            data: {
                labels: rows.map(r => r.label),
                datasets: [{
                    data: rows.map(r => r.frequency),
                    backgroundColor: colors,
                    borderRadius: 4,
                    borderSkipped: 'start',
                    maxBarThickness: 24,
                }],
            },
            options: {
                ...baseOptions,
                layout: { padding: { right: 90 } },
                plugins: {
                    ...baseOptions.plugins,
                    endLabels: { format: i => `${rows[i].frequency} (${rows[i].percentage.toFixed(1)}%)` },
                    tooltip: { callbacks: { label: ctx => ` ${ctx.raw} respondents (${rows[ctx.dataIndex].percentage.toFixed(2)}%)` } },
                },
                scales: {
                    x: { min: 0, ticks: { precision: 0 }, grid: { color: GRID } },
                    y: { grid: { display: false }, ticks: { color: INK } },
                },
            },
            plugins: [endLabels],
        });
    });
</script>
@endpush
