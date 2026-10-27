import { strict as assert } from 'node:assert';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { test } from 'node:test';

const require = createRequire( import.meta.url );
const ajvRequire = createRequire( require.resolve( 'ajv' ) );
const uri = ajvRequire( 'fast-uri' );
const expressRequire = createRequire( require.resolve( 'express' ) );

// GHSA-w74x-v6f9-j3xg: encoded schemes must not become executable URLs.
test( 'URI normalization never activates percent-encoded schemes', () => {
	for ( const value of [ '%6Aavascript:alert(1)', '%u006Aavascript:alert(1)' ] ) {
		assert.equal( uri.normalize( value ), value );
		assert.match( uri.parse( value ).error, /scheme.*malformed/i );
		assert.throws(
			() => uri.resolve( 'https://example.test/', value ),
			/scheme.*malformed/i,
		);
	}
	assert.equal(
		uri.normalize( 'https://example.test/events' ),
		'https://example.test/events',
	);
} );

test( 'gzip trailing data cannot terminate the development HTTP server', () => {
	execFileSync(
		process.execPath,
		[
			'--input-type=commonjs',
			'-e',
			`
		const assert = require('node:assert/strict');
		const http = require('node:http');
		const {gzipSync} = require('node:zlib');
		const parser = require(process.argv[1]).json();
		const payload = Buffer.concat([
			gzipSync('{"ok":true}'), Buffer.from([0]), Buffer.alloc(100 * 1024, 88)
		]);
		const server = http.createServer((request, response) => {
			parser(request, response, error => {
				assert.ifError(error);
				response.end(JSON.stringify(request.body));
			});
		});
		server.listen(0, '127.0.0.1', () => {
			const client = http.request({
				host: '127.0.0.1', port: server.address().port, method: 'POST', agent: false,
				headers: {'Content-Type': 'application/json', 'Content-Encoding': 'gzip',
					'Content-Length': payload.length}
			}, response => {
				let result = '';
				response.setEncoding('utf8');
				response.on('data', chunk => { result += chunk; });
				response.on('end', () => {
					assert.equal(response.statusCode, 200);
					assert.deepEqual(JSON.parse(result), {ok: true});
					setTimeout(() => server.close(), 200);
				});
			});
			client.on('error', error => { throw error; });
			client.setTimeout(2000, () => client.destroy(new Error('Request did not finish')));
			client.end(payload);
		});
	`,
			expressRequire.resolve( 'body-parser' ),
		],
		{ timeout: 5000, stdio: 'pipe' },
	);
} );

// GHSA-27r3-jxmh-p828: send the tail only after decompression completes.
// A bounded child isolates a regressed uncaught decoder error or stalled body.
test( 'compressed request completion drains trailing bytes without losing other consumers', () => {
	execFileSync(
		process.execPath,
		[
			'--input-type=commonjs',
			'-e',
			`
		const assert = require('node:assert/strict');
		const http = require('node:http');
		const {createHash} = require('node:crypto');
		const {deflateSync} = require('node:zlib');
		const parser = require(process.argv[1]).json();
		const first = Buffer.concat([deflateSync('{"ok":true}'), Buffer.from([0])]);
		const tail = Buffer.alloc(1024);
		const expected = createHash('sha256').update(first).update(tail).digest('hex');
		let client;
		const server = http.createServer((request, response) => {
			const hash = createHash('sha256');
			request.pipe(hash);
			parser(request, response, (error) => {
				assert.ifError(error);
				assert.deepEqual(request.body, {ok: true});
				hash.once('finish', () => {
					assert.equal(hash.read().toString('hex'), expected);
					response.end('complete');
				});
				client.end(tail);
			});
		});
		server.listen(0, '127.0.0.1', () => {
			client = http.request({
				host: '127.0.0.1', port: server.address().port, method: 'POST', agent: false,
				headers: {'Content-Type': 'application/json', 'Content-Encoding': 'deflate',
					'Content-Length': first.length + tail.length}
			}, response => {
				let result = '';
				response.setEncoding('utf8');
				response.on('data', chunk => { result += chunk; });
				response.on('end', () => {
					assert.equal(response.statusCode, 200);
					assert.equal(result, 'complete');
					server.close();
				});
			});
			client.on('error', error => { throw error; });
			client.setTimeout(2000, () => client.destroy(new Error('Request did not finish')));
			client.write(first);
		});
	`,
			expressRequire.resolve( 'body-parser' ),
		],
		{ timeout: 5000, stdio: 'pipe' },
	);
} );
