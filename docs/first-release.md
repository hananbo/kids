# First release

A private, mobile-first Hebrew quiz show for one household, built with Laravel, MySQL, and Filament 5. The game will have its own name, rules, and visuals. The player app and parent-facing Filament screens use Hebrew RTL. Arithmetic reads left to right within Hebrew text. The catalog supports additional languages from launch.

## Child play

A parent signs in and selects a child profile. Returning to Filament or switching profiles requires fresh parent authentication.

A quiz run has eight questions in three ascending difficulty groups. Each question shows four answer choices. The child can use one optional hint per run and play without a timer. After each answer, the game shows an explanation and continues even after a miss.

| Quiz level | First 3 questions | Next 3 questions | Final 2 questions |
| --- | --- | --- | --- |
| 1 | Difficulty 1 | Difficulty 2 | Difficulty 3 |
| 2 | Difficulty 4 | Difficulty 5 | Difficulty 6 |
| 3 | Difficulty 7 | Difficulty 8–9 | Difficulty 10 |

Each correct first answer earns one star, including answers given after using a hint. The next level unlocks after at least six correct answers in each of two consecutive completed runs at the child's highest available level. Progress belongs to a child profile and game. The parent chooses the starting level and subjects per child profile. Changing subjects preserves progress.

Each run uses eight distinct questions within the selected subjects and at or below the child's learning age, which the parent sets directly. Within each difficulty group, choose unseen questions first and prefer the closest target age among them. If more questions are needed, choose the least recently seen eligible questions. At each child's starting level, the launch pool must have at least six distinct eligible questions in each of the first two groups and four in the final group. If the available pool cannot fill a group, block the run and show the gap in Filament.

Refreshing the page resumes the active run and keeps recorded answers. A run uses the published versions selected at its start. Explicitly withdrawing a version ends affected active runs and preserves progress from completed runs.

## Authoring and publishing

Parents can create questions manually, generate a batch with AI, and attach source links. AI can propose subjects, target age, difficulty, game eligibility, hints, and explanations. Parents can retry failed enrichment or finish the draft manually.

```mermaid
flowchart LR
    New --> Assessment --> Enrichment --> Draft
    Draft -->|Reviewer approval| Publishing
    Publishing -->|Review any generated output| Published
```

The quiz requires one correct answer, three reviewed distractors, and an explanation in the player's language. Prompts and choices can use text, numbers, images, audio, or video. Filament shows source links to reviewers. Serve approved media from platform-managed storage, load it on demand, and give audio and video playback controls and written alternatives.

A reviewer confirms game eligibility and approves the exact content and language going live, including generated translations or game assets. A change to a shared field requires reapproval for each language that inherits it. Further edits leave the last published version available. Parents can publish a private copy in place of a shared question for their household. If they withdraw the private copy, the original stays out of their pool until they explicitly restore it.

## Game package

The quiz ships as the first game package. Its package owns the UI and quiz rules. Laravel serves playable content, keeps answer keys server-side, and records attempts and progress. Platform admins control overall game availability. Parents can turn available games on or off within their household. Disabling a game prevents new runs while active runs can finish and progress is retained. See [Adding a game package](game-packages.md).

## End-to-end acceptance

- A parent generates or authors a Hebrew question in Filament, reviews it, and finds the approved version in the selected child's quiz. A draft stays out of play.
- The quiz plays at phone width in Hebrew RTL with readable arithmetic and working media controls. Attempts and stars belong to the active child profile. Switching profiles or entering Filament requires fresh parent authentication.
- Refresh restores an active run and its recorded answers. A level unlocks only after two qualifying runs. Insufficient coverage blocks a start and identifies the missing difficulty group in Filament.
- A draft edit keeps the previous version live. Withdrawing a version ends affected runs. Disabling a game blocks new runs and lets active runs finish. Completed progress survives both actions.

## Later releases

Automated web imports follow the first release. The first graphics-heavy game uses WebAssembly. Drawing and guessing starts with parent-hosted, invite-only rooms and limits text input to guesses. See the [original brief](RAW.md) for the full set of game ideas.
