<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notification_id')->nullable()->index();
            $table->string('notification_type')->nullable();
            $table->string('recipient_email')->index();
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->string('mailer')->default('postmark');
            $table->string('message_stream')->nullable();
            $table->string('provider')->default('postmark');
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('status')->default('sent')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('spam_reported_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sent_at']);
            $table->index(['application_id', 'sent_at']);
            $table->index(['status', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
