# Documentation

This directory contains the user guides and the product, engineering, security
and release contracts for MiMe Simple Events and Calendar.

## User documentation

- [User guide](USER-GUIDE.md) — the task-based entry point.
- [Getting started](GETTING-STARTED.md) — installation, WordPress settings, first
  event and first events page.
- [Displaying events](DISPLAYING-EVENTS.md) — archive, list/grid, calendar,
  filters, colors, shortcodes and Add to Calendar.
- [Recurring events](RECURRING-EVENTS.md) — creation, previews and safe edit
  scopes.
- [Builders and templates](BUILDERS-AND-TEMPLATES.md) — Gutenberg, Elementor,
  Divi 5 and native theme ownership.
- [Troubleshooting](TROUBLESHOOTING.md) — common editor, calendar, recurrence,
  filter, timezone and template issues.
- [Privacy, data and updates](PRIVACY-DATA-AND-UPDATES.md) — public data,
  retention, uninstall and supported upgrades.

## Engineering start here

- [Product specification](PRODUCT-SPECIFICATION.md) — normative functional and
  technical scope.
- [Product roadmap](ROADMAP.md) — prioritized future work and the next
  implementation plan.
- [Public 1.x compatibility contract](PUBLIC-COMPATIBILITY-CONTRACT.md) — stable
  identities, extension boundaries and deprecation policy.
- [Development standards](DEVELOPMENT-STANDARDS.md) — implementation conventions.
- [Decision log](DECISIONS.md) — intentional architectural and product changes.
- [Contributing](../CONTRIBUTING.md) — local setup, testing and review workflow.

## Architecture and behaviour

- [Data contract](DATA-CONTRACT.md)
- [Recurrence and occurrence contract](RECURRENCE-CONTRACT.md)
- [Add to Calendar contract](ADD-TO-CALENDAR-CONTRACT.md)
- [Public query contract](PUBLIC-QUERY-CONTRACT.md)
- [Presentation contract](PRESENTATION-CONTRACT.md)
- [Template contract](TEMPLATE-CONTRACT.md)
- [Archive settings contract](ARCHIVE-SETTINGS-CONTRACT.md)
- [Maintenance contract](MAINTENANCE-CONTRACT.md)
- [Uninstall contract](UNINSTALL-CONTRACT.md)
- [Structured data](STRUCTURED-DATA.md)
- [Gutenberg integration](GUTENBERG-INTEGRATION.md)
- [Elementor integration](ELEMENTOR-INTEGRATION.md)
- [Divi 5 integration](DIVI-5-INTEGRATION.md)

## Security, privacy and quality

- [Security and privacy audit](SECURITY-PRIVACY-AUDIT.md)
- [Security permission matrix](SECURITY-PERMISSION-MATRIX.md)
- [Security reporting policy](../SECURITY.md)
- [Test strategy](TEST-STRATEGY.md)
- [Performance budgets](PERFORMANCE-BUDGETS.md)
- [Manual QA checklist](QA-CHECKLIST.md)
- [Hardening gap audit](HARDENING-GAP-AUDIT.md)

`BACKLOG-TESTING.md`, `BACKLOG-EXECUTION-PLAN.md` and versioned or phase-specific
`QA-REPORT-*` documents are retained as historical development and release
evidence. They are not user documentation and are excluded from the installable
plugin package. Current future work belongs in [the roadmap](ROADMAP.md), not in
those completed backlogs.

The current public release evidence is the
[0.7.0 qualification and publication report](QA-REPORT-0.7.0.md); its detailed
editor, provider and interoperability checkpoint is in
[QA-REPORT-0.7.0-WP3-4.md](QA-REPORT-0.7.0-WP3-4.md). The
[0.9.0 RC1 report](QA-REPORT-0.9.0-RC1.md) records the active release-candidate
cycle's completed public compatibility freeze, while the
[0.9.0 RC2 report](QA-REPORT-0.9.0-RC2.md) records clean-install, historical
upgrade and lifecycle qualification. The
[0.9.0 RC3 report](QA-REPORT-0.9.0-RC3.md) records the current security/privacy,
permission and supply-chain re-audit. The
[0.9.0 RC4 report](QA-REPORT-0.9.0-RC4.md) records the WCAG-oriented visitor and
editor accessibility qualification. The
[0.9.0 RC5 report](QA-REPORT-0.9.0-RC5.md) records the bounded dataset,
performance budgets and collection-query optimization. The
[0.9.0 RC6 report](QA-REPORT-0.9.0-RC6.md) records the task-based user
documentation, public readme rewrite and documentation-contract review. The
[0.9.0 RC7 report](QA-REPORT-0.9.0-RC7.md) records destructive local and
non-destructive production exploratory qualification plus the final bounded UX
regressions before publication. The
[0.9.0 RC8 report](QA-REPORT-0.9.0-RC8.md) records the exact versioned package,
complete local and hosted release matrix, official Plugin Check and final
publication authorization. The
[0.6.0 filter and color release report](QA-REPORT-0.6.0.md),
[0.5.0 Divi qualification report](QA-REPORT-0.5.0-DIVI-COMPOSITES.md) and
[0.4.0 recurrence foundation report](QA-REPORT-0.4.0-FOUNDATION.md) remain the
normative historical evidence for the builder and recurring-events contracts
retained by the current release. The post-release UX findings and their qualified
maintenance candidate are documented in the
[0.5.0 exploratory report](QA-REPORT-0.5.0-EXPLORATORY.md) and
[0.5.1 release-candidate report](QA-REPORT-0.5.1.md). The frozen 0.6.0 filter and
color baseline is recorded in the
[FCR-0 QA evidence](QA-REPORT-0.6.0-FCR-0.md); the shared semantic filter and
bounded URL-state implementation is qualified in the
[FCR-2 QA evidence](QA-REPORT-0.6.0-FCR-2.md); and the responsive progressive
interaction is qualified in [FCR-3 QA evidence](QA-REPORT-0.6.0-FCR-3.md).
Builder parity, the color domain and public calendar integration are qualified
in the [FCR-4](QA-REPORT-0.6.0-FCR-4.md),
[FCR-5](QA-REPORT-0.6.0-FCR-5.md) and
[FCR-6](QA-REPORT-0.6.0-FCR-6.md) reports. The accepted 0.7.0 portability scope
is frozen in the [Add to Calendar contract](ADD-TO-CALENDAR-CONTRACT.md).

## Releases and WordPress.org

- [Release process](RELEASE-PROCESS.md)
- [WordPress.org submission](WORDPRESS-ORG-SUBMISSION.md)
- [WordPress.org visual assets](WORDPRESS-ORG-ASSETS.md)

The `.wordpress-org/` directory contains the prepared icon, banner and screenshot
files for the future WordPress.org SVN `assets/` directory. These files are not
part of the installable plugin zip.
