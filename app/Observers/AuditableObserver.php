<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class AuditableObserver
{
    public function created(Model $model): void
    {
        app(AuditLogger::class)->logModelCreated($model);
    }

    public function updated(Model $model): void
    {
        app(AuditLogger::class)->logModelUpdated($model);
    }

    public function deleted(Model $model): void
    {
        app(AuditLogger::class)->logModelDeleted($model);
    }
}
