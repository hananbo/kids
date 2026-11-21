# Adding a game package

The platform installs standalone packages at deploy time. Filament controls whether an installed game is available to start. A new run requires overall and household availability. See the [game package decision](adr/0004-install-game-packages-at-deploy-time.md) and the [first release](first-release.md) for the quiz example.

## Package checklist

1. Identify whether the game consumes Questions or Word Entries. Specify the fields, media, and language requirements that a published item must satisfy to be playable.
2. Give the package ownership of its game UI and rules. Use the host's mobile-first player experience, support RTL and mixed-direction content, and start with the shared browser graphics runtime for 2D scenes.
3. Use Laravel to retrieve approved, game-eligible content for the active child profile. Keep answer keys server-side until the rules allow answers to be revealed. Use Laravel to record attempts and progress by child profile and game.
4. Respect the publishing and availability decisions. Edits keep the last approved version playable. Runs keep the versions selected at their start. Withdrawal ends active runs using a withdrawn version. Disabling a game allows active runs to finish and preserves progress.
5. Verify the full flow in an end-to-end test. Publish valid content in Filament, play it in Hebrew RTL at a phone viewport, submit an answer, and check progress on the selected child profile. Exercise missing translations, unapproved content, and game availability as failure paths.

## Browser graphics

PixiJS 8 with WebGL 2 is the shared renderer for 2D games. The quiz already uses PixiJS and needs sprites, meshes, particles, and timed effects layered with Hebrew controls. This replaces the initial WebAssembly assumption in [the package decision](adr/0004-install-game-packages-at-deploy-time.md). PixiJS recommends WebGL for production and labels its WebGPU and Canvas 2D renderers experimental. [PixiJS renderer guide](https://pixijs.com/8.x/guides/components/renderers)

| Engine | Where it fits |
| --- | --- |
| **PixiJS 8** | Default for 2D games that want a scene graph, custom shaders, and control over drawing. Its ticker drives animation and its particle container can limit GPU uploads to changing attributes. [Ticker](https://pixijs.com/8.x/guides/components/ticker), [Particle container](https://pixijs.com/8.x/guides/components/scene-objects/particle-container) |
| Phaser 4 | Revisit for a game that needs an integrated scene manager, physics, cameras, input, or tilemaps. [Phaser API](https://docs.phaser.io/api-documentation/function/core), [Scenes](https://docs.phaser.io/phaser/concepts/scenes) |
| Three.js | Revisit for a game with an actual 3D world. Its WebGPU renderer can use a WebGL 2 backend and comes with a separate material and post-processing model. [Three.js renderer guide](https://threejs.org/manual/pages/webgpurenderer) |
| Godot web export | Revisit when a game needs Godot's editor or native exports. Its browser build requires WebAssembly and the WebGL 2 Compatibility renderer. Threaded builds require cross-origin isolation. [Godot web export guide](https://docs.godotengine.org/en/stable/tutorials/export/exporting_for_web.html) |

Mount [`GameCanvas`](../resources/js/components/games/game-canvas.tsx) in a game-owned container with a defined size. Pass a `GameScene` factory that builds the scene with the provided PixiJS module, root stage, and canvas. Use `resize(width, height)` to lay out the scene in CSS pixels, `update(deltaSeconds, reducedMotion)` to advance effects, and `dispose()` to release resources the scene owns, such as custom shaders. Keep the factory's visual state independent of the server-authoritative game state. Send accepted game events and current phase into the scene instead of recreating the renderer on every transition.

The shared canvas loads PixiJS on demand using its CSP-compatible extension. It owns the rendering loop, resolution cap, visibility pauses, context-loss recovery, and renderer cleanup. Give every decorative scene a static fallback and keep answer controls, text, and media in accessible HTML. For games that require a rendered playfield, opt in to `reducedMotion="render"` and suppress decorative motion when the scene receives the reduced-motion flag. [PixiJS application lifecycle](https://pixijs.com/8.x/guides/components/application), [PixiJS CSP extension](https://github.com/pixijs/pixijs/blob/dev/src/unsafe-eval/init.ts)

Verify effects and game controls on mobile and desktop. Check that reduced motion and renderer failure still expose the game's fallback and that leaving and reopening the game mounts a fresh scene.

## Register a game

Add the package provider to \`bootstrap/providers.php\`. In its \`register()\` method, bind a \`GamePackage\` descriptor and tag the binding with \`GamePackage::class\`. The descriptor supplies the stable game key, display name, child entry route, and a pool status callback for the selected Child Profile. The parent panel builds the play and readiness actions from registered packages with a valid entry route.

In the provider’s \`boot()\` method, mount the game’s named routes under \`{current_team}\` with \`web\`, \`auth\`, \`verified\`, and \`EnsureChildPlay\`. Use the bound \`Team $current_team\` and \`EnsureChildPlay::profile($request)\` to access the authorized play context. Use the same game key for the availability row, catalog rules, and progress records. Include a migration for a new game’s availability row.

## Hebrew quiz installation

\`HebrewQuizServiceProvider\` registers the \`hebrew-quiz\` package and its \`quiz:seed\` command. Its play screen is \`resources/js/pages/games/hebrew-quiz.tsx\` and uses Wayfinder’s generated quiz routes. Run migrations and build frontend assets as part of deployment. If removing the quiz, remove its provider registration and its Wayfinder-dependent page from the build together. Rebuild route and frontend caches. The host omits play and readiness actions for an uninstalled package and retains recorded progress.

For an existing household, run \`php artisan quiz:seed <household-slug>\`. This seeds 96 approved Hebrew General Knowledge questions. The target-age-one band uses familiar objects, sounds, routines, and early counting, and a parent can read it aloud. The target-age-six band adds more advanced topics. Each band supplies two distinct runs at each starting level. Profiles that select other subjects need their own eligible questions. Use the parent panel to check each child’s pool.

## Availability and play

- Use **Games** in the platform admin panel to enable or disable an installed game across all households. Use **משחקים** in the parent panel to set the current Household's availability. Installation enables the game by default. Platform disablement preserves each Household's choice.
- A new start must pass both settings inside the run creation transaction. The play page also shows when new starts are unavailable. Current runs can resume, accept answers, and finish while starts are disabled. Re-enabling preserves attempts and progress.
- `PlayableQuestionVersions` supplies eligible, approved versions for the Household. Game rules apply the Child Profile's learning age, subjects, level, and seen history. A `QuizRunQuestion` pins the exact Published Version and the shuffled choice order. Media is served from private managed storage through a route scoped to the active child and pinned version.
- The game sends prompts and ordered choices before the answer. `RecordQuizAnswer` accepts the first choice for the current question and keeps the expected answer and explanation server-side until feedback. Attempts and progress belong to the Child Profile and game key. Correct first answers earn stars, and completed runs determine level unlocks.
