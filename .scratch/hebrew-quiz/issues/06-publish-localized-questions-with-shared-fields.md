# 06: Publish localized questions with shared fields

**What to build:** Reviewers can publish the same Question in more than one language, reusing common numbers and approved media while approving the exact version each player will see.

**Blocked by:** 05 (Play questions with approved media)

**Status:** ready-for-agent

- [ ] Localized fields can override per-language content. Required language-dependent fields must be approved in the requested language for the Question to enter play. Numeric values and explicitly shared media can fall back to the base item.
- [ ] Reviewers approve the exact localized content and assets served to the player, including generated translations or game assets produced during publishing.
- [ ] Editing a shared field requires reapproval in every language that inherits it. The last approved versions keep serving during review. Languages with an independent override remain available on their own approved versions.
- [ ] Verify a Hebrew version and a second-language fixture through the review and playable-content boundaries, exercising missing translations, shared values, shared-field changes, and continued delivery of previously approved content.
