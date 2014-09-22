<?php
/*
Plugin Name:       Custom Code for medonline.at
Plugin URI:        https://github.com/medizinmedien/cc-medonline-at
Description:       A plugin to provide functionality specific for medONLINE.
Version:           0.6
Author:            Frank St&uuml;rzebecher
GitHub Plugin URI: https://github.com/medizinmedien/cc-medonline-at
*/

if ( ! defined( 'ABSPATH' ) )
	exit;

/**
 * Embed Groove code into page footers except certain public pages
 * to avoid anonymous support requests.
 */
function cc_medonline_add_groove() {
	if( ! function_exists( 'is_medonline_public_page' ) ) {
		error_log( "\nfunction_exists( 'is_medonline_public_page' ) returns FALSE !! in plugin 'cc-medonline-at'\n" );
		return;
	}

	// No support offers on public pages.
	if ( is_medonline_public_page()
	|| ( defined( 'DOING_CRON'    ) && DOING_CRON )
	|| ( defined( 'XMLRPC_REQUEST') && XMLRPC_REQUEST )
	|| ( defined( 'DOING_AUTOSAVE') && DOING_AUTOSAVE )
	|| ( defined( 'DOING_AJAX'    ) && DOING_AJAX) )
		return;

	$groove_include = WP_PLUGIN_DIR . '/Shared-Includes/inc/groove/groove-help-widget.php';
	if( file_exists( $groove_include ) )
		include( $groove_include );

}
add_action(  'wp_footer', 'cc_medonline_add_groove' );
add_action( 'login_head', 'cc_medonline_add_groove' );

/**
 * Make a https link for jnewsticker CSS in header of the WP signup page.
 *
 * This is, because the jnewsticker plugin uses cached URL's from the wp_options
 * table for style enqueueing what has no chance to react on actual pages' URL scheme.
 * We need https for CSS header links on https://medonline.at/wp-signup.php to avoid 
 * certificate warnings.
 */
function cc_medonline_set_jnews_css_url_to_https() {
	wp_dequeue_style( 'jnewsticker_css' );
	$jnews_settings = get_option('jnewsticker');
	$jnews_css = str_replace( 'http://', 'https://', $jnews_settings['skin'] );
	wp_register_style( 'cc_medonline_jnewsticker_css_to_https', $jnews_css );
	wp_enqueue_style('cc_medonline_jnewsticker_css_to_https');
}
if( $_SERVER['SERVER_PORT'] == 443 && class_exists( 'jNewsticker_Bootstrap' ) )
	add_action( 'wp_enqueue_scripts', 'cc_medonline_set_jnews_css_url_to_https' );

/**
 * Track clicks on Zozo Tabs in Clicky.
 *
 * The 1st param for clicky.log() is composed from titles here and has not to be a
 * real link - it has to be a link because it's the distinguishing mark for Clicky.
 *
 * see: https://clicky.com/help/custom/manual#log
 */
function cc_medonline_track_clicky_clicks_on_zozo_tabs(){
	?>
	<script type="text/javascript">jQuery(document).ready(function($){
		$('li a.z-link').bind( 'click', function() {
			var title = this.innerHTML;
			var length = title.indexOf('<span>');
			title = (length > 0) ? title.substr(0, length) : title;
			var link = '#tab_' + title.replace(/\s/g, '-').toLowerCase();
			var page  = '<?php print esc_url( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ); ?>';
			clicky.log( link, title + ' (auf Seite ' + page + ')' );
		});
	});</script>
	<?php
}
if ( $_SERVER['PRODUCTION'] )
	add_action( 'wp_footer', 'cc_medonline_track_clicky_clicks_on_zozo_tabs', 200 );

/**
 * Track clicks on post tags in Clicky.
 *
 * see: https://clicky.com/help/custom/manual#log
 */
function cc_medonline_clicky_track_tags( $term_links ) {
	if( ! $term_links )
		return $term_links;

	global $post;
	$clickyfied_links = array();

	foreach( $term_links as $term_link ) {
		$link  = preg_replace( '|<a.*href="(.*?)".*>|', '$1', $term_link );
		$page  = esc_url( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		if( strpos( $page, 'admin-ajax.php' ) && isset( $_SERVER['HTTP_REFERER'] ) )
			$page = esc_url( $_SERVER['HTTP_REFERER'] );
		$title = esc_html( $post->post_title );
		$clickyfied_links[] = str_replace(
			' href=',
			" onclick=\"clicky.log( '$link', 'Schlagwort des Beitrags: \'$title\' (auf Seite: $page)' );\" href=",
			$term_link
		);
	}

	return $clickyfied_links;
}
if ( $_SERVER['PRODUCTION'] )
	add_filter( 'term_links-post_tag', 'cc_medonline_clicky_track_tags' );


