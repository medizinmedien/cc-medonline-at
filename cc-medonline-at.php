<?php
/*
Plugin Name:       Custom Code for medonline.at
Plugin URI:        https://github.com/medizinmedien/cc-medonline-at
Description:       A plugin to provide functionality specific for medONLINE.
Version:           0.97
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
	if ( ! class_exists( 'jNewsticker_Bootstrap' ) )
		return;

	wp_dequeue_style( 'jnewsticker_css' );
	$jnews_settings = get_option('jnewsticker');

	if( $_SERVER['SERVER_PORT'] == 443 || is_ssl() ) {
		$jnews_css = str_replace( 'http://', 'https://', $jnews_settings['skin'] );
	} 
	else {
		$jnews_css = $jnews_settings['skin'];
	}

	wp_register_style( 'cc_medonline_jnewsticker_css_to_https', $jnews_css );
	wp_enqueue_style('cc_medonline_jnewsticker_css_to_https');
}
add_action( 'wp_enqueue_scripts', 'cc_medonline_set_jnews_css_url_to_https', 15 );


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


/**
 * Estimate time required to read the article.
 *
 * Inspired by: http://wptavern.com/estimated-time-to-read-this-post-eternity
 *
 * @return bool false ($estimated_time < 1 min) || string $estimated_time
 */
function cc_medonline_print_estimated_reading_time( $post_content = '' ) {
	$post  = get_post();

	$speed = 250; // words per minute
	$out   = '';

	$words = str_word_count( strip_tags( $post->post_content ) );
	$minutes = floor( $words / $speed );

	if ( 1 < $minutes ) {
		$estimated_time = $minutes . ' min';
		$out = '<span id="est-read-time" class="fa fa-clock-o fa-lg" title="Lesezeit"><span>' . $estimated_time . '</span></span>' . $post_content;
	}

	print $out;
}


/**
 * Add modified output of plugin Thumbs Rating just before post content.
 */
function cc_medonline_insert_thumbs_rating_before_post_content( $content ) {
	global $post;
	if( is_user_logged_in() && ! cc_medonline_thumbs_rating_is_undesired() ) {
		$thumbs = thumbs_rating_getlink( get_the_ID() );
		$thumbs = cc_medonline_replacements_for_thumbs_rating_output( $thumbs );
		return $thumbs . $content;
	}
	else
		return $content;
}
add_filter( 'the_content', 'cc_medonline_insert_thumbs_rating_before_post_content' );

/**
 * Return TRUE when Thumbs Ratings are undesired.
 */
function cc_medonline_thumbs_rating_is_undesired() {
	global $post;
	if( is_page( array( 'impressum', 'ueber', 'join' ) )
	||  is_front_page()
	||  ! function_exists( 'is_medonline_public_page' ) // Fallback: display no ratings.
	||  ! function_exists( 'thumbs_rating_getlink' )
	||  is_medonline_public_page()
	||  ! is_singular()
	||  ( in_category( 'pubmed' ) || post_is_in_descendant_category( 295 ) )
	||  ( in_category( 'jobs'   ) || post_is_in_descendant_category( 294 ) )
	||  ( in_category( 'feeds'  ) || post_is_in_descendant_category( 286 ) )
	||    in_category( 'quiz'   )
	||  ! empty( get_metadata( 'post', $post->ID, 'wpe_feed', true ) ) // WP Ematico
	||  ! empty( get_metadata( 'post', $post->ID, 'no_thumbs_rating', true ) ) // Thumbs Rating
	){
		return true;
	}
	else {
		return false;
	}
}

/**
 * Determine if any of a post's assigned categories are descendants of target categories.
 * see: http://codex.wordpress.org/Function_Reference/in_category#Testing_if_a_post_is_in_a_descendant_category
 */
if ( ! function_exists( 'post_is_in_descendant_category' ) ) {
	function post_is_in_descendant_category( $cats, $_post = null ) {
		foreach ( (array) $cats as $cat ) {
			// get_term_children() accepts integer ID only
			$descendants = get_term_children( (int) $cat, 'category' );
			if ( $descendants && in_category( $descendants, $_post ) )
				return true;
		}
		return false;
	}
}

/**
 * Modify output of the Thumbs Rating plugin. Also used to modify AJAX callback output.
 */
function cc_medonline_replacements_for_thumbs_rating_output( $thumbs ) {
	$thumbs = str_replace(
		array(
			'thumbs-rating-up',
			'thumbs-rating-down',
			'data-text="Vote',
			'Du hast',
			'Vote Up +',
			'Vote Down -'
		),
		array(
			'thumbs-rating-up fa fa-thumbs-o-up fa-lg',
			'thumbs-rating-down fa fa-thumbs-o-down fa-lg',
			'title="Vote',
			'Sie haben',
			'Gef&auml;llt mir',
			'Missf&auml;llt mir'
		),
		$thumbs
	);
	return $thumbs;
}

// Prevent loading German translations of plugin Thumbs Rating (even if not existing yet):
remove_action('plugins_loaded', 'thumbs_rating_init');

