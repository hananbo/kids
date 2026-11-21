<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_question_versions', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable();
        });

        Schema::table('quiz_runs', function (Blueprint $table): void {
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_runs', function (Blueprint $table): void {
            $table->dropColumn('cancelled_at');
        });

        Schema::table('published_question_versions', function (Blueprint $table): void {
            $table->dropColumn('withdrawn_at');
        });
    }
};
