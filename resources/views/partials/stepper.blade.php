{{-- Upload progress. Usage: @include('partials.stepper', ['current' => 1]) --}}
@php($steps = [1 => 'Upload file', 2 => 'Confirm column types', 3 => 'View results'])

<ol class="stepper" aria-label="Progress">
    @foreach ($steps as $number => $label)
        <li class="{{ $number < $current ? 'is-done' : ($number === $current ? 'is-current' : '') }}"
            @if ($number === $current) aria-current="step" @endif>
            <span class="step-circle">
                @if ($number < $current)
                    <i class="bi bi-check-lg"></i>
                @else
                    {{ $number }}
                @endif
            </span>
            {{ $label }}
        </li>
    @endforeach
</ol>