/**
 * Load Font Awesome into header. Used to modify Thumbs Rating output.
 */
function cc_medonline_load_font_awesome() {
	wp_register_style( 'cc_medonline_font_awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css' );
	wp_enqueue_style('cc_medonline_font_awesome');
}

function cc_medonline_load_font_awesome_with_thumbs_rating() {
	if( ! cc_medonline_thumbs_rating_is_undesired() )
		cc_medonline_load_font_awesome();
}
add_action( 'template_redirect', 'cc_medonline_load_font_awesome_with_thumbs_rating' );


/**
 * Additional menu profile link for bbPress after login.
 */
function cc_medonline_members_nav( $items, $menu, $args ) {
	global $current_user;

	// DEBUG
	//error_log( "$items \n" . print_r( $items, 1 ) );

	$user = $current_user->data->user_nicename;
	$user_profile_url = esc_url( home_url() . "/foren/nutzer/$user" );
	$profile_index = cc_medonline_array_index_of( 'Mein Profil', $items );

	if ( is_user_logged_in() ) {
		if ( $profile_index ) {
			// Replace custom menu URL
			$items[$profile_index]->url = $user_profile_url;
		}
	} else {
		// User is not logged in.
		if ( $profile_index ) {
			unset( $items[$profile_index] );
		}
	}
	return $items;
}
add_filter( 'wp_get_nav_menu_items', 'cc_medonline_members_nav', 10, 3 );


/**
 * Get index number of an menu item from a menu item's title.
 *
 * @return integer Index of array with menu item objects | FALSE if not found.
 */
function cc_medonline_array_index_of( $menu_title, $array ) {
	if ( strlen( $menu_title ) && is_array( $array ) && count( $array ) ) {
		for( $i = count( $array ) - 1; $i >= 0; $i-- ) {
			if ( is_object( $array[$i] ) && property_exists( $array[$i], 'title' ) && $array[$i]->title == $menu_title ) {
				return $i;
			}
		}
	}
	return false;
}


/**
 * Pics in feed
 */
function cc_medonline_featured_image_in_feed( $content ) {
	global $post;
	static $aligncounter = 2;
	if ( has_post_thumbnail( $post->ID ) ){
		$output = get_the_post_thumbnail( $post->ID, 'thumbnail', array( 'style' => '' ) );
		$align = ( $aligncounter % 2 ) ? 'align="left"' : 'align="right"';
		$output = str_replace(
			'<img ',
			"<img hspace=\"0\" vspace=\"0\" $align ",
			$output
		);
		$content = $output . $content;
		$aligncounter++;
	}
	return $content;
}
add_filter('the_excerpt_rss', 'cc_medonline_featured_image_in_feed');
add_filter('the_content_feed', 'cc_medonline_featured_image_in_feed');


/**
 * Load JS async on homepage ...
 */
function defer_parsing_of_js ( $url ) {
	if ( FALSE === strpos( $url, '.js' ) )
		return $url;

	if ( strpos( $url, 'jquery.js' ) )
		return $url;

	// Nur auf der Startseite asynchron laden
	if( $_SERVER['REQUEST_URI'] == '/' || $_SERVER['REQUEST_URI'][1] == '#' || $_SERVER['REQUEST_URI'][1] == '?' )
		return "$url' defer='defer";
	else
		return $url;
}
add_filter( 'clean_url', 'defer_parsing_of_js', 11, 1 );


/**
 * Turn protocol of mO author's URL to https. Saves 1 redirect.
 */
function cc_medonline_comment_author_url_to_https( $url, $comment_ID, $comment ) {
	return str_replace( 'http://medonline.at', 'https://medonline.at', $url );
}
add_filter( 'get_comment_author_url', 'cc_medonline_comment_author_url_to_https', 200, 3 );

/**
 * Turn local avatar URL's to https.
 *
 * Caused by WP core: img src is constructed from site_url, which has http defined.
 * see https://github.com/medizinmedien/allgemein/issues/208
 */
function cc_medonline_comment_author_avatar_to_https( $avatar, $id_or_email, $size, $default, $alt ) {
	return str_replace( 'http://medonline.at', 'https://medonline.at', $avatar );
}
add_filter( 'get_avatar', 'cc_medonline_comment_author_avatar_to_https', 20, 5 );


/**
 * Prevent 'display-posts'-shortcode queries from beeing executed unless whistles are fetched by AJAX.
 *
 * This reduces massively render time and amount of db queries with ajaxified whistles.
 */
function cc_medonline_remove_display_posts_shortcode_from_whistles( $posts ) {
	foreach( $posts as $post ) {
		if( $post->post_type == 'whistle') {
			if( ! empty( get_metadata( 'post', $post->ID, 'ajax-load', true ) ) || strpos( $post->post_content, '[display-posts' ) !== false ) {
				remove_shortcode( 'display-posts' );
				//error_log(print_r($post,1));
				return $posts;
			}
		}
	}
	return $posts;
}
add_action( 'the_posts', 'cc_medonline_remove_display_posts_shortcode_from_whistles' );

