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
        Schema::table('quiz_runs', function (Blueprint $table) {
            $table->string('outcome', 10)->nullable();
            $table->json('lifelines')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_runs', function (Blueprint $table) {
            $table->dropColumn(['outcome', 'lifelines']);
        });
    }
};
