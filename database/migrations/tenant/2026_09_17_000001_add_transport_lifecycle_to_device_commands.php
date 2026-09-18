<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_commands', function (Blueprint $table): void {
            $table->string('transport')->default('ebio')->after('command_content');
            $table->uuid('external_id')->nullable()->unique()->after('transport');
            $table->unsignedBigInteger('protocol_command_id')->nullable()->after('external_id');
            $table->string('delivery_status')->default('queued')->after('status');
            $table->integer('result_code')->nullable()->after('delivery_status');
            $table->timestamp('expires_at')->nullable()->after('acknowledged_at');
            $table->index(['transport', 'delivery_status']);
        });

        // The constraint change is intentionally non-destructive. PostgreSQL
        // will stop the migration if existing rows violate the stronger key.
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropUnique('unique_punch');
            $table->unique(['device_id', 'pin', 'punched_at', 'status', 'verify_type'], 'attendance_device_punch_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table): void {
            $table->dropUnique('attendance_device_punch_unique');
            $table->unique(['pin', 'punched_at'], 'unique_punch');
        });
        Schema::table('device_commands', function (Blueprint $table): void {
            $table->dropIndex(['transport', 'delivery_status']);
            $table->dropUnique(['external_id']);
            $table->dropColumn(['transport', 'external_id', 'protocol_command_id', 'delivery_status', 'result_code', 'expires_at']);
        });
    }
};
