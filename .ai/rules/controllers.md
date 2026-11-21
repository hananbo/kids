---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Inertia render is not UI verification
Returning Inertia::render() only proves the server sent props. After changing a page or its props, keep the Feature assertInertia() contract and add a visit() check when the user-visible output or client interaction changed.
