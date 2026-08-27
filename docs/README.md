# Documentation

This directory contains the product, engineering, security and release contracts
for MiMe Simple Events and Calendar.

## Start here

- [Product specification](PRODUCT-SPECIFICATION.md) — normative functional and
  technical scope.
- [Product roadmap](ROADMAP.md) — prioritized future work and the next
  implementation plan.
- [Development standards](DEVELOPMENT-STANDARDS.md) — implementation conventions.
- [Decision log](DECISIONS.md) — intentional architectural and product changes.
- [Contributing](../CONTRIBUTING.md) — local setup, testing and review workflow.

## Architecture and behaviour

- [Data contract](DATA-CONTRACT.md)
- [Recurrence and occurrence contract](RECURRENCE-CONTRACT.md)
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
- [Security reporting policy](../SECURITY.md)
- [Test strategy](TEST-STRATEGY.md)
- [Manual QA checklist](QA-CHECKLIST.md)
- [Hardening gap audit](HARDENING-GAP-AUDIT.md)

`BACKLOG-TESTING.md`, `BACKLOG-EXECUTION-PLAN.md` and versioned or phase-specific
`QA-REPORT-*` documents are retained as historical development and release
evidence. They are not user documentation and are excluded from the installable
plugin package. Current future work belongs in [the roadmap](ROADMAP.md), not in
those completed backlogs.

The current public release evidence is the
[0.5.0 Divi qualification report](QA-REPORT-0.5.0-DIVI-COMPOSITES.md). The
[0.4.0 recurrence foundation report](QA-REPORT-0.4.0-FOUNDATION.md) remains the
normative historical evidence for the recurring-events contract retained by
0.5.0.

## Releases and WordPress.org

- [Release process](RELEASE-PROCESS.md)
- [WordPress.org submission](WORDPRESS-ORG-SUBMISSION.md)
- [WordPress.org visual assets](WORDPRESS-ORG-ASSETS.md)

The `.wordpress-org/` directory contains the prepared icon, banner and screenshot
files for the future WordPress.org SVN `assets/` directory. These files are not
part of the installable plugin zip.
