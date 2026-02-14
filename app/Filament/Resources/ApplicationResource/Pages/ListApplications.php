<?php

namespace App\Filament\Resources\ApplicationResource\Pages;

use App\Enums\ApplicationStatus;
use App\Filament\Exports\ApplicationsExcelExport;
use App\Filament\Resources\ApplicationResource;
use App\Models\Application;
use App\Models\Organization;
use App\Notifications\ApplicationStatusMessageNotification;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction as ExcelExportAction;

class ListApplications extends Page
{
    protected static string $resource = ApplicationResource::class;

    protected static string $view = 'filament.resources.application-resource.pages.list-applications';

    protected function getHeaderActions(): array
    {
        return [
            ExcelExportAction::make('exportApplications')
                ->label('Excel exportieren')
                ->exports([
                    ApplicationsExcelExport::make('all_applications')
                        ->label('Alle Bewerbungen'),
                ]),
        ];
    }

    public function statusTransitionAction(): Action
    {
        return Action::make('statusTransition')
            ->modalHeading('Status aktualisieren')
            ->modalDescription('Moechten Sie dem Bewerber eine Nachricht senden?')
            ->modalSubmitActionLabel(fn (): string => $this->isSendMessageSelectedForMountedAction()
                ? 'E-Mail senden'
                : 'Status aktualisieren')
            ->modalCancelAction(false)
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('skipMessage', arguments: ['force_without_message' => true])
                    ->label('Keine Nachricht senden')
                    ->color('gray')
                    ->extraAttributes(['class' => 'ms-auto'])
                    ->visible(fn (): bool => $this->isSendMessageSelectedForMountedAction()),
            ])
            ->mountUsing(function (Form $form, array $arguments): void {
                $applicationId = (int) ($arguments['applicationId'] ?? 0);
                $newStatus = (string) ($arguments['newStatus'] ?? '');
                $application = $this->getBoardQuery()->whereKey($applicationId)->first();

                $templates = $this->getMessageTemplatesIndexed();
                $defaultTemplateKey = array_key_first($templates);
                $defaultTemplate = $defaultTemplateKey !== null ? $templates[$defaultTemplateKey] : null;
                $recipientName = $this->resolveRecipientName($application);

                $form->fill([
                    'application_id' => $applicationId,
                    'new_status' => $newStatus,
                    'recipient_email' => $application?->email ?? '',
                    'recipient_name' => $recipientName,
                    'send_message' => 0,
                    'template_key' => $defaultTemplateKey,
                    'subject' => $defaultTemplate['subject'] ?? '',
                    'message_html' => $this->buildMessageHtmlWithSalutation(
                        (string) ($defaultTemplate['body_html'] ?? ''),
                        $recipientName,
                    ),
                ]);
            })
            ->form([
                Hidden::make('application_id')
                    ->required(),
                Hidden::make('new_status')
                    ->required(),
                Hidden::make('recipient_email'),
                Hidden::make('recipient_name'),
                Select::make('send_message')
                    ->label('Nachricht an Bewerber')
                    ->options([
                        0 => 'Ohne Nachricht',
                        1 => 'Mit Nachricht senden',
                    ])
                    ->default(0)
                    ->native(false)
                    ->live()
                    ->required(),
                Placeholder::make('recipient_email_info')
                    ->label('Empfaenger E-Mail')
                    ->content(function (Get $get): string {
                        $email = trim((string) ($get('recipient_email') ?? ''));

                        return filled($email)
                            ? $email
                            : 'Keine E-Mail-Adresse hinterlegt';
                    })
                    ->visible(fn (Get $get): bool => $this->wantsToSendMessage($get)),
                Select::make('template_key')
                    ->label('Vorlage')
                    ->options(fn (): array => $this->getMessageTemplateOptions())
                    ->native(false)
                    ->live()
                    ->visible(fn (Get $get): bool => $this->wantsToSendMessage($get))
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        $template = $this->resolveMessageTemplate($state);

                        if (! $template) {
                            return;
                        }

                        $recipientName = trim((string) ($get('recipient_name') ?? ''));

                        $set('subject', $template['subject']);
                        $set('message_html', $this->buildMessageHtmlWithSalutation(
                            (string) $template['body_html'],
                            $recipientName,
                        ));
                    }),
                TextInput::make('subject')
                    ->label('Betreff')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $this->wantsToSendMessage($get)),
                RichEditor::make('message_html')
                    ->label('Nachricht')
                    ->visible(fn (Get $get): bool => $this->wantsToSendMessage($get))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, array $arguments): void {
                $applicationId = (int) ($arguments['applicationId'] ?? $data['application_id'] ?? 0);
                $newStatus = (string) ($arguments['newStatus'] ?? $data['new_status'] ?? '');

                if (! in_array($newStatus, $this->getKanbanStatusValues(), true)) {
                    return;
                }

                $application = $this->getBoardQuery()
                    ->whereKey($applicationId)
                    ->first();

                if (! $application) {
                    return;
                }

                if (! $this->applyStatusChange($application, $newStatus)) {
                    return;
                }

                $forceWithoutMessage = (bool) ($arguments['force_without_message'] ?? false);
                $sendMessage = ! $forceWithoutMessage && (int) ($data['send_message'] ?? 0) === 1;

                if (! $sendMessage) {
                    return;
                }

                $validated = Validator::make($data, [
                    'template_key' => ['required', 'string'],
                    'subject' => ['required', 'string', 'max:255'],
                    'message_html' => ['required', 'string'],
                ])->validate();

                $templateKey = (string) $validated['template_key'];
                $template = $this->resolveMessageTemplate($templateKey);
                $subject = trim((string) $validated['subject']);
                $messageHtml = (string) $validated['message_html'];

                if (blank(trim(strip_tags($messageHtml)))) {
                    throw ValidationException::withMessages([
                        'message_html' => 'Die Nachricht darf nicht leer sein.',
                    ]);
                }

                Notification::route('mail', $application->email)
                    ->notify(new ApplicationStatusMessageNotification($subject, $messageHtml));

                $application->recordActivity('applicant_message_sent', null, [
                    'recipient' => $application->email,
                    'subject' => $subject,
                    'template' => $template['name'] ?? 'Benutzerdefiniert',
                    'status_to' => ApplicationStatus::tryFrom($newStatus)?->label() ?? $newStatus,
                    'status_to_value' => $newStatus,
                ], auth()->id());
            });
    }

    public function moveApplication(int $applicationId, string $newStatus): void
    {
        if (! in_array($newStatus, $this->getKanbanStatusValues(), true)) {
            return;
        }

        $application = $this->getBoardQuery()
            ->whereKey($applicationId)
            ->first();

        if (! $application) {
            return;
        }

        $this->applyStatusChange($application, $newStatus);
    }

    public function getLanesProperty(): array
    {
        $statuses = $this->getKanbanStatuses();
        $applicationsByStatus = $this->getBoardQuery()
            ->whereIn('status', $this->getKanbanStatusValues())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (Application $application): string => $application->status instanceof ApplicationStatus
                ? $application->status->value
                : (string) $application->status);

        return array_map(function (ApplicationStatus $status) use ($applicationsByStatus): array {
            return [
                'value' => $status->value,
                'label' => $status->label(),
                'applications' => $applicationsByStatus->get($status->value, collect()),
            ];
        }, $statuses);
    }

    protected function getBoardQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return Application::query()
            ->with(['campaign', 'assignedUser'])
            ->when($tenant, function (Builder $query) use ($tenant): void {
                $query->whereHas('campaign', fn (Builder $campaignQuery) => $campaignQuery->where('organization_id', $tenant->id));
            });
    }

    /**
     * @return list<ApplicationStatus>
     */
    protected function getKanbanStatuses(): array
    {
        return [
            ApplicationStatus::New,
            ApplicationStatus::Reviewed,
            ApplicationStatus::Interview,
            ApplicationStatus::Accepted,
            ApplicationStatus::Dismissed,
        ];
    }

    /**
     * @return list<string>
     */
    protected function getKanbanStatusValues(): array
    {
        return array_map(
            static fn (ApplicationStatus $status): string => $status->value,
            $this->getKanbanStatuses()
        );
    }

    protected function applyStatusChange(Application $application, string $newStatus): bool
    {
        $currentStatus = $application->status instanceof ApplicationStatus
            ? $application->status->value
            : (string) $application->status;

        if ($currentStatus === $newStatus) {
            return false;
        }

        if (
            Application::statusRequiresEvaluation($newStatus)
            && ! $application->hasCompleteEvaluation()
        ) {
            FilamentNotification::make()
                ->danger()
                ->title('Bewertung erforderlich')
                ->body('Vor finalen Entscheidungen ist eine vollstaendige Stage-Bewertung mit Begruendung und Leitfragen noetig.')
                ->send();

            return false;
        }

        $application->update([
            'status' => $newStatus,
        ]);

        $application->recordActivity('status_changed', null, [
            'from' => ApplicationStatus::tryFrom($currentStatus)?->label() ?? $currentStatus,
            'to' => ApplicationStatus::tryFrom($newStatus)?->label() ?? $newStatus,
            'from_value' => $currentStatus,
            'to_value' => $newStatus,
        ], auth()->id());

        return true;
    }

    protected function wantsToSendMessage(Get $get): bool
    {
        return (int) ($get('send_message') ?? 0) === 1;
    }

    protected function isSendMessageSelectedForMountedAction(): bool
    {
        if (! is_array($this->mountedActionsData) || $this->mountedActionsData === []) {
            return false;
        }

        $lastIndex = array_key_last($this->mountedActionsData);
        $data = is_int($lastIndex) ? ($this->mountedActionsData[$lastIndex] ?? []) : [];

        return (int) ($data['send_message'] ?? 0) === 1;
    }

    /**
     * @return array<string, array{name: string, subject: string, body_html: string}>
     */
    protected function getMessageTemplatesIndexed(): array
    {
        $templates = $this->getTenantOrganization()?->getApplicationStatusMessageTemplatesOrDefault()
            ?? Organization::defaultApplicationStatusMessageTemplates();

        $indexed = [];

        foreach (array_values($templates) as $index => $template) {
            if (! is_array($template)) {
                continue;
            }

            $name = trim((string) ($template['name'] ?? ''));
            $subject = trim((string) ($template['subject'] ?? ''));
            $bodyHtml = (string) ($template['body_html'] ?? '');

            if (! filled($name) || ! filled($subject) || ! filled($bodyHtml)) {
                continue;
            }

            $indexed[(string) $index] = [
                'name' => $name,
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ];
        }

        if ($indexed !== []) {
            return $indexed;
        }

        return collect(Organization::defaultApplicationStatusMessageTemplates())
            ->mapWithKeys(fn (array $template, int $index): array => [
                (string) $index => $template,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function getMessageTemplateOptions(): array
    {
        return collect($this->getMessageTemplatesIndexed())
            ->mapWithKeys(fn (array $template, string $key): array => [$key => $template['name']])
            ->all();
    }

    /**
     * @return array{name: string, subject: string, body_html: string} | null
     */
    protected function resolveMessageTemplate(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        return $this->getMessageTemplatesIndexed()[$key] ?? null;
    }

    protected function getTenantOrganization(): ?Organization
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Organization ? $tenant : null;
    }

    protected function resolveRecipientName(?Application $application): string
    {
        if (! $application) {
            return '';
        }

        return trim($application->full_name);
    }

    protected function buildMessageHtmlWithSalutation(string $templateBodyHtml, string $recipientName): string
    {
        $recipientName = trim($recipientName);
        $salutation = filled($recipientName)
            ? "Guten Tag {$recipientName}"
            : 'Guten Tag';
        $salutationParagraph = '<p>' . e($salutation) . ',</p>';

        $templateBodyHtml = trim($templateBodyHtml);

        if ($templateBodyHtml === '') {
            return $salutationParagraph;
        }

        $textStart = Str::of(strip_tags($templateBodyHtml))
            ->squish()
            ->lower();

        if ($textStart->startsWith('guten tag')) {
            return $templateBodyHtml;
        }

        $templateBodyHtml = (string) preg_replace('/^\s*<p>\s*hallo\s*,?\s*<\/p>/i', '', $templateBodyHtml);

        return $salutationParagraph . ltrim($templateBodyHtml);
    }
}
