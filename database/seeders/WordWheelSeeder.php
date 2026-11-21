<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\WordEntry;
use App\Support\Catalog\CatalogLocale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WordWheelSeeder extends Seeder
{
    public function run(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            Team::query()->lockForUpdate()->findOrFail($team->id);

            $existing = WordEntry::query()->whereBelongsTo($team)->get(['content'])
                ->map(fn (WordEntry $entry): mixed => $entry->content['word'] ?? null)
                ->all();

            $puzzles = [
                ['קשת בענן', 'פסים צבעוניים שרואים לפעמים בשמיים אחרי גשם.', 'מזג אוויר', 4],
                ['שעון חול', 'כלי שמודד זמן בעזרת גרגרים העוברים דרך פתח צר.', 'כלים', 6],
                ['גן שעשועים', 'מקום שבו מטפסים, מתנדנדים וגולשים במגלשה.', 'מקומות', 4],
                ['כדור פורח', 'כלי טיס שעולה בעזרת אוויר חם בתוך מעטפת גדולה.', 'תחבורה', 6],
                ['רכבת הרים', 'מתקן בלונה פארק שנוסע במסלול תלול ומתפתל.', 'תחבורה', 6],
                ['שביל החלב', 'הגלקסיה שבה נמצאת מערכת השמש שלנו.', 'חלל', 8],
                ['קיפוד', 'יונק קטן שמתכדרר כשהוא פוחד ומכוסה קוצים.', 'בעלי חיים', 4],
                ['עץ זית', 'צמח רב־שנתי שמפיקים מפירותיו שמן לבישול.', 'צמחים', 6],
                ['שק שינה', 'מתעטפים בו בלילה כשישנים באוהל.', 'כלים', 4],
                ['עפיפון', 'צעצוע שעף ברוח ונשאר קשור בחוט שמחזיקים ביד.', 'משחקים', 4],
                ['מגדלור', 'מבנה גבוה ששולח אלומת אור כדי לכוון ספינות.', 'תחבורה', 6],
                ['שונית אלמוגים', 'בית גידול בים שנבנה על ידי יצורים זעירים וצבעוניים.', 'טבע', 8],
                ['גלידת וניל', 'קינוח קפוא ובהיר בטעם תרמילים ריחניים.', 'אוכל', 5],
                ['בקבוק מים', 'כלי לשתייה שלוקחים בתיק לטיול.', 'כלים', 4],
                ['מגרש כדורסל', 'רחבה שמשחקים בה ומנסים לקלוע לסל.', 'משחקים', 6],
                ['יומן מסע', 'ספר שבו כותבים מה קרה בכל יום של הטיול.', 'כלים', 7],
                ['חתול רחוב', 'חיה עם שפם שמייללת וחיה בחוץ בלי בית קבוע.', 'בעלי חיים', 5],
                ['שן חלב', 'אחת מהשיניים הראשונות שנושרות בילדות.', 'גוף האדם', 5],
            ];

            foreach ($puzzles as [$word, $meaning, $subject, $age]) {
                if (in_array($word, $existing, true)) {
                    continue;
                }

                $entry = WordEntry::query()->create([
                    'team_id' => $team->id,
                    'content' => [
                        'word' => $word,
                        'meaning' => $meaning,
                        'hints' => [],
                        'target_age' => $age,
                        'subjects' => ['ידע כללי', $subject],
                        'wheel_eligible' => true,
                    ],
                ]);

                $entry->versions()->create(['locale' => CatalogLocale::BASE, 'content' => $entry->content]);
            }
        });
    }
}
