<?php

namespace App\Support;

use App\Enums\ApplicationStatus;
use App\Enums\CampaignStatus;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private const IGNORED_FIELDS = [
        'updated_at',
        'password',
        'remember_token',
    ];

    public function logAuth(string $event, User $actor): void
    {
        if (! in_array($event, [AuditLog::EVENT_AUTH_LOGIN, AuditLog::EVENT_AUTH_LOGOUT], true)) {
            return;
        }

        $description = $event === AuditLog::EVENT_AUTH_LOGIN
            ? sprintf('Benutzer "%s" hat sich angemeldet.', $actor->name)
            : sprintf('Benutzer "%s" hat sich abgemeldet.', $actor->name);

        $this->store([
            'actor_id' => $actor->id,
            'organization_id' => $this->resolveOrganizationId(),
            'event' => $event,
            'subject_type' => $actor::class,
            'subject_id' => $actor->id,
            'description' => $description,
            'changes' => null,
            'context' => $this->buildContext(),
        ]);
    }

    public function logModelCreated(Model $model): void
    {
        $this->logModelEvent($model, 'created');
    }

    public function logModelUpdated(Model $model): void
    {
        $this->logModelEvent($model, 'updated');
    }

    public function logModelDeleted(Model $model): void
    {
        $this->logModelEvent($model, 'deleted');
    }

    private function logModelEvent(Model $model, string $action): void
    {
        if (! auth()->check()) {
            return;
        }

        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $event = $this->resolveModelEvent($model, $action);

        if (! $event) {
            return;
        }

        $changes = match ($action) {
            'created' => $this->buildCreateChanges($model),
            'updated' => $this->buildUpdateChanges($model),
            'deleted' => $this->buildDeleteChanges($model),
            default => [],
        };

        if ($action === 'updated' && $changes === []) {
            return;
        }

        $this->store([
            'actor_id' => $actor->id,
            'organization_id' => $this->resolveOrganizationId($model),
            'event' => $event,
            'subject_type' => $model::class,
            'subject_id' => $model->getKey(),
            'description' => $this->buildModelDescription($model, $action),
            'changes' => $changes === [] ? null : $changes,
            'context' => $this->buildContext(),
        ]);
    }

    private function resolveModelEvent(Model $model, string $action): ?string
    {
        return match (true) {
            $model instanceof Campaign => match ($action) {
                'created' => AuditLog::EVENT_CAMPAIGN_CREATED,
                'updated' => AuditLog::EVENT_CAMPAIGN_UPDATED,
                'deleted' => AuditLog::EVENT_CAMPAIGN_DELETED,
                default => null,
            },
            $model instanceof Application => match ($action) {
                'created' => AuditLog::EVENT_APPLICATION_CREATED,
                'updated' => AuditLog::EVENT_APPLICATION_UPDATED,
                'deleted' => AuditLog::EVENT_APPLICATION_DELETED,
                default => null,
            },
            default => null,
        };
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function buildCreateChanges(Model $model): array
    {
        $attributes = $this->filterFields($model->getAttributes());
        $changes = [];

        foreach ($attributes as $field => $value) {
            $changes[$field] = [
                'before' => null,
                'after' => $this->normalizeFieldValue($model, (string) $field, $value),
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function buildUpdateChanges(Model $model): array
    {
        $afterValues = $this->filterFields($model->getChanges());
        $beforeValues = $model->getPrevious();
        $changes = [];

        foreach ($afterValues as $field => $afterValue) {
            $beforeValue = $beforeValues[$field] ?? null;

            $changes[$field] = [
                'before' => $this->normalizeFieldValue($model, (string) $field, $beforeValue),
                'after' => $this->normalizeFieldValue($model, (string) $field, $afterValue),
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, array{before: mixed, after: mixed}>
     */
    private function buildDeleteChanges(Model $model): array
    {
        $attributes = $this->filterFields($model->getOriginal());
        $changes = [];

        foreach ($attributes as $field => $value) {
            $changes[$field] = [
                'before' => $this->normalizeFieldValue($model, (string) $field, $value),
                'after' => null,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filterFields(array $attributes): array
    {
        foreach (self::IGNORED_FIELDS as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }

    private function normalizeFieldValue(Model $model, string $field, mixed $value): mixed
    {
        if ($field === 'status') {
            if ($model instanceof Application) {
                if ($value instanceof ApplicationStatus) {
                    return $value->label();
                }

                $status = ApplicationStatus::tryFrom((string) $value);

                return $status?->label() ?? $value;
            }

            if ($model instanceof Campaign) {
                if ($value instanceof CampaignStatus) {
                    return $value->label();
                }

                $status = CampaignStatus::tryFrom((string) $value);

                return $status?->label() ?? $value;
            }
        }

        if ($model instanceof Application && $field === 'assigned_user_id') {
            if (! filled($value)) {
                return 'Nicht zugewiesen';
            }

            $user = User::query()->find((int) $value);

            return $user?->name ?? sprintf('User #%s', (string) $value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: null;
        }

        if (is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: null;
        }

        return $value;
    }

    private function buildModelDescription(Model $model, string $action): string
    {
        $entity = $model instanceof Campaign ? 'Job' : 'Bewerbung';
        $verb = match ($action) {
            'created' => 'erstellt',
            'updated' => 'aktualisiert',
            'deleted' => 'geloescht',
            default => $action,
        };

        return sprintf('%s %s wurde %s.', $entity, $this->describeSubject($model), $verb);
    }

    private function describeSubject(Model $model): string
    {
        if ($model instanceof Campaign) {
            $title = trim((string) $model->title);

            return $title !== ''
                ? sprintf('"%s" (ID %d)', $title, (int) $model->getKey())
                : sprintf('(ID %d)', (int) $model->getKey());
        }

        if ($model instanceof Application) {
            $fullName = trim(sprintf('%s %s', (string) $model->first_name, (string) $model->last_name));

            return $fullName !== ''
                ? sprintf('"%s" (ID %d)', $fullName, (int) $model->getKey())
                : sprintf('(ID %d)', (int) $model->getKey());
        }

        return sprintf('(ID %d)', (int) $model->getKey());
    }

    private function resolveOrganizationId(?Model $model = null): ?int
    {
        $tenantId = $this->resolveTenantId();

        if ($tenantId !== null) {
            return $tenantId;
        }

        if (! $model) {
            return null;
        }

        if ($model instanceof Campaign) {
            return filled($model->organization_id) ? (int) $model->organization_id : null;
        }

        if ($model instanceof Application) {
            $campaignOrganizationId = $model->campaign?->organization_id;

            if (filled($campaignOrganizationId)) {
                return (int) $campaignOrganizationId;
            }

            if (filled($model->campaign_id)) {
                $resolved = Campaign::query()
                    ->whereKey($model->campaign_id)
                    ->value('organization_id');

                return filled($resolved) ? (int) $resolved : null;
            }

            return null;
        }

        $modelOrganizationId = $model->getAttribute('organization_id');

        return filled($modelOrganizationId) ? (int) $modelOrganizationId : null;
    }

    private function resolveTenantId(): ?int
    {
        try {
            $tenant = Filament::getTenant();

            return $tenant ? (int) $tenant->getKey() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(): array
    {
        $request = app()->bound('request') ? request() : null;
        $route = $request?->route();

        return array_filter([
            'guard' => Auth::getDefaultDriver(),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'route' => $route?->getName(),
            'method' => $request?->method(),
            'url' => $request?->fullUrl(),
            'tenant_id' => $this->resolveTenantId(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function store(array $payload): void
    {
        try {
            AuditLog::query()->create($payload);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
