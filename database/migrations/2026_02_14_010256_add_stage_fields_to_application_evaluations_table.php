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
        Schema::table('application_evaluations', function (Blueprint $table): void {
            $table->string('stage', 50)
                ->nullable()
                ->after('evaluator_id');
            $table->json('question_responses')
                ->nullable()
                ->after('scores');

            $table->index(['application_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_evaluations', function (Blueprint $table): void {
            $table->dropIndex(['application_id', 'stage']);
            $table->dropColumn(['stage', 'question_responses']);
        });
    }
};
