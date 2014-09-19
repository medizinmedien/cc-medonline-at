<?php
/*
Plugin Name:       Custom Code for medonline.at
Plugin URI:        https://github.com/medizinmedien/cc-medonline-at
Description:       A plugin to provide functionality specific for medONLINE.
Version:           0.4
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
 * The link param for "clicky.log" is composed from titles and can be whatever
   - but it is the distinguishing mark for Clicky.
 */
function cc_medonline_track_clicky_clicks_on_zozo_tabs(){
	?>
	<script type="text/javascript">jQuery(document).ready(function($){
		if( $('li a').hasClass('z-link') ) {
			$('li a').bind( 'click', function() { 
				var title = this.innerHTML;
				var pos = title.indexOf('<span>');
				title = (pos > 0)?title.substr(0, title.indexOf('<span>')):title;
				var link = '#tab_' + title.replace(/\s/g, '-').toLowerCase();
				clicky.log( link, title );<?php
				print "\n"; 
				?>
			});
		}
	});</script>
	<?php
}
if ( $_SERVER['PRODUCTION'] )
	add_action(  'wp_footer', 'cc_medonline_track_clicky_clicks_on_zozo_tabs', 200 );


