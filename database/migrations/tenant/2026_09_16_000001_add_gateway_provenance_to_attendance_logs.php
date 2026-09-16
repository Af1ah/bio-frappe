<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->uuid('gateway_event_id')->nullable()->unique()->after('id');
            $table->uuid('gateway_receipt_id')->nullable()->index()->after('gateway_event_id');
            $table->unsignedInteger('source_line')->nullable()->after('gateway_receipt_id');
            $table->string('source_digest', 64)->nullable()->after('source_line');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique(['gateway_event_id']);
            $table->dropColumn(['gateway_event_id', 'gateway_receipt_id', 'source_line', 'source_digest']);
        });
    }
};
