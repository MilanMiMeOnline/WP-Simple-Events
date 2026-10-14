import { strict as assert } from 'node:assert';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

const require = createRequire( import.meta.url );
const wpEnvRequire = createRequire(
	new URL( '../node_modules/@wordpress/env/package.json', import.meta.url ),
);

test( 'wp-env writes usable Docker configuration with the supported YAML serializer', async () => {
	const directory = await mkdtemp( join( tmpdir(), 'mime-yaml-compat-' ) );
	try {
		const pluginPath = join( directory, 'plugin with spaces' );
		const environment = ( port ) => ( {
			port,
			phpVersion: '8.2',
			coreSource: null,
			pluginSources: [ { path: pluginPath, basename: 'mime-fixture' } ],
			themeSources: [],
			mappings: {},
		} );
		const config = {
			workDirectoryPath: directory,
			dockerComposeConfigPath: join( directory, 'docker-compose.yml' ),
			xdebug: false,
			spx: false,
			env: {
				development: environment( 8888 ),
				tests: environment( 8889 ),
			},
		};
		const {
			writeDockerFiles,
		} = require( '../node_modules/@wordpress/env/lib/runtime/docker/docker-config.js' );
		await writeDockerFiles( config );
		const yaml = wpEnvRequire( 'js-yaml' );
		const output = yaml.load(
			await readFile( config.dockerComposeConfigPath, 'utf8' ),
		);
		for ( const [ service, port ] of [
			[ 'wordpress', '${WP_ENV_PORT:-8888}:80' ],
			[ 'tests-wordpress', '${WP_ENV_TESTS_PORT:-8889}:80' ],
		] ) {
			assert.deepEqual( output.services[ service ].ports, [ port ] );
			assert.ok(
				output.services[ service ].volumes.includes(
					`${ pluginPath }:/var/www/html/wp-content/plugins/mime-fixture`,
				),
			);
			assert.equal(
				typeof output.services[ service ].environment.APACHE_RUN_USER,
				'string',
			);
		}
		for ( const prefix of [ '', 'Tests-' ] ) {
			assert.match(
				await readFile(
					join( directory, `${ prefix }WordPress.Dockerfile` ),
					'utf8',
				),
				/FROM wordpress:php8\.2/,
			);
			assert.match(
				await readFile(
					join( directory, `${ prefix }CLI.Dockerfile` ),
					'utf8',
				),
				/FROM wordpress:cli-php8\.2/,
			);
		}
	} finally {
		await rm( directory, { recursive: true, force: true } );
	}
} );
