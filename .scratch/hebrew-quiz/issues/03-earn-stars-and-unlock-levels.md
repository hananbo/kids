# 03: Earn stars and unlock levels

**What to build:** A child can use a hint while playing, see the stars they earned, and unlock a harder Quiz Level by doing well across two runs.

**Blocked by:** 02 (Personalize each child’s question pool)

**Status:** resolved

- [x] The player can reveal one approved hint per run when a Question offers one.
- [x] Each correct first answer earns one star, including after using a hint. Repeating an answer submission cannot add stars or duplicate progress.
- [x] The next level opens after at least six correct answers in each of two consecutive completed runs at the child's highest available level. An unfinished run cannot qualify.
- [x] Stars and unlocked levels belong to the selected Child Profile and quiz. A sibling's progress is separate, and changing Subjects preserves earned progress.
- [x] Coverage verifies the exact qualifying threshold, a below-threshold result, repeated submissions, hint scoring, and separate profile progress through the game's normal entry points.

## Comments

The quiz reveals one approved hint per run, shows stars from accepted answers, and unlocks levels after two consecutive completed runs with at least six stars each. Completed progress is scoped to the child and game. Verified all three level boundaries, repeat submissions, sibling isolation, and the phone-width Filament-to-quiz hint flow.
