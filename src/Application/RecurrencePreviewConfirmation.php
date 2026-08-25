<?php
/**
 * Server-signed recurrence preview confirmations.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;

/**
 * Proves that one exact proposed mutation passed server-side impact preview.
 */
final readonly class RecurrencePreviewConfirmation {
	/**
	 * Create the confirmation service.
	 *
	 * @param RecurrenceAggregateRevision $revisions Canonical aggregate revisions.
	 */
	public function __construct(
		private RecurrenceAggregateRevision $revisions = new RecurrenceAggregateRevision()
	) {}

	/**
	 * Sign one exact preview context.
	 *
	 * @param int                        $event_id         Canonical event post ID.
	 * @param int                        $user_id          Current authenticated user ID.
	 * @param string                     $current_revision Revision used as preview source.
	 * @param RecurrenceAggregate        $proposed         Complete proposed aggregate.
	 * @param RecurrenceEditScope        $scope            Explicit edit scope.
	 * @param string|null                $target           Optional occurrence identity.
	 * @param RecurrenceGenerationWindow $window           Exact bounded preview window.
	 * @param string                     $secret           Site-owned signing secret.
	 * @throws InvalidArgumentException When signing inputs are not canonical or bounded.
	 */
	public function issue(
		int $event_id,
		int $user_id,
		string $current_revision,
		RecurrenceAggregate $proposed,
		RecurrenceEditScope $scope,
		?string $target,
		RecurrenceGenerationWindow $window,
		string $secret
	): string {
		if ( $event_id <= 0 || $user_id <= 0 || ! $this->revisions->valid( $current_revision ) || strlen( $secret ) < 32 ) {
			throw new InvalidArgumentException( 'A recurrence preview confirmation requires canonical signing context.' );
		}

		$payload = implode(
			"\0",
			array(
				'mime-simple-events-calendar:recurrence-preview:v1',
				(string) $event_id,
				(string) $user_id,
				$current_revision,
				$this->revisions->token( $proposed ),
				$scope->value,
				$target ?? '',
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
	 * @param mixed                      $confirmation    Candidate token.
	 * @param int                        $event_id         Canonical event post ID.
	 * @param int                        $user_id          Current authenticated user ID.
	 * @param string                     $current_revision Revision used as preview source.
	 * @param RecurrenceAggregate        $proposed         Complete proposed aggregate.
	 * @param RecurrenceEditScope        $scope            Explicit edit scope.
	 * @param string|null                $target           Optional occurrence identity.
	 * @param RecurrenceGenerationWindow $window           Exact bounded preview window.
	 * @param string                     $secret           Site-owned signing secret.
	 */
	public function valid(
		mixed $confirmation,
		int $event_id,
		int $user_id,
		string $current_revision,
		RecurrenceAggregate $proposed,
		RecurrenceEditScope $scope,
		?string $target,
		RecurrenceGenerationWindow $window,
		string $secret
	): bool {
		if ( ! is_string( $confirmation ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $confirmation ) ) {
			return false;
		}

		try {
			$expected = $this->issue(
				$event_id,
				$user_id,
				$current_revision,
				$proposed,
				$scope,
				$target,
				$window,
				$secret
			);
		} catch ( InvalidArgumentException ) {
			return false;
		}

		return hash_equals( $expected, $confirmation );
	}
}
