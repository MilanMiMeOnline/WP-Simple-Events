<?php
/**
 * Hybrid theme single-event override fixture.
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

get_header();
?>
<main id="wpse-test-php-single-override">
	<?php do_action( 'wpse_render_single_template' ); ?>
</main>
<?php
get_footer();
