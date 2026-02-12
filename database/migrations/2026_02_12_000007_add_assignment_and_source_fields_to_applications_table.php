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
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('campaign_id')->constrained('users')->nullOnDelete();
            $table->string('source')->default('direct')->after('cover_letter_text');
            $table->string('source_medium')->nullable()->after('source');
            $table->string('source_campaign')->nullable()->after('source_medium');
            $table->text('referrer_url')->nullable()->after('source_campaign');

            $table->index(['campaign_id', 'source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'source']);
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn([
                'source',
                'source_medium',
                'source_campaign',
                'referrer_url',
            ]);
        });
    }
};
