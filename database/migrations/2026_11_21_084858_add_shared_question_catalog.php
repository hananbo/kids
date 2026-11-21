<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('published_question_versions', function (Blueprint $table): void {
            $table->foreignId('shared_from_version_id')->nullable()->unique()
                ->constrained('published_question_versions')->restrictOnDelete();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('replaces_question_id')->nullable()->constrained('questions')->restrictOnDelete();
            $table->foreignId('copied_from_version_id')->nullable()->constrained('published_question_versions')->restrictOnDelete();
            $table->boolean('suppresses_original')->default(false);
            $table->unique(['replaces_question_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropForeign(['replaces_question_id']);
            $table->dropForeign(['copied_from_version_id']);
            $table->dropUnique(['replaces_question_id', 'team_id']);
            $table->dropColumn(['replaces_question_id', 'copied_from_version_id', 'suppresses_original']);
        });

        Schema::table('published_question_versions', function (Blueprint $table): void {
            $table->dropForeign(['shared_from_version_id']);
            $table->dropUnique(['shared_from_version_id']);
            $table->dropColumn('shared_from_version_id');
        });
    }
};
