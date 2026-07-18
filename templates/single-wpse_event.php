<?php
/**
 * Native classic-theme single-event fallback.
 *
 * Copy to wp-simple-events/single-wpse_event.php in a theme to override.
 *
 * @package MiMe\WPSimpleEvents
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="wpse-template wpse-template-single">
	<?php do_action( 'wpse_render_single_template' ); ?>
</main>
<?php
get_footer();
