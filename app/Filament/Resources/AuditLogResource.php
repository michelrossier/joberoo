<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Aktivitaetslog';

    protected static ?string $modelLabel = 'Aktivitaetslog';

    protected static ?string $pluralModelLabel = 'Aktivitaetslog';

    protected static ?int $navigationSort = 90;

    protected static bool $isScopedToTenant = false;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Benutzer')
                    ->placeholder('System'),
                TextColumn::make('organization.name')
                    ->label('Organisation')
                    ->placeholder('-'),
                TextColumn::make('event')
                    ->label('Ereignis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AuditLog::labelForEvent($state)),
                TextColumn::make('description')
                    ->label('Aktion')
                    ->wrap(),
                TextColumn::make('changes_summary')
                    ->label('Aenderungen')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label('Ereignis')
                    ->options(AuditLog::eventOptions()),
                SelectFilter::make('actor_id')
                    ->label('Benutzer')
                    ->relationship('actor', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('organization_id')
                    ->label('Organisation')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label('Zeitraum')
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
                                fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from)
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $until): Builder => $query->whereDate('created_at', '<=', $until)
                            );
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['actor', 'organization']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
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
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
