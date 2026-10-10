@props(['badge', 'earned' => false, 'progress' => null, 'date' => null])
{{-- One achievement badge (catalogue: App\Support\Badges). Locked badges show progress instead of a date. --}}
<div {{ $attributes->class(['badge', 'earned' => $earned, 'locked' => ! $earned]) }}>
    <span class="badge-medal" aria-hidden="true">
        @switch($badge['icon'])
            @case('star') <i class="star-ico"></i> @break
            @case('flame') <x-chessflow.flame /> @break
            @case('medal') <x-chessflow.medal /> @break
            @case('half') <span class="badge-glyph">½</span> @break
            @case('xp') <span class="badge-glyph">XP</span> @break
            @default <i class="pc {{ $badge['icon'] }}"></i>
        @endswitch
    </span>
    <b>{{ $badge['name'] }}</b>
    <small>{{ $badge['description'] }}</small>
    @if ($earned && $date)
        <span class="badge-date">Dapat {{ $date->locale('ms')->translatedFormat('j M Y') }}</span>
    @elseif (! $earned && $progress)
        <span class="badge-progress">
            <span class="meter"><span style="width: {{ round($progress['have'] / $progress['need'] * 100) }}%"></span></span>
            <span>{{ $progress['have'] }}/{{ $progress['need'] }}</span>
        </span>
    @endif
</div>
