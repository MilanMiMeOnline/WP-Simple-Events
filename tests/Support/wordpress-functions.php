<?php
/**
 * Namespaced WordPress function doubles for isolated unit tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents;

use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;

/**
 * Test double for WordPress add_action().
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Registered callback.
 * @param int      $priority      Hook priority.
 * @param int      $accepted_args Number of accepted arguments.
 */
function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature mirrors WordPress.
	HookRecorder::add_action( $hook_name, $callback );

	return true;
}

/**
 * Test double for WordPress do_action().
 *
 * @param string $hook_name Hook name.
 * @param mixed  ...$args   Action arguments.
 */
function do_action( string $hook_name, mixed ...$args ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Signature mirrors WordPress.
	HookRecorder::fire( $hook_name );
}
