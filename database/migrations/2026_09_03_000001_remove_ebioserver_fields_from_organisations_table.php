<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'ebio_url',
                'ebio_webhook_token',
                'ebio_aes_password',
                'ebio_soap_username',
                'ebio_soap_password',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('ebio_url')->nullable();
            $table->string('ebio_webhook_token')->nullable();
            $table->text('ebio_aes_password')->nullable();
            $table->text('ebio_soap_username')->nullable();
            $table->text('ebio_soap_password')->nullable();
        });
    }
};
