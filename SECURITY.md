# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ Yes    |

## Reporting a Vulnerability

If you discover a security vulnerability in `vimatech/laravel-invitation`, **please do not open a public GitHub issue**.

Instead, report it responsibly by emailing: **hello@adelzemzemi.dev**

Please include:
- A description of the vulnerability
- Steps to reproduce
- Potential impact
- Any suggested fix (optional)

You will receive an acknowledgement within **48 hours**, and a fix will be prioritised based on severity.

## Security Design Notes

This package handles invitation tokens. Key security decisions:

- Tokens are generated using `Str::random(64)` (cryptographically random)
- Tokens are **never stored in plain text** — only their hash is persisted
- Default strategy is **HMAC** (deterministic, O(1) lookup, tied to `APP_KEY`)
- Optional **bcrypt** strategy for resistance against database leaks
- Token verification uses **constant-time comparison** to prevent timing attacks
- Public routes are **rate-limited** per IP (configurable, default: 30 req/min)
- Route token parameter is validated via regex (`[a-zA-Z0-9]{64}`) before any DB query
