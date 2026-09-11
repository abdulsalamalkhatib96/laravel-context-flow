# Security Policy

## Threat model

Laravel Context Flow treats inbound network metadata as untrusted unless an explicit trust mechanism proves otherwise.

### Rules

- Public baggage is accepted only for keys whose `accept_from` explicitly contains `public`.
- Restricted internal baggage requires a valid HMAC signature when using the built-in inbound trust mechanism.
- The signature is time-bounded to limit replay.
- External HTTP propagation is denied by default.
- Sensitive-looking key names are blocked as a defense in depth measure.
- Context must never be used as the sole authorization source.

## Secrets

Do not place passwords, bearer tokens, API keys, private keys, session IDs, card data or other secrets in Laravel Context.

Laravel hidden context is hidden from logs; it is not a cryptographic secret container and may still participate in queue serialization.

## Signing key

Use a random key of at least 32 bytes and rotate it using normal secret-management procedures. All services that share one key can authenticate context as belonging to that trust domain, so use separate trust domains when cross-service impersonation would be unacceptable.

## Reporting

Please report security issues privately to the package maintainer instead of opening a public issue with exploit details.
