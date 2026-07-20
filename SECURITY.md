# Security policy

## Supported versions

Security fixes are applied to the latest released version of Simple Events by MiMe. Until the first stable release, the current development branch is the only supported line.

## Reporting a vulnerability

Report suspected vulnerabilities privately to the maintainer, MiMe. Do not open a public issue containing exploit details, secrets or personal data. Include the affected version, impact, reproduction steps and any proposed mitigation. A public disclosure process can be agreed after a fix is available.

No public security contact address has been defined yet; add one before the first public release.

## Security baseline

The project follows the secure-coding rules in `AGENTS.md`. Security-sensitive behaviour needs negative tests for unauthorised, invalid and forged requests. High or critical dependency findings block a release unless a documented review establishes that the vulnerable path is not reachable.
