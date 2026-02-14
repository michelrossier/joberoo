<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailMessageStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function statusesFromHistory(array $history): array
    {
        return array_map(
            static fn (array $entry): string => $entry['status'],
            $history
        );
    }

    public function test_status_history_contains_reached_postmark_statuses_with_timestamps(): void
    {
        $sentAt = CarbonImmutable::parse('2026-02-14 09:15:00');
        $deliveredAt = CarbonImmutable::parse('2026-02-14 09:16:00');
        $openedAt = CarbonImmutable::parse('2026-02-14 10:02:00');

        $message = EmailMessage::query()->create([
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'status' => EmailMessage::STATUS_OPENED,
            'sent_at' => $sentAt,
            'delivered_at' => $deliveredAt,
            'first_opened_at' => $openedAt,
        ]);

        $history = $message->statusHistory();

        $this->assertSame(
            [
                EmailMessage::STATUS_SENT,
                EmailMessage::STATUS_DELIVERED,
                EmailMessage::STATUS_OPENED,
            ],
            $this->statusesFromHistory($history)
        );

        $this->assertSame(
            [
                $sentAt->toDateTimeString(),
                $deliveredAt->toDateTimeString(),
                $openedAt->toDateTimeString(),
            ],
            array_map(
                static fn (array $entry): string => $entry['occurred_at']->toDateTimeString(),
                $history
            )
        );
        $this->assertSame('Geöffnet', $history[2]['label']);
    }

    public function test_failed_status_history_uses_last_event_timestamp(): void
    {
        $failedAt = CarbonImmutable::parse('2026-02-14 11:40:00');

        $message = EmailMessage::query()->create([
            'recipient_email' => 'failed@example.com',
            'subject' => 'Fehlgeschlagene Nachricht',
            'status' => EmailMessage::STATUS_FAILED,
            'last_event_at' => $failedAt,
        ]);

        $history = $message->statusHistory();

        $this->assertSame([EmailMessage::STATUS_FAILED], $this->statusesFromHistory($history));
        $this->assertSame($failedAt->toDateTimeString(), $history[0]['occurred_at']->toDateTimeString());
    }

    public function test_status_history_falls_back_to_record_timestamp_when_no_status_timestamps_exist(): void
    {
        $message = EmailMessage::query()->create([
            'recipient_email' => 'fallback@example.com',
            'subject' => 'Fallback',
            'status' => EmailMessage::STATUS_DELIVERED,
        ]);

        $history = $message->statusHistory();

        $this->assertSame([EmailMessage::STATUS_DELIVERED], $this->statusesFromHistory($history));
        $this->assertSame($message->updated_at?->toDateTimeString(), $history[0]['occurred_at']->toDateTimeString());
    }
}
