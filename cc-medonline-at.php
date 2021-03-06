<?php
/*
Plugin Name:       Custom Code for medonline.at
Plugin URI:        https://github.com/medizinmedien/cc-medonline-at
Description:       A plugin to provide functionality specific for medONLINE.
Version:           1.8
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
	$user_profile_url = esc_url( home_url() . "/user/$user" );
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
			"<img hspace=\"10\" vspace=\"0\" $align ",
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


/**
 * Live stream for a special page.
 */ /* // Maybe used later again.
function cc_medonline_enqueue_live_stream() {
	if( is_page( 'asco' ) )
		wp_enqueue_script(
			'cc_medonline_asco_stream', // handle
			plugins_url() . '/Shared-Includes/inc/streaming-medonline/livevideo.js', // src
			array(), // deps
			'1.0',   // version
			true     // in footer
		);
}
add_action( 'wp_enqueue_scripts', 'cc_medonline_enqueue_live_stream', 15 );
*/

/**
 * Replace output of "BBP Profile Information" since Name & E-Mail is not shown
 * to other users - what we want. Then, "Vorname", "Name", "E-Mail" is hard
 * translated here, since otherwise we get english output (wrong domain).
 */
remove_action ( 'bbp_template_after_user_profile', 'user_profile_bbp_profile_information' );
add_action    ( 'bbp_template_after_user_profile', 'cc_medonline_user_profile_bbp_profile_information' );
function cc_medonline_user_profile_bbp_profile_information() {
	// This function adds items to the profile display, even if the user
	// cannot edit other users display first name, lastname and email.
	global $rpi_options;

	// item 1
	if ($rpi_options['Activate_item1']== true) {
		$label1 =  $rpi_options['item1_label'] ;
		echo "<!-- cc-medonline-at BEGIN --><p>" ;
		printf ( __( $label1.' : ', 'bbpress' ));
		echo esc_attr( bbp_get_displayed_user_field( 'rpi_label1' )); 
		echo"</p>" ;
	}

	// item 2
	if ($rpi_options['Activate_item2']== true) {
		$label2 =  $rpi_options['item2_label'] ;
		echo "<p>" ;
		printf ( __( $label2.' : ', 'bbpress' ));
		echo esc_attr( bbp_get_displayed_user_field( 'rpi_label2' )); 
		echo"</p>" ;
	}

	// item 3
	if ($rpi_options['Activate_item3']== true) {
		$label3 =  $rpi_options['item3_label'] ;
		echo "<p>" ;
		printf ( __( $label3.' : ', 'bbpress' ));
		echo esc_attr( bbp_get_displayed_user_field( 'rpi_label3' )); 
		echo"</p>" ;
	}

	// item 4
	if ($rpi_options['Activate_item4']== true) {
		$label4 =  $rpi_options['item4_label'] ;
		echo "<p>" ;
		printf ( __( $label4.' : ', 'bbpress' ));
		echo esc_attr( bbp_get_displayed_user_field( 'rpi_label4' )); 
		echo"</p>" ;
	}

	echo '<p>';
	printf ( 'Vorname: %s', bbp_get_displayed_user_field( 'first_name') );
	echo "</p><p>";
	printf ( 'Name: %s', bbp_get_displayed_user_field( 'last_name') );
	echo "</p>";
	if ( current_user_can( 'edit_users' ) || wp_get_current_user()->user_email == bbp_get_displayed_user_field( 'user_email' ) ) {
		echo "<p>";
		echo "E-Mail: ";
		echo esc_attr( bbp_get_displayed_user_field( 'user_email' ) );
		echo "</p><!-- cc-medonline-at END -->" ;
	}
}


/**
 * Check required files and include helper script to perform static page caching.
 */
$static_cache_helper = WP_PLUGIN_DIR . '/Shared-Includes/inc/auth-cache/medonline-at-transients.php';
if( file_exists( $static_cache_helper ) ) {
	require_once( $static_cache_helper );
}

/**
 * Let cached public pages be delivered faster.
 */
function cc_medonline_mark_public_pages_for_auth_cache() {
	if( function_exists( 'is_medonline_public_page' ) && is_medonline_public_page() ) {
		// Signal for auth-cache to skip db query.
		add_action( 'wp_head', function(){
			?><meta name='auth-cache' content='public' />
			<?php
		}, -10 );
	}
}
add_action( 'template_redirect', 'cc_medonline_mark_public_pages_for_auth_cache' );


/**
 * Custom CSS for plugin "WF Cookie Consent", which does not contain
 * hooks for tasks like this.
 */
add_action( 'plugins_loaded', 'cc_medonline_add_cookieconsent_css' );
function cc_medonline_add_cookieconsent_css() {

	// Do nothing when "WF Cookie Consent" is not loaded.
	if ( ! function_exists( 'wf_cookieconsent_load' ) )
		return;

	add_action( 'wp_head', 'cookieconsent_custom_css' );
	function cookieconsent_custom_css() {
		?>
		<style type="text/css" id="cc-medonline-at-cookieconsent-custom-css">
			#cookieChoiceDismiss {
				margin: 5px 24px;
				-webkit-border-radius: 5;
				-moz-border-radius: 5;
				border-radius: 5px;
				color: #000000;
				background: #ffec20;
				padding: 5px 10px 5px 10px;
				text-decoration: none;
				display: inline-block;
				font-weight: 100;
			}
			#cookieChoiceDismiss:hover {
				background: #e6d300;
			}
			#cookieChoiceInfo {
				background-color: rgba(0, 0, 0, 1) !important;
				color: #ffffff;
			}
			#cookieChoiceInfo a[href="https://medonline.at/cookies/"] {
				text-decoration: underline;
			}
		</style>
		<?php
	}
}

