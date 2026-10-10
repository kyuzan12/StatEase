@extends('layouts.app')

@section('title', 'Column Types · '.$survey->title)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('surveys.index') }}">My Surveys</a></li>
            <li class="breadcrumb-item"><a href="{{ route('surveys.show', $survey) }}">{{ $survey->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Column types</li>
        </ol>
    </nav>

    <div class="page-header">
        <div>
            <h1 class="page-title">Confirm the column types</h1>
            <p class="page-lead">
                StatEase guessed what each column of <strong>{{ $survey->title }}</strong> contains.
                Check the guesses, change any that are wrong, then save.
            </p>
        </div>
    </div>

    @include('partials.stepper', ['current' => 2])

    {{-- What the three types mean --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['likert', 'Likert item', 'bi-ui-radios', 'A statement rated on the scale below. StatEase computes its weighted mean and interpretation.'],
            ['profile', 'Profile question', 'bi-people', 'Describes the respondent, e.g. sex, year level or program. StatEase computes frequency and percentage.'],
            ['ignore', 'Ignore', 'bi-eye-slash', 'Not analyzed: timestamps, names, emails and open-ended comments.'],
        ] as [$type, $label, $icon, $text])
            <div class="col-md-4">
                <div class="card h-100 type-card type-card-{{ $type }}">
                    <div class="card-body">
                        <span class="badge type-{{ $type }} mb-2"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</span>
                        <p class="small mb-0 text-secondary-se">{{ $text }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="d-flex gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('surveys.columns.update', $survey) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-5">
                        <label for="likert_scale_id" class="form-label mb-1">Likert scale of this survey</label>
                        <p class="small text-secondary-se mb-0">Every Likert item is checked against this scale when you save.</p>
                    </div>
                    <div class="col-md-7">
                        @include('partials.scale-select', ['scales' => $scales, 'selected' => $survey->likert_scale_id])
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title-se">Columns ({{ $survey->columns->count() }})</h2>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 56px">#</th>
                            <th>Column (question)</th>
                            <th>Sample answers</th>
                            <th style="width: 220px">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($survey->columns as $column)
                            @php($field = "columns.{$column->id}")
                            <tr>
                                <td class="text-secondary-se num">{{ $column->position + 1 }}</td>
                                <td class="fw-semibold">{{ $column->name }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse ($samples[$column->id] as $sample)
                                            <span class="sample-chip">{{ \Illuminate\Support\Str::limit($sample, 30) }}</span>
                                        @empty
                                            <span class="small text-secondary-se fst-italic">no answers</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <select name="columns[{{ $column->id }}]" aria-label="Type of {{ $column->name }}"
                                            class="form-select form-select-sm column-type @error($field) is-invalid @enderror">
                                        @foreach (\App\Models\SurveyColumn::TYPES as $value => $label)
                                            <option value="{{ $value }}" @selected(old($field, $column->type) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Stays visible at the bottom of the screen while scrolling through long questionnaires --}}
            <div class="card-footer sticky-actions d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="small d-flex flex-wrap align-items-center gap-1" id="typeSummary" aria-live="polite"></div>
                <div class="d-flex gap-2">
                    <a href="{{ route('surveys.show', $survey) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Save and View Results
                    </button>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Color each dropdown by its type, and keep a live count of each type.
    const selects = document.querySelectorAll('.column-type');
    const summary = document.getElementById('typeSummary');

    function update() {
        const count = { likert: 0, profile: 0, ignore: 0 };
        selects.forEach(select => {
            count[select.value]++;
            select.dataset.type = select.value;
        });

        summary.innerHTML =
            `<span class="badge type-likert">${count.likert} Likert</span>` +
            `<span class="badge type-profile">${count.profile} profile</span>` +
            `<span class="badge type-ignore">${count.ignore} ignored</span>` +
            (count.likert === 0 ? '<span class="text-danger ms-1">Choose at least one Likert item.</span>' : '');
    }

    selects.forEach(select => select.addEventListener('change', update));
    update();
</script>
@endpush
