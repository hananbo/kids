---
paths:
  - 'tests/Browser/**'
---

# Browser

## Real-browser tests live here
Put Pest visit() tests in tests/Browser. Use them only for JavaScript or rendered-UI behavior a Feature HTTP test cannot reach. Keep RefreshDatabase applied in Pest.php. Run with php artisan test --compact tests/Browser. One-off probes use ./vendor/bin/pest --agent='...' with single outer quotes and fully qualified class names. Load the pest-plugin-agent skill first.
