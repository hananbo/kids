---
paths:
  - 'resources/js/**'
---

# Js

## Verify visible UI in a real browser
HTTP assertInertia() checks do not prove React rendered. After a UI change, confirm it with Pest visit() — ./vendor/bin/pest --agent='...' for a one-off, or a test in tests/Browser for a regression. Assert visible text or data-test controls and call assertNoJavaScriptErrors(). For layout review, resolve the Herd URL with Boost get-absolute-url, then read browser-logs. Stale assets mean composer run dev or npm run build.

## Use data-test hooks for clicks
Give interactive controls a stable data-test attribute. Prefer that attribute or the accessible name over CSS classes. Add one when adding a control a browser test or agent must click.

## Live Herd review uses the seeded user
Authenticated routes are prefixed /{current_team}. The DatabaseSeeder user is test@example.com with factory password password. Email verification, 2FA, passkeys, and Filament /admin are separate states.
