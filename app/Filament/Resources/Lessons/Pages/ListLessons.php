<?php

namespace App\Filament\Resources\Lessons\Pages;

use App\Filament\Resources\Lessons\LessonResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListLessons extends ListRecords
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label(__('Import dari lessons.json'))
                ->icon('heroicon-o-arrow-down-tray')
                ->requiresConfirmation()
                ->modalDescription(__('Kandungan (tajuk, langkah, tip) dikemas kini daripada data/lessons.json selepas disemak oleh validate-lessons. Kedudukan dan status terbit di sini tidak berubah.'))
                ->action(function () {
                    $ok = Artisan::call('chessflow:import-lessons') === 0;
                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title($ok ? __('Import selesai') : __('Import gagal'))
                        ->body($output)
                        ->status($ok ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }
}
