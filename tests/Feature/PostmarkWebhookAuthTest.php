<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostmarkWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.postmark.webhook_basic_user', 'postmark_user');
        config()->set('services.postmark.webhook_basic_pass', 'postmark_pass');
    }

    public function test_postmark_webhook_requires_valid_basic_auth(): void
    {
        $payload = [
            'RecordType' => 'Delivery',
            'MessageID' => 'pm-auth-1',
            'Recipient' => 'candidate@example.com',
            'DeliveredAt' => now()->toIso8601String(),
        ];

        $this->postJson('/webhooks/postmark', $payload)
            ->assertStatus(401);

        $this->withHeaders($this->basicAuthHeaders('wrong', 'creds'))
            ->postJson('/webhooks/postmark', $payload)
            ->assertStatus(401);
    }

    public function test_postmark_webhook_accepts_valid_basic_auth(): void
    {
        $this->withHeaders($this->basicAuthHeaders())
            ->postJson('/webhooks/postmark', [
                'RecordType' => 'Delivery',
                'MessageID' => 'pm-auth-2',
                'Recipient' => 'candidate@example.com',
                'DeliveredAt' => now()->toIso8601String(),
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
            ]);
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
