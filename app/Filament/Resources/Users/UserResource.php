<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\UserDataExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Admins promote teachers/admins and fix names. Accounts are created by users, teachers and parents. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            Role::Murid->value => 'Murid',
            Role::IbuBapa->value => 'Ibu bapa',
            Role::Guru->value => 'Guru',
            Role::Admin->value => 'Admin',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(60),
                Select::make('role')->label('Peranan')->options(self::roleOptions())->required()
                    // Students have no email/password login, so they stay students.
                    ->disabled(fn (?User $record) => $record?->email === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('username')->label('Nama pengguna')->searchable()->placeholder('—'),
                TextColumn::make('email')->label('E-mel')->searchable()->placeholder('—'),
                TextColumn::make('role')->label('Peranan')->badge()
                    ->formatStateUsing(fn (Role $state) => self::roleOptions()[$state->value]),
                TextColumn::make('xp')->label('XP')->sortable(),
                TextColumn::make('created_at')->label('Dicipta')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peranan')->options(self::roleOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                // For PDPA access requests sent by email.
                Action::make('exportData')->label('Eksport data')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->action(fn (User $record) => app(UserDataExport::class)->download($record)),
                DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
