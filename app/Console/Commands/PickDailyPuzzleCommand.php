<?php

namespace App\Console\Commands;

use App\Actions\PickDailyPuzzle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('chessflow:pick-daily {--date= : Local date (Y-m-d), defaults to today in Asia/Kuala_Lumpur}')]
#[Description('Choose the daily puzzle (no repeats within 30 days)')]
class PickDailyPuzzleCommand extends Command
{
    public function handle(PickDailyPuzzle $pick): int
    {
        $date = $this->option('date');
        $puzzle = $pick->handle(is_string($date) ? $date : null);
        $puzzle->load('lesson');

        $this->info(sprintf('%s: %s, step %d', $puzzle->date->toDateString(), $puzzle->lesson->slug, $puzzle->step_index));

        return self::SUCCESS;
    }
}
