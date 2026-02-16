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
        Schema::table('jobs', function (Blueprint $table): void {
            $table->index(
                ['queue', 'reserved_at', 'available_at', 'id'],
                'jobs_queue_reserved_available_id_idx'
            );
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index(
                ['campaign_id', 'created_at'],
                'applications_campaign_created_at_idx'
            );

            $table->index(
                ['campaign_id', 'status', 'created_at'],
                'applications_campaign_status_created_at_idx'
            );
        });

        Schema::table('application_activities', function (Blueprint $table): void {
            $table->index(
                ['actor_id', 'type', 'created_at'],
                'application_activities_actor_type_created_at_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_activities', function (Blueprint $table): void {
            $table->dropIndex('application_activities_actor_type_created_at_idx');
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex('applications_campaign_status_created_at_idx');
            $table->dropIndex('applications_campaign_created_at_idx');
        });

        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex('jobs_queue_reserved_available_id_idx');
        });
    }
};
