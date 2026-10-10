@props(['rows', 'lessons', 'actions' => null])
{{-- Students × lessons: best stars per lesson (✓ for passed exams). --}}
<div class="progress-wrap">
    <table class="progress-table">
        <thead>
            <tr>
                <th scope="col" class="sticky">{{ __('Murid') }}</th>
                <th scope="col">XP</th>
                <th scope="col">Streak</th>
                <th scope="col">{{ __('Ujian lulus') }}</th>
                <th scope="col">{{ __('Aktiviti terakhir') }}</th>
                @foreach ($lessons as $lesson)
                    <th scope="col" class="lesson-col" title="{{ $lesson->title }}"><span>{{ $lesson->title }}</span></th>
                @endforeach
                @if ($actions)<th scope="col"><span class="sr-only">{{ __('Tindakan') }}</span></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <th scope="row" class="sticky">{{ $row['student']->name }}<small>{{ $row['student']->username }}</small></th>
                    <td>{{ $row['student']->xp }}</td>
                    <td>{{ $row['streak'] }}</td>
                    <td>{{ $row['examsPassed'] }}</td>
                    <td>{{ $row['lastActive']?->locale('ms')->diffForHumans() ?? '—' }}</td>
                    @foreach ($lessons as $lesson)
                        @php
                            $stars = $row['stars'][$lesson->id] ?? null;
                        @endphp
                        <td class="cell{{ $stars === null ? ' empty' : '' }}">
                            @if ($stars === null)
                                ·
                            @elseif ($lesson->isExam())
                                ✓
                            @else
                                {{ str_repeat('★', $stars) }}
                            @endif
                        </td>
                    @endforeach
                    @if ($actions)<td>{!! $actions($row['student']) !!}</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ 5 + $lessons->count() + ($actions ? 1 : 0) }}">{{ __('Belum ada murid.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
