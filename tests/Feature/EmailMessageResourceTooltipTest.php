<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class EmailMessageResourceTooltipTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @throws \ReflectionException
     */
    public function test_status_tooltip_uses_tabular_markup_to_align_dates(): void
    {
        $message = EmailMessage::query()->create([
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'status' => EmailMessage::STATUS_OPENED,
            'sent_at' => CarbonImmutable::parse('2026-02-14 09:00:00'),
            'delivered_at' => CarbonImmutable::parse('2026-02-14 09:01:00'),
            'first_opened_at' => CarbonImmutable::parse('2026-02-14 09:05:00'),
        ]);

        $method = new ReflectionMethod(EmailMessageResource::class, 'formatStatusTooltip');
        $method->setAccessible(true);

        $tooltip = $method->invoke(null, $message);

        $this->assertNotNull($tooltip);

        $html = $tooltip->toHtml();

        $this->assertStringContainsString('<table style="border-collapse: collapse;"><tbody>', $html);
        $this->assertStringContainsString('Gesendet:</td><td style="padding: 0; white-space: nowrap;">14.02.2026 09:00:00</td>', $html);
        $this->assertStringContainsString('Zugestellt:</td><td style="padding: 0; white-space: nowrap;">14.02.2026 09:01:00</td>', $html);
        $this->assertStringContainsString('Geöffnet:</td><td style="padding: 0; white-space: nowrap;">14.02.2026 09:05:00</td>', $html);
        $this->assertStringNotContainsString('<br>', $html);
    }
}
