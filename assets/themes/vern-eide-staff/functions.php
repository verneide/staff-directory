<?php
/**
 * WP Bootstrap Starter functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WP_Bootstrap_Starter
 */

if ( ! function_exists( 'wp_bootstrap_starter_setup' ) ) :
/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function wp_bootstrap_starter_setup() {
	/*
	 * Make theme available for translation.
	 * Translations can be filed in the /languages/ directory.
	 * If you're building a theme based on WP Bootstrap Starter, use a find and replace
	 * to change 'wp-bootstrap-starter' to the name of your theme in all the template files.
	 */
	load_theme_textdomain( 'wp-bootstrap-starter', get_template_directory() . '/languages' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	/*
	 * Enable support for Post Thumbnails on posts and pages.
	 *
	 * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
	 */
	add_theme_support( 'post-thumbnails' );

	// This theme uses wp_nav_menu() in one location.
	register_nav_menus( array(
		'primary' => esc_html__( 'Primary', 'wp-bootstrap-starter' ),
	) );

	/*
	 * Switch default core markup for search form, comment form, and comments
	 * to output valid HTML5.
	 */
	add_theme_support( 'html5', array(
		'comment-form',
		'comment-list',
		'caption',
	) );

	// Set up the WordPress core custom background feature.
	add_theme_support( 'custom-background', apply_filters( 'wp_bootstrap_starter_custom_background_args', array(
		'default-color' => 'ffffff',
		'default-image' => '',
	) ) );

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );

    function wp_boostrap_starter_add_editor_styles() {
        add_editor_style( 'custom-editor-style.css' );
    }
    add_action( 'admin_init', 'wp_boostrap_starter_add_editor_styles' );

}
endif;
add_action( 'after_setup_theme', 'wp_bootstrap_starter_setup' );

/**
 * Set the content width in pixels, based on the theme's design and stylesheet.
 *
 * Priority 0 to make it available to lower priority callbacks.
 *
 * @global int $content_width
 */
function wp_bootstrap_starter_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'wp_bootstrap_starter_content_width', 1170 );
}
add_action( 'after_setup_theme', 'wp_bootstrap_starter_content_width', 0 );

/**
 * Register widget area.
 *
 * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
 */
