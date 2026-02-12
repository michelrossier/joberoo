<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'application_status_message_templates',
    ];

    protected $casts = [
        'application_status_message_templates' => 'array',
    ];

    /**
     * @return array<int, array{name: string, subject: string, body_html: string}>
     */
    public static function defaultApplicationStatusMessageTemplates(): array
    {
        return [
            [
                'name' => 'Danke, wir melden uns zeitnah',
                'subject' => 'Vielen Dank fuer Ihre Bewerbung',
                'body_html' => '<p>Hallo,</p><p>vielen Dank fuer Ihre Bewerbung und Ihr Interesse an unserem Unternehmen.</p><p>Wir melden uns zeitnah mit einem Update bei Ihnen.</p><p>Freundliche Gruesse</p>',
            ],
            [
                'name' => 'Bitte um Geduld',
                'subject' => 'Kurzes Update zu Ihrer Bewerbung',
                'body_html' => '<p>Hallo,</p><p>vielen Dank fuer Ihre Bewerbung.</p><p>Unser Team prueft Ihre Unterlagen aktuell. Wir bitten Sie noch um ein wenig Geduld und melden uns schnellstmoeglich.</p><p>Freundliche Gruesse</p>',
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, subject: string, body_html: string}>
     */
    public function getApplicationStatusMessageTemplatesOrDefault(): array
    {
        $templates = $this->application_status_message_templates;

        if (! is_array($templates) || $templates === []) {
            return static::defaultApplicationStatusMessageTemplates();
        }

        $normalized = collect($templates)
            ->filter(fn (mixed $template): bool => is_array($template))
            ->map(function (array $template): array {
                return [
                    'name' => trim((string) ($template['name'] ?? '')),
                    'subject' => trim((string) ($template['subject'] ?? '')),
                    'body_html' => (string) ($template['body_html'] ?? ''),
                ];
            })
            ->filter(fn (array $template): bool => filled($template['name']) && filled($template['subject']) && filled($template['body_html']))
            ->values()
            ->all();

        if ($normalized === []) {
            return static::defaultApplicationStatusMessageTemplates();
        }

        return $normalized;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function admins(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'admin');
    }

    public function recruiters(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'recruiter');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
