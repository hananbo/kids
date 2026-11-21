<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('learning_age')->default(6);
            $table->unsignedTinyInteger('starting_level')->default(1);
            $table->json('subjects')->nullable();
        });

        foreach (['questions', 'published_question_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedTinyInteger('target_age')->default(6);
                $table->json('subjects')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('child_profiles', function (Blueprint $table) {
            $table->dropColumn(['learning_age', 'starting_level', 'subjects']);
        });

        foreach (['questions', 'published_question_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['target_age', 'subjects']);
            });
        }
    }
};
