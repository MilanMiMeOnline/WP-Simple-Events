import { stopE2EEnvironment } from './support/environment.mjs';

export default async function globalTeardown() {
	await stopE2EEnvironment();
}