function wp_bootstrap_starter_widgets_init() {
    register_sidebar( array(
        'name'          => esc_html__( 'Sidebar', 'wp-bootstrap-starter' ),
        'id'            => 'sidebar-1',
        'description'   => esc_html__( 'Add widgets here.', 'wp-bootstrap-starter' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 1', 'wp-bootstrap-starter' ),
        'id'            => 'footer-1',
        'description'   => esc_html__( 'Add widgets here.', 'wp-bootstrap-starter' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 2', 'wp-bootstrap-starter' ),
        'id'            => 'footer-2',
        'description'   => esc_html__( 'Add widgets here.', 'wp-bootstrap-starter' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
    register_sidebar( array(
        'name'          => esc_html__( 'Footer 3', 'wp-bootstrap-starter' ),
        'id'            => 'footer-3',
        'description'   => esc_html__( 'Add widgets here.', 'wp-bootstrap-starter' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'wp_bootstrap_starter_widgets_init' );


/**
 * Enqueue scripts and styles.
 */
function wp_bootstrap_starter_scripts() {
	// load bootstrap css
    if ( get_theme_mod( 'cdn_assets_setting' ) === 'yes' ) {
        wp_enqueue_style( 'wp-bootstrap-starter-bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css' );
        wp_enqueue_style( 'wp-bootstrap-starter-fontawesome-cdn', 'https://use.fontawesome.com/releases/v5.15.1/css/all.css' );
    } else {
        wp_enqueue_style( 'wp-bootstrap-starter-bootstrap-css', get_template_directory_uri() . '/inc/assets/css/bootstrap.min.css' );
        wp_enqueue_style( 'wp-bootstrap-starter-fontawesome-cdn', get_template_directory_uri() . '/inc/assets/css/fontawesome.min.css' );
    }
	// load bootstrap css
	// load AItheme styles
	// load WP Bootstrap Starter styles
	wp_enqueue_style( 'wp-bootstrap-starter-style', get_stylesheet_uri() );
    if(get_theme_mod( 'theme_option_setting' ) && get_theme_mod( 'theme_option_setting' ) !== 'default') {
        wp_enqueue_style( 'wp-bootstrap-starter-'.get_theme_mod( 'theme_option_setting' ), get_template_directory_uri() . '/inc/assets/css/presets/theme-option/'.get_theme_mod( 'theme_option_setting' ).'.css', false, '' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'poppins-lora') {
        wp_enqueue_style( 'wp-bootstrap-starter-poppins-lora-font', 'https://fonts.googleapis.com/css?family=Lora:400,400i,700,700i|Poppins:300,400,500,600,700' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'montserrat-merriweather') {
        wp_enqueue_style( 'wp-bootstrap-starter-montserrat-merriweather-font', 'https://fonts.googleapis.com/css?family=Merriweather:300,400,400i,700,900|Montserrat:300,400,400i,500,700,800' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'poppins-poppins') {
        wp_enqueue_style( 'wp-bootstrap-starter-poppins-font', 'https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'roboto-roboto') {
        wp_enqueue_style( 'wp-bootstrap-starter-roboto-font', 'https://fonts.googleapis.com/css?family=Roboto:300,300i,400,400i,500,500i,700,700i,900,900i' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'arbutusslab-opensans') {
        wp_enqueue_style( 'wp-bootstrap-starter-arbutusslab-opensans-font', 'https://fonts.googleapis.com/css?family=Arbutus+Slab|Open+Sans:300,300i,400,400i,600,600i,700,800' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'oswald-muli') {
        wp_enqueue_style( 'wp-bootstrap-starter-oswald-muli-font', 'https://fonts.googleapis.com/css?family=Muli:300,400,600,700,800|Oswald:300,400,500,600,700' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'montserrat-opensans') {
        wp_enqueue_style( 'wp-bootstrap-starter-montserrat-opensans-font', 'https://fonts.googleapis.com/css?family=Montserrat|Open+Sans:300,300i,400,400i,600,600i,700,800' );
    }
    if(get_theme_mod( 'preset_style_setting' ) === 'robotoslab-roboto') {
        wp_enqueue_style( 'wp-bootstrap-starter-robotoslab-roboto', 'https://fonts.googleapis.com/css?family=Roboto+Slab:100,300,400,700|Roboto:300,300i,400,400i,500,700,700i' );
    }
    if(get_theme_mod( 'preset_style_setting' ) && get_theme_mod( 'preset_style_setting' ) !== 'default') {
        wp_enqueue_style( 'wp-bootstrap-starter-'.get_theme_mod( 'preset_style_setting' ), get_template_directory_uri() . '/inc/assets/css/presets/typography/'.get_theme_mod( 'preset_style_setting' ).'.css', false, '' );
    }

	wp_enqueue_script('jquery');

    // Internet Explorer HTML5 support
    wp_enqueue_script( 'html5hiv',get_template_directory_uri().'/inc/assets/js/html5.js', array(), '3.7.0', false );
    wp_script_add_data( 'html5hiv', 'conditional', 'lt IE 9' );

	// load bootstrap js
    if ( get_theme_mod( 'cdn_assets_setting' ) === 'yes' ) {
        wp_enqueue_script('wp-bootstrap-starter-popper', 'https://cdn.jsdelivr.net/npm/popper.js@1/dist/umd/popper.min.js', array(), '', true );
    	wp_enqueue_script('wp-bootstrap-starter-bootstrapjs', 'https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.min.js', array(), '', true );
    } else {
        wp_enqueue_script('wp-bootstrap-starter-bootstrapjs', get_template_directory_uri() . '/inc/assets/js/bootstrap.bundle.min.js', array(), '', true );
    }
    wp_enqueue_script('wp-bootstrap-starter-themejs', get_template_directory_uri() . '/inc/assets/js/theme-script.min.js', array(), '', true );
	wp_enqueue_script( 'wp-bootstrap-starter-skip-link-focus-fix', get_template_directory_uri() . '/inc/assets/js/skip-link-focus-fix.min.js', array(), '20151215', true );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}

}
add_action( 'wp_enqueue_scripts', 'wp_bootstrap_starter_scripts' );



/**
 * Add Preload for CDN scripts and stylesheet
 */
function wp_bootstrap_starter_preload( $hints, $relation_type ){
    if ( 'preconnect' === $relation_type && get_theme_mod( 'cdn_assets_setting' ) === 'yes' ) {
        $hints[] = [
            'href'        => 'https://cdn.jsdelivr.net/',
            'crossorigin' => 'anonymous',
        ];
        $hints[] = [
            'href'        => 'https://use.fontawesome.com/',
            'crossorigin' => 'anonymous',
        ];
    }
    return $hints;
} 

add_filter( 'wp_resource_hints', 'wp_bootstrap_starter_preload', 10, 2 );



function wp_bootstrap_starter_password_form() {
    global $post;
    $label = 'pwbox-'.( empty( $post->ID ) ? rand() : $post->ID );
    $o = '<form action="' . esc_url( home_url( 'wp-login.php?action=postpass', 'login_post' ) ) . '" method="post">
    <div class="d-block mb-3">' . __( "To view this protected post, enter the password below:", "wp-bootstrap-starter" ) . '</div>
    <div class="form-group form-inline"><label for="' . $label . '" class="mr-2">' . __( "Password:", "wp-bootstrap-starter" ) . ' </label><input name="post_password" id="' . $label . '" type="password" size="20" maxlength="20" class="form-control mr-2" /> <input type="submit" name="Submit" value="' . esc_attr__( "Submit", "wp-bootstrap-starter" ) . '" class="btn btn-primary"/></div>
    </form>';
    return $o;
}
add_filter( 'the_password_form', 'wp_bootstrap_starter_password_form' );

/**
 * Include Staff Query Functions
 */
require get_template_directory() . '/inc/ve-staff/staff-query-functions.php';

/**
 * Implement the Custom Header feature.
 */
require get_template_directory() . '/inc/custom-header.php';

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require get_template_directory() . '/inc/extras.php';

/**
 * Customizer additions.
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Load plugin compatibility file.
 */
require get_template_directory() . '/inc/plugin-compatibility/plugin-compatibility.php';

/**
 * Load custom WordPress nav walker.
 */
if ( ! class_exists( 'wp_bootstrap_navwalker' )) {
    require_once(get_template_directory() . '/inc/wp_bootstrap_navwalker.php');
}

/**
 * Add bootstrap to admin pages
 */
add_action( 'admin_enqueue_scripts', 'load_admin_styles' );
function load_admin_styles() {
    wp_enqueue_style( 'admin_css_bootsrap', get_stylesheet_directory_uri() . '/inc/assets/css/bootstrap-grid.css', false, '1.0.0' );
	wp_enqueue_style( 'admin_css_post', get_stylesheet_directory_uri() . '/inc/assets/css/admin-post.css', false, '1.0.0' );
} 

/**
 * Custom CSS for Staff Listings
 */
function ve_staff_enqueue_styles() {
	wp_register_style( 've-staff-listing', get_template_directory_uri()  . '/inc/assets/css/listing.css',false,'1.2','all');
	wp_register_style( 've-staff-display', get_template_directory_uri()  . '/inc/assets/css/listing-display.css',false,'1.2','all');
}
// Register style sheet.
add_action( 'wp_enqueue_scripts', 've_staff_enqueue_styles' );


/**
 * Custom JS for Staff Listings
 */
function ve_staff_enqueue_scripts() {
	wp_register_script( 'listing-js', get_template_directory_uri() . '/inc/assets/js/listing.js', array ( 'jquery' ), 1.1, false);
	wp_register_script( 've-lazy-load', get_template_directory_uri() . '/inc/assets/js/ve-lazy-load.js', array ( 'jquery' ), 1.1, false);
	wp_register_script( 've-ga-events', get_template_directory_uri() . '/inc/assets/js/ga-events.js', array ( 'jquery' ), 1.1, false);
	wp_localize_script('listing-js', 'veStaffSuggestEdit', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('ve_staff_suggest_edit'),
	));
}
// Register style sheet.
add_action( 'wp_enqueue_scripts', 've_staff_enqueue_scripts' );

/**
 * Get Custom CSS Source URL for Listings
 */
function get_staff_css_src_url( $handle ) {
	$styles = wp_styles();
	if ( isset( $styles->registered[ $handle ] ) ) {
		return $styles->registered[ $handle ]->src;
	}
	return false;
}
function get_staff_css_link_tag( $handle ) {
    $src_url = get_staff_css_src_url( $handle );

    if ( $src_url ) {
        $link_tag = sprintf( '<link rel="stylesheet" href="%s">', esc_url( $src_url ) );
        return $link_tag;
    }

    return false;
}
/**
 * Get Custom JS Source URL for Listings
 */
function get_staff_js_src_url( $handle ) {
    $scripts = wp_scripts();
    if ( isset( $scripts->registered[ $handle ] ) ) {
        $data = $scripts->registered[ $handle ];
        return $data->src;
    }
    return false;
}

/**
 * DISABLE SCRIPTS AND STYLES IN ADMIN PAGES / BACKEND TO REMOVE CONFLICTING SCRIPTS ETC
 */

function ve_disable_scripts_styles_admin() {
	wp_dequeue_style('listing-css');
	wp_dequeue_script('listing-js');
}
add_action('admin_enqueue_scripts', 've_disable_scripts_styles_admin', 100);

/** Custom Admin Logo Replaces Wordpress Logo **/
function wpb_custom_logo() {
echo '
<style type="text/css">
#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {
background-image: url(' . get_stylesheet_directory_uri() . '/inc/assets/img/eideweb-logo-blue-white-favicon.png) !important;
background-position: 0 0;
color:rgba(0, 0, 0, 0);
background-size: cover;
}
#wpadminbar #wp-admin-bar-wp-logo.hover > .ab-item .ab-icon {
background-position: 0 0;
}
</style>
';
}
 
//hook into the administrative header output
add_action('wp_before_admin_bar_render', 'wpb_custom_logo');

/*** CONSOLE LOGGING FUNCTION ***/
function console_log($output, $with_script_tags = true) {
    $js_code = 'console.log(' . json_encode($output, JSON_HEX_TAG) . 
');';
    if ($with_script_tags) {
        $js_code = '<script>' . $js_code . '</script>';
    }
    echo $js_code;
}
// ********************
// ACF Auto Sync
// ********************

// Adjust the Sync Save Path
// Save JSON
add_filter('acf/settings/save_json', 'my_acf_json_save_point');
 
function my_acf_json_save_point( $path ) {
    $path = get_stylesheet_directory() . '/inc/assets/acf/json';
    return $path;
}

// Adjust the Sync Load Path 
// Load JSON
add_filter('acf/settings/load_json', 'my_acf_json_load_point');

function my_acf_json_load_point( $paths ) {
    // remove original path (optional)
    unset($paths[0]);
    // append path
    $paths[] = get_stylesheet_directory() . '/inc/assets/acf/json';
    return $paths;
    
}

// Save & Load PHP Paths
add_filter('acfe/settings/php_save', 'my_acfe_php_save_point');
function my_acfe_php_save_point($path){

    return get_stylesheet_directory() . '/inc/assets/acf/php';    
}

add_filter('acfe/settings/php_load', 'my_acfe_php_load_point');
function my_acfe_php_load_point($paths){
    // remove original path (optional)
    unset($paths[0]);
    
    $paths[] = get_stylesheet_directory() . '/inc/assets/acf/php';
    
    return $paths;
}
function yt_bio_video($staffid) {
    $vidid = get_field('bio_yt_video_id', $staffid);
    if (!empty($vidid)) {
        $videoplayer = '<div id="player-container-'.$staffid.'" style="aspect-ratio: 16 / 9; width: 100%;"></div>';
        $videoplayer .= '
            <script>
                var ytplayer'.$staffid.';

                function onYouTubeIframeAPIReady_'.$staffid.'() {
                    ytplayer'.$staffid.' = new YT.Player("player-container-'.$staffid.'", {
                        height: "100%",
                        width: "100vw",
                        videoId: "'.$vidid.'",
                        playerVars: {
                            "controls": 1,
                            "start": 0,
                            "playsinline": 0,
                            "modestbranding": 1,
                            "rel": 0,
                            "showinfo": 0,
                            "loop": 1
                        },
                        events: {
                            "onReady": onPlayerReady_'.$staffid.'
                        }
                    });
                }

                function onPlayerReady_'.$staffid.'(event) {
                    // Player is ready
                }

                function loadYouTubeAPI() {
                    if (typeof YT === "undefined" || typeof YT.Player === "undefined") {
                        var tag = document.createElement("script");
                        tag.src = "https://www.youtube.com/iframe_api";
                        var firstScriptTag = document.getElementsByTagName("script")[0];
                        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
                    }

                    var checkYT = setInterval(function() {
                        if (typeof YT !== "undefined" && typeof YT.Player !== "undefined") {
                            clearInterval(checkYT);
                            onYouTubeIframeAPIReady_'.$staffid.'();
                        }
                    }, 100);
                }

                loadYouTubeAPI();

                jQuery(document).ready(function($) {
                    $("#biomodal'.$staffid.'").on("veshown.bs.vemodal", function (e) {
                            ytplayer'.$staffid.'.playVideo();
                    });

                    $("#biomodal'.$staffid.'").on("vehidden.bs.vemodal", function (e) {
                            ytplayer'.$staffid.'.stopVideo();
                    });
                });
            </script>';
        return $videoplayer;
    }
}

function staff_debug_show_dialog() {
    // Only output the debug information if the debug flag is set in the URL
    if (isset($_GET['debug']) && $_GET['debug'] == 'showdialog') {
        ?>
        <style>
            #debugDialog {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                color: white;
                font-family: Arial, sans-serif;
                z-index: 9999;
                padding: 10px;
                box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.3);
            }

            #debugDialog pre {
                margin: 0;
                padding: 0;
                white-space: pre-wrap;
            }
        </style>
        <div id="debugDialog">
            <strong>Debug Information:</strong>
            <pre id="debugInfo"></pre>
        </div>

        <script>
            function getBrowserInfo() {
                var ua = navigator.userAgent;

                // Detect the browser
                if (ua.indexOf("Edg") > -1) {
                    return "Edge " + ua.match(/Edg\/(\d+)/)[1];
                } else if (ua.indexOf("Chrome") > -1 && ua.indexOf("Edg") === -1 && ua.indexOf("OPR") === -1) {
                    return "Chrome " + ua.match(/Chrome\/(\d+)/)[1];
                } else if (ua.indexOf("Firefox") > -1) {
                    return "Firefox " + ua.match(/Firefox\/(\d+)/)[1];
                } else if (ua.indexOf("Safari") > -1 && ua.indexOf("Chrome") === -1) {
                    return "Safari " + ua.match(/Version\/(\d+)/)[1];
                } else if (ua.indexOf("OPR") > -1 || ua.indexOf("Opera") > -1) {
                    return "Opera " + ua.match(/(Opera|OPR)\/(\d+)/)[2];
                } else if (ua.indexOf("Trident") > -1 || ua.indexOf("MSIE") > -1) {
                    return "Internet Explorer";
                } else if (/Android/.test(ua)) {
                    return "Android " + ua.match(/Android\s([0-9.]+)/)[1];
                } else if (/iPhone|iPad|iPod/.test(ua)) {
                    return "iOS Safari " + ua.match(/OS (\d+_\d+)/)[1].replace('_', '.');
                } else {
                    return "Unknown Browser";
                }
            }

            // Detect if the browser is zoomed
            function getZoomLevel() {
                var zoomLevel = Math.round((window.outerWidth / window.innerWidth) * 100);
                return zoomLevel !== 100 ? zoomLevel + "%" : "No Zoom";
            }

            // Get viewport and document dimensions
            function getDocumentAndViewportInfo() {
                var viewportWidth = window.innerWidth;
                var viewportHeight = window.innerHeight;
                var documentWidth = document.documentElement.scrollWidth;
                var documentHeight = document.documentElement.scrollHeight;

                return `
                    Viewport Width: ${viewportWidth}px
                    Viewport Height: ${viewportHeight}px
                    Document Width: ${documentWidth}px
                    Document Height: ${documentHeight}px
                `;
            }

            // Check for CSS feature support
            function checkCssSupport() {
                var cssSupportInfo = `
                    Flexbox: ${CSS.supports('display', 'flex')}
                    Grid: ${CSS.supports('display', 'grid')}
                    Position Sticky: ${CSS.supports('position', 'sticky')}
                    Transforms: ${CSS.supports('transform', 'rotate(45deg)')}
                `;
                return cssSupportInfo;
            }

            // Get active media queries
            function getActiveMediaQueries() {
                var mediaQueryList = [
                    window.matchMedia("(min-width: 768px)"),
                    window.matchMedia("(min-width: 1024px)"),
                    window.matchMedia("(min-width: 1200px)")
                ];

                var activeQueries = mediaQueryList
                    .filter(function(mq) { return mq.matches; })
                    .map(function(mq) { return mq.media; })
                    .join(", ");

                return activeQueries ? `Active Media Queries: ${activeQueries}` : "No active media queries";
            }

            function displayDebugInfo() {
                var screenWidth = window.innerWidth;
                var screenHeight = window.innerHeight;
                var browserInfo = getBrowserInfo();
                var userAgent = navigator.userAgent;
                var zoomLevel = getZoomLevel();
                var documentInfo = getDocumentAndViewportInfo();
                var cssSupportInfo = checkCssSupport();
                var mediaQueryInfo = getActiveMediaQueries();

                var debugInfo = `
                    Screen Width: ${screenWidth}px
                    Screen Height: ${screenHeight}px
                    Browser: ${browserInfo}
                    Zoom Level: ${zoomLevel}
                    User Agent: ${userAgent}
                    ${documentInfo}
                    CSS Support:
                    ${cssSupportInfo}
                    ${mediaQueryInfo}
                `;

                document.getElementById('debugInfo').textContent = debugInfo;
            }

            window.addEventListener('resize', displayDebugInfo);
            window.addEventListener('load', displayDebugInfo);
        </script>
        <?php
    }
}



/**
 * Staff suggested edit submissions.
 */
function ve_staff_suggest_edit_allowed_fields() {
	return array(
		'email' => 'Email',
		'extension' => 'Extension',
		'tracking_number' => 'Tracking Number',
		'title' => 'Title',
		'cell_phone' => 'Cell Phone',
		'direct_office' => 'Direct Office',
		'other' => 'Other',
	);
}

function ve_staff_suggest_edit_table_name() {
	global $wpdb;
	return $wpdb->prefix . 've_staff_suggested_edits';
}

function ve_staff_register_suggest_edit_field_group() {
	if (!function_exists('acf_add_local_field_group')) {
		return;
	}

	acf_add_local_field_group(array(
		'key' => 'group_ve_staff_suggest_edit_notifications',
		'title' => 'Suggested Edit Notifications',
		'fields' => array(
			array(
				'key' => 'field_ve_staff_suggest_edit_recipient_email',
				'label' => 'Recipient Email',
				'name' => 've_staff_suggest_edit_recipient_email',
				'type' => 'email',
				'instructions' => 'Suggested staff edits will be sent to this address.',
				'required' => 1,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'options_page',
					'operator' => '==',
					'value' => 've-staff-settings',
				),
			),
		),
		'position' => 'side',
	));
}
add_action('acf/init', 've_staff_register_suggest_edit_field_group');

function ve_staff_load_suggest_edit_recipient_email($value) {
	if ($value !== null && $value !== false && $value !== '') {
		return $value;
	}

	return get_option('ve_staff_suggest_edit_recipient_email', get_option('admin_email'));
}
add_filter('acf/load_value/key=field_ve_staff_suggest_edit_recipient_email', 've_staff_load_suggest_edit_recipient_email');

function ve_staff_get_suggest_edit_recipient_email() {
	$value = get_field('ve_staff_suggest_edit_recipient_email', 'option');
	return is_email($value) ? $value : get_option('admin_email');
}

function ve_staff_create_suggest_edit_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table_name = ve_staff_suggest_edit_table_name();
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_post_id bigint(20) unsigned NOT NULL,
		employee_name varchar(255) NOT NULL,
		requester_name varchar(255) NOT NULL,
		requester_email varchar(255) NOT NULL,
		changes longtext NOT NULL,
		submitted_at datetime NOT NULL,
		remote_addr varchar(100) NOT NULL,
		PRIMARY KEY  (id),
		KEY staff_post_id (staff_post_id)
	) {$charset_collate};";
	dbDelta($sql);
}
add_action('after_switch_theme', 've_staff_create_suggest_edit_table');
add_action('admin_init', 've_staff_create_suggest_edit_table');

function ve_staff_sanitize_suggest_edit_value($value) {
	if (!is_string($value)) {
		return '';
	}
	return sanitize_textarea_field(wp_unslash($value));
}

function ve_staff_handle_suggest_edit_submission() {
	if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 've_staff_suggest_edit')) {
		wp_send_json_error(array('message' => 'Invalid security token.'), 403);
	}

	$is_internal_listing = isset($_POST['internal_listing']) && sanitize_text_field(wp_unslash($_POST['internal_listing'])) === '1';
	if (!$is_internal_listing) {
		wp_send_json_error(array('message' => 'Suggested edits are only available on internal listings.'), 403);
	}

	$staff_post_id = isset($_POST['staff_post_id']) ? absint($_POST['staff_post_id']) : 0;
	$employee_name = isset($_POST['employee_name']) ? sanitize_text_field(wp_unslash($_POST['employee_name'])) : '';
	$requester_name = isset($_POST['requester_name']) ? sanitize_text_field(wp_unslash($_POST['requester_name'])) : '';
	$requester_email = isset($_POST['requester_email']) ? sanitize_email(wp_unslash($_POST['requester_email'])) : '';

	if (!$staff_post_id || get_post_type($staff_post_id) !== 'staff' || $employee_name === '' || $requester_name === '' || !is_email($requester_email)) {
		wp_send_json_error(array('message' => 'Please complete all required fields with valid values.'), 400);
	}

	$allowed_fields = ve_staff_suggest_edit_allowed_fields();
	$changes = array();
	foreach ($allowed_fields as $field_key => $field_label) {
		$current_key = 'current_' . $field_key;
		$suggested_key = 'suggested_' . $field_key;
		$current_value = isset($_POST[$current_key]) ? ve_staff_sanitize_suggest_edit_value($_POST[$current_key]) : '';
		$suggested_value = isset($_POST[$suggested_key]) ? ve_staff_sanitize_suggest_edit_value($_POST[$suggested_key]) : '';
		if ($suggested_value !== '') {
			$changes[$field_key] = array('label' => $field_label, 'current' => $current_value, 'suggested' => $suggested_value);
		}
	}

	if (!$changes) {
		wp_send_json_error(array('message' => 'Please enter at least one suggested edit.'), 400);
	}

	ve_staff_create_suggest_edit_table();

	global $wpdb;
	$submitted_at = current_time('mysql');
	$remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
	$wpdb->insert(ve_staff_suggest_edit_table_name(), array(
		'staff_post_id' => $staff_post_id,
		'employee_name' => $employee_name,
		'requester_name' => $requester_name,
		'requester_email' => $requester_email,
		'changes' => wp_json_encode($changes),
		'submitted_at' => $submitted_at,
		'remote_addr' => $remote_addr,
	), array('%d', '%s', '%s', '%s', '%s', '%s', '%s'));

	$recipient = ve_staff_get_suggest_edit_recipient_email();
	$edit_link = admin_url('post.php?post=' . $staff_post_id . '&action=edit');
	$rows = '';
	foreach ($changes as $change) {
		$rows .= '<tr><th align="left">' . esc_html($change['label']) . '</th><td><del>' . esc_html($change['current']) . '</del> &rarr; <strong>' . esc_html($change['suggested']) . '</strong></td></tr>';
	}
	$message = '<p>A suggested edit was submitted by ' . esc_html($requester_name) . ' on ' . esc_html(wp_date('F j, Y g:i a')) . ' for ' . esc_html($employee_name) . '.</p><table cellpadding="6" cellspacing="0" border="1">' . $rows . '</table><p><a href="' . esc_url($edit_link) . '">Edit this staff member</a></p>';
	wp_mail($recipient, 'Suggested Staff Edit: ' . $employee_name, $message, array('Content-Type: text/html; charset=UTF-8', 'Reply-To: ' . $requester_name . ' <' . $requester_email . '>'));

	wp_send_json_success(array('message' => 'Thank you. Your suggested edit has been submitted.'));
}
add_action('wp_ajax_ve_staff_suggest_edit', 've_staff_handle_suggest_edit_submission');
add_action('wp_ajax_nopriv_ve_staff_suggest_edit', 've_staff_handle_suggest_edit_submission');

function ve_staff_suggest_edit_modal_html() {
	$fields = ve_staff_suggest_edit_allowed_fields();
	ob_start();
	?>
	<div class="vemodal vefade ve-suggest-edit-modal" id="veSuggestEditModal" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;">
		<div class="vemodal-dialog vemodal-lg vemodal-dialog-centered vemodal-dialog-scrollable" role="document">
			<div class="vemodal-content shadow-lg">
				<div class="vemodal-header" style="background-color:#373737;color:#fff;">
					<h3 class="vemodal-title" style="color:#fff;">Suggest Staff Edit: <span data-suggest-edit-employee></span></h3>
					<button type="button" class="btn btn-secondary" data-suggest-edit-close aria-label="Close"><span aria-hidden="true">&times;</span></button>
				</div>
				<form id="veSuggestEditForm" class="ve-suggest-edit-form">
					<div class="vemodal-body">
						<p class="ve-suggest-edit-instructions">If you believe this employee's contact information or other details are incorrect, enter the corrected value in the suggested edit field below. Your suggestion will notify an administrator, who will review it before updating the staff directory.</p>
						<input type="hidden" name="staff_post_id" value="">
						<input type="hidden" name="employee_name" value="">
						<input type="hidden" name="internal_listing" value="1">
						<div class="ve-row">
							<div class="ve-col-md-6 ve-pad-sm"><label>Employee Name<input type="text" name="employee_name_display" disabled></label></div>
							<div class="ve-col-md-6 ve-pad-sm"><label>Your Name *<input type="text" name="requester_name" required></label></div>
							<div class="ve-col-md-6 ve-pad-sm"><label>Your Email *<input type="email" name="requester_email" required></label></div>
						</div>
						<p>Enter only the fields that need to change.</p>
						<div class="ve-suggest-edit-columns" aria-hidden="true">
							<strong>Current Value</strong>
							<strong>Suggested Edit</strong>
						</div>
						<?php foreach ($fields as $field_key => $field_label) : ?>
							<div class="ve-suggest-edit-field ve-pad-sm">
								<div class="ve-suggest-edit-current">
									<span class="ve-suggest-edit-label"><?php echo esc_html($field_label); ?> (Current Value)</span>
									<span data-current-value="<?php echo esc_attr($field_key); ?>">Not Set</span>
									<input type="hidden" name="current_<?php echo esc_attr($field_key); ?>" value="">
								</div>
								<label><?php echo esc_html($field_label); ?> (Suggested Edit)<input type="text" name="suggested_<?php echo esc_attr($field_key); ?>" placeholder="Enter suggested <?php echo esc_attr(strtolower($field_label)); ?>"></label>
							</div>
						<?php endforeach; ?>
						<div class="ve-suggest-edit-status" role="status"></div>
					</div>
					<div class="vemodal-footer"><button type="button" class="btn btn-secondary" data-suggest-edit-close>Close</button><button type="submit" class="btn btn-primary">Submit Suggested Edit</button></div>
				</form>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
