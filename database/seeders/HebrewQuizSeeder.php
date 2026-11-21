<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HebrewQuizSeeder extends Seeder
{
    public function run(Team $team): void
    {
        $questions = [
            [1, 'איזו חיה אומרת מיאו?', 'חתול', 'כלב', 'ברווז', 'סוס', 'מיאו הוא הקול של החתול.'],
            [1, 'באיזה צבע בדרך כלל העלים באביב?', 'ירוק', 'סגול', 'שחור', 'ורוד', 'רוב העלים ירוקים בגלל חומר שנקרא כלורופיל.'],
            [1, 'איזה חפץ עוזר לראות בחושך?', 'פנס', 'מחברת', 'מזלג', 'צעיף', 'פנס מאיר במקומות חשוכים.'],
            [2, 'איזה חלק של הצמח קולט מים מהאדמה?', 'השורש', 'הפרח', 'הפרי', 'הזרע', 'השורשים קולטים מים ומינרלים מהאדמה.'],
            [2, 'לאיזה כוכב לכת שייכת מערכת הטבעות הבולטת ביותר?', 'שבתאי', 'מאדים', 'כדור הארץ', 'נוגה', 'הטבעות של שבתאי עשויות מגושי קרח וחלקיקי אבן.'],
            [2, 'מה קופא והופך לקרח?', 'מים', 'מלח', 'חול', 'נייר', 'כאשר מים מתקררים מספיק הם הופכים לקרח.'],
            [3, 'איזה כוח מושך עצמים אל הקרקע?', 'כוח הכבידה', 'כוח החיכוך', 'כוח הרוח', 'כוח הציפה', 'כוח הכבידה של כדור הארץ מושך עצמים אל מרכזו.'],
            [3, 'מה תפקיד הריאות?', 'לקלוט חמצן מהאוויר', 'לפרק מזון', 'להזרים דם', 'להניע את הרגליים', 'הריאות מעבירות חמצן מהאוויר אל הדם.'],
        ];

        DB::transaction(function () use ($questions, $team): void {
            foreach ($questions as [$difficulty, $prompt, $correct, $one, $two, $three, $explanation]) {
                $content = [
                    'prompt' => $prompt,
                    'correct_answer' => $correct,
                    'distractor_one' => $one,
                    'distractor_two' => $two,
                    'distractor_three' => $three,
                    'explanation' => $explanation,
                ];

                $question = Question::query()->create([
                    'team_id' => $team->id,
                    'content' => $content,
                    'difficulty' => $difficulty,
                    'quiz_eligible' => true,
                ]);

                $question->versions()->create([
                    'locale' => 'he',
                    'content' => $content,
                    'difficulty' => $difficulty,
                    'quiz_eligible' => true,
                ]);
            }
        });
    }
}
