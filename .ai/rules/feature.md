---
paths:
  - 'tests/Feature/**'
---

# Feature

## Feature tests stay HTTP
Keep Feature tests on $this->get() / assertInertia(). Do not convert them to visit(). Add a tests/Browser test or --agent probe when the change is client-side.
