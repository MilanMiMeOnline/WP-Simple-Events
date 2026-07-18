<?php

declare(strict_types=1);

namespace Elementor;

abstract class Widget_Base {
	public function __construct( mixed $data = array(), mixed $args = null ) {}

	/** @return array<string, mixed>|mixed */
	public function get_settings_for_display( ?string $setting_key = null ): mixed {}

	/** @param array<string, mixed> $args */
	public function start_controls_section( string $section_id, array $args = array() ): void {}

	public function end_controls_section(): void {}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $options
	 */
	public function add_control( string $id, array $args, array $options = array() ): void {}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $options
	 */
	public function add_responsive_control( string $id, array $args, array $options = array() ): void {}

	/**
	 * @param array<string, mixed> $args
	 * @param array<string, mixed> $options
	 */
	public function add_group_control( string $group_name, array $args = array(), array $options = array() ): void {}

	/** @return string[] */
	public function get_categories(): array {}

	/** @return string[] */
	public function get_style_depends(): array {}

	/** @return string[] */
	public function get_script_depends(): array {}

	public function has_widget_inner_wrapper(): bool {}
}

final class Controls_Manager {
	public const TAB_CONTENT = 'content';
	public const TAB_STYLE   = 'style';
	public const SELECT      = 'select';
	public const SELECT2     = 'select2';
	public const NUMBER      = 'number';
	public const SWITCHER    = 'switcher';
	public const COLOR       = 'color';
	public const SLIDER      = 'slider';
}

final class Group_Control_Typography {
	public static function get_type(): string {}
}

final class Group_Control_Border {
	public static function get_type(): string {}
}

final class Widgets_Manager {
	public function register( Widget_Base $widget ): bool {}
}

final class Elements_Manager {
	/** @param array<string, mixed> $properties */
	public function add_category( string $name, array $properties ): void {}
}

final class Editor {
	public function is_edit_mode(): bool {}
}

final class Plugin {
	public static self $instance;
	public Editor $editor;
}
