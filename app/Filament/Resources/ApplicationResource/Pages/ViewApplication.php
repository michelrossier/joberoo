<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Filament\Resources\ApplicationResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add_note')
                ->label('Notiz hinzufuegen')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Textarea::make('note')
                        ->label('Notiz')
                        ->required()
                        ->rows(5)
                        ->maxLength(5000),
                ])
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    $record->recordActivity('note_added', $data['note'], [], auth()->id());
                    $this->record = $record->fresh();
                }),
            Actions\EditAction::make(),
        ];
    }
}
