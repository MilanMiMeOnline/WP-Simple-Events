<?php
/**
 * Minimal Elementor widget base double.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace Elementor; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedNamespaceFound -- The test double must mirror Elementor's public namespace.

/**
 * Supplies the control and settings surface used by widget tests.
 */
abstract class Widget_Base {
	/**
	 * Test display settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $wpse_test_settings = array();

	/**
	 * Recorded group controls for registration assertions.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $wpse_test_group_controls = array();

	/**
	 * Mirror Elementor's untyped constructor for extension compatibility.
	 *
	 * @param mixed $data Widget data.
	 * @param mixed $args Widget arguments.
	 */
	public function __construct( $data = array(), $args = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Exact external signature.
	}

	/**
	 * Return all settings or one requested setting.
	 *
	 * @param string|null $setting_key Optional setting key.
	 * @return array<string, mixed>|mixed
	 */
	public function get_settings_for_display( ?string $setting_key = null ): mixed {
		return null === $setting_key ? $this->wpse_test_settings : ( $this->wpse_test_settings[ $setting_key ] ?? null );
	}

	/**
	 * Store test-only display settings.
	 *
	 * @param array<string, mixed> $settings Test-only display settings.
	 */
	final public function wpse_set_test_settings( array $settings ): void {
		$this->wpse_test_settings = $settings;
	}

	/**
	 * Return group controls recorded during a registration test.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	final public function wpse_test_group_controls(): array {
		return $this->wpse_test_group_controls;
	}

	/**
	 * Accept a control section.
	 *
	 * @param string               $section_id Section identifier.
	 * @param array<string, mixed> $args       Section arguments.
	 */
	public function start_controls_section( string $section_id, array $args = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test recorder is added only when needed.
	}

	/** End a control section. */
	public function end_controls_section(): void {
	}

	/**
	 * Accept a regular control.
	 *
	 * @param string               $id      Control identifier.
	 * @param array<string, mixed> $args    Control arguments.
	 * @param array<string, mixed> $options Registration options.
	 */
	public function add_control( string $id, array $args, array $options = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test recorder is added only when needed.
	}

	/**
	 * Accept a responsive control.
	 *
	 * @param string               $id      Control identifier.
	 * @param array<string, mixed> $args    Control arguments.
	 * @param array<string, mixed> $options Registration options.
	 */
	public function add_responsive_control( string $id, array $args, array $options = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test recorder is added only when needed.
	}

	/**
	 * Accept an Elementor group control.
	 *
	 * @param string               $group_name Group control name.
	 * @param array<string, mixed> $args       Group arguments.
	 * @param array<string, mixed> $options    Registration options.
	 */
	public function add_group_control( string $group_name, array $args = array(), array $options = array() ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Test double mirrors Elementor's complete signature.
		$name = $args['name'] ?? '';

		if ( is_string( $name ) && '' !== $name ) {
			$this->wpse_test_group_controls[ $name ] = array(
				'group_name' => $group_name,
				'args'       => $args,
			);
		}
	}
}
