<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_bindings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('serial_number');
            $table->string('protocol')->default('adms');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('capability_profile')->nullable();
            $table->text('credential_reference')->nullable();
            $table->unsignedInteger('ownership_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('organisations')->cascadeOnDelete();
            $table->unique('serial_number');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_bindings');
    }
};
