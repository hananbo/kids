# Local Markdown issue tracker

- Store each feature spec at `.scratch/<feature-slug>/spec.md`.
- Store each ticket at `.scratch/<feature-slug>/issues/<NN>-<slug>.md`. Number tickets from `01` in dependency order.
- Record `Status:` and `Blocked by:` near the top. Append discussion under `## Comments`.
- When a skill says to publish, write the corresponding local file. Fetch tickets by path or issue number within the feature directory.

## Wayfinder

- Store the map at `.scratch/<effort>/map.md` and its child tickets in `.scratch/<effort>/issues/`.
- Record each child's `Type:` as `research`, `prototype`, `grilling`, or `task`. Use `Blocked by: NN, NN` for dependencies.
- Take the lowest-numbered open, unclaimed ticket whose blockers are `resolved`. Set it to `claimed` before starting.
- On resolution, add `## Answer`, set `Status: resolved`, and summarize and link the result in the map's Decisions-so-far section.
