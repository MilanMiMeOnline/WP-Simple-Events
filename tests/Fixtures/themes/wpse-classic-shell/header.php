<?php
/**
 * Classic smoke-test header.
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header id="wpse-test-classic-header">Classic theme header</header>
