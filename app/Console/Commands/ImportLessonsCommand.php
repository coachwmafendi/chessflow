<?php

namespace App\Console\Commands;

use App\Actions\ImportLessons;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

#[Signature('chessflow:import-lessons {--path=data/lessons.json : Lesson JSON file, relative to the project root} {--skip-validation : Do not run tools/validate-lessons.cjs first}')]
#[Description('Import levels and lessons from data/lessons.json (idempotent, keyed by slug)')]
class ImportLessonsCommand extends Command
{
    public function handle(ImportLessons $import): int
    {
        $option = (string) $this->option('path');
        $path = str_starts_with($option, '/') ? $option : base_path($option);

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        if (! $this->option('skip-validation')) {
            $result = Process::path(base_path())->run(['node', 'tools/validate-lessons.cjs', $path]);
            $this->line(trim($result->output()));

            if (! $result->successful()) {
                $this->error(trim($result->errorOutput()) ?: 'Lesson validation failed; nothing imported.');

                return self::FAILURE;
            }
        }

        $stats = $import->handle($path);

        $this->info(sprintf(
            '%d levels; lessons: %d created, %d updated, %d unchanged.',
            $stats['levels'], $stats['created'], $stats['updated'], $stats['unchanged'],
        ));

        if ($stats['orphaned'] !== []) {
            $this->warn('In DB but not in JSON (left untouched): '.implode(', ', $stats['orphaned']));
        }

        return self::SUCCESS;
    }
}
