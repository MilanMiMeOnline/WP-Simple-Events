<?php
/**
 * Server-signed recurrence-disable confirmations.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Proves one exact survivor passed the destructive server preview.
 */
final readonly class RecurrenceDisableConfirmation {
	/**
	 * Create the confirmation service.
	 *
	 * @param RecurrenceAggregateRevision $revisions Canonical revision validator.
	 */
	public function __construct(
		private RecurrenceAggregateRevision $revisions = new RecurrenceAggregateRevision()
	) {}

	/**
	 * Sign one exact destructive conversion context.
	 *
	 * @param int                        $event_id Event post ID.
	 * @param int                        $user_id Current authenticated user ID.
	 * @param string                     $revision Exact canonical recurrence revision.
	 * @param string                     $target Selected surviving recurrence identity.
	 * @param RecurrenceGenerationWindow $window Exact bounded preview window.
	 * @param string                     $secret Site-owned signing secret.
	 * @throws InvalidArgumentException When signing inputs are not canonical or bounded.
	 */
	public function issue(
		int $event_id,
		int $user_id,
		string $revision,
		string $target,
		RecurrenceGenerationWindow $window,
		string $secret
	): string {
		if ( $event_id <= 0
			|| $user_id <= 0
			|| ! $this->revisions->valid( $revision )
			|| 'one-off' === $target
			|| ! OccurrenceIdentity::valid_recurrence_id( $target )
			|| strlen( $secret ) < 32
		) {
			throw new InvalidArgumentException( 'A recurrence-disable confirmation requires canonical signing context.' );
		}

		$payload = implode(
			"\0",
			array(
				'mime-simple-events-calendar:recurrence-disable:v1',
				(string) $event_id,
				(string) $user_id,
				$revision,
				$target,
				$window->from_date(),
				$window->through_date(),
				(string) $window->max_rows(),
			)
		);

		return hash_hmac( 'sha256', $payload, $secret );
	}

	/**
	 * Verify one untrusted confirmation in constant time.
	 *
	 * @param mixed                      $confirmation Candidate token.
	 * @param int                        $event_id Event post ID.
	 * @param int                        $user_id Current authenticated user ID.
	 * @param string                     $revision Exact canonical recurrence revision.
	 * @param string                     $target Selected surviving recurrence identity.
	 * @param RecurrenceGenerationWindow $window Exact bounded preview window.
	 * @param string                     $secret Site-owned signing secret.
	 */
	public function valid(
		mixed $confirmation,
		int $event_id,
		int $user_id,
		string $revision,
		string $target,
		RecurrenceGenerationWindow $window,
		string $secret
	): bool {
		if ( ! is_string( $confirmation ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $confirmation ) ) {
			return false;
		}

		try {
			$expected = $this->issue( $event_id, $user_id, $revision, $target, $window, $secret );
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return hash_equals( $expected, $confirmation );
	}
}
