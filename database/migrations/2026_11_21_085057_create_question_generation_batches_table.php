<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_generation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->unsignedTinyInteger('question_count');
            $table->string('stage')->default('new');
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignId('question_generation_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('generation_media_suggestion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('question_generation_batch_id');
            $table->dropColumn('generation_media_suggestion');
        });

        Schema::dropIfExists('question_generation_batches');
    }
};
