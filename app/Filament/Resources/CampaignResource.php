<?php

namespace App\Filament\Resources;

use App\Enums\CampaignStatus;
use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Bewerbungsmanagement';

    protected static ?string $modelLabel = 'Job';

    protected static ?string $pluralModelLabel = 'Jobs';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $state, callable $set): void {
                        $set('slug', Str::slug($state));
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: Campaign::class,
                        column: 'slug',
                        ignoreRecord: true,
                        modifyRuleUsing: function (Unique $rule) {
                            $tenant = Filament::getTenant();

                            return $tenant
                                ? $rule->where('organization_id', $tenant->id)
                                : $rule;
                        }
                    ),
                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options(collect(CampaignStatus::cases())
                        ->mapWithKeys(fn (CampaignStatus $status) => [$status->value => $status->label()])
                        ->all())
                    ->default(CampaignStatus::Draft->value),
                TextInput::make('subtitle')
                    ->label('Untertitel')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Beschreibung')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
                TextInput::make('location')
                    ->label('Standort')
                    ->maxLength(255),
                TextInput::make('employment_type')
                    ->label('Anstellungsart')
                    ->maxLength(255),
                TextInput::make('salary_range')
                    ->label('Gehaltsrahmen')
                    ->maxLength(255),
                FileUpload::make('hero_image_path')
                    ->label('Hero-Bild')
                    ->disk('public')
                    ->directory('campaign-hero')
                    ->image(),
                TextInput::make('cta_text')
                    ->label('CTA-Text')
                    ->maxLength(255)
                    ->default('Jetzt bewerben'),
                ColorPicker::make('primary_color')
                    ->label('Primaerfarbe')
                    ->default('#1f2937'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        if ($state instanceof CampaignStatus) {
                            return $state->label();
                        }

                        return CampaignStatus::tryFrom($state)?->label() ?? $state;
                    })
                    ->color(function ($state): string {
                        $value = $state instanceof CampaignStatus ? $state->value : $state;

                        return match ($value) {
                            CampaignStatus::Published->value => 'success',
                            CampaignStatus::Archived->value => 'gray',
                            default => 'warning',
                        };
                    })
                    ->sortable(),
                TextColumn::make('submissions_vs_views')
                    ->label('Bewerbungen / Aufrufe')
                    ->state(function (Campaign $record): string {
                        return sprintf('%d / %d', $record->applications_count, $record->views_count);
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = $direction === 'desc' ? 'desc' : 'asc';

                        return $query
                            ->orderBy('applications_count', $direction)
                            ->orderBy('views_count', $direction);
                    }),
                TextColumn::make('conversion_rate')
                    ->label('Konversionsrate')
                    ->state(function (Campaign $record): string {
                        if ($record->views_count === 0) {
                            return '0%';
                        }

                        return number_format(($record->applications_count / $record->views_count) * 100, 1) . '%';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $direction = $direction === 'desc' ? 'desc' : 'asc';

                        return $query->orderByRaw(
                            'CASE WHEN views_count = 0 THEN 0 ELSE applications_count * 1.0 / views_count END ' . $direction
                        );
                    }),
                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(CampaignStatus::cases())
                        ->mapWithKeys(fn (CampaignStatus $status) => [$status->value => $status->label()])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('open_public_job')
                    ->label('Job-URL')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->visible(fn (Campaign $record): bool => filled(static::getPublicJobUrl($record)))
                    ->url(function (Campaign $record): ?string {
                        return static::getPublicJobUrl($record);
                    }, shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function canViewAny(): bool
    {
        return static::userIsAdmin();
    }

    public static function canCreate(): bool
    {
        return static::userIsAdmin();
    }

    public static function canEdit($record): bool
    {
        return static::userIsAdmin();
    }

    public static function canDelete($record): bool
    {
        return static::userIsAdmin();
    }

    protected static function userIsAdmin(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        if (! $user || ! $tenant) {
            return false;
        }

        return $user->isAdminForOrganization($tenant);
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->withCount('applications')
            ->when($tenant, fn (Builder $query) => $query->where('organization_id', $tenant->id));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }

    public static function getPublicJobUrl(Campaign $record): ?string
    {
        $organizationSlug = Filament::getTenant()?->slug;

        if (! filled($organizationSlug)) {
            $organizationSlug = $record->organization?->slug;
        }

        if (! filled($organizationSlug) || blank($record->slug)) {
            return null;
        }

        return route('campaign.show', [
            'org_slug' => $organizationSlug,
            'campaign_slug' => $record->slug,
        ]);
    }
}
