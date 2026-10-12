<?php

namespace App\Filament\Resources\Lessons;

use App\Enums\LessonKind;
use App\Filament\Resources\Lessons\Pages\ListLessons;
use App\Filament\Resources\Lessons\Pages\ViewLesson;
use App\Models\Lesson;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lesson content lives in data/lessons.json (git). Admins only see it, preview it,
 * publish/hide it and change the order — the fields the importer leaves alone.
 */
class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.lesson');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.lessons');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Butiran'))
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('slug'),
                        TextEntry::make('level.name')->label(__('Tahap'))
                            ->formatStateUsing(fn (string $state, Lesson $record) => $record->level->number.'. '.$state),
                        TextEntry::make('kind')->label(__('Jenis'))->badge()
                            ->formatStateUsing(fn (LessonKind $state) => $state === LessonKind::Exam ? __('Ujian') : __('Pelajaran')),
                        TextEntry::make('position')->label(__('Kedudukan')),
                        TextEntry::make('xp')->label(__('XP')),
                        IconEntry::make('is_published')->label(__('Diterbitkan'))->boolean(),
                        TextEntry::make('content_version')->label(__('Versi kandungan')),
                        TextEntry::make('steps')->label(__('Langkah'))
                            ->state(fn (Lesson $record) => count($record->steps)),
                        TextEntry::make('updated_at')->label(__('Dikemas kini'))->dateTime(),
                        TextEntry::make('tip')->columnSpanFull()->hidden(fn (Lesson $record) => blank($record->tip)),
                    ]),
                Section::make(__('Pratonton'))
                    ->description(__('Pulau TS yang sama seperti murid lihat. Keputusan tidak disimpan.'))
                    ->schema([
                        ViewEntry::make('preview')->hiddenLabel()->view('filament.lesson-preview'),
                    ]),
                Section::make(__('Langkah'))
                    ->description(__('Semakan kandungan. Untuk ubah: edit data/lessons.json, jalankan npm run validate:lessons, kemudian import.'))
                    ->schema([
                        ViewEntry::make('steps_readable')->hiddenLabel()->view('filament.lesson-steps-readable'),
                    ]),
                Section::make(__('Langkah (JSON)'))
                    ->description(__('Data mentah, untuk pembangun.'))
                    ->collapsed()
                    ->schema([
                        ViewEntry::make('steps_json')->hiddenLabel()->view('filament.lesson-steps'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            // How students do on each lesson: how many finished it and their average best stars.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['progress as done_count' => fn (Builder $p) => $p->whereNotNull('completed_at')])
                ->withAvg(['progress as avg_stars' => fn (Builder $p) => $p->whereNotNull('completed_at')], 'best_stars'))
            ->defaultGroup(Group::make('level.number')->label(__('Tahap'))
                ->getTitleFromRecordUsing(fn (Lesson $record) => $record->level->number.'. '.$record->level->name)
                ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderBy('position', $direction === 'desc' ? 'desc' : 'asc'))
                ->collapsible())
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('position')->label(__('#'))->sortable(),
                TextColumn::make('title')->label(__('Tajuk'))->searchable()
                    ->description(fn (Lesson $record) => $record->slug),
                TextColumn::make('level.number')->label(__('Tahap'))->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('kind')->label(__('Jenis'))->badge()
                    ->formatStateUsing(fn (LessonKind $state) => $state === LessonKind::Exam ? __('Ujian') : __('Pelajaran'))
                    ->color(fn (LessonKind $state) => $state === LessonKind::Exam ? 'warning' : 'gray'),
                TextColumn::make('steps')->label(__('Langkah'))
                    ->state(fn (Lesson $record) => count($record->steps)),
                TextColumn::make('xp')->label(__('XP')),
                TextColumn::make('done_count')->label(__('Murid siap'))->sortable()->alignEnd(),
                TextColumn::make('avg_stars')->label(__('Purata ★'))->sortable()->alignEnd()->placeholder('—')
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 1))
                    // Low averages point at lessons that are too hard or unclear.
                    ->color(fn ($state) => $state === null ? null : ((float) $state < 2 ? 'danger' : ((float) $state < 2.5 ? 'warning' : 'success'))),
                TextColumn::make('content_version')->label(__('Versi')),
                ToggleColumn::make('is_published')->label(__('Diterbitkan')),
            ])
            ->filters([
                SelectFilter::make('level')->label(__('Tahap'))->relationship('level', 'name'),
                TernaryFilter::make('is_published')->label(__('Diterbitkan')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'view' => ViewLesson::route('/{record}'),
        ];
    }
}
