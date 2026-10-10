<?php

use App\Actions\AwardBadges;
use App\Enums\GameResult;
use App\Models\Lesson;
use App\Models\Level;
use App\Support\AssignmentReport;
use App\Support\Badges;
use App\Support\Chessflow;
use App\Support\Curriculum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Peta')] class extends Component {
    public function with(Curriculum $curriculum): array
    {
        $user = auth()->user();

        // Catch up on badges (e.g. progress made before badges existed), then celebrate the unseen ones once.
        app(AwardBadges::class)->handle($user, markSeen: false);
        $catalogue = Badges::all();
        $unseen = $user->badges()->whereNull('seen_at')->get();
        $newBadges = $unseen->map(fn ($b) => $catalogue[$b->badge] ?? null)->filter()->values();
        if ($unseen->isNotEmpty()) {
            $user->badges()->whereNull('seen_at')->update(['seen_at' => now()]);
        }
        $lessons = $curriculum->lessons();
        $completed = $curriculum->completedIds($user);
        $progress = $user->lessonProgress()->get()->keyBy('lesson_id');
        $examScores = $user->examAttempts()
            ->where('passed', true)
            ->get()
            ->groupBy('lesson_id')
            ->map(fn ($attempts) => $attempts->sortByDesc('score')->first());

        $stops = $lessons->map(fn (Lesson $l) => [
            'lesson' => $l,
            'done' => isset($completed[$l->id]),
            'locked' => ! $curriculum->isUnlocked($user, $l, $completed),
            'stars' => $progress->get($l->id)?->best_stars ?? 0,
            'score' => ($a = $examScores->get($l->id)) ? $a->score.'/'.$a->total : null,
        ]);

        return [
            'levels' => Level::orderBy('position')->get(),
            'stops' => $stops->groupBy(fn (array $s) => $s['lesson']->level_id),
            'next' => $stops->first(fn (array $s) => ! $s['done'] && ! $s['locked'])['lesson'] ?? null,
            'doneCount' => count($completed),
            'total' => $lessons->count(),
            'dailyDone' => $user->dailyCompletions()->whereDate('date', Chessflow::today())->exists(),
            'streak' => $user->currentStreak(),
            'wins' => $user->games()->where('result', GameResult::Win)->count(),
            'assignments' => app(AssignmentReport::class)->forStudent($user),
            'newBadges' => $newBadges,
            'badgeCount' => $user->badges()->count(),
            'badgeTotal' => count($catalogue),
            'reviewDue' => $user->reviewItems()->due()->count(),
            'reviewTotal' => $user->reviewItems()->count(),
        ];
    }
}; ?>

@php
    $zig = [0, 55, 85, 55, 0, -55, -85, -55];
    $zi = 0;
@endphp

