# Changelog

## 1.0.0 - 2026-09-11

- Initial release.
- Laravel Context remains the source of truth.
- Automatic HTTP request correlation and response IDs.
- Trust-aware outgoing HTTP propagation.
- Native Laravel queue dehydration filtering and execution rotation.
- Console and scheduler root contexts.
- Signed internal context support.
- W3C-style baggage transport for allowlisted application metadata.
- Sensitive-key filtering, size limits, inspection and doctor commands.
- Long-running worker-safe design with no request state stored in package singletons.
