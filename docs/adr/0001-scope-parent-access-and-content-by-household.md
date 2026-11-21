# Scope parent access and content by household

The first release is private to one household. Use the existing Laravel Team as the household boundary, give parents household-scoped access in Filament, and let them manage separate child profiles without requiring each child to have an email address. Parent-created content stays private unless a platform admin approves it for the shared catalog, so one family can adapt material without changing what other families see.

A parent selects a child profile on a shared device. Switching profiles or returning to Filament requires fresh parent authentication. Editing a shared item creates a private copy that the parent can use in place of the original within their household. Withdrawing the private copy keeps the original out of the household's pool until the parent explicitly restores it.
