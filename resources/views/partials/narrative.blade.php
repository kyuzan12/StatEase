{{-- Auto-written interpretation with a Copy button. Usage: @include('partials.narrative', ['text' => $paragraph]) --}}
@php($narrativeId = 'narrative-'.\Illuminate\Support\Str::random(6))

<div class="narrative-box">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
        <span class="narrative-label"><i class="bi bi-pencil-square me-1"></i> Interpretation</span>
        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none copy-button" data-copy-target="{{ $narrativeId }}">
            <i class="bi bi-clipboard"></i> <span>Copy</span>
        </button>
    </div>
    <p class="mb-0" id="{{ $narrativeId }}">{{ $text }}</p>
</div>
