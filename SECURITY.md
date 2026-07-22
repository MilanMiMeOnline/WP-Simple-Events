# Security policy

## Supported versions

Security fixes are applied to the latest released version of Simple Events by MiMe. The `main` branch may contain an unreleased fix and is not a substitute for an official package.

| Version | Supported |
| --- | --- |
| Latest release | Yes |
| Older releases | No |

## Reporting a vulnerability

Do not open a public issue containing exploit details, credentials, nonces, private event data or other personal information.

Use [GitHub Private Vulnerability Reporting](https://github.com/MilanMiMeOnline/WP-Simple-Events/security/advisories/new) to send exploit details and supporting evidence privately to the maintainer. Do not include sensitive security information in a public GitHub issue or WordPress.org support topic.

A useful private report includes:

- the affected plugin version;
- the WordPress and PHP versions;
- the required user role or authentication state;
- the security impact and affected data;
- minimal reproduction steps or a proof of concept;
- any proposed mitigation;
- a safe way to coordinate disclosure.

The maintainer will acknowledge a complete private report, assess severity and coordinate a fix before public disclosure. No response-time or bounty guarantee is made.

## Security baseline

The project follows the secure-coding rules in `AGENTS.md`. Privileged browser actions require both capability checks and action-specific nonces. Inputs are validated and sanitized at their boundary, output is escaped for its final context, public queries are bounded, and drafts, private events and protected event details are excluded from public plugin collections.

High or critical dependency findings block a release unless a documented review proves the affected path is not present in the production package. Security-sensitive changes require negative tests for unauthorized, invalid and forged requests.

## Scope notes

The plugin does not operate a hosted service and does not receive site or visitor data. Reports about WordPress core, Elementor, WooCommerce, FullCalendar or hosting infrastructure should be sent to the responsible project unless the issue is caused by this plugin's integration code.
