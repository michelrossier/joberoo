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
        Schema::create('email_message_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_message_id')->nullable()->constrained('email_messages')->nullOnDelete();
            $table->string('event')->index();
            $table->string('fingerprint')->unique();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('recipient_email')->nullable()->index();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('payload');
            $table->timestamps();

            $table->index(['email_message_id', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_message_events');
    }
};
