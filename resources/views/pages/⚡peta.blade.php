<?php

use App\Enums\GameResult;
use App\Models\Lesson;
use App\Models\Level;
use App\Support\Chessflow;
use App\Support\Curriculum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::chessflow')] #[Title('Peta')] class extends Component {
    public function with(Curriculum $curriculum): array
    {
        $user = auth()->user();
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
            <h1>{{ $doneCount ? 'Selamat kembali!' : 'Jom main catur!' }}</h1>
            <p>Saya Pak Kuda. Kita belajar catur langkah demi langkah: kenal buah, belajar taktik, kutip bintang dan kumpul XP.</p>
            <div class="hero-row">
                @if ($next)
                    <a class="cta" href="{{ route('pelajaran', $next) }}">{{ $doneCount ? 'Sambung' : 'Mula' }}: {{ $next->title }}</a>
                @else
                    <span class="pill">Semua pelajaran selesai!</span>
                @endif
                <div class="meter" aria-label="Kemajuan"><span style="width: {{ $total ? round($doneCount / $total * 100) : 0 }}%"></span></div>
                <small class="meter-lbl">{{ $doneCount }}/{{ $total }} pelajaran</small>
            </div>
        </div>
    </section>

    <section class="quick">
        <a class="qcard{{ $dailyDone ? ' done' : '' }}" href="{{ route('harian') }}">
            <span class="qico flame"><x-chessflow.flame /></span>
            <span><b>Teka-teki Hari Ini</b><small>{{ $dailyDone ? 'Selesai! Datang lagi esok.' : 'Satu teka-teki baharu setiap hari' }}</small></span>
            <span class="qnum">{{ $streak }}<small>hari</small></span>
        </a>
        <a class="qcard" href="{{ route('main') }}">
            <span class="qico"><i class="pc bK"></i></span>
            <span><b>Main lawan Pak Kuda</b><small>Permainan penuh: mudah, sederhana atau sukar</small></span>
            <span class="qnum">{{ $wins }}<small>menang</small></span>
        </a>
    </section>
    @if (auth()->user()->isStudent())
        <p class="join-link"><a href="{{ route('sertai') }}">Ada kod kelas daripada guru? Sertai kelas</a></p>
    @endif

    @foreach ($levels as $level)
        @php
            $levelStops = $stops->get($level->id, collect());
        @endphp
        @continue($levelStops->isEmpty())
        <section class="tahap">
            <div class="tahap-head">
                <small>Tahap {{ $level->number }}</small>
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
                            <button class="node" type="button" disabled aria-label="{{ $l->title }}, terkunci">
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
                                @if ($exam) Lulus · {{ $s['score'] }} @else <x-chessflow.stars :n="$s['stars']" /> @endif
                            @elseif ($s['locked'])
                                Terkunci
                            @else
                                {{ $exam ? count($l->steps).' soalan · lulus 70%' : count($l->steps).' langkah · '.$l->xp.' XP' }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
