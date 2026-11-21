# 01: Play the first Hebrew quiz

**What to build:** A parent publishes a text Question in Filament, selects a Child Profile, and hands over the phone. The child completes an original Hebrew quiz run and their answers are saved to their profile.

**Blocked by:** None (can start immediately)

**Status:** resolved

- [x] The existing Team scopes the private Household. A parent can create and select separate Child Profiles without email addresses or dates of birth. Switching profiles or reaching Filament from child play requires fresh parent authentication, including through direct navigation.
- [x] The parent can author, review, and approve a Hebrew text Question in Filament with a prompt, one correct answer, three Distractors, an explanation, and explicit quiz eligibility. Only approved versions are available in play.
- [x] The installed quiz package starts a seeded Level 1 run with eight distinct Questions, in groups of three at Difficulty 1, three at Difficulty 2, and two at Difficulty 3. It shows four choices, gives an explanation after every answer, and continues after a miss. Play is untimed.
- [x] Laravel keeps the answer key server-side until the quiz rules permit reveal. Attempts are recorded for the selected Child Profile and quiz, with one accepted answer per Question per run.
- [x] An end-to-end phone-width browser test publishes a Question in Hebrew RTL Filament, plays it in the Hebrew RTL quiz, and verifies feedback, the recorded attempt, and a clean JavaScript console. Request-level checks verify Household and child-mode access boundaries.

## Comments

Implemented the parent panel, child handoff, reviewed Hebrew question versions, and the first eight-question quiz run. The phone-width browser test completes a run and confirms that direct parent navigation requires a fresh password after handoff. The feature and browser suites passed all 117 tests.
