# Senior QA checklist

Use the applicable items before every handoff. Record anything not tested and why.

## Requirement and regression

- [ ] Behaviour matches the written requirement and agreed non-goals.
- [ ] Acceptance paths and failure paths are covered.
- [ ] Existing blog, WooCommerce and theme behaviour remains unaffected.
- [ ] Tests would fail if the new behaviour broke.

## Security and privacy

- [ ] Capabilities are checked server-side.
- [ ] State-changing browser requests use and verify a nonce.
- [ ] Input is validated, normalized and sanitized at the boundary.
- [ ] Output is escaped for its exact context.
- [ ] Public queries expose only intended event statuses and fields.
- [ ] Queries are bounded; no secret or personal data is logged.
- [ ] Dependency audit has no unreviewed high/critical finding.

## Event correctness

- [ ] Site time zone and DST boundaries are correct.
- [ ] All-day, timed, same-day and multi-day cases are correct.
- [ ] Upcoming, ongoing and past classification and ordering are correct.
- [ ] Invalid start/end combinations produce a useful error.

## Experience and compatibility

- [ ] Keyboard use, focus, labels and semantic structure are sound.
- [ ] Empty, loading and error states are understandable.
- [ ] Narrow and wide layouts work with theme-inherited styling.
- [ ] User-facing strings are translatable and contain no hard-coded locale.
- [ ] Behaviour without Elementor and WooCommerce is correct.
- [ ] Elementor widgets register and render on the supported 3.x and current tested 4.x versions.
- [ ] Multiple shortcode and Elementor instances do not duplicate DOM IDs or request namespaces.

## Delivery evidence

- [ ] Relevant PHP, JS and CSS gates pass.
- [ ] Relevant unit, integration and end-to-end tests pass.
- [ ] Public versions match and the translation catalogue is current.
- [ ] The release archive passes content, checksum, PHP, autoloader and reproducibility verification.
- [ ] The packaged plugin passes the supported WordPress smoke matrix.
- [ ] Official WordPress Plugin Check passes against the package staging directory.
- [ ] Documentation and changelog are current.
- [ ] Remaining risks and untested cases are stated in the handoff.
