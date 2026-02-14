<?php

namespace App\Filament\Resources;

use App\Enums\ApplicationStatus;
use App\Filament\Resources\ApplicationResource\Pages;
use App\Models\Application;
use App\Models\ApplicationActivity;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Filament\Infolists\Components\TextEntry;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Bewerbungsmanagement';

    protected static ?string $modelLabel = 'Bewerbung';

    protected static ?string $pluralModelLabel = 'Bewerbungen';

    protected static ?int $navigationSort = 20;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('first_name')
                    ->label('Vorname')
                    ->disabled(),
                TextInput::make('last_name')
                    ->label('Nachname')
                    ->disabled(),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->disabled(),
                TextInput::make('phone')
                    ->label('Telefon')
                    ->disabled(),
                TextInput::make('linkedin_url')
                    ->label('LinkedIn-URL')
                    ->disabled(),
                TextInput::make('portfolio_url')
                    ->label('Portfolio-URL')
                    ->disabled(),
                Textarea::make('cover_letter_text')
                    ->label('Anschreiben')
                    ->rows(6)
                    ->disabled()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options(collect(ApplicationStatus::cases())
                        ->mapWithKeys(fn (ApplicationStatus $status) => [$status->value => $status->label()])
                        ->all()),
                Select::make('assigned_user_id')
                    ->label('Zustaendig')
                    ->placeholder('Nicht zugewiesen')
                    ->options(function (): array {
                        $tenant = Filament::getTenant();

                        if (! $tenant) {
                            return [];
                        }

                        return $tenant->users()
                            ->wherePivotIn('role', ['admin', 'recruiter'])
                            ->orderBy('users.name')
                            ->pluck('users.name', 'users.id')
                            ->all();
                    })
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->label('Bewerber')
                    ->formatStateUsing(fn ($state, Application $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('campaign.title')
                    ->label('Job')
                    ->sortable(),
                TextColumn::make('assignedUser.name')
                    ->label('Zustaendig')
                    ->placeholder('-'),
                TextColumn::make('source')
                    ->label('Quelle')
                    ->formatStateUsing(fn ($state) => filled($state) ? $state : 'Direkt'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof ApplicationStatus) {
                            return $state->label();
                        }

                        return ApplicationStatus::tryFrom($state)?->label() ?? $state;
                    })
                    ->color(function ($state): string {
                        $value = $state instanceof ApplicationStatus ? $state->value : $state;

                        return match ($value) {
                            ApplicationStatus::Accepted->value => 'success',
                            ApplicationStatus::Dismissed->value => 'danger',
                            ApplicationStatus::Interview->value => 'info',
                            ApplicationStatus::Reviewed->value => 'warning',
                            default => 'gray',
                        };
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Eingegangen am')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(ApplicationStatus::cases())
                        ->mapWithKeys(fn (ApplicationStatus $status) => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('campaign_id')
                    ->label('Job')
                    ->relationship('campaign', 'title', function (Builder $query) {
                        $tenant = Filament::getTenant();
                        if ($tenant) {
                            $query->where('organization_id', $tenant->id);
                        }
                    }),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Bewerberdaten')
                    ->schema([
                        TextEntry::make('full_name')->label('Bewerber'),
                        TextEntry::make('email')->label('E-Mail'),
                        TextEntry::make('phone')->label('Telefon'),
                        TextEntry::make('linkedin_url')->label('LinkedIn-URL'),
                        TextEntry::make('portfolio_url')->label('Portfolio-URL'),
                        TextEntry::make('cover_letter_text')
                            ->label('Anschreiben')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Bewerbungsprozess')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->formatStateUsing(fn ($state) => $state instanceof ApplicationStatus
                                ? $state->label()
                                : (ApplicationStatus::tryFrom($state)?->label() ?? $state)),
                        TextEntry::make('assignedUser.name')
                            ->label('Zustaendig')
                            ->placeholder('-'),
                        TextEntry::make('source')
                            ->label('Quelle')
                            ->formatStateUsing(fn ($state) => filled($state) ? $state : 'Direkt'),
                        TextEntry::make('source_medium')
                            ->label('Medium')
                            ->placeholder('-'),
                        TextEntry::make('source_campaign')
                            ->label('UTM-Kampagne')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Eingegangen am')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Section::make('Anhaenge')
                    ->schema([
                        TextEntry::make('resume')
                            ->label('Lebenslauf')
                            ->formatStateUsing(fn ($state, Application $record) => $record->resumeAttachment
                                ? new HtmlString('<a class="text-primary-600 underline" href="' . e($record->resumeAttachment->downloadUrl()) . '">Herunterladen</a>')
                                : '-')
                            ->html(),
                        TextEntry::make('cover_letter')
                            ->label('Anschreiben')
                            ->formatStateUsing(fn ($state, Application $record) => $record->coverLetterAttachment
                                ? new HtmlString('<a class="text-primary-600 underline" href="' . e($record->coverLetterAttachment->downloadUrl()) . '">Herunterladen</a>')
                                : '-')
                            ->html(),
                    ]),
                Section::make('Aktivitaetsverlauf')
                    ->schema([
                        RepeatableEntry::make('activities')
                            ->label('')
                            ->contained(false)
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Ereignis')
                                    ->badge()
                                    ->formatStateUsing(fn (string $state): string => ApplicationActivity::labelForType($state)),
                                TextEntry::make('actor.name')
                                    ->label('Von')
                                    ->placeholder('System'),
                                TextEntry::make('note')
                                    ->label('Notiz')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('details')
                                    ->label('Details')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('created_at')
                                    ->label('Zeitpunkt')
                                    ->since(),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->with([
                'campaign.scorecardCompetencies',
                'assignedUser',
                'resumeAttachment',
                'coverLetterAttachment',
                'activities.actor',
                'evaluations.evaluator',
            ])
            ->when($tenant, function (Builder $query) use ($tenant) {
                $query->whereHas('campaign', fn (Builder $campaignQuery) => $campaignQuery->where('organization_id', $tenant->id));
            });
    }

    public static function canViewAny(): bool
    {
        return static::userIsRecruiterOrAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::userIsRecruiterOrAdmin();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    protected static function userIsRecruiterOrAdmin(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->organizations()
            ->whereKey($tenant)
            ->wherePivotIn('role', ['admin', 'recruiter'])
            ->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'view' => Pages\ViewApplication::route('/{record}'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
