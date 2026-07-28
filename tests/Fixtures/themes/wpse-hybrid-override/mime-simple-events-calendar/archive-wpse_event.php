<?php
/**
 * Hybrid theme event-archive override fixture.
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

get_header();
?>
<main id="wpse-test-php-archive-override">
	<?php do_action( 'wpse_render_archive_template' ); ?>
</main>
<?php
get_footer();
