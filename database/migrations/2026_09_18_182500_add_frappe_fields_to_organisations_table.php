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
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('frappe_url')->nullable();
            $table->string('frappe_api_key')->nullable();
            $table->text('frappe_api_secret')->nullable();
            $table->string('frappe_employee_fieldname')->nullable()->default('attendance_device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'frappe_url',
                'frappe_api_key',
                'frappe_api_secret',
                'frappe_employee_fieldname',
            ]);
        });
    }
};
