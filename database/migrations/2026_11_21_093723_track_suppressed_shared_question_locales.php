<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->json('suppressed_locales')->nullable();
        });

        DB::table('questions')->where('suppresses_original', true)->select('id')
            ->chunkById(100, function (Collection $replacements): void {
                $locales = DB::table('published_question_versions')
                    ->whereIn('question_id', $replacements->pluck('id'))
                    ->whereNull('shared_from_version_id')
                    ->select('question_id', 'locale')
                    ->distinct()
                    ->get()
                    ->groupBy('question_id');

                foreach ($locales as $questionId => $versions) {
                    DB::table('questions')->where('id', $questionId)->update([
                        'suppressed_locales' => json_encode($versions->pluck('locale')->sort()->values()->all(), JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('suppresses_original');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->boolean('suppresses_original')->default(false);
        });

        DB::table('questions')->whereJsonLength('suppressed_locales', '>', 0)->update(['suppresses_original' => true]);

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('suppressed_locales');
        });
    }
};
