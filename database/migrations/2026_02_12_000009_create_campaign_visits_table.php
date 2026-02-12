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
        Schema::create('campaign_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('source')->default('direct');
            $table->string('source_medium')->nullable();
            $table->string('source_campaign')->nullable();
            $table->text('referrer_url')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'source']);
            $table->index(['campaign_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_visits');
    }
};
