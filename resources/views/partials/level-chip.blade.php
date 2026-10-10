{{-- A colored interpretation label. Expects $level with 'interpretation', 'background' and 'text'. --}}
<span class="level-chip" style="background: {{ $level['background'] }}; color: {{ $level['text'] }}">{{ $level['interpretation'] }}</span>
