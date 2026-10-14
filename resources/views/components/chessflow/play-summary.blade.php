@props(['rows'])
{{-- Games against Pak Kuda and practice, per student (App\Support\PlayReport), for parents and teachers. --}}
@php
    $levels = [__('Mudah'), __('Sederhana'), __('Sukar')];
    $any = $rows->contains(fn ($r) => $r['played'] > 0 || $r['reviewTotal'] > 0);
@endphp
<section class="panel-card play-summary" aria-labelledby="play-summary-h">
    <h2 id="play-summary-h">{{ __('Permainan & latihan') }}</h2>
    @if (! $any)
        <p class="muted">{{ __('Belum ada permainan lawan Pak Kuda atau Latih semula.') }}</p>
    @else
        <p class="muted">{{ __('"Kesilapan kerap" ialah corak yang paling banyak dalam Latih semula daripada permainan, jadi itulah yang paling perlu dilatih.') }}</p>
        <div class="progress-wrap">
            <table class="progress-table play-table">
                <thead>
                    <tr>
                        <th scope="col" class="sticky">{{ __('Murid') }}</th>
                        <th scope="col">{{ __('Permainan') }}</th>
                        <th scope="col">{{ __('Menang · Kalah · Seri') }}</th>
                        <th scope="col">{{ __('Menang tertinggi') }}</th>
                        <th scope="col" class="left">{{ __('Pembukaan kerap') }}</th>
                        <th scope="col" class="left">{{ __('Kesilapan kerap') }}</th>
                        <th scope="col">{{ __('Latih semula') }}</th>
                        <th scope="col">{{ __('Permainan terakhir') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <th scope="row" class="sticky">{{ $row['student']->name }}<small>{{ $row['student']->username }}</small></th>
                            <td>{{ $row['played'] ?: '—' }}</td>
                            <td>@if ($row['played'])<span class="w">{{ $row['won'] }}</span> · <span class="l">{{ $row['lost'] }}</span> · {{ $row['drawn'] }}@else — @endif</td>
                            <td>{{ $row['bestWin'] !== null ? $levels[$row['bestWin']] ?? '—' : '—' }}</td>
                            <td class="left" lang="en">{{ $row['opening'] ?? '—' }}</td>
                            <td class="left">@if ($row['mistake']){{ $row['mistake']['title'] }} <small>× {{ $row['mistake']['count'] }}</small>@else — @endif</td>
                            <td>@if ($row['reviewTotal']){{ __(':due hari ini · :total', ['due' => $row['reviewDue'], 'total' => $row['reviewTotal']]) }}@else — @endif</td>
                            <td>{{ $row['lastGame']?->locale(app()->getLocale())->diffForHumans() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
