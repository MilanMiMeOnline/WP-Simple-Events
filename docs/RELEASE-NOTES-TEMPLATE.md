# GitHub release notes template

GitHub release notes are public, user-facing product communication. Describe what changed, why it matters and whether an update requires attention. Keep test counts, commit hashes, CI run identifiers, archive checksums and other qualification evidence in the matching QA report.

Remove every instruction and placeholder before publishing.

```markdown
## MiMe Simple Events and Calendar {VERSION}

Version {VERSION} {summarize the release in one plain-language sentence that explains the main user benefit}.

### Highlights

- {Describe a visible capability or improvement and its practical benefit.}
- {Describe another important capability or workflow improvement.}
- {Mention supported editors or integrations only when relevant to this release.}
- {Add up to seven concise highlights; omit marginal implementation details.}

### Safety and compatibility

- {Explain whether existing events, pages, settings or templates remain compatible.}
- {Mention an important privacy, security or data-handling boundary when relevant.}
- Qualified on WordPress {SUPPORTED_VERSIONS} with PHP {SUPPORTED_PHP_VERSIONS}.
- The official WordPress Plugin Check completed with no errors.

{Add a short upgrade note only when the user must take action. Omit this paragraph otherwise.}

See [CHANGELOG.md](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/v{VERSION}/CHANGELOG.md) for the complete technical changelog.
```

## Editorial rules

- Lead with user outcomes instead of architecture or internal terminology.
- Use three to seven highlights and only the safety notes that matter for this release.
- Do not publish test counts, CI job lists, commit hashes, checksums or internal qualification details here.
- Do not claim compatibility or a clean Plugin Check unless the release evidence supports it.
- Keep detailed implementation notes in `CHANGELOG.md` and verification evidence in `docs/QA-REPORT-{VERSION}.md`.
- Use the same title, headings and closing changelog link for every regular release.
- A security release may lead with the security impact and update advice, but must not disclose exploit details before users can update safely.
