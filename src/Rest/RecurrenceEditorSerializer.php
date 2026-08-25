<?php
/**
 * Recurrence editor REST response serialization.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Rest;

use MiMe\WPSimpleEvents\Application\RecurrenceDisablePreview;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorContext;
use MiMe\WPSimpleEvents\Application\RecurrenceOccurrenceEditContext;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorPreview;
use MiMe\WPSimpleEvents\Application\RecurrenceFollowingEditorPreview;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\EventOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactChange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactItem;

/**
 * Converts validated domain values into bounded scalar response shapes.
 */
final readonly class RecurrenceEditorSerializer {
	/**
	 * Create the serializer.
	 *
	 * @param RecurrenceAggregateCodec $codec Exact canonical aggregate codec.
	 */
	public function __construct( private RecurrenceAggregateCodec $codec = new RecurrenceAggregateCodec() ) {}

	/**
	 * Serialize one current editor context.
	 *
	 * @param RecurrenceEditorContext $context Authorized editor context.
	 * @return array<string, mixed>
	 */
	public function context( RecurrenceEditorContext $context ): array {
		return array(
			'recurring' => $context->recurring,
			'revision'  => $context->revision,
			'status'    => $context->status->value,
			'aggregate' => $this->codec->encode( $context->aggregate ),
		);
	}

	/**
	 * Serialize one complete impact preview and confirmation.
	 *
	 * @param RecurrenceEditorPreview $preview Authorized bounded preview.
	 * @return array<string, mixed>
	 */
	public function preview( RecurrenceEditorPreview $preview ): array {
		$impact = $preview->impact;

		return array(
			'context'      => $this->context( $preview->context ),
			'confirmation' => $preview->confirmation,
			'impact'       => array(
				'scope'              => $impact->scope->value,
				'target'             => $impact->target,
				'added'              => $impact->count( RecurrenceImpactChange::ADDED ),
				'removed'            => $impact->count( RecurrenceImpactChange::REMOVED ),
				'moved'              => $impact->count( RecurrenceImpactChange::MOVED ),
				'status_changed'     => $impact->count( RecurrenceImpactChange::STATUS_CHANGED ),
				'source_changed'     => $impact->count( RecurrenceImpactChange::SOURCE_CHANGED ),
				'exception_affected' => $impact->exception_affected_count(),
				'items'              => array_map( array( $this, 'impact_item' ), $impact->items ),
			),
		);
	}

	/**
	 * Serialize a server-built following proposal with its signed impact.
	 *
	 * @param RecurrenceFollowingEditorPreview $preview Authorized following preview.
	 * @return array<string, mixed>
	 */
	public function following_preview( RecurrenceFollowingEditorPreview $preview ): array {
		$data             = $this->preview(
			new RecurrenceEditorPreview(
				$preview->context,
				$preview->impact,
				$preview->confirmation
			)
		);
		$data['proposal'] = $this->codec->encode( $preview->proposal );

		return $data;
	}

	/**
	 * Serialize bounded effective survivor choices.
	 *
	 * @param array $occurrences Effective recurring occurrences.
	 * @phpstan-param list<EventOccurrence> $occurrences
	 * @return list<array<string, mixed>>
	 */
	public function occurrences( array $occurrences ): array {
		return array_map( array( $this, 'identified_occurrence' ), $occurrences );
	}

	/**
	 * Serialize server-resolved occurrence edit state without losing sparse fields.
	 *
	 * @param RecurrenceOccurrenceEditContext $edit_context Authorized target context.
	 * @return array<string, mixed>
	 */
	public function occurrence_context( RecurrenceOccurrenceEditContext $edit_context ): array {
		return array(
			'context'          => $this->context( $edit_context->context ),
			'window'           => array(
				'from_date'    => $edit_context->window->from_date(),
				'through_date' => $edit_context->window->through_date(),
				'max_rows'     => $edit_context->window->max_rows(),
			),
			'target'           => $edit_context->current->identity->recurrence_id(),
			'current'          => $this->identified_occurrence( $edit_context->current ),
			'inherited'        => $this->identified_occurrence( $edit_context->inherited ),
			'inherited_fields' => array(
				'title'             => $edit_context->inherited_fields->title,
				'note'              => $edit_context->inherited_fields->note,
				'featured_image_id' => $edit_context->inherited_fields->featured_image_id,
				'venue'             => $edit_context->inherited_fields->venue,
				'address'           => $edit_context->inherited_fields->address,
				'location_url'      => $edit_context->inherited_fields->location_url,
				'event_url'         => $edit_context->inherited_fields->event_url,
				'event_url_label'   => $edit_context->inherited_fields->event_url_label,
			),
			'override_fields'  => $this->override_fields( $edit_context->override ),
			'exclusion_action' => $edit_context->exclusion_action?->value,
		);
	}

	/**
	 * Serialize one destructive recurrence-disable preview.
	 *
	 * The bounded removal list is intentionally accompanied by an explicit flag
	 * that the complete series outside this window is removed as well.
	 *
	 * @param RecurrenceDisablePreview $preview Authorized destructive preview.
	 * @return array<string, mixed>
	 */
	public function disable_preview( RecurrenceDisablePreview $preview ): array {
		return array(
			'context'      => $this->context( $preview->context ),
			'confirmation' => $preview->confirmation,
			'survivor'     => $this->identified_occurrence( $preview->survivor ),
			'impact'       => array(
				'scope'                  => 'complete_series',
				'target'                 => $preview->survivor->identity->recurrence_id(),
				'added'                  => 0,
				'removed'                => count( $preview->removed ),
				'moved'                  => 0,
				'status_changed'         => 0,
				'source_changed'         => 1,
				'exception_affected'     => $preview->exception_affected,
				'outside_window_removed' => true,
				'items'                  => array_map( array( $this, 'removed_occurrence' ), $preview->removed ),
			),
		);
	}

	/**
	 * Serialize one changed occurrence identity.
	 *
	 * @param RecurrenceImpactItem $item Validated impact item.
	 * @return array<string, mixed>
	 */
	private function impact_item( RecurrenceImpactItem $item ): array {
		return array(
			'recurrence_id'      => $item->recurrence_id,
			'public_key'         => $item->public_key,
			'changes'            => array_map(
				static fn ( RecurrenceImpactChange $change ): string => $change->value,
				$item->changes
			),
			'exception_affected' => $item->exception_affected,
			'before'             => $this->occurrence( $item->before ),
			'after'              => $this->occurrence( $item->after ),
		);
	}

	/**
	 * Serialize one optional effective occurrence.
	 *
	 * @param EventOccurrence|null $occurrence Effective occurrence or absent side.
	 * @return array<string, mixed>|null
	 */
	private function occurrence( ?EventOccurrence $occurrence ): ?array {
		if ( null === $occurrence ) {
			return null;
		}

		return array(
			'start_local' => $occurrence->date_range->start_local(),
			'end_local'   => $occurrence->date_range->end_local(),
			'all_day'     => $occurrence->date_range->all_day(),
			'timezone'    => $occurrence->date_range->timezone(),
			'status'      => $occurrence->status->value,
			'source'      => $occurrence->source->value,
		);
	}

	/**
	 * Serialize one effective occurrence with its immutable identity.
	 *
	 * @param EventOccurrence $occurrence Effective occurrence.
	 * @return array<string, mixed>
	 */
	private function identified_occurrence( EventOccurrence $occurrence ): array {
		return array(
			'recurrence_id' => $occurrence->identity->recurrence_id(),
			'public_key'    => $occurrence->identity->public_key(),
		) + ( $this->occurrence( $occurrence ) ?? array() );
	}

	/**
	 * Serialize one optional sparse override through exact scalar field shapes.
	 *
	 * @param OccurrenceOverride|null $override Validated sparse target override.
	 * @return array<string, mixed>|null
	 */
	private function override_fields( ?OccurrenceOverride $override ): ?array {
		if ( null === $override ) {
			return null;
		}

		$serialized = array();

		foreach ( $override->fields() as $field => $value ) {
			$serialized[ $field ] = match ( true ) {
				$value instanceof EventDateRange => array(
					'start_local' => $value->start_local(),
					'end_local'   => $value->end_local(),
					'all_day'     => $value->all_day(),
				),
				$value instanceof EventStatus => $value->value,
				default                       => $value,
			};
		}

		return $serialized;
	}

	/**
	 * Serialize one bounded occurrence removed by disabling recurrence.
	 *
	 * @param EventOccurrence $occurrence Effective occurrence.
	 * @return array<string, mixed>
	 */
	private function removed_occurrence( EventOccurrence $occurrence ): array {
		return array(
			'recurrence_id'      => $occurrence->identity->recurrence_id(),
			'public_key'         => $occurrence->identity->public_key(),
			'changes'            => array( 'removed' ),
			'exception_affected' => false,
			'before'             => $this->occurrence( $occurrence ),
			'after'              => null,
		);
	}
}
