<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_bindings', function (Blueprint $table): void {
            $table->string('source_cidr')->nullable()->after('timezone');
            $table->ipAddress('last_source_ip')->nullable()->after('source_cidr');
            $table->timestamp('last_seen_at')->nullable()->after('last_source_ip');
        });

        Schema::table('gateway_receipts', function (Blueprint $table): void {
            $table->dropUnique(['device_binding_id', 'payload_digest']);
            $table->index(['device_binding_id', 'payload_digest']);
            $table->ipAddress('source_ip')->nullable()->after('method');
            $table->json('query')->nullable()->after('source_ip');
            $table->binary('raw_payload')->nullable()->after('query');
            $table->unsignedInteger('delivery_attempts')->default(0)->after('status');
            $table->text('last_error')->nullable()->after('delivery_attempts');
            $table->timestamp('delivered_at')->nullable()->after('last_error');
        });

        Schema::create('gateway_commands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('device_binding_id');
            $table->string('tenant_id');
            $table->unsignedBigInteger('tenant_device_id');
            $table->unsignedBigInteger('tenant_command_id');
            $table->unsignedBigInteger('protocol_command_id')->nullable();
            $table->string('command_type');
            $table->text('command');
            $table->string('state')->default('requested');
            $table->integer('result_code')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('device_binding_id')->references('id')->on('device_bindings')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unique(['tenant_id', 'tenant_command_id']);
            $table->unique(['device_binding_id', 'protocol_command_id']);
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_commands');
        Schema::table('gateway_receipts', function (Blueprint $table): void {
            $table->dropIndex(['device_binding_id', 'payload_digest']);
            $table->unique(['device_binding_id', 'payload_digest']);
            $table->dropColumn(['source_ip', 'query', 'raw_payload', 'delivery_attempts', 'last_error', 'delivered_at']);
        });
        Schema::table('device_bindings', function (Blueprint $table): void {
            $table->dropColumn(['source_cidr', 'last_source_ip', 'last_seen_at']);
        });
    }
};
