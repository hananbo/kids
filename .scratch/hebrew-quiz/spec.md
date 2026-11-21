# First release for the Hebrew quiz show

Status: ready-for-agent

## Problem Statement

A parent wants a private place to curate learning material and let their children play with it on a phone. Each child needs questions suited to their age and interests, separate progress, and useful feedback after answering. The parent needs to review AI-generated questions, translations, and media before they reach a child. The same approved content should be reusable across games and languages.

## Solution

Ship an original, mobile-first Hebrew quiz show for one household. Parents use Filament to manage child profiles, author or generate Questions, and approve what goes live. Children play eight-question runs at ascending difficulty, read an explanation after each answer, and unlock harder levels through repeated successful runs. The quiz is the first standalone game package backed by the platform catalog and progress service.

## User Stories

1. As a parent, I want to sign in to a private household, so that I can manage my children's play and content in one place.
2. As a parent, I want separate child profiles without email addresses, so that siblings can share a device while keeping their own progress.
3. As a parent, I want to set each child's learning age without giving a date of birth, so that the quiz can select suitable questions.
4. As a parent, I want to choose a starting level for each child, so that their first run is suitably challenging.
5. As a parent, I want to choose subjects separately for each child, so that siblings can work on different topics.
6. As a parent, I want subject changes to preserve earned progress, so that I can adjust what a child practices during the release.
7. As a parent, I want to select a child profile on a shared device, so that the child can start playing without a separate sign-in.
8. As a parent, I want fresh authentication to switch profiles or return to Filament, so that a child cannot enter the parent's tools or a sibling's profile.
9. As a parent, I want to author a Question directly in Filament, so that I can add material I already trust.
10. As a parent, I want to generate a batch with AI, so that I can build a question pool without writing every item myself.
11. As a parent, I want AI to propose subjects, age, difficulty, and game eligibility, so that I can correct those choices during review.
12. As a parent, I want AI to suggest hints, explanations, and media when useful, so that I can enrich questions before publishing.
13. As a parent, I want to retry failed enrichment or finish a draft manually, so that an automation failure does not block a usable question.
14. As a parent, I want to edit a prompt, its correct answer, and its distractors together, so that I can review the full quiz question in one place.
15. As a parent, I want to tag a question with multiple subjects, so that relevant children can encounter it through different subject choices.
16. As a parent, I want prompts and choices to support text, numbers, images, audio, and video, so that I can ask questions that depend on more than text.
17. As a reviewer, I want to see source links in Filament, so that I can verify material before approving it.
18. As a reviewer, I want to approve the exact content and language that will appear in play, so that generated or edited material receives review before children see it.
19. As a reviewer, I want to reuse numbers and shared media across languages, so that I can localize text without duplicating common assets.
20. As a reviewer, I want changes to shared fields reviewed in every language that inherits them, so that each language still has a valid approved version.
21. As a parent, I want a published question to stay playable while I edit its draft, so that the pool remains usable during review.
22. As a parent, I want to publish a private copy of a shared Question, so that I can adapt it for my household without altering the Shared Catalog.
23. As a parent, I want to explicitly restore the original after withdrawing a private replacement, so that a question I meant to remove stays out of play.
24. As a platform admin, I want to approve household contributions to the Shared Catalog, so that shared material has explicit platform approval.
25. As a parent, I want Filament to identify a missing difficulty group when a run cannot start, so that I know which questions to add.
26. As a child, I want Hebrew text and controls that read right to left, so that I can navigate naturally on a phone.
27. As a child, I want arithmetic to remain readable within Hebrew text, so that I can interpret mixed-language questions correctly.
28. As a child, I want eight distinct questions in ascending groups, so that every run has a clear beginning, middle, and challenge.
29. As a child, I want the quiz to prefer questions I have yet to see, so that another run gives me something new to practice when possible.
30. As a child, I want four choices, one optional hint per run, and no timer, so that I can answer at my own pace.
31. As a child, I want to play audio or video myself and have a written alternative, so that I can understand the item with sound on or off.
32. As a child, I want an explanation after every answer and the chance to continue after a miss, so that I can finish the run and learn from it.
33. As a child, I want a star for each correct first answer, including after a hint, so that I can see how I did.
34. As a child, I want consistently good runs to unlock the next level, so that I can take on harder questions as I improve.
35. As a child, I want to resume my run after a refresh, so that I keep the answers I already submitted.
36. As a parent, I want to withdraw a published version from play immediately, so that a child cannot keep playing with material I have removed.
37. As a parent, I want to turn an available game off for my household, so that I can control what can be started while preserving past progress.
38. As a platform admin, I want to control overall game availability, so that I can stop new starts across the platform without removing the package.
39. As a game developer, I want a documented package contract for content, attempts, and progress, so that I can add another game backed by the same platform.

## Implementation Decisions

### Household access

- Launch for one household. Reuse Laravel Team as the household boundary and existing adult authentication. Give parents Filament access scoped to their own household and keep platform-admin approvals and controls at the platform level.
- Scope child play, attempts, and progress to the selected child profile and game. Child profiles need neither an email nor a date of birth. Require fresh parent authentication to switch profiles or regain access to Filament, including through direct navigation.
- Household content is private unless a platform admin approves it for the Shared Catalog. A published private copy can replace a shared Question within that household. After withdrawal of that copy, keep the original excluded until a parent explicitly restores it.

