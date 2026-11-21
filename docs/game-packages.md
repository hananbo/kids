# Adding a game package

The platform installs standalone packages at deploy time. Filament controls whether an installed game is available to start. A new run requires overall and household availability. See the [game package decision](adr/0004-install-game-packages-at-deploy-time.md) and the [first release](first-release.md) for the quiz example.

## Package checklist

1. Identify whether the game consumes Questions or Word Entries. Specify the fields, media, and language requirements that a published item must satisfy to be playable.
2. Give the package ownership of the original game UI and rules. Use the host's mobile-first player experience and support RTL and mixed-direction content. Choose a renderer suited to the game. The first graphics-heavy game uses WebAssembly.
3. Use Laravel to retrieve approved, game-eligible content for the active child profile. Keep answer keys server-side until the rules allow answers to be revealed. Use Laravel to record attempts and progress by child profile and game.
4. Respect the publishing and availability decisions. Edits keep the last approved version playable. Runs keep the versions selected at their start. Withdrawal ends active runs using a withdrawn version. Disabling a game allows active runs to finish and preserves progress.
5. Verify the full flow in an end-to-end test. Publish valid content in Filament, play it in Hebrew RTL at a phone viewport, submit an answer, and check progress on the selected child profile. Exercise missing translations, unapproved content, and game availability as failure paths.
