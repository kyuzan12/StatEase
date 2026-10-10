@extends('layouts.app')

@section('title', 'Sample Size Calculator')

@php
    // 0.002500 -> 0.0025, 3.500000 -> 3.5, 1000 -> 1,000
    $num = fn ($value, $decimals = 6) => rtrim(rtrim(number_format($value, $decimals), '0'), '.');
@endphp

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-title">Sample Size Calculator</h1>
            <p class="page-lead">Use Slovin's formula to find how many respondents you need from a population.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <div class="formula text-center mb-3">
                        n = <span class="d-inline-block align-middle text-center">
                            <span class="d-block border-bottom border-dark px-2">N</span>
                            <span class="d-block px-2">1 + N e<sup>2</sup></span>
                        </span>
                    </div>
                    <ul class="small text-secondary-se mb-4">
                        <li><strong>n</strong> = sample size (number of respondents needed)</li>
                        <li><strong>N</strong> = population size</li>
                        <li><strong>e</strong> = margin of error (e.g. 5% = 0.05)</li>
                    </ul>

                    <form action="{{ route('slovin.calculate') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="population" class="form-label">Population size (N)</label>
                            <input type="number" id="population" name="population" min="1" step="1"
                                   value="{{ old('population', $result['population'] ?? '') }}"
                                   class="form-control @error('population') is-invalid @enderror"
                                   placeholder="e.g. 1200 (all BSIT students)" required>
                            @error('population')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="margin_of_error" class="form-label">Margin of error (e)</label>
                            <div class="input-group has-validation">
                                <input type="number" id="margin_of_error" name="margin_of_error" min="0.1" max="50" step="0.1"
                                       value="{{ old('margin_of_error', isset($result) ? $num($result['margin_of_error'] * 100, 2) : 5) }}"
                                       class="form-control @error('margin_of_error') is-invalid @enderror" required>
                                <span class="input-group-text">%</span>
                                @error('margin_of_error')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="mt-2">
                                @foreach ([1, 3, 5, 10] as $preset)
                                    <button type="button" class="btn btn-sm btn-outline-secondary preset-margin" data-value="{{ $preset }}">{{ $preset }}%</button>
                                @endforeach
                                <span class="small text-secondary-se ms-1">5% is the most common.</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-calculator me-1"></i> Compute Sample Size
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                @if ($result)
                    <div class="card-body">
                        <div class="stat-label">Required sample size</div>
                        <div class="stat-value">{{ number_format($result['sample_size']) }} respondents</div>
                        <p class="text-secondary-se">
                            out of {{ number_format($result['population']) }}
                            ({{ number_format($result['sample_size'] / $result['population'] * 100, 2) }}% of the population)
                            at a {{ $num($result['margin_of_error'] * 100, 2) }}% margin of error.
                        </p>

                        <h2 class="card-title-se mt-4 mb-2">Solution</h2>
                        <div class="formula-box">
                            <div class="solution-step">n = N / (1 + N e²)</div>
                            <div class="solution-step">n = {{ $num($result['population']) }} / (1 + {{ $num($result['population']) }} × {{ $num($result['margin_of_error']) }}²)</div>
                            <div class="solution-step">n = {{ $num($result['population']) }} / (1 + {{ $num($result['population']) }} × {{ $num($result['e_squared'], 8) }})</div>
                            <div class="solution-step">n = {{ $num($result['population']) }} / (1 + {{ $num($result['n_times_e_squared'], 4) }})</div>
                            <div class="solution-step">n = {{ $num($result['population']) }} / {{ $num($result['denominator'], 4) }}</div>
                            <div class="solution-step">n = {{ number_format($result['exact'], 2) }}</div>
                            <div class="solution-step fw-bold">n ≈ {{ number_format($result['sample_size']) }}</div>
                        </div>

                        <p class="small text-secondary-se mt-3 mb-0">
                            <i class="bi bi-info-circle"></i>
                            The answer is rounded <strong>up</strong> to a whole respondent. Rounding down would give a sample
                            slightly too small for the chosen margin of error.
                        </p>
                    </div>
                @else
                    <div class="empty-state my-auto">
                        <i class="bi bi-calculator d-block mb-2"></i>
                        <p class="mb-0">Enter the population size and margin of error to see the sample size and the step-by-step solution.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.preset-margin').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('margin_of_error').value = button.dataset.value;
        });
    });
</script>
@endpush