### Catalog and publishing

- Use Laravel, MySQL, and Filament 5. Keep Questions and Word Entries distinct under a common catalog and editorial workflow. The quiz consumes Questions. A Question can have multiple subjects, a target age from 1 to 18, and a difficulty from 1 to 10.
- Support manual authoring and AI batch generation with source references. The automated workflow progresses through New, Assessment, Enrichment, Draft, Publishing, and Published. Let the parent retry failed enrichment or complete the draft manually. AI suggestions remain editable and subject to review.
- Require one correct answer, three reviewed distractors, an explanation in the player's language, and reviewer-confirmed game eligibility for quiz play. Prompts and choices can use text, numbers, images, audio, or video. Keep source links in the reviewer experience. Serve approved media from platform-managed storage, load it on demand, and provide playback controls and written alternatives for audio and video.
- Support localized overrides per content field. Require language-dependent fields in the requested language. Allow fallback for numeric values and explicitly shared media. A shared field change requires approval for every language that inherits it. Generated translations and game assets must be approved in their final form before publication.
- Approval applies to an exact Published Version and language. Edits leave the currently approved version playable while review continues. Started runs keep their selected versions. Explicit withdrawal ends affected active runs and preserves progress from completed runs.

### Quiz rules and selection

- Deliver an original, untimed quiz show with its own name, rules, and visuals. Ship Hebrew RTL for the player app and parent-facing Filament. Keep arithmetic left to right within Hebrew text.
- Start a run with eight distinct eligible Questions across the three groups below. Show four choices per question, allow one optional hint per run, and reveal the explanation after the answer. Continue after an incorrect answer.

| Quiz level | First 3 questions | Next 3 questions | Final 2 questions |
| --- | --- | --- | --- |
| 1 | Difficulty 1 | Difficulty 2 | Difficulty 3 |
| 2 | Difficulty 4 | Difficulty 5 | Difficulty 6 |
| 3 | Difficulty 7 | Difficulty 8–9 | Difficulty 10 |

- Filter by the profile's selected subjects and learning age. Exclude Questions whose target age exceeds that age. Within each group, prefer unseen Questions, then the closest target age among them. Fill any remaining places with the least recently seen eligible Questions.
- Seed enough Hebrew Questions for two distinct runs at each child's starting level. This requires at least six, six, and four eligible Questions across the respective groups. Block a new run if any group cannot be filled and show the shortfall in Filament.
- Refresh resumes the run with recorded answers intact. Count each question's first accepted answer once per run. Award one star for each correct first answer, including after a hint. Unlock the next level after at least six correct answers in each of two consecutive completed runs at the child's highest available level. Preserve progress when subjects change.

### Game package boundary

- Install standalone packages at deploy time. The game package owns the UI and rules. Laravel selects approved playable content, keeps answer keys server-side until the rules allow reveal, and records answers and progress by child profile and game.
- Require both platform and household availability for new runs. Platform admins control overall availability. Parents control household availability for enabled games. Disabling a game stops new runs in its scope, lets active runs finish, and retains progress.

## Testing Decisions

- Use one primary browser path through the real product entry points. In Filament, a parent adds and approves a Hebrew Question against a seeded eligible pool. The parent selects a child profile, and the child answers the approved Question in the mobile quiz. Verify visible Hebrew RTL, mixed-direction arithmetic, media controls, answer feedback, and persisted progress for the selected profile. Refresh mid-run and check that the answer is still recorded. Check for JavaScript errors.
- Use focused Pest HTTP feature tests for the distinct authorization and publishing contracts. Cover another household's records, child-mode attempts to switch profiles or reach Filament, pending edits, missing translations, shared-field reapproval, private replacement and restoration, version withdrawal, and game disablement.
- Use deterministic fixtures for question selection and scoring through the normal run entry points. Check group coverage, age and subject filters, unseen-first selection, the last-seen fallback, repeated answer submissions, hint scoring, and the two-run unlock threshold. Keep the AI provider deterministic in tests while exercising the application's own review and publishing behavior.
- Follow the existing Pest browser login smoke test for rendered behavior and JavaScript errors, and the existing authentication, password-confirmation, and Team authorization feature tests for request-level boundaries. Assert observable behavior and saved outcomes so coverage survives changes to internal classes or storage layout.

## Out of Scope

- Automated web imports, public registration for multiple households, and multiplayer play.
- Additional playable games, including word games, the runner, and drawing and guessing.
- WebAssembly in the launch quiz. The first graphics-heavy game will use it.

## Further Notes

Use the [first-release brief](../../docs/first-release.md), [domain glossary](../../GLOSSARY.md), [architecture decisions](../../docs/adr/), and [game package guide](../../docs/game-packages.md) when turning this spec into implementation tickets.

Start with a narrow runnable slice that publishes a Hebrew Question through Filament and records a child's answer in a seeded mobile quiz run. That slice exercises the publishing, profile, and game boundaries before adding more authoring or progression behavior.
