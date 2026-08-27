import wordpress from '@wordpress/eslint-plugin';

export default [
  {
    ignores: [ 'assets/dist/**' ],
  },
  ...wordpress.configs[ 'recommended-with-formatting' ],
  {
    files: [
      'assets/src/js/calendar.js',
      'assets/src/js/divi-editor.js',
      'assets/src/js/divi-editor-utils.mjs',
      'assets/src/js/recurrence-editor.js',
      'assets/src/js/recurrence-editor-utils.mjs',
    ],
    // esbuild resolves these package exports during every build. The WordPress
    // preset's TypeScript resolver reports an invalid-interface error for them.
    rules: {
      'import/default': 'off',
      'import/named': 'off',
      'import/no-extraneous-dependencies': 'off',
      'import/no-unresolved': 'off',
    },
  },
  {
    files: [ 'tools/**/*.mjs', 'tests/E2E/**/*.mjs', 'playwright.config.mjs' ],
    languageOptions: {
      globals: {
        fetch: 'readonly',
        process: 'readonly',
      },
    },
    // Node validates built-in module specifiers when this smoke-test tool runs.
    // The WordPress preset's TypeScript resolver does not support node: URLs.
    rules: {
      'import/named': 'off',
      'import/no-extraneous-dependencies': 'off',
      'import/no-unresolved': 'off',
    },
  },
];
