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
        Schema::create('published_word_entry_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shared_from_version_id')->nullable()->unique()->constrained('published_word_entry_versions')->restrictOnDelete();
            $table->string('locale', 16);
            $table->json('content');
            $table->timestamp('withdrawn_at')->nullable();
            $table->index(['word_entry_id', 'locale', 'id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('published_word_entry_versions');
    }
};
