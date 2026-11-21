# Adding a game package

The platform installs standalone packages at deploy time. Filament controls whether an installed game is available to start. A new run requires overall and household availability. See the [game package decision](adr/0004-install-game-packages-at-deploy-time.md) and the [first release](first-release.md) for the quiz example.

## Package checklist

1. Identify whether the game consumes Questions or Word Entries. Specify the fields, media, and language requirements that a published item must satisfy to be playable.
2. Give the package ownership of the original game UI and rules. Use the host's mobile-first player experience and support RTL and mixed-direction content. Choose a renderer suited to the game. The first graphics-heavy game uses WebAssembly.
3. Use Laravel to retrieve approved, game-eligible content for the active child profile. Keep answer keys server-side until the rules allow answers to be revealed. Use Laravel to record attempts and progress by child profile and game.
4. Respect the publishing and availability decisions. Edits keep the last approved version playable. Runs keep the versions selected at their start. Withdrawal ends active runs using a withdrawn version. Disabling a game allows active runs to finish and preserves progress.
5. Verify the full flow in an end-to-end test. Publish valid content in Filament, play it in Hebrew RTL at a phone viewport, submit an answer, and check progress on the selected child profile. Exercise missing translations, unapproved content, and game availability as failure paths.

## Hebrew quiz installation

The application registers `HebrewQuizServiceProvider` in `bootstrap/providers.php`. The provider mounts the authenticated play routes, and `resources/js/pages/games/hebrew-quiz.tsx` renders the game. Run migrations and build frontend assets as part of deployment. The game availability migration registers the stable `hebrew-quiz` key. Use the same key in the installed package, its catalog selection, and its progress records.

For an existing household, run `php artisan quiz:seed <household-slug>`. This seeds 48 approved Hebrew General Knowledge questions with a target age of six, enough for two distinct runs at each starting level. Subject selections outside General Knowledge need their own eligible questions. A parent can check pool shortages for each child in Filament.

## Availability and play

- Use **Games** in the platform admin panel to enable or disable an installed game across all households. Use **משחקים** in the parent panel to set the current Household's availability. Installation enables the game by default. Platform disablement preserves each Household's choice.
- A new start must pass both settings inside the run creation transaction. The play page also shows when new starts are unavailable. Current runs can resume, accept answers, and finish while starts are disabled. Re-enabling preserves attempts and progress.
- `PlayableQuestionVersions` supplies eligible, approved versions for the Household. Game rules apply the Child Profile's learning age, subjects, level, and seen history. A `QuizRunQuestion` pins the exact Published Version and the shuffled choice order. Media is served from private managed storage through a route scoped to the active child and pinned version.
- The game sends prompts and ordered choices before the answer. `RecordQuizAnswer` accepts the first choice for the current question and keeps the expected answer and explanation server-side until feedback. Attempts and progress belong to the Child Profile and game key. Correct first answers earn stars, and completed runs determine level unlocks.
