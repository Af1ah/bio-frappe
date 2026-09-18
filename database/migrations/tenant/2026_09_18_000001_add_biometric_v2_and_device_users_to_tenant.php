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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'face_v2_templates')) {
                $table->text('face_v2_templates')->nullable()->after('face_templates');
            }
        });

        if (! Schema::hasTable('device_users')) {
            Schema::create('device_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
                $table->string('pin');
                $table->string('name')->nullable();
                $table->string('card_number')->nullable();
                $table->integer('privilege')->default(0);
                $table->integer('fingerprint_count')->default(0);
                $table->integer('face_count')->default(0);
                $table->json('enrolled_methods')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['device_id', 'pin']);
                $table->index(['pin']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_users');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'face_v2_templates')) {
                $table->dropColumn('face_v2_templates');
            }
        });
    }
};
