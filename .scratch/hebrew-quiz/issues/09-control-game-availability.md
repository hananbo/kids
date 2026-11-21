# 09: Control game availability

**What to build:** Platform admins and parents control when an installed game can start. Children can finish active runs and keep their progress while new starts are disabled.

**Blocked by:** 04 (Resume runs and withdraw published versions)

**Status:** resolved

- [x] A platform admin can enable or disable an installed game overall in Filament. A parent can turn an available game on or off for their Household. Both settings must allow new starts.
- [x] A disabled game cannot start a new run in the affected scope. Active runs can resume and finish. Re-enabling the game preserves recorded attempts and earned progress.
- [x] Verify both availability scopes through Filament and the quiz start boundary, including blocked direct requests, active-run completion, and re-enabled play.
- [x] Update the game package guide with the working install, availability, playable-content, answer handling, and progress contract used by the quiz.

## Comments

Added platform and household settings for installed games. The start transaction checks both flags after it looks for an active run. Verified both Filament switches at phone width, direct start requests, household isolation, completion while disabled, and recorded attempts after re-enabling. Updated the package installation and runtime guide.
