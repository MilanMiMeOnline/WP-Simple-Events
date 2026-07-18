<?php
/**
 * Tests for safe JSON-LD document rendering.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Seo\StructuredDataDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( StructuredDataDocument::class )]
/**
 * Protects the script element boundary from stored event content.
 */
final class StructuredDataDocumentTest extends TestCase {
	/**
	 * HTML-significant bytes are encoded inside the JSON payload.
	 */
	public function test_renders_json_ld_without_allowing_script_breakout(): void {
		$document = ( new StructuredDataDocument() )->render(
			array(
				'@context'    => 'https://schema.org',
				'@type'       => 'Event',
				'name'        => '</script><script>alert("stored-xss")</script>',
				'startDate'   => '2026-08-01',
				'endDate'     => '2026-08-01',
				'eventStatus' => 'https://schema.org/EventScheduled',
				'url'         => 'https://example.com/event/',
			)
		);

		self::assertStringStartsWith( '<script type="application/ld+json">', $document );
		self::assertStringEndsWith( '</script>', $document );
		self::assertStringNotContainsString( '</script><script>', $document );
		self::assertStringContainsString( '\\u003C/script\\u003E', $document );
	}

	/**
	 * JSON encoding failures produce no partial script element.
	 */
	public function test_returns_empty_output_when_encoding_fails(): void {
		$document = ( new StructuredDataDocument() )->render(
			array( 'invalid' => "\xB1\x31" )
		);

		self::assertSame( '', $document );
	}
}
