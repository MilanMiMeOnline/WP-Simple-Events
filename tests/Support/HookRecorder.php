<?php
/**
 * Records WordPress hook calls made during isolated unit tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

/**
 * Minimal deterministic hook recorder for unit tests.
 */
final class HookRecorder {
	/**
	 * Registered action callbacks keyed by hook name.
	 *
	 * @var array<string, list<callable>>
	 */
	private static array $actions = array();

	/**
	 * Fired action names.
	 *
	 * @var list<string>
	 */
	private static array $fired = array();

	/**
	 * Clear all recorded hooks between tests.
	 */
	public static function reset(): void {
		self::$actions = array();
		self::$fired   = array();
	}

	/**
	 * Record a callback registration.
	 *
	 * @param string   $hook_name Hook name.
	 * @param callable $callback  Registered callback.
	 */
	public static function add_action( string $hook_name, callable $callback ): void {
		self::$actions[ $hook_name ][] = $callback;
	}

	/**
	 * Retrieve a registered callback.
	 *
	 * @param string $hook_name Hook name.
	 * @return callable|null Registered callback, if present.
	 */
	public static function action( string $hook_name ): ?callable {
		return self::$actions[ $hook_name ][0] ?? null;
	}

	/**
	 * Retrieve all callbacks registered for a hook.
	 *
	 * @param string $hook_name Hook name.
	 * @return list<callable> Registered callbacks.
	 */
	public static function actions( string $hook_name ): array {
		return self::$actions[ $hook_name ] ?? array();
	}

	/**
	 * Record a fired action.
	 *
	 * @param string $hook_name Hook name.
	 */
	public static function fire( string $hook_name ): void {
		self::$fired[] = $hook_name;
	}

	/**
	 * Determine whether an action was fired.
	 *
	 * @param string $hook_name Hook name.
	 */
	public static function was_fired( string $hook_name ): bool {
		return in_array( $hook_name, self::$fired, true );
	}
}
