{{--
    Likert scale dropdown with a live preview of the selected scale's labels.
    Usage: @include('partials.scale-select', ['scales' => $scales, 'selected' => $id])
--}}
@php
    $selectedId = (int) old('likert_scale_id', $selected ?? $scales->first()?->id);

    $levelsByScale = $scales->mapWithKeys(function ($scale) {
        $colors = $scale->colors();

        return [$scale->id => $scale->levels->map(fn ($level) => [
            'value' => $level->value,
            'label' => $level->label,
            'background' => $colors[$level->value]['background'],
            'text' => $colors[$level->value]['text'],
        ])];
    });
@endphp

<select id="likert_scale_id" name="likert_scale_id"
        class="form-select @error('likert_scale_id') is-invalid @enderror" required>
    @foreach ($scales as $scale)
        <option value="{{ $scale->id }}" @selected($selectedId === $scale->id)
                data-levels="{{ json_encode($levelsByScale[$scale->id]) }}">
            {{ $scale->name }} ({{ $scale->points }} points)
        </option>
    @endforeach
</select>
@error('likert_scale_id')
    <div class="invalid-feedback">{{ $message }}</div>
@enderror

<div id="scalePreview" class="d-flex flex-wrap gap-1 mt-2" aria-live="polite"></div>
<div class="form-text">
    Answers can be the number (1 = lowest) or the exact label.
    <a href="{{ route('scales.index') }}" target="_blank">Manage scales</a>
</div>

@push('scripts')
<script>
    (function () {
        const select = document.getElementById('likert_scale_id');
        const preview = document.getElementById('scalePreview');

        function render() {
            const levels = JSON.parse(select.selectedOptions[0].dataset.levels);
            preview.innerHTML = '';
            levels.forEach(level => {
                const chip = document.createElement('span');
                chip.className = 'level-chip';
                chip.style.background = level.background;
                chip.style.color = level.text;
                chip.textContent = `${level.value} = ${level.label}`;
                preview.appendChild(chip);
            });
        }

        select.addEventListener('change', render);
        render();
    })();
</script>
@endpush
