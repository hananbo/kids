# 10: Review Word Entries in the Shared Catalog

**What to build:** Reviewers can create and publish Word Entries with their own word, meaning, and hints through the same localized editorial workflow used by Questions.

**Blocked by:** 06 (Publish localized questions with shared fields), 08 (Share and replace catalog questions)

**Status:** resolved

- [x] A reviewer can author and review a Word Entry with a target word, meaning, and optional hints. Word Entries retain their own content structure and use the catalog's localized draft, approval, and publication workflow.
- [x] The Household privacy and platform-admin approval boundaries apply to Word Entries. A draft localization stays in review while its previously approved version remains available.
- [x] The quiz supplies only eligible Questions. Verify that a published Word Entry appears in the appropriate catalog scope and stays outside the quiz pool.

## Comments

Word Entries use the catalog’s locale draft, exact-revision approval, and platform sharing actions with separate word, meaning, and hint data. A pending translation leaves its approved version available. Parents can publish Word Entries in Hebrew Filament and read only the versions explicitly approved for the Shared Catalog outside their household.

The full feature and browser suites passed 150 tests and 2,123 assertions. Coverage includes Hebrew phone-width authoring and approval, an English draft and exact admin-approved snapshot, stale review rejection, and a normal quiz attempt that reports zero eligible questions when the catalog contains only a shared Word Entry.
