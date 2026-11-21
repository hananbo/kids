# 08: Share and replace catalog questions

**What to build:** Platform admins can approve Questions for the Shared Catalog. A parent can adapt a shared Question into a private replacement and choose when to restore the original for their Household.

**Blocked by:** 04 (Resume runs and withdraw published versions)

**Status:** resolved

- [x] A parent-created Question stays private unless a platform admin approves it for the Shared Catalog. A parent can review and publish private content within their own Household.
- [x] Editing a shared Question creates a private copy. Publishing that copy replaces the original in the Household's quiz pool while the Shared Catalog version remains available to other authorized Households.
- [x] Withdrawing the private replacement keeps the original out of the Household's pool until the parent explicitly restores it. An eligible restored original is available for new runs.
- [x] Coverage uses separate Household fixtures to verify private access, platform approval, replacement scope, withdrawal, and explicit restoration through Filament and the quiz pool boundary.

## Resolution

- Platform reviewers approve an exact published version as a separate Shared Catalog version. Household edits, publication, and withdrawal stay scoped to private versions.
- Parents open a private copy from the Shared Catalog. Publishing suppresses the shared source in their Household until they explicitly restore it after withdrawing the copy.
- Feature tests exercise separate Households, exact shared approval, draft isolation, replacement, restoration, and platform-only withdrawal.
