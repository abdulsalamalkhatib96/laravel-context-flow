# Contributing

1. Create a focused branch.
2. Add or update tests for behavior changes.
3. Run `composer test`.
4. Keep Laravel Context as the source of truth; do not introduce a second global context store.
5. Do not add network propagation of unregistered application metadata.
6. Avoid mandatory observability/vendor SDK dependencies in the core package.

Changes affecting queue dehydration, trust boundaries, signing, or long-running workers should include regression tests for leakage and failure behavior.
