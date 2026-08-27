<?php

declare(strict_types=1);

namespace ET\Builder\Framework\DependencyManagement\Interfaces;

interface DependencyInterface {
	public function load(): void;
}

namespace ET\Builder\Packages\ModuleLibrary;

final class ModuleRegistration {
	/** @param array<string, mixed> $args */
	public static function register_module( string $metadata_folder, array $args = array() ): ?\WP_Block_Type {}
}

namespace ET\Builder\FrontEnd\BlockParser;

final class BlockParserStore {
	public static function get_parent( string $id, mixed $store_instance ): ?object {}
}

namespace ET\Builder\FrontEnd\Module;

final class Style {
	/** @param array<string, mixed> $args */
	public static function add( array $args ): void {}
}

namespace ET\Builder\Packages\Module;

final class Module {
	/** @param array<string, mixed> $args */
	public static function render( array $args ): string {}
}

namespace ET\Builder\Packages\Module\Layout\Components\ModuleElements;

final class ModuleElements {
	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function style( array $args ): array {}
}

namespace ET\Builder\Packages\Module\Options\Element;

final class ElementComponents {
	/** @param array<string, mixed> $args */
	public static function component( array $args ): string {}
}

final class ElementScriptData {
	/** @param array<string, mixed> $args */
	public static function set( array $args ): void {}
}

namespace ET\Builder\VisualBuilder\Assets;

final class PackageBuildManager {
	/** @param array<string, mixed> $params */
	public static function register_package_build( array $params ): void {}
}
