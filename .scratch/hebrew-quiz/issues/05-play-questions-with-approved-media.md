# 05: Play questions with approved media

**What to build:** Parents can publish Questions whose prompts or choices use numbers, images, audio, or video. Children can use the approved media in a mobile quiz and read an equivalent text alternative for audio or video.

**Blocked by:** 04 (Resume runs and withdraw published versions)

**Status:** resolved

- [x] Filament lets reviewers author, inspect, and approve text, numbers, images, audio, and video in both prompts and answer choices. Reviewers can attach and inspect source links in Filament.
- [x] Child play receives only approved media from platform-managed storage. Source links remain in the Filament reviewer view. Draft and withdrawn media stay out of play.
- [x] Media loads on demand. Audio and video have playback controls and written alternatives. Arithmetic reads left to right inside Hebrew RTL text.
- [x] Extend the primary phone-width browser flow to use approved media and verify controls and mixed-direction arithmetic. Cover draft and withdrawal filtering through the normal content boundary.

## Comments

Parents can upload and review typed prompts and choices, and inspect private source links. Play receives only media from the active question’s approved version. The phone-width browser flow uploads and plays an audio choice, checks its written alternative, and verifies left-to-right arithmetic inside the Hebrew prompt. HTTP coverage checks byte ranges, draft and withdrawal isolation, household access, approved private adaptations, and unsupported uploads.

The combined feature and browser suites passed 137 tests and 945 assertions. The browser test adapts Pest’s in-process multipart parser to PHP’s normal files[] input shape before invoking Livewire’s signed upload handler.
