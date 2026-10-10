<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Certificate;
use App\Models\Classroom;
use App\Models\DailyCompletion;
use App\Models\ExamAttempt;
use App\Models\Game;
use App\Models\LessonProgress;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\XpEvent;
use Carbon\CarbonInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everything ChessFlow holds about one account, as JSON: the copy a person can ask for under the
 * Personal Data Protection Act 2010 (access and data portability). Secrets (password, PIN, 2FA,
 * passkeys, remember token) are never included.
 */
class UserDataExport
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $data = [
            'exported_at' => now()->toIso8601String(),
            'service' => 'ChessFlow (WM AFENDI ENTERPRISE)',
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $this->date($user->created_at),
                'xp' => (int) $user->xp,
                'streak_current' => (int) $user->streak_current,
                'streak_best' => (int) $user->streak_best,
            ],
        ];

        if ($user->role === Role::Murid) {
            return $data + $this->learning($user);
        }

        return $data + [
            'linked_children' => $user->students()->orderBy('name')->get()
                ->map(fn (User $s) => ['name' => $s->name, 'username' => $s->username])->all(),
            'classes_taught' => $user->taughtClassrooms()->with(['assignments.lesson'])->withCount('students')->orderBy('name')->get()
                ->map(fn (Classroom $c) => [
                    'name' => $c->name,
                    'join_code' => $c->join_code,
                    'students' => $c->students_count,
                    'assignments' => $c->assignments->map(fn (Assignment $a) => [
                        'lesson' => $a->lesson->title, 'due_on' => $a->due_on?->toDateString(), 'note' => $a->note,
                    ])->all(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function learning(User $user): array
    {
        return [
            'lessons' => LessonProgress::where('user_id', $user->id)->with('lesson')->orderBy('id')->get()
                ->map(fn (LessonProgress $p) => [
                    'lesson' => $p->lesson?->title, 'best_stars' => $p->best_stars, 'last_stars' => $p->last_stars,
                    'mistakes' => $p->mistakes, 'attempts' => $p->attempts, 'completed_at' => $this->date($p->completed_at),
                ])->all(),
            'exams' => ExamAttempt::where('user_id', $user->id)->with('lesson')->orderBy('id')->get()
                ->map(fn (ExamAttempt $e) => [
                    'exam' => $e->lesson?->title, 'score' => $e->score, 'total' => $e->total, 'passed' => $e->passed, 'at' => $this->date($e->created_at),
                ])->all(),
            'certificates' => Certificate::where('user_id', $user->id)->with('level')->orderBy('id')->get()
                ->map(fn (Certificate $c) => [
                    'level' => $c->level?->name, 'name_on_certificate' => $c->display_name, 'score' => $c->score.'/'.$c->total,
                    'code' => $c->code, 'issued_at' => $this->date($c->issued_at),
                ])->all(),
            'daily_puzzles_solved' => DailyCompletion::where('user_id', $user->id)->orderBy('date')->get()
                ->map(fn (DailyCompletion $d) => $d->date->toDateString())->all(),
            'games' => Game::where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn (Game $g) => [
                    'colour' => $g->user_color, 'level' => $g->level, 'result' => $g->result->value,
                    'moves' => $g->move_count, 'pgn' => $g->pgn, 'finished_at' => $this->date($g->finished_at),
                ])->all(),
            'xp_history' => XpEvent::where('user_id', $user->id)->orderBy('id')->get()
                ->map(fn (XpEvent $x) => ['amount' => $x->amount, 'for' => $x->source_type ? class_basename($x->source_type) : null, 'at' => $this->date($x->created_at)])->all(),
            'review_queue' => ReviewItem::where('user_id', $user->id)->with('lesson')->orderBy('due_on')->get()
                ->map(fn (ReviewItem $r) => ['lesson' => $r->lesson?->title, 'step' => $r->step_index + 1, 'box' => $r->box, 'due_on' => $r->due_on->toDateString()])->all(),
            'badges' => UserBadge::where('user_id', $user->id)->orderBy('awarded_at')->get()
                ->map(fn (UserBadge $b) => ['badge' => Badges::all()[$b->badge]['name'] ?? $b->badge, 'awarded_at' => $this->date($b->awarded_at)])->all(),
            'classes' => $user->classrooms()->with('teacher')->orderBy('name')->get()
                ->map(fn (Classroom $c) => ['name' => $c->name, 'teacher' => $c->teacher?->name])->all(),
            'guardians' => $user->guardians()->orderBy('name')->pluck('name')->all(),
        ];
    }

    /** JSON file download, e.g. chessflow-data-aina2-2026-10-10.json */
    public function download(User $user): StreamedResponse
    {
        $slug = str((string) ($user->username ?: $user->name))->slug()->limit(30, '');
        $name = 'chessflow-data-'.$slug.'-'.Chessflow::today().'.json';

        return response()->streamDownload(
            fn () => print (json_encode($this->for($user), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $name,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    private function date(?CarbonInterface $at): ?string
    {
        return $at?->toIso8601String();
    }
}
