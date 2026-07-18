<?php
/**
 * Native classic-theme event-archive fallback.
 *
 * Copy to wp-simple-events/archive-wpse_event.php in a theme to override.
 *
 * @package MiMe\WPSimpleEvents
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="wpse-template wpse-template-archive">
	<?php do_action( 'wpse_render_archive_template' ); ?>
</main>
<?php
get_footer();
