@extends('layouts.app')

@section('title', 'Statistical Tests · '.$survey->title)

@use('App\Services\InferentialService', 'T')

@php
    $goals = [
        T::TEST_ANOVA => [
            'icon' => 'bi-bar-chart-steps',
            'question' => 'Do groups differ in their ratings?',
            'example' => 'Is there a significant difference in the assessment when respondents are grouped by year level?',
            'test' => 'One-way ANOVA',
            'why' => 'You are comparing the average rating (a number) of two or more groups (categories). ANOVA tests whether the group means are too far apart to be due to chance. With 2 groups it gives the same result as an independent t-test.',
            'a' => ['Group by (profile question)', 'profile'],
            'b' => ['Rating to compare', 'score'],
        ],
        T::TEST_CHI_SQUARE => [
            'icon' => 'bi-grid-3x3',
            'question' => 'Are two categories related?',
            'example' => 'Is sex related to program? Is internet connection related to how students answered an item?',
            'test' => 'Chi-square test of independence',
            'why' => 'Both variables are categories (groups or answer choices). Chi-square compares the counts you got with the counts you would expect if the two were unrelated.',
            'a' => ['First category (profile question)', 'profile'],
            'b' => ['Second category (profile question or item answers)', 'category'],
        ],
        T::TEST_PEARSON => [
            'icon' => 'bi-graph-up',
            'question' => 'Do two ratings go together?',
            'example' => 'Do students who rate ease of use higher also give a higher overall assessment?',
            'test' => "Pearson's r (correlation)",
            'why' => 'Both variables are ratings (numbers). Pearson\'s r measures how strongly they move together, from −1 (opposite) through 0 (unrelated) to +1 (together).',
            'a' => ['First rating', 'score'],
            'b' => ['Second rating', 'score'],
        ],
    ];

    $selectedTest = old('test', $profiles->isEmpty() ? T::TEST_PEARSON : T::TEST_ANOVA);
@endphp

@section('content')
    @include('partials.survey-tabs', ['survey' => $survey])

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($profiles->isEmpty())
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            This survey has no profile questions, so only Pearson's r is available. Mark a column like Sex or Year Level as a
            <a href="{{ route('surveys.columns.edit', $survey) }}">profile question</a> to compare groups.
        </div>
    @endif

    <form method="GET" action="{{ route('surveys.tests.run', $survey) }}" id="testForm">
        <h2 class="step-heading"><span class="step-number">1</span> What do you want to find out?</h2>
        <div class="row g-3 mb-4">
            @foreach ($goals as $test => $goal)
                <div class="col-md-4">
                    <input type="radio" class="btn-check" name="test" id="goal-{{ $test }}" value="{{ $test }}" @checked($selectedTest === $test)
                           @disabled($test !== T::TEST_PEARSON && $profiles->isEmpty())>
                    <label class="card goal-card h-100" for="goal-{{ $test }}">
                        <div class="card-body">
                            <i class="bi {{ $goal['icon'] }} goal-icon"></i>
                            <div class="fw-semibold mt-2">{{ $goal['question'] }}</div>
                            <div class="small text-secondary-se mt-1">e.g. {{ $goal['example'] }}</div>
                        </div>
                    </label>
                </div>
            @endforeach
        </div>

        @foreach ($goals as $test => $goal)
            <div class="test-panel" data-test="{{ $test }}" hidden>
                <div class="recommendation mb-4">
                    <div class="small text-uppercase fw-semibold">Recommended test</div>
                    <div class="h5 mb-1">{{ $goal['test'] }}</div>
                    <div class="small">{{ $goal['why'] }}</div>
                </div>

                <h2 class="step-heading"><span class="step-number">2</span> Choose the variables</h2>
                <div class="card mb-4">
                    <div class="card-body row g-3">
                        @foreach (['a', 'b'] as $field)
                            @php([$label, $kind] = $goal[$field])
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">{{ $label }}</label>
                                <select name="{{ $field }}" class="form-select variable-select">
                                    <option value="">Choose…</option>
                                    @if ($kind === 'profile' || $kind === 'category')
                                        <optgroup label="Profile questions">
                                            @foreach ($profiles as $column)
                                                <option value="col:{{ $column->id }}" @selected(old($field) === "col:{$column->id}" && $selectedTest === $test)>{{ $column->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if ($kind === 'score')
                                        <option value="{{ T::OVERALL }}" @selected(old($field) === T::OVERALL && $selectedTest === $test)>
                                            Overall assessment (average of all items)
                                        </option>
                                    @endif
                                    @if ($kind === 'score' || $kind === 'category')
                                        <optgroup label="Likert items">
                                            @foreach ($items as $column)
                                                <option value="col:{{ $column->id }}" @selected(old($field) === "col:{$column->id}" && $selectedTest === $test)>
                                                    {{ $loop->iteration }}. {{ \Illuminate\Support\Str::limit($column->name, 70) }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        <h2 class="step-heading"><span class="step-number">3</span> Level of significance</h2>
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                @foreach (['0.01' => '0.01 (stricter)', '0.05' => '0.05 (most common)', '0.10' => '0.10'] as $value => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="alpha" id="alpha{{ $value }}" value="{{ $value }}" @checked(old('alpha', '0.05') === $value)>
                        <label class="form-check-label" for="alpha{{ $value }}">α = {{ $label }}</label>
                    </div>
                @endforeach
                <span class="small text-secondary-se">If p is below α, the result is called significant.</span>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-play-circle me-1"></i> Run Test
        </button>
    </form>
@endsection

@push('scripts')
<script>
    // Show only the panel of the chosen test. Selects in hidden panels are disabled, so they are not sent.
    function showPanel() {
        const chosen = document.querySelector('input[name="test"]:checked')?.value;
        document.querySelectorAll('.test-panel').forEach(panel => {
            const active = panel.dataset.test === chosen;
            panel.hidden = !active;
            panel.querySelectorAll('select').forEach(select => select.disabled = !active);
        });
    }

    document.querySelectorAll('input[name="test"]').forEach(radio => radio.addEventListener('change', showPanel));
    showPanel();
</script>
@endpush
