<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_binding_id');
            $table->string('tenant_id');
            $table->unsignedInteger('binding_version');
            $table->string('payload_digest', 64);
            $table->string('endpoint');
            $table->string('method', 10);
            $table->timestamp('received_at');
            $table->string('status')->default('received');
            $table->json('routing_metadata')->nullable();
            $table->timestamps();

            $table->foreign('device_binding_id')->references('id')->on('device_bindings')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unique(['device_binding_id', 'payload_digest']);
            $table->index(['status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_receipts');
    }
};
