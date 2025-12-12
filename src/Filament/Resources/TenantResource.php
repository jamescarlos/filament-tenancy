<?php

namespace TomatoPHP\FilamentTenancy\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages\CreateTenant;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages\EditTenant;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages\ListTenants;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages\ViewTenant;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\RelationManagers\DomainsRelationManager;
use TomatoPHP\FilamentTenancy\Models\Tenant;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = 'icon-card';

    public static function getNavigationGroup(): ?string
    {
        return trans('filament-tenancy::messages.group');
    }

    public static function getNavigationLabel(): string
    {
        return trans('filament-tenancy::messages.single');
    }

    public static function getPluralLabel(): ?string
    {
        return trans('filament-tenancy::messages.title');
    }

    public static function getLabel(): ?string
    {
        return trans('filament-tenancy::messages.title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    TextInput::make('name')
                        ->label(trans('filament-tenancy::messages.columns.name'))
                        ->required()
                        ->unique(table: 'tenants', ignoreRecord: true)->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('id', $slug = Str::of($state)->slug('_')->toString());
                            $set('domain', Str::of($state)->slug()->toString());
                        }),
//                    TextInput::make('id')
//                        ->label(trans('filament-tenancy::messages.columns.unique_id'))
//                        ->required()
//                        ->disabled(fn($context) => $context !== 'create')
//                        ->unique(table: 'tenants', ignoreRecord: true),
                    TextInput::make('domain')
                        //->columnSpanFull()
                        ->label(trans('filament-tenancy::messages.columns.domain'))
                        ->required()
                        ->visible(fn($context) => $context === 'create')
                        ->unique(table: 'domains', ignoreRecord: true)
                        ->prefix(request()->getScheme() . '://')
                        ->suffix('.' . request()->getHost()),
                    TextInput::make('email')
                        ->label(trans('filament-tenancy::messages.columns.email'))
                        ->required()
                        ->email(),
                    TextInput::make('phone')
                        ->label(trans('filament-tenancy::messages.columns.phone'))
                        ->tel(),
                    TextInput::make('password')
                        ->label(trans('filament-tenancy::messages.columns.password'))
                        ->password()
                        ->revealable(filament()->arePasswordsRevealable())
                        ->rule(Password::default())
                        ->autocomplete('new-password')
                        ->dehydrated(fn($state): bool => filled($state))
                        ->dehydrateStateUsing(fn($state): string => Hash::make($state))
                        ->live(debounce: 500)
                        ->same('passwordConfirmation'),
                    TextInput::make('passwordConfirmation')
                        ->label(trans('filament-tenancy::messages.columns.passwordConfirmation'))
                        ->password()
                        ->revealable(filament()->arePasswordsRevealable())
                        ->dehydrated(false),
                    Toggle::make('is_active')
                        ->label(trans('filament-tenancy::messages.columns.is_active'))
                        ->default(true),
                ])
                    ->columns()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(trans('filament-tenancy::messages.columns.id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(trans('filament-tenancy::messages.columns.name'))
                    ->description(function ($record) {
                        $path = filament('filament-tenancy')->getTenantPanelPath();

                        return request()->getScheme() . '://' . $record->domains()->first()?->domain . '.' . config('filament-tenancy.central_domain') . $path;
                    }),
                ToggleColumn::make('is_active')
                    ->sortable()
                    ->label(trans('filament-tenancy::messages.columns.is_active')),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(trans('filament-tenancy::messages.columns.is_active')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->label(trans('filament-tenancy::messages.actions.view'))
                    ->tooltip(trans('filament-tenancy::messages.actions.view'))
                    ->iconButton()
                    ->icon('heroicon-s-link')
                    ->url(function ($record) {
                        $path = filament('filament-tenancy')->getTenantPanelPath();

                        return request()->getScheme() . '://' . $record->domains()->first()?->domain . '.' . config('filament-tenancy.central_domain') . $path;
                    })
                    ->openUrlInNewTab(),
                Action::make('login')
                    ->label(trans('filament-tenancy::messages.actions.login'))
                    ->tooltip(trans('filament-tenancy::messages.actions.login'))
                    ->visible(filament('filament-tenancy')->allowImpersonate)
                    ->requiresConfirmation()
                    ->color('warning')
                    ->iconButton()
                    ->icon('heroicon-s-arrow-left-on-rectangle')
                    ->action(function ($record) {
                        $path = filament('filament-tenancy')->getTenantPanelPath();
                        $token = tenancy()->impersonate($record, 1, $path, 'web');

                        return redirect()->to(request()->getScheme() . '://' . $record->domains[0]->domain . '.' . config('filament-tenancy.central_domain') . '/login/url?token=' . $token->token . '&email=' . urlencode($record->email));
                    }),
                Action::make('password')
                    ->label(trans('filament-tenancy::messages.actions.password'))
                    ->tooltip(trans('filament-tenancy::messages.actions.password'))
                    ->requiresConfirmation()
                    ->icon('heroicon-s-lock-closed')
                    ->iconButton()
                    ->color('danger')
                    ->schema([
                        TextInput::make('password')
                            ->label(trans('filament-tenancy::messages.columns.password'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrated(fn($state): bool => filled($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label(trans('filament-tenancy::messages.columns.passwordConfirmation'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->dehydrated(false),
                    ])
                    ->action(function (array $data, $record) {
                        $record->password = bcrypt($data['password']);
                        $record->save();

                        Notification::make()
                            ->title(trans('filament-tenancy::messages.actions.notificaitons.password.title'))
                            ->body(trans('filament-tenancy::messages.actions.notificaitons.password.body'))
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label(trans('filament-tenancy::messages.actions.edit'))
                    ->tooltip(trans('filament-tenancy::messages.actions.edit'))
                    ->iconButton(),
                DeleteAction::make()
                    ->label(trans('filament-tenancy::messages.actions.delete'))
                    ->tooltip(trans('filament-tenancy::messages.actions.delete'))
                    ->iconButton()
                    // Disable database transaction because tenant deletion switches
                    // database connections, which breaks the transaction context
                    ->databaseTransaction(false),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // Disable database transaction because tenant deletion switches
                        // database connections, which breaks the transaction context
                        ->databaseTransaction(false),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            DomainsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'view' => ViewTenant::route('/{record}'),
            'edit' => EditTenant::route('/{record}/edit'),
        ];
    }
}