<div>
    <section class="hero">
        <div class="avatar"><i class="pc pk"></i></div>
        <div>
            <h1>{{ $doneCount ? __('Selamat kembali!') : __('Jom main catur!') }}</h1>
            <p>{{ __('Saya Pak Kuda. Kita belajar catur langkah demi langkah: kenal buah, belajar taktik, kutip bintang dan kumpul XP.') }}</p>
            <div class="hero-row">
                @if ($next)
                    <a class="cta" href="{{ route('pelajaran', $next) }}">{{ $doneCount ? __('Sambung: :lesson', ['lesson' => $next->title]) : __('Mula: :lesson', ['lesson' => $next->title]) }}</a>
                @else
                    <span class="pill">{{ __('Semua pelajaran selesai!') }}</span>
                @endif
                <div class="meter" role="progressbar" aria-label="{{ __('Kemajuan pelajaran') }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $doneCount }}"><span style="width: {{ $total ? round($doneCount / $total * 100) : 0 }}%"></span></div>
                <small class="meter-lbl">{{ __(':done/:total pelajaran', ['done' => $doneCount, 'total' => $total]) }}</small>
            </div>
        </div>
    </section>

    @if ($newBadges->isNotEmpty())
        <section class="badge-toast" role="status">
            <b>{{ $newBadges->count() === 1 ? __('Lencana baru!') : __(':n lencana baru!', ['n' => $newBadges->count()]) }}</b>
            <div class="badge-toast-list">
                @foreach ($newBadges as $b)
                    <x-chessflow.badge :badge="$b" :earned="true" class="mini" />
                @endforeach
            </div>
            <a class="ghost" href="{{ route('lencana') }}">{{ __('Lihat semua lencana') }}</a>
        </section>
    @endif

    @if ($assignments->isNotEmpty())
        <section class="tasks" aria-labelledby="tasks-h">
            <h2 id="tasks-h">{{ __('Tugasan daripada guru') }}</h2>
            <ul>
                @foreach ($assignments as $t)
                    @php $a = $t['assignment']; @endphp
                    <li>
                        <a class="task-card{{ $t['done'] ? ' done' : ($a->isOverdue() ? ' overdue' : '') }}" href="{{ route('pelajaran', $a->lesson) }}">
                            <span class="qico"><i class="pc {{ $a->lesson->icon }}"></i></span>
                            <span class="task-body">
                                <b>{{ $a->lesson->title }}</b>
                                <small>{{ $a->classroom->name }}@if ($a->note) · {{ $a->note }}@endif</small>
                            </span>
                            <span class="task-state">
                                @if ($t['done'])
                                    {{ __('Siap') }}
                                @elseif ($a->isOverdue())
                                    {{ __('Lewat') }}
                                @else
                                    {{ $a->dueLabel() ?? __('Buat sekarang') }}
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="quick">
        <a class="qcard{{ $dailyDone ? ' done' : '' }}" href="{{ route('harian') }}">
            <span class="qico flame"><x-chessflow.flame /></span>
            <span><b>{{ __('Teka-teki Hari Ini') }}</b><small>{{ $dailyDone ? __('Selesai! Datang lagi esok.') : __('Satu teka-teki baharu setiap hari') }}</small></span>
            <span class="qnum">{{ $streak }}<small>{{ __('hari') }}</small></span>
        </a>
        @if ($reviewTotal > 0)
            <a class="qcard{{ $reviewDue ? '' : ' done' }}" href="{{ route('latih') }}">
                <span class="qico review"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35A7.97 7.97 0 0 0 12 4z"/></svg></span>
                <span><b>{{ __('Latih semula') }}</b><small>{{ $reviewDue ? __('Soalan yang awak tersilap sebelum ini') : __('Tiada latihan hari ini. Bagus!') }}</small></span>
                <span class="qnum">{{ $reviewDue }}<small>{{ __('hari ini') }}</small></span>
            </a>
        @endif
        <a class="qcard" href="{{ route('lencana') }}">
            <span class="qico badge-qico"><x-chessflow.medal /></span>
            <span><b>{{ __('Lencana') }}</b><small>{{ __('Kumpul lencana dengan belajar dan bermain') }}</small></span>
            <span class="qnum">{{ $badgeCount }}<small>/{{ $badgeTotal }}</small></span>
        </a>
        <a class="qcard" href="{{ route('main') }}">
            <span class="qico"><i class="pc bK"></i></span>
            <span><b>{{ __('Main lawan Pak Kuda') }}</b><small>{{ __('Permainan penuh: mudah, sederhana atau sukar') }}</small></span>
            <span class="qnum">{{ $wins }}<small>{{ __('menang') }}</small></span>
        </a>
    </section>
    @if (auth()->user()->isStudent())
        <p class="join-link"><a href="{{ route('sertai') }}">{{ __('Ada kod kelas daripada guru? Sertai kelas') }}</a></p>
    @endif

    @foreach ($levels as $level)
        @php
            $levelStops = $stops->get($level->id, collect());
        @endphp
        @continue($levelStops->isEmpty())
        <section class="tahap">
            <div class="tahap-head">
                <small>{{ __('Tahap :n', ['n' => $level->number]) }}</small>
                <h2>{{ $level->name }}</h2>
                <span>{{ $level->note }} · {{ $levelStops->where('done', true)->count() }}/{{ $levelStops->count() }}</span>
            </div>
            <div class="path">
                @foreach ($levelStops as $s)
                    @php
                        $l = $s['lesson'];
                        $exam = $l->isExam();
                        $x = $exam ? 0 : $zig[$zi++ % count($zig)];
                        $cls = ($s['done'] ? 'done' : ($s['locked'] ? 'locked' : 'open')).($exam ? ' exam' : '');
                    @endphp
                    <div class="stop {{ $cls }}" style="--x: {{ $x }}px">
                        @if ($s['locked'])
                            <button class="node" type="button" disabled aria-label="{{ __(':lesson, terkunci', ['lesson' => $l->title]) }}">
                                @if ($exam)<span class="medal"><x-chessflow.medal /></span>@else<i class="pc {{ $l->icon }}"></i>@endif
                                <span class="lock"><x-chessflow.lock /></span>
                            </button>
                        @else
                            <a class="node" href="{{ route('pelajaran', $l) }}" aria-label="{{ $l->title }}">
                                @if ($exam)<span class="medal"><x-chessflow.medal /></span>@else<i class="pc {{ $l->icon }}"></i>@endif
                            </a>
                        @endif
                        <span class="lbl">{{ $l->title }}</span>
                        <span class="sub">
                            @if ($s['done'])
                                @if ($exam) {{ __('Lulus · :score', ['score' => $s['score']]) }} @else <x-chessflow.stars :n="$s['stars']" /> @endif
                            @elseif ($s['locked'])
                                {{ __('Terkunci') }}
                            @else
                                {{ $exam ? __(':n soalan · lulus 70%', ['n' => count($l->steps)]) : __(':n langkah · :xp XP', ['n' => count($l->steps), 'xp' => $l->xp]) }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
