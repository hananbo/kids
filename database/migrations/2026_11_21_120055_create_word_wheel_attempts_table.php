<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_wheel_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('child_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_word_entry_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phase')->default('spin');
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedTinyInteger('spin_index')->nullable();
            $table->unsignedInteger('spins')->default(0);
            $table->json('guessed_letters');
            $table->json('feedback')->nullable();
            $table->timestamps();
            $table->index(['child_profile_id', 'id']);
        });

        DB::table('games')->insert([
            'key' => 'word-wheel',
            'name' => 'גלגל המילים',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('games')->where('key', 'word-wheel')->delete();
        Schema::dropIfExists('word_wheel_attempts');
    }
};
