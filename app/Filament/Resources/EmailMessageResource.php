<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailMessageResource\Pages;
use App\Models\EmailMessage;
use App\Models\EmailMessageEvent;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class EmailMessageResource extends Resource
{
    protected static ?string $model = EmailMessage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'E-Mail Log';

    protected static ?string $modelLabel = 'E-Mail Nachricht';

    protected static ?string $pluralModelLabel = 'E-Mail Log';

    protected static ?int $navigationSort = 95;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Gesendet am')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('recipient_email')
                    ->label('Empfaenger')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label('Betreff')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => EmailMessage::labelForStatus($state))
                    ->color(fn (string $state): string => EmailMessage::colorForStatus($state))
                    ->tooltip(static fn (EmailMessage $record): ?Htmlable => self::formatStatusTooltip($record)),
                TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('application.full_name')
                    ->label('Bewerbung')
                    ->placeholder('-'),
                TextColumn::make('last_event_at')
                    ->label('Letztes Event')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('provider_message_id')
                    ->label('Postmark Message ID')
                    ->copyable()
                    ->limit(32)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EmailMessage::statusOptions()),
                SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false),
                Filter::make('sent_range')
                    ->label('Gesendet am')
                    ->form([
                        DatePicker::make('from')
                            ->label('Von'),
                        DatePicker::make('until')
                            ->label('Bis'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $from): Builder => $query->whereDate('sent_at', '>=', $from)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $until): Builder => $query->whereDate('sent_at', '<=', $until)
                            );
                    }),
                Filter::make('event')
                    ->label('Eventtyp')
                    ->form([
                        Select::make('event')
                            ->label('Event')
                            ->options(EmailMessageEvent::eventOptions()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $event = $data['event'] ?? null;

                        if (! filled($event)) {
                            return $query;
                        }

                        return $query->whereHas('events', fn (Builder $eventQuery): Builder => $eventQuery->where('event', $event));
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('sent_at', 'desc');
    }

    private static function formatStatusTooltip(EmailMessage $record): ?Htmlable
    {
        $history = $record->statusHistory();

        if ($history === []) {
            return null;
        }

        $lines = collect($history)
            ->map(static fn (array $entry): string => sprintf(
                '%s: %s',
                e($entry['label']),
                e($entry['occurred_at']->format('d.m.Y H:i:s'))
            ))
            ->implode('<br>');

        return new HtmlString($lines);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['organization', 'application', 'events']);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $tenant = Filament::getTenant();

        if (! $tenant || ! $user->isAdminForOrganization($tenant)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organization_id', $tenant->id);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        return $user->isAdminForOrganization($tenant);
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = Filament::getTenant();

        if (! $tenant || ! $user->isAdminForOrganization($tenant)) {
            return false;
        }

        return (int) $record->organization_id === (int) $tenant->id;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmailMessages::route('/'),
        ];
    }
}
