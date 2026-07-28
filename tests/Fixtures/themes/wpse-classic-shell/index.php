<?php
/**
 * Classic smoke-test fallback template.
 *
 * @package MiMe\WPSimpleEvents\Tests\Fixtures
 */

get_header();
?>
<main id="wpse-test-classic-content">
	<?php
	while ( have_posts() ) {
		the_post();
		the_title( '<h1>', '</h1>' );
		the_content();
	}
	?>
</main>
<?php
get_footer();
