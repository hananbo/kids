# 04: Resume runs and withdraw published versions

**What to build:** A child can refresh and resume a Quiz Run with the same Questions and recorded answers. A parent can review an edit while the old version stays playable or withdraw a version from play immediately.

**Blocked by:** 01 (Play the first Hebrew quiz)

**Status:** ready-for-agent

- [ ] Refresh restores the active run, selected Published Versions, recorded answers, and current place in the quiz for the selected Child Profile.
- [ ] Editing a Question creates a reviewable draft while the last approved version remains playable. After approval, new runs can use the new version while active runs retain the versions selected at their start.
- [ ] Explicit withdrawal removes the version from new runs and ends active runs that contain it. Completed runs retain their answers and progress. Unaffected active runs can continue.
- [ ] The primary browser flow verifies refresh mid-run. Focused coverage verifies pending edits, approved replacements, and explicit withdrawal through the normal publishing and play boundaries.
