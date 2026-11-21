# 07: Generate and review AI question batches

**What to build:** A parent can request an AI batch in Filament, correct the proposed Questions and enrichment, recover from failures, and review individual Questions before they reach play.

**Blocked by:** 02 (Personalize each child’s question pool)

**Status:** ready-for-agent

- [ ] Filament exposes New, Assessment, Enrichment, Draft, Publishing, and Published stages for generated Questions, with source references available to reviewers.
- [ ] Assessment can propose Subjects, Target Age, Difficulty, and game eligibility. Enrichment can suggest hints, explanations, and media where useful. Reviewers can edit proposals before approval.
- [ ] A failed enrichment can be retried or completed manually. Generated Questions enter play only after a reviewer approves the exact eligible version.
- [ ] A deterministic AI provider fixture verifies a successful batch, recovery from an enrichment failure, and proof that approval is required for a generated Question to enter play.
