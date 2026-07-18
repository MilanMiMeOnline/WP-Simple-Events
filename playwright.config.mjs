import { defineConfig } from '@playwright/test';

export default defineConfig( {
	testDir: './tests/E2E',
	testMatch: '**/*.spec.mjs',
	fullyParallel: false,
	workers: 1,
	forbidOnly: Boolean( process.env.CI ),
	retries: 0,
	timeout: 30_000,
	globalTimeout: 180_000,
	globalSetup: './tests/E2E/global-setup.mjs',
	globalTeardown: './tests/E2E/global-teardown.mjs',
	reporter: [ [ 'line' ] ],
	use: {
		baseURL: 'http://localhost:8888',
		viewport: { width: 1280, height: 900 },
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
	},
} );
