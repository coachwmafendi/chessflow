<?php

namespace App\Support;

use App\Enums\GameResult;
use App\Enums\LessonKind;
use App\Models\Lesson;
use App\Models\User;

/**
 * The badge catalogue. Every badge is a threshold on a number the server already records,
 * so nothing here trusts the browser. `target: null` means "all of them" (stat + '_total').
 */
class Badges
{
    /**
     * @return array<string, array{name: string, description: string, icon: string, stat: string, target: int|null}>
     */
    public static function all(): array
    {
        return [
            'pelajaran-pertama' => ['name' => 'Langkah Pertama', 'description' => 'Siapkan pelajaran pertama.', 'icon' => 'wP', 'stat' => 'lessons', 'target' => 1],
            'separuh-jalan' => ['name' => 'Separuh Jalan', 'description' => 'Siapkan 16 pelajaran.', 'icon' => 'wN', 'stat' => 'lessons', 'target' => 16],
            'bintang-10' => ['name' => 'Pengumpul Bintang', 'description' => 'Dapat 3 bintang dalam 10 pelajaran.', 'icon' => 'star', 'stat' => 'three_stars', 'target' => 10],
            'ujian-pertama' => ['name' => 'Lulus Ujian', 'description' => 'Lulus ujian tahap pertama.', 'icon' => 'medal', 'stat' => 'exams', 'target' => 1],
            'juara' => ['name' => 'Juara ChessFlow', 'description' => 'Lulus semua ujian tahap.', 'icon' => 'wK', 'stat' => 'exams', 'target' => null],
            'api-3' => ['name' => 'Api Kecil', 'description' => 'Teka-teki harian 3 hari berturut-turut.', 'icon' => 'flame', 'stat' => 'streak', 'target' => 3],
            'api-7' => ['name' => 'Seminggu Berturut', 'description' => 'Teka-teki harian 7 hari berturut-turut.', 'icon' => 'flame', 'stat' => 'streak', 'target' => 7],
            'api-30' => ['name' => 'Sebulan Tanpa Henti', 'description' => 'Teka-teki harian 30 hari berturut-turut.', 'icon' => 'flame', 'stat' => 'streak', 'target' => 30],
            'teka-teki-10' => ['name' => 'Peminat Teka-teki', 'description' => 'Selesaikan 10 teka-teki harian.', 'icon' => 'wB', 'stat' => 'daily', 'target' => 10],
            'menang-pertama' => ['name' => 'Menang Pertama', 'description' => 'Menang lawan Pak Kuda.', 'icon' => 'wR', 'stat' => 'wins', 'target' => 1],
            'pencabar' => ['name' => 'Pencabar', 'description' => 'Menang lawan Pak Kuda tahap Sederhana.', 'icon' => 'wQ', 'stat' => 'wins_medium', 'target' => 1],
            'penakluk' => ['name' => 'Penakluk', 'description' => 'Menang lawan Pak Kuda tahap Sukar.', 'icon' => 'bK', 'stat' => 'wins_hard', 'target' => 1],
            'sama-kuat' => ['name' => 'Sama Kuat', 'description' => 'Seri dengan Pak Kuda.', 'icon' => 'half', 'stat' => 'draws', 'target' => 1],
            'xp-1000' => ['name' => 'Seribu XP', 'description' => 'Kumpul 1000 XP.', 'icon' => 'xp', 'stat' => 'xp', 'target' => 1000],
        ];
    }

    /**
     * The numbers the badges are measured on, in a handful of queries.
     *
     * @return array<string, int>
     */
    public static function stats(User $user): array
    {
        $games = $user->games()->selectRaw('result, level, count(*) as n')->groupBy('result', 'level')->get();
        $wins = $games->where('result', GameResult::Win);

        return [
            'lessons' => $user->lessonProgress()->whereNotNull('completed_at')->count(),
            'three_stars' => $user->lessonProgress()->whereNotNull('completed_at')->where('best_stars', '>=', 3)->count(),
            'exams' => $user->examAttempts()->where('passed', true)->distinct()->count('lesson_id'),
            'exams_total' => Lesson::where('is_published', true)->where('kind', LessonKind::Exam)->count(),
            'streak' => max((int) $user->streak_best, $user->currentStreak()),
            'daily' => $user->dailyCompletions()->count(),
            'wins' => (int) $wins->sum('n'),
            'wins_medium' => (int) $wins->where('level', '>=', 1)->sum('n'),
            'wins_hard' => (int) $wins->where('level', '>=', 2)->sum('n'),
            'draws' => (int) $games->where('result', GameResult::Draw)->sum('n'),
            'xp' => (int) $user->xp,
        ];
    }

    /**
     * @param  array{stat: string, target: int|null}  $badge
     * @param  array<string, int>  $stats
     * @return array{have: int, need: int}
     */
    public static function progress(array $badge, array $stats): array
    {
        $need = $badge['target'] ?? ($stats[$badge['stat'].'_total'] ?? 0);

        return ['have' => min($stats[$badge['stat']] ?? 0, max($need, 1)), 'need' => max($need, 1)];
    }

    /**
     * @param  array{stat: string, target: int|null}  $badge
     * @param  array<string, int>  $stats
     */
    public static function earned(array $badge, array $stats): bool
    {
        $need = $badge['target'] ?? ($stats[$badge['stat'].'_total'] ?? 0);

        return $need > 0 && ($stats[$badge['stat']] ?? 0) >= $need;
    }
}
