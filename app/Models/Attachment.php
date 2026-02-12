<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'type',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_path',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function downloadUrl(): string
    {
        return route('attachments.download', $this);
    }
}
