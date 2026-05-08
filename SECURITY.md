# Security Policy

## Supported Versions

`ci4-api-core` is a pre-1.0 package. Only the latest minor receives security
fixes; prior minors are not back-ported.

| Version | Status              |
| ------- | ------------------- |
| `0.2.x` | ✅ Supported (HEAD) |
| `0.1.x` | ❌ Not supported    |

Pre-1.0 releases follow a relaxed SemVer: `MINOR` bumps may break the public
contract. Pin to an exact tag (`0.2.0`) if you need stability.

## Reporting a Vulnerability

**Do not open a public GitHub issue for security-sensitive reports.**

Email the maintainer directly:

- **Contact:** `code.dcl@gmail.com`
- **Subject prefix:** `[SECURITY] ci4-api-core: <short description>`

Include:

- Affected version (tag or commit SHA).
- Reproduction: minimal CodeIgniter 4 setup, exact command or HTTP request,
  expected vs. observed behavior.
- Impact: what an attacker can read, write, or trigger.
- Optional: a suggested patch.

### What to expect

| Stage              | Target SLA                           |
| ------------------ | ------------------------------------ |
| Acknowledgement    | Within 72 hours                      |
| Triage + severity  | Within 7 days                        |
| Coordinated fix    | Negotiated per severity              |
| Public disclosure  | After patch is published and tagged  |

The maintainer will credit the reporter in the CHANGELOG entry unless the
reporter prefers to remain anonymous.

## Out of scope

The following are not treated as `ci4-api-core` vulnerabilities — file with the
upstream project instead:

- Bugs in `codeigniter4/framework`, `monolog/monolog`, `zircote/swagger-php`,
  or any other dependency. Run `composer audit` to surface those advisories.
- Misconfiguration in **consumer applications** (missing `JWT_SECRET_KEY`,
  permissive CORS in `Config\Cors`, etc.). The package documents the
  contract; the consumer is responsible for their bootstrap.
- Issues that require a malicious `composer.json` author (supply-chain attack
  is the consumer's responsibility to mitigate via `composer audit` and pinned
  lockfiles).

If you are unsure whether something is in scope, email anyway — the maintainer
will redirect you.
