import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const targets = [
	{
		file: 'isomorphic-git-internals-DwkB8rfP.js',
		hash: 'b0bf39a2c26b5a74ed574276627d46f97efb34efefdc4ed9bb98c37e7c194293',
		needle: '      r.push({\n        mode: s,\n        path: h,',
	},
	{
		file: 'isomorphic-git-internals-oTG5Z0SU.cjs',
		hash: '71778a868f49dcd739f20a3bd943d013a07cb03743bf567de4b82da6465d3bfe',
		needle: 'r.push({mode:s,path:h,oid:d,type:Es(s)})',
	},
];

// ADR-112: storage has its own GitTree parser, independent of the npm override.
// Mirror upstream 1.38.6's reserved-name guard in both published module formats.
/*
 * The guard below is adapted from isomorphic-git under the MIT license.
 * Copyright 2017-2023 the 'isomorphic-git' authors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
const guard = String.raw`/* wpse-storage-path-guard */
const wpseCleanPath = h.replace(/[\u200C-\u200F\u202A-\u202E\u206A-\u206F\uFEFF]/g, '');
const wpseNormalizedPath = wpseCleanPath.toLowerCase().replace(/[. ]+$/, '');
if (h.includes('\\') || h.includes('/') || wpseCleanPath === '.' || wpseCleanPath === '..' || wpseNormalizedPath === '.git' || /^\.?git~[1-9]$/.test(wpseNormalizedPath)) {
  throw new Error('Unsafe Git tree entry');
}
/* wpse-storage-path-guard-end */
`;

export function patchStorageSource( source, { hash, needle } ) {
	const original = source.replace( guard, '' );
	if (
		createHash( 'sha256' ).update( original ).digest( 'hex' ) !== hash ||
		original.split( needle ).length !== 2
	) {
		throw new Error( 'Unexpected Playground storage source; review the security patch.' );
	}
	return original.replace( needle, guard + needle );
}

export async function patchStorage( directory ) {
	const manifest = JSON.parse( await readFile( resolve( directory, 'package.json' ), 'utf8' ) );
	if ( manifest.version !== '3.1.44' ) {
		throw new Error( 'Review the Git tree guard for this Playground storage version.' );
	}
	const changes = [];
	for ( const target of targets ) {
		const path = resolve( directory, target.file );
		changes.push( [ path, patchStorageSource( await readFile( path, 'utf8' ), target ) ] );
	}
	for ( const [ path, source ] of changes ) {
		await writeFile( path, source );
	}
}

if ( process.argv[ 1 ] && resolve( process.argv[ 1 ] ) === fileURLToPath( import.meta.url ) ) {
	await patchStorage( fileURLToPath( new URL( '../node_modules/@wp-playground/storage', import.meta.url ) ) );
}
