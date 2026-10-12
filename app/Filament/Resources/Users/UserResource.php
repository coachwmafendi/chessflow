<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\UserDataExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Admins promote teachers/admins and fix names. Accounts are created by users, teachers and parents. */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users');
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptions(): array
    {
        return [
            Role::Murid->value => __('roles.murid'),
            Role::IbuBapa->value => __('roles.ibubapa'),
            Role::Guru->value => __('roles.guru'),
            Role::Admin->value => __('roles.admin'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('Nama'))->required()->maxLength(60),
                Select::make('role')->label(__('Peranan'))->options(self::roleOptions())->required()->native(false)->selectablePlaceholder(false)
                    // Students have no email/password login, so they stay students.
                    ->disabled(fn (?User $record) => $record?->email === null)
                    ->helperText(fn (?User $record) => $record?->email === null
                        ? __('Murid log masuk dengan nama pengguna dan PIN, jadi peranannya kekal Murid.')
                        : __('Guru boleh mencipta kelas dan murid. Pentadbir boleh membuka panel ini.')),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            // Last activity = last XP earned (lessons, daily puzzle, practice, games); links = classes, children, guardians.
            ->modifyQueryUsing(fn (Builder $query) => $query->withMax('xpEvents', 'created_at')
                ->withCount(['classrooms', 'taughtClassrooms', 'students', 'guardians']))
            ->columns([
                // Students sign in with a username, adults with an email: show whichever the account has.
                TextColumn::make('name')->label(__('Nama'))->searchable(['name', 'username', 'email'])
                    ->description(fn (User $record) => $record->email ?? '@'.$record->username),
                TextColumn::make('role')->label(__('Peranan'))->badge()
                    ->formatStateUsing(fn (Role $state) => self::roleOptions()[$state->value]),
                IconColumn::make('email_verified_at')->label(__('E-mel disahkan'))
                    ->state(fn (User $record) => $record->email === null ? null : $record->email_verified_at !== null)
                    ->boolean()->placeholder('—')->alignCenter(),
                TextColumn::make('links')->label(__('Kaitan'))
                    ->state(fn (User $record) => self::links($record))->placeholder('—')->color('gray'),
                TextColumn::make('xp')->label(__('XP'))->sortable(),
                TextColumn::make('xp_events_max_created_at')->label(__('Aktif terakhir'))
                    ->since()->sortable()->placeholder('—')
                    ->tooltip(fn (User $record) => $record->xp_events_max_created_at),
                TextColumn::make('created_at')->label(__('Dicipta'))->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->label(__('Peranan'))->options(self::roleOptions()),
                Filter::make('unverified')->label(__('E-mel belum disahkan'))
                    ->query(fn (Builder $query) => $query->whereNotNull('email')->whereNull('email_verified_at')),
                Filter::make('inactive')->label(__('Tidak aktif 30 hari'))
                    ->query(fn (Builder $query) => $query->whereDoesntHave('xpEvents', fn (Builder $x) => $x->where('created_at', '>=', now()->subDays(30)))),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::Large)
                        ->modalDescription(fn (User $record) => $record->email ?? '@'.$record->username),
                    // For PDPA access requests sent by email.
                    Action::make('exportData')->label(__('Eksport data'))->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                        ->action(fn (User $record) => app(UserDataExport::class)->download($record)),
                    DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
                ]),
            ]);
    }

    /** "2 kelas", "1 anak", "1 kelas · 1 penjaga": what this account is connected to. */
    public static function links(User $user): ?string
    {
        $parts = array_filter([
            $user->taught_classrooms_count ? trans_choice(':n kelas diajar', $user->taught_classrooms_count, ['n' => $user->taught_classrooms_count]) : null,
            $user->classrooms_count ? trans_choice(':n kelas', $user->classrooms_count, ['n' => $user->classrooms_count]) : null,
            $user->students_count ? trans_choice(':n anak', $user->students_count, ['n' => $user->students_count]) : null,
            $user->guardians_count ? trans_choice(':n penjaga', $user->guardians_count, ['n' => $user->guardians_count]) : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
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
