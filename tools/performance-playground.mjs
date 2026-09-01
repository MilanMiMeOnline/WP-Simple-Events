import { strict as assert } from 'node:assert';
import {
	measurePerformanceScenario,
	startPerformanceEnvironment,
	stopPerformanceEnvironment,
} from '../tests/Performance/support/environment.mjs';

const repetitions = 5;
const budgets = {
	occurrence_window_filtered: {
		count: 100,
		minimumTotal: 5000,
		maximumQueries: 2,
		maximumBytes: 0,
		medianDurationMs: 750,
	},
	event_list: {
		count: 50,
		minimumTotal: 400,
		maximumQueries: 24,
		maximumBytes: 1024 * 1024,
		medianDurationMs: 7500,
	},
	calendar_feed: {
		count: 100,
		minimumTotal: 400,
		maximumQueries: 24,
		maximumBytes: 256 * 1024,
		medianDurationMs: 7500,
	},
	builder_options: {
		count: 50,
		minimumTotal: 50,
		maximumQueries: 10,
		maximumBytes: 0,
		medianDurationMs: 1000,
	},
	recurrence_engine: {
		count: 551,
		minimumTotal: 551,
		maximumQueries: 0,
		maximumBytes: 0,
		medianDurationMs: 250,
	},
};

/**
 * Return the median from an odd-length numeric sample.
 *
 * @param {number[]} values Numeric sample.
 * @return {number} Median sample value.
 */
function median( values ) {
	const ordered = [ ...values ].sort( ( first, second ) => first - second );
	return ordered[ Math.floor( ordered.length / 2 ) ];
}

/**
 * Measure and enforce one complete scenario budget.
 *
 * @param {string} scenario Scenario identifier.
 * @param {Object} budget   Hard acceptance budget.
 * @return {Promise<Record<string, number|string>>} Observed scenario metrics.
 */
async function qualify( scenario, budget ) {
	await measurePerformanceScenario( scenario );
	const samples = [];

	for ( let index = 0; index < repetitions; index += 1 ) {
		const sample = await measurePerformanceScenario( scenario );

		assert.equal( sample.error, undefined, `${ scenario } returned an error.` );
		samples.push( sample );
	}

	const medianDuration = median(
		samples.map( ( sample ) => Number( sample.duration_ms ) ),
	);
	const maximumQueries = Math.max(
		...samples.map( ( sample ) => Number( sample.queries ) ),
	);
	const maximumBytes = Math.max(
		...samples.map( ( sample ) => Number( sample.bytes ) ),
	);

	for ( const sample of samples ) {
		assert.equal(
			Number( sample.count ),
			budget.count,
			`${ scenario } returned an unexpected bounded result count: ${ JSON.stringify( sample ) }.`,
		);
		assert.ok(
			Number( sample.total ) >= budget.minimumTotal,
			`${ scenario } did not exercise the required matching dataset.`,
		);
	}

	assert.ok(
		maximumQueries <= budget.maximumQueries,
		`${ scenario } used ${ maximumQueries } queries; budget is ${ budget.maximumQueries }.`,
	);
	assert.ok(
		maximumBytes <= budget.maximumBytes,
		`${ scenario } produced ${ maximumBytes } bytes; budget is ${ budget.maximumBytes }.`,
	);
	assert.ok(
		medianDuration <= budget.medianDurationMs,
		`${ scenario } median was ${ medianDuration } ms; budget is ${ budget.medianDurationMs } ms.`,
	);

	return {
		scenario,
		medianMs: medianDuration,
		maxQueries: maximumQueries,
		maxBytes: maximumBytes,
		count: budget.count,
	};
}

let started = false;

try {
	await startPerformanceEnvironment();
	started = true;
	const results = [];

	for ( const [ scenario, budget ] of Object.entries( budgets ) ) {
		results.push( await qualify( scenario, budget ) );
	}

	process.stdout.write( `${ JSON.stringify( results, null, 2 ) }\n` );
	process.stdout.write( 'Performance budgets passed.\n' );
} finally {
	if ( started ) {
		await stopPerformanceEnvironment();
	}
}
