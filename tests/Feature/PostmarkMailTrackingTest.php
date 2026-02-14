<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\EmailMessage;
use App\Models\EmailMessageEvent;
use App\Models\Organization;
use App\Models\Campaign;
use App\Models\User;
use App\Notifications\ApplicationStatusMessageNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use RuntimeException;
use Tests\TestCase;

class PostmarkMailTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.postmark.webhook_basic_user', 'postmark_user');
        config()->set('services.postmark.webhook_basic_pass', 'postmark_pass');
        config()->set('mail.default', 'failover');
        config()->set('mail.mailers.postmark.message_stream_id', 'outbound');
    }

    public function test_notification_sent_event_creates_email_message_with_provider_message_id(): void
    {
        [$organization, $campaign, $application, $actor] = $this->createApplicationContext();

        $notification = new ApplicationStatusMessageNotification(
            'Kurzes Update zu Ihrer Bewerbung',
            '<p>Vielen Dank fuer Ihre Geduld.</p>',
            $application,
            $actor->id,
        );
        $notification->id = 'notif-sent-1';

        $notifiable = new AnonymousNotifiable;
        $notifiable->route('mail', 'candidate@example.com');

        event(new NotificationSent(
            $notifiable,
            $notification,
            'mail',
            $this->fakeSentResponse('pm-msg-1', 'Kurzes Update zu Ihrer Bewerbung'),
        ));

        $this->assertDatabaseHas('email_messages', [
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'application_id' => $application->id,
            'actor_id' => $actor->id,
            'notification_id' => 'notif-sent-1',
            'recipient_email' => 'candidate@example.com',
            'provider_message_id' => 'pm-msg-1',
            'status' => EmailMessage::STATUS_SENT,
            'message_stream' => 'outbound',
        ]);
    }

    public function test_notification_failed_event_marks_email_message_as_failed(): void
    {
        [, , $application, $actor] = $this->createApplicationContext();

        $notification = new ApplicationStatusMessageNotification(
            'Kurzes Update zu Ihrer Bewerbung',
            '<p>Vielen Dank fuer Ihre Geduld.</p>',
            $application,
            $actor->id,
        );
        $notification->id = 'notif-failed-1';

        $notifiable = new AnonymousNotifiable;
        $notifiable->route('mail', 'candidate@example.com');

        event(new NotificationSent(
            $notifiable,
            $notification,
            'mail',
            $this->fakeSentResponse('pm-msg-2', 'Kurzes Update zu Ihrer Bewerbung'),
        ));

        event(new NotificationFailed(
            $notifiable,
            $notification,
            'mail',
            ['exception' => new RuntimeException('Postmark transport timeout')],
        ));

        $this->assertDatabaseHas('email_messages', [
            'notification_id' => 'notif-failed-1',
            'recipient_email' => 'candidate@example.com',
            'status' => EmailMessage::STATUS_FAILED,
            'failure_reason' => 'Postmark transport timeout',
        ]);
    }

    public function test_legacy_status_notification_payload_without_application_context_still_sends(): void
    {
        /** @var ApplicationStatusMessageNotification $notification */
        $notification = (new \ReflectionClass(ApplicationStatusMessageNotification::class))
            ->newInstanceWithoutConstructor();
        $notification->subjectLine = 'Legacy Bewerbungs-Update';
        $notification->messageHtml = '<p>Bitte melden Sie sich bei Fragen.</p>';
        $notification->id = 'legacy-status-notif-1';

        $notifiable = new AnonymousNotifiable;
        $notifiable->route('mail', 'candidate@example.com');

        event(new NotificationSent(
            $notifiable,
            $notification,
            'mail',
            $this->fakeSentResponse('pm-legacy-1', 'Legacy Bewerbungs-Update'),
        ));

        $this->assertDatabaseHas('email_messages', [
            'notification_id' => 'legacy-status-notif-1',
            'notification_type' => ApplicationStatusMessageNotification::class,
            'recipient_email' => 'candidate@example.com',
            'provider_message_id' => 'pm-legacy-1',
            'status' => EmailMessage::STATUS_SENT,
        ]);
    }

    public function test_delivery_webhook_updates_message_and_writes_application_activity(): void
    {
        [, , $application] = $this->createApplicationContext();

        $message = EmailMessage::query()->create([
            'application_id' => $application->id,
            'campaign_id' => $application->campaign_id,
            'organization_id' => $application->campaign->organization_id,
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'provider_message_id' => 'pm-delivery-1',
            'status' => EmailMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $deliveredAt = CarbonImmutable::parse('2026-02-14 10:00:00');

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Delivery',
                'MessageID' => 'pm-delivery-1',
                'Recipient' => 'candidate@example.com',
                'DeliveredAt' => $deliveredAt->toIso8601String(),
                'Details' => 'Delivered to inbox.',
            ])
            ->assertOk();

        $message->refresh();

        $this->assertSame(EmailMessage::STATUS_DELIVERED, $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertDatabaseHas('email_message_events', [
            'email_message_id' => $message->id,
            'event' => EmailMessageEvent::EVENT_DELIVERY,
            'provider_message_id' => 'pm-delivery-1',
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'type' => 'email_delivered',
        ]);
    }

    public function test_open_webhook_tracks_first_and_last_open_and_deduplicates_retries(): void
    {
        [, , $application] = $this->createApplicationContext();

        $message = EmailMessage::query()->create([
            'application_id' => $application->id,
            'campaign_id' => $application->campaign_id,
            'organization_id' => $application->campaign->organization_id,
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'provider_message_id' => 'pm-open-1',
            'status' => EmailMessage::STATUS_DELIVERED,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $firstOpenAt = CarbonImmutable::parse('2026-02-14 11:00:00');
        $secondOpenAt = CarbonImmutable::parse('2026-02-14 12:15:00');

        $payload = [
            'RecordType' => 'Open',
            'MessageID' => 'pm-open-1',
            'Recipient' => 'candidate@example.com',
            'FirstOpen' => $firstOpenAt->toIso8601String(),
        ];

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', $payload)
            ->assertOk();

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', $payload)
            ->assertOk();

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Open',
                'MessageID' => 'pm-open-1',
                'Recipient' => 'candidate@example.com',
                'ReceivedAt' => $secondOpenAt->toIso8601String(),
            ])
            ->assertOk();

        $message->refresh();

        $this->assertSame(EmailMessage::STATUS_OPENED, $message->status);
        $this->assertSame($firstOpenAt->toDateTimeString(), $message->first_opened_at?->toDateTimeString());
        $this->assertSame($secondOpenAt->toDateTimeString(), $message->last_opened_at?->toDateTimeString());
        $this->assertSame(2, EmailMessageEvent::query()->where('email_message_id', $message->id)->where('event', EmailMessageEvent::EVENT_OPEN)->count());
        $this->assertSame(1, $application->activities()->where('type', 'email_opened')->count());
    }

    public function test_bounce_and_spam_webhooks_set_terminal_statuses(): void
    {
        [, , $application] = $this->createApplicationContext();

        $bouncedMessage = EmailMessage::query()->create([
            'application_id' => $application->id,
            'campaign_id' => $application->campaign_id,
            'organization_id' => $application->campaign->organization_id,
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'provider_message_id' => 'pm-bounce-1',
            'status' => EmailMessage::STATUS_OPENED,
            'sent_at' => now(),
        ]);

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Bounce',
                'MessageID' => 'pm-bounce-1',
                'Recipient' => 'candidate@example.com',
                'BouncedAt' => now()->toIso8601String(),
                'Description' => 'Mailbox unavailable',
            ])
            ->assertOk();

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Delivery',
                'MessageID' => 'pm-bounce-1',
                'Recipient' => 'candidate@example.com',
                'DeliveredAt' => now()->addMinute()->toIso8601String(),
            ])
            ->assertOk();

        $bouncedMessage->refresh();

        $this->assertSame(EmailMessage::STATUS_BOUNCED, $bouncedMessage->status);
        $this->assertSame('Mailbox unavailable', $bouncedMessage->failure_reason);

        $spamMessage = EmailMessage::query()->create([
            'application_id' => $application->id,
            'campaign_id' => $application->campaign_id,
            'organization_id' => $application->campaign->organization_id,
            'recipient_email' => 'candidate@example.com',
            'subject' => 'Ihre Bewerbung',
            'provider_message_id' => 'pm-spam-1',
            'status' => EmailMessage::STATUS_OPENED,
            'sent_at' => now(),
        ]);

        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'SpamComplaint',
                'MessageID' => 'pm-spam-1',
                'Recipient' => 'candidate@example.com',
                'ReportedAt' => now()->toIso8601String(),
            ])
            ->assertOk();

        $spamMessage->refresh();

        $this->assertSame(EmailMessage::STATUS_SPAM_COMPLAINT, $spamMessage->status);
        $this->assertNotNull($spamMessage->spam_reported_at);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'type' => 'email_bounced',
        ]);
        $this->assertDatabaseHas('application_activities', [
            'application_id' => $application->id,
            'type' => 'email_spam_reported',
        ]);
    }

    public function test_unknown_message_id_webhook_is_accepted_and_stored_unlinked(): void
    {
        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Delivery',
                'MessageID' => 'pm-unknown-1',
                'Recipient' => 'candidate@example.com',
                'DeliveredAt' => now()->toIso8601String(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('email_message_events', [
            'event' => EmailMessageEvent::EVENT_DELIVERY,
            'provider_message_id' => 'pm-unknown-1',
            'email_message_id' => null,
        ]);
    }

    /**
     * @return array{0: Organization, 1: Campaign, 2: Application, 3: User}
     */
    private function createApplicationContext(): array
    {
        $organization = Organization::factory()->create();
        $campaign = Campaign::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $application = Application::factory()->create([
            'campaign_id' => $campaign->id,
            'status' => ApplicationStatus::Interview,
            'email' => 'candidate@example.com',
        ]);

        $actor = User::factory()->create();
        $actor->organizations()->attach($organization, ['role' => 'admin']);

        return [$organization, $campaign, $application->load('campaign'), $actor];
    }

    /**
     * @return object
     */
    private function fakeSentResponse(string $messageId, string $subject): object
    {
        return new class($messageId, $subject)
        {
            public function __construct(
                private readonly string $messageId,
                private readonly string $subject,
            ) {
            }

            public function getMessageId(): string
            {
                return $this->messageId;
            }

            public function getOriginalMessage(): object
            {
                return new class($this->subject)
                {
                    public function __construct(private readonly string $subject)
                    {
                    }

                    public function getSubject(): string
                    {
                        return $this->subject;
                    }
                };
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function basicAuthHeaders(string $user = 'postmark_user', string $pass = 'postmark_pass'): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode("{$user}:{$pass}"),
        ];
    }
}
