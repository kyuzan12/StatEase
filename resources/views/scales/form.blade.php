@extends('layouts.app')

@php
    $editing = $scale->exists;
    $points = (int) old('points', $scale->points);
    $existing = $scale->levels->keyBy('value');

    // Colors for every possible number of points, so the preview updates when "points" changes.
    $palettes = collect(range(\App\Models\LikertScale::MIN_POINTS, \App\Models\LikertScale::MAX_POINTS))
        ->mapWithKeys(fn ($n) => [$n => \App\Support\DivergingPalette::forPoints($n)]);
@endphp

@section('title', $editing ? 'Edit Scale' : 'New Scale')

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('scales.index') }}">Likert Scales</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editing ? $scale->name : 'New scale' }}</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="page-header">
                <div>
                    <h1 class="page-title">{{ $editing ? 'Edit "'.$scale->name.'"' : 'New Likert Scale' }}</h1>
                    <p class="page-lead">
                        Value 1 is the <strong>lowest</strong> (most negative) answer. The interpretation is optional:
                        leave it blank to use the label in the results.
                    </p>
                </div>
            </div>

            @if ($editing && $scale->surveys()->exists())
                <div class="alert alert-info d-flex gap-2">
                    <i class="bi bi-lock-fill mt-1"></i>
                    <div>This scale is used by {{ $scale->surveys()->count() }} survey(s), so its number of points cannot change. Labels and interpretations can still be edited.</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Please fix the errors below.</strong>
                </div>
            @endif

            <form action="{{ $editing ? route('scales.update', $scale) : route('scales.store') }}" method="POST" novalidate>
                @csrf
                @if ($editing)
                    @method('PUT')
                @endif

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label">Scale name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $scale->name) }}"
                                       class="form-control @error('name') is-invalid @enderror" placeholder="e.g. 5-point Effectiveness" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="points" class="form-label">Number of points <span class="text-danger">*</span></label>
                                <select id="points" name="points" class="form-select @error('points') is-invalid @enderror">
                                    @foreach (range(\App\Models\LikertScale::MIN_POINTS, \App\Models\LikertScale::MAX_POINTS) as $n)
                                        <option value="{{ $n }}" @selected($points === $n)>{{ $n }} points</option>
                                    @endforeach
                                </select>
                                @error('points')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" id="description" name="description" value="{{ old('description', $scale->description) }}"
                                       class="form-control @error('description') is-invalid @enderror" placeholder="Optional: when to use this scale">
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h2 class="card-title-se">Levels</h2><p class="card-subtitle-se">From the lowest (1) to the highest value. Colors show how results will look.</p></div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px" class="text-center">Value</th>
                                    <th>Answer label <span class="text-danger">*</span></th>
                                    <th>Interpretation (optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- All 10 rows are rendered; rows above the chosen number of points are hidden and disabled. --}}
                                @foreach (range(1, \App\Models\LikertScale::MAX_POINTS) as $value)
                                    <tr class="level-row" data-value="{{ $value }}">
                                        <td class="text-center">
                                            <span class="level-chip level-swatch">{{ $value }}</span>
                                        </td>
                                        <td>
                                            <input type="text" name="levels[{{ $value }}][label]"
                                                   value="{{ old("levels.$value.label", $existing[$value]->label ?? '') }}"
                                                   class="form-control form-control-sm @error("levels.$value.label") is-invalid @enderror"
                                                   placeholder="{{ $value === 1 ? 'e.g. Strongly Disagree' : '' }}">
                                            @error("levels.$value.label")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="text" name="levels[{{ $value }}][interpretation]"
                                                   value="{{ old("levels.$value.interpretation", $existing[$value]->interpretation ?? '') }}"
                                                   class="form-control form-control-sm @error("levels.$value.interpretation") is-invalid @enderror"
                                                   placeholder="{{ $value === 1 ? 'e.g. Not Effective' : '' }}">
                                            @error("levels.$value.interpretation")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('levels')
                        <div class="card-body text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('scales.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> {{ $editing ? 'Save Changes' : 'Create Scale' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const palettes = @json($palettes);
        const pointsSelect = document.getElementById('points');

        // Show only rows 1..points; disabled inputs are not sent with the form.
        function update() {
            const points = parseInt(pointsSelect.value, 10);
            const colors = palettes[points];

            document.querySelectorAll('.level-row').forEach(row => {
                const value = parseInt(row.dataset.value, 10);
                const visible = value <= points;
                row.hidden = !visible;
                row.querySelectorAll('input').forEach(input => input.disabled = !visible);

                if (visible) {
                    const swatch = row.querySelector('.level-swatch');
                    swatch.style.background = colors[value].background;
                    swatch.style.color = colors[value].text;
                }
            });
        }

        pointsSelect.addEventListener('change', update);
        update();
    })();
</script>
@endpush
