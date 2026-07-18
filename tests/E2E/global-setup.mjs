import { startE2EEnvironment } from './support/environment.mjs';

export default async function globalSetup() {
	await startE2EEnvironment();
}
