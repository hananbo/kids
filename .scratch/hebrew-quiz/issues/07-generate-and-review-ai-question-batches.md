# 07: Generate and review AI question batches

**What to build:** A parent can request an AI batch in Filament, correct the proposed Questions and enrichment, recover from failures, and review individual Questions before they reach play.

**Blocked by:** 02 (Personalize each child’s question pool)

**Status:** resolved

- [x] Filament exposes New, Assessment, Enrichment, Draft, Publishing, and Published stages for generated Questions, with source references available to reviewers.
- [x] Assessment can propose Subjects, Target Age, Difficulty, and game eligibility. Enrichment can suggest hints, explanations, and media where useful. Reviewers can edit proposals before approval.
- [x] A failed enrichment can be retried or completed manually. Generated Questions enter play only after a reviewer approves the exact eligible version.
- [x] A deterministic AI provider fixture verifies a successful batch, recovery from an enrichment failure, and proof that approval is required for a generated Question to enter play.

## Comments

Parents can queue a batch in Filament, track assessment and enrichment, and retry failed enrichment on the same drafts. They can also complete drafts manually. Source references and media suggestions are available to reviewers. The existing publication action still governs entry into the quiz. Two deterministic browser tests cover publication gating, tenant scope, manual rescue, and retries.
