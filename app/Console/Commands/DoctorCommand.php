<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\DailyPuzzle;
use App\Models\Lesson;
use App\Models\User;
use App\Support\Chessflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Throwable;

#[Signature('chessflow:doctor')]
#[Description('Check that this install is ready for production (run inside the container after deploy)')]
class DoctorCommand extends Command
{
    /** @var list<array{string, string, string}> */
    private array $rows = [];

    public function handle(): int
    {
        $prod = app()->isProduction();

        $this->check('APP_ENV=production', $prod, 'warn', (string) config('app.env'));
        $this->check('APP_DEBUG off', ! config('app.debug'), $prod ? 'fail' : 'warn');
        $this->check('APP_URL is https', str_starts_with((string) config('app.url'), 'https://'), $prod ? 'fail' : 'warn', (string) config('app.url'));
        $this->check('Timezone Asia/Kuala_Lumpur', config('app.timezone') === Chessflow::timezone(), 'fail', (string) config('app.timezone'));
        $this->check('Locale ms', config('app.locale') === 'ms', 'warn', (string) config('app.locale'));
        $this->check('Secure session cookie', (bool) config('session.secure'), $prod ? 'fail' : 'warn', 'SESSION_SECURE_COOKIE');

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (Throwable $e) {
            $dbOk = false;
        }
        $this->check('Database reachable', $dbOk, 'fail', (string) config('database.default'));

        if ($dbOk) {
            $json = json_decode((string) file_get_contents(base_path('data/lessons.json')), true);
            $expected = is_array($json) ? count($json['lessons'] ?? []) : 0;
            $lessons = Lesson::count();
            $this->check('Lessons imported', $lessons >= $expected && $expected > 0, 'fail', "{$lessons} in DB / {$expected} in lessons.json");
            $this->check('An admin account exists', User::where('role', Role::Admin)->exists(), 'warn', 'php artisan chessflow:set-role <email> admin');
            $this->check("Today's daily puzzle picked", DailyPuzzle::whereDate('date', Chessflow::today())->exists(), 'warn', 'scheduler: php artisan schedule:run every minute');
        }

        $node = Process::run(['node', '--version']);
        $this->check('Node available (lesson validation on import)', $node->successful(), 'warn', trim($node->output()) ?: 'use --skip-validation if Node is absent');
        $this->check('Vite build present', is_file(public_path('build/manifest.json')), 'fail', 'npm run build');
        $this->check('Stockfish files present', is_file(public_path('stockfish/stockfish.js')) && is_file(public_path('stockfish/stockfish.wasm')), 'fail', 'public/stockfish/');

        $this->table(['', 'Check', 'Detail'], $this->rows);
        $this->line('Also check from outside: curl -I '.rtrim((string) config('app.url'), '/').'/stockfish/stockfish.wasm  → Content-Type: application/wasm');

        $failed = collect($this->rows)->contains(fn (array $r) => $r[0] === 'FAIL');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $severity, string $detail = ''): void
    {
        $this->rows[] = [$ok ? 'ok' : strtoupper($severity), $label, $detail];
    }
}
