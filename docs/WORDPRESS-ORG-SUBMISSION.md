# WordPress.org submission plan

This checklist separates source hosting on GitHub from distribution through the WordPress.org Plugin Directory. GitHub remains the public source, issue and development repository. WordPress.org creates a separate SVN repository after manual approval and serves plugin updates from that repository.

## Before submission

- [x] Public plugin name: **Simple Events by MiMe**.
- [x] Requested slug and text domain: `simple-events-by-mime`.
- [x] WordPress.org contributor username: `mimeonline`.
- [x] GPL-2.0-or-later metadata and complete GPLv2 licence text.
- [x] Public source and build instructions available on GitHub.
- [x] Professional `readme.txt` below 10 KB with installation, FAQ, privacy, screenshots and current changelog.
- [x] Runtime has no telemetry, external service, remote asset or visitor-storage dependency.
- [x] Security/privacy audit and password-protected REST regression completed.
- [x] Deterministic production allowlist, checksum and reproducible archive.
- [x] Enable GitHub Private Vulnerability Reporting and publish its direct private-reporting route in `SECURITY.md`.
- [x] Version the security and documentation candidate as 0.2.2.
- [ ] Run the final versioned package through every local gate and the strict GitHub Actions Plugin Check job.
- [ ] Confirm the WordPress.org account email is current and protected with strong two-factor authentication/passkey recovery.
- [x] Prepare and review the final icon, banner and seven screenshots according to `WORDPRESS-ORG-ASSETS.md`.

## Submit for review

1. Sign in as `mimeonline` at https://wordpress.org/plugins/developers/add/.
2. Upload the final installable zip, not the development repository and not the WordPress.org display assets.
3. Verify the proposed slug before submitting. WordPress.org derives it from the plugin name and a reserved slug cannot later be renamed casually.
4. Respond to review feedback from the same WordPress.org account. Never weaken sanitization, escaping, permissions, nonces or test coverage merely to silence a tool.
5. Treat review timing as external: WordPress.org performs a manual review and may request changes before approval.

Official policies:

- Detailed guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Readme format: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Common review issues: https://developer.wordpress.org/plugins/wordpress-org/common-issues/

## After approval

WordPress.org supplies an SVN repository. Use it as a distribution channel:

- `trunk/` contains the current development release contents for WordPress.org.
- `tags/{version}/` contains the immutable released plugin version.
- top-level `assets/` contains icons, banners and screenshots and is not copied into the plugin zip.

Follow the official SVN guide at https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/. Build from the reviewed Git commit, compare the SVN tag contents with the verified staging tree and never edit production files only in SVN without bringing the same change back to Git.

For each release:

1. Synchronize the plugin header, `WPSE_VERSION`, `package.json`, `readme.txt` stable tag and changelog.
2. Run `docs/RELEASE-PROCESS.md` completely.
3. Tag the reviewed Git commit.
4. Copy the exact staged package into WordPress.org `trunk/` and `tags/{version}/`.
5. Update display assets separately only when needed.
6. Complete any WordPress.org release-confirmation email step before expecting the new version to publish.

## Support and disclosure

- Ordinary user support may use WordPress.org support forums after listing and GitHub Issues for reproducible development defects.
- Security reports must follow `SECURITY.md` and must never contain exploit details or private data in public forums/issues.
- The WordPress.org `Contributors` value must remain the exact account name `mimeonline` so attribution and plugin-profile links work correctly.
