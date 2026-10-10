<?php

return [
    // UI languages; the first is the default. Lesson content falls back to Malay.
    'locales' => [
        'ms' => 'Bahasa Melayu',
        'en' => 'English',
    ],

    // Streaks, daily puzzles and "today" are always computed in this zone.
    'timezone' => 'Asia/Kuala_Lumpur',

    'exam_pass_ratio' => 0.7,

    // Replaying a finished lesson earns this share of its XP.
    'repeat_xp_ratio' => 0.2,

    'daily' => [
        'xp' => 15,
        'no_repeat_days' => 30,
        // Lessons whose puzzles may become the daily puzzle (DAILY_IDS in the prototype).
        'lessons' => ['fork', 'pin', 'terbuka', 'cct', 'mate2', 'gambit', 'ujian1', 'ujian2', 'ujian3', 'sah', 'seri', 'mateq', 'mater', 'nilai'],
    ],

    'game' => [
        // XP for beating Pak Kuda, by level: Mudah, Sederhana, Sukar.
        'xp' => [20, 50, 100],
        // Wins per day that earn XP (games are reported by the browser).
        'rewarded_wins_per_day' => 10,
    ],

    // "Latih semula": steps answered wrongly come back the next day, then after these gaps (days) once
    // answered right; a right answer in the last box retires the step. XP per right answer on a due step.
    'review' => [
        'intervals' => [3, 7, 14],
        'xp' => 5,
        // Step types that stand on their own and can be asked again.
        'step_types' => ['puzzle', 'quiz', 'find', 'tap', 'play', 'collect'],
    ],

    // Results are reported by the browser, so the server limits how often they count.
    'limits' => [
        'lessons_per_minute' => 6,
        'daily_per_minute' => 5,
        'games_per_minute' => 6,
        'review_per_minute' => 8,
        // A lesson finished faster than this (per step) is not recorded.
        'min_seconds_per_step' => 2,
    ],

    'student_login' => [
        'max_attempts_per_minute' => 5,
    ],
];
