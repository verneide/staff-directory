<?php
/**
 * The template for displaying staff displays
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 * @package VE_Staff
 */

date_default_timezone_set('America/Chicago');

/* === GET URL PARAMETERS === */
$type  = $_GET['type'] ?? '';
$group = $_GET['group'] ?? '';
$debug = isset($_GET['debug']) && $_GET['debug'] == true;

if ($debug) {
	error_log('/////////// DEBUG INFORMATION /////////////');
}

staff_debug_show_dialog();

/* === PAGE ACCESS CHECK === */
if (!ve_staff_check_page_access()) {
	$access_error = "Error: Unauthorized - Contact Support";
	if ($type === "script") {
		header("content-type: application/x-javascript");
		echo 'document.write("' . esc_js($access_error) . '");';
	} else {
		echo esc_html($access_error);
	}
	exit;
}

/* === PAGE ASSETS === */
wp_enqueue_script('ve-lazy-load');
wp_enqueue_style('ve-staff-display');

$jquerylink     = '<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js" integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI=" crossorigin="anonymous"></script>';
$bootstrapscript = '<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>';
$htmlcss         = '<link rel="stylesheet" href="' . get_template_directory_uri() . '/inc/assets/css/ve-staff-display.min.css">';
$htmljs          = '';

/* === GET POST DATA === */
$displayid        = get_the_ID();
$displaycustomtitle = get_field('staff_display_title');
$displaytitle       = $displaycustomtitle
	? '<h1 class="ve-display-title">' . esc_html($displaycustomtitle) . '</h1>'
	: the_title('<h1 class="ve-display-title">', '</h1>', false);

$displaylogo        = get_field('staff_display_logo');
$displayprimcolor   = get_field('staff_display_primary_color');
$displayseccolor    = get_field('staff_display_secondary_color');
$displaytype        = get_field('staff_display_type');
$displayratio       = get_field('staff_display_ratio');
$displayrowheight   = get_field('staff_display_row_height') ?: 'full';
$displaylocations   = get_field('staff_display_locations') ?: [];
$displaydepartments = get_field('staff_display_departments') ?: [];
$stafftags          = get_field('staff_display_tags') ?: [];
$infodisplayed      = get_field('staff_display_profile_info_displayed') ?: [];

$staffshown   = get_field('staff_display_max_profiles_displayed') ?: 12;
$staffperrow  = get_field('staff_display_profiles_per_row') ?: 6;
$staffpgnumber = get_field('staff_display_page_number') ?: 1;

/* === LIMIT MAX PROFILES BASED ON PER ROW === */
if ($staffperrow == 5 && $staffshown > 10) {
	$staffshown = 10;
} elseif ($staffperrow == 4 && $staffshown > 8) {
	$staffshown = 8;
}

$embedcustomcss = get_field('embed_custom_css');
if ($embedcustomcss) {
	$embedcustomcss = '<style>' . $embedcustomcss . '</style>';
}

if ($debug) {
	error_log("Display Type: $displaytype");
}

/* === DATE VARIABLES & LOGIC PER DISPLAY TYPE === */
$birthdaydisplay = false;
$newpromotiondisplay = false;
$birthdaymatch = false;
$newpromomatch = false;
$sortdate = '';

if ($displaytype === 'birthday') {
	$birthdaydisplay = true;
	$birthdaygroup = get_field('birthday_display');

	if ($birthdaygroup) {
		$bddaysbeforetoday = (int) get_field('birthday_display_days_shown_before');
		$bddaysaftertoday  = (int) get_field('birthday_display_days_shown_after');

		$prevyear = date("Y", strtotime("-1 year"));
		$curyear  = date("Y");
		$nextyear = date("Y", strtotime("+1 year"));

		$datebackward = date('Y-m-d', strtotime("-$bddaysbeforetoday days"));
		$dateforward  = date('Y-m-d', strtotime("+$bddaysaftertoday days"));

		if ($debug) {
			error_log("Days Before Today: $bddaysbeforetoday ($datebackward)");
			error_log("Days After Today: $bddaysaftertoday ($dateforward)");
		}
	}
}

if ($displaytype === 'newpromo') {
	$newpromotiondisplay = true;
	$newpromogroup = get_field('new_promotion_display');

	if ($newpromogroup) {
		$newmonthsprior = (int) get_field('new_promotion_display_months_before_current');
		$prevyear = date("Y", strtotime("-1 year"));
		$curyear  = date("Y");
		$nextyear = date("Y", strtotime("+1 year"));
		$monthsbackward = date('Y-m-d', strtotime("-$newmonthsprior months"));

		if ($debug) {
			error_log("Months Before Today: $newmonthsprior ($monthsbackward)");
		}
	}
}

/* === OPTIONAL LOCATION LOGO SETTINGS === */
$showloclogo = get_field('display_show_location_logo');

/* === SCRIPT EMBED MODE === */
if ($type === "script") {
	header("content-type: application/x-javascript");
	$initaldependencies = implode(' ', [
		$googleanalytics ?? '',
		$jquerylink,
		$bootstrapscript,
		$embedcustomcss,
		$htmljs,
		$htmlcss,
		$listloader ?? ''
	]);
}

/* === STAFF QUERY === */
if ($debug) {
	error_log('Locations Listed: ' . print_r($displaylocations, true));
	error_log('Departments Listed: ' . print_r($displaydepartments, true));
}

switch ($displaytype) {
	case 'results':
		$posts = staff_get_filtered_staff_posts($displayid, $stafftags, $displaylocations, $displaydepartments, $staffpgnumber, $staffshown);
		break;

	case 'birthday':
		$posts = staff_get_birthday_range_posts($bddaysbeforetoday, $bddaysaftertoday, $displaylocations, $displaydepartments);
		break;

	case 'newpromo':
		$posts = staff_get_new_promo_hire_posts($monthsbackward, $displaylocations, $displaydepartments);
		break;

	case 'staff':
	case 'individual':
		$staffPostObjects = [];
		if ($displaytype === 'staff' && have_rows('staff_displayed')) {
			while (have_rows('staff_displayed')) {
				the_row();
				$staffPostObject = get_sub_field('staff_profile');
				if ($staffPostObject) {
					$staffPostObject->title_override = get_sub_field('staff_title_override');
					$staffPostObjects[] = $staffPostObject;
				}
			}
		} elseif ($displaytype === 'individual') {
			$staffPostObjects[] = get_field('staff_display_individual');
		}
		$posts = $staffPostObjects;
		break;

	case 'anniversary':
		if ( ! class_exists( 'Ve_Staff_Admin' ) ) {
			require_once WP_PLUGIN_DIR . '/ve-staff/admin/class-ve-staff-admin.php';
		}
		$posts = staff_get_anniversary_milestone_posts(
			$displayid,
			$displaylocations,
			$displaydepartments,
			$staffpgnumber,
			$staffshown
		);
		break;

	default:
		$posts = [];
}

/* === BUILD STAFF DATA ARRAY === */
$staff = [];

if ($debug) {
	error_log('Staff Profiles Shown: ' . print_r($posts, true));
}

foreach ($posts as $post) {
	$first_name = in_array('firstname', $infodisplayed) ? get_field('first_name', $post->ID) : '';
	$last_name  = in_array('lastname', $infodisplayed) ? get_field('last_name', $post->ID) : '';
	$full_name  = trim("$first_name $last_name");

	$department = in_array('department', $infodisplayed) ? get_field('department', $post->ID) : null;
	$location   = in_array('location', $infodisplayed) ? get_field('primary_location', $post->ID) : null;
	$webvisible = get_field('website_visible', $post->ID);
	$title_term = get_field('title', $post->ID);
	$title = isset($post->title_override) && !empty($post->title_override)
		? $post->title_override
		: ($title_term && !is_wp_error($title_term) ? $title_term->name : '');

	$photo = get_field('photo', $post->ID);
	if ($photo) {
		$photosized = $photo['sizes']['1536x1536'] ?? $photo['url'];
		$photofull  = $photo['url'];
	} else {
		$default = get_stylesheet_directory_uri() . '/inc/assets/img/default-no-photo.jpg';
		$photosized = $photofull = $default;
	}

	$customizations = get_field('profile_customizations', $post->ID);

	/* === Internal Info Setup === */
	$int_info = [];
	$intbirthdayinfo = get_field('birthday', $post->ID) ?: [];
	$newpromodetail = [];

	if ($birthdaydisplay && !empty($intbirthdayinfo['birthday_month']) && !empty($intbirthdayinfo['birthday_day'])) {
		$bdaymonth = $intbirthdayinfo['birthday_month'];
		$bdayday   = $intbirthdayinfo['birthday_day'];

		$bdaycuryrdate  = date('Y-m-d', strtotime("$curyear-$bdaymonth-$bdayday"));
		$bdaynextyrdate = date('Y-m-d', strtotime("$nextyear-$bdaymonth-$bdayday"));
		$bdayprevyrdate = date('Y-m-d', strtotime("$prevyear-$bdaymonth-$bdayday"));
		$bdayformatted  = date('F d', strtotime("$curyear-$bdaymonth-$bdayday"));

		if (($bdaycuryrdate >= $datebackward && $bdaycuryrdate <= $dateforward) ||
			($bdaynextyrdate >= $datebackward && $bdaynextyrdate <= $dateforward) ||
			($bdayprevyrdate >= $datebackward && $bdayprevyrdate <= $dateforward)) {
			$birthdaymatch = true;
			$birthdaydate  = $bdaycuryrdate;
		} else {
			$birthdaymatch = false;
		}

		$intbirthdayinfo += [
			'birthday_date'         => $birthdaydate ?? '',
			'birthday_month_day'    => $bdayformatted,
			'birthday_filter_match' => $birthdaymatch,
		];
	}

	if ($newpromotiondisplay) {
		$staffpublisheddate = get_the_date('Y-m-d', $post->ID);
		$staffstartdate     = get_field('start_date', $post->ID) ?: $staffpublisheddate;
		$staffpromotiondate = date('Y-m-d', strtotime(get_field('promotion_date', $post->ID)));

		$comparedate = date('Y-m-01', strtotime("-$newmonthsprior months"));

		if ($staffstartdate >= $comparedate && $staffstartdate <= date('Y-m-d')) {
			$newpromomatch = true;
			$newpromodate  = $staffstartdate;
			$newpromotype  = 'new';
		} elseif ($staffpromotiondate >= $comparedate && $staffpromotiondate <= date('Y-m-d')) {
			$newpromomatch = true;
			$newpromodate  = $staffpromotiondate;
			$newpromotype  = 'promotion';
		} else {
			$newpromomatch = false;
		}

		$newpromodetail = [
			'newpromo_type'          => $newpromotype ?? '',
			'newpromo_date'          => $newpromodate ?? '',
			'newpromo_filter_match'  => $newpromomatch,
		];
	}

	$int_info[] = [
		'birthday' => $intbirthdayinfo,
		'newpromo' => $newpromodetail,
	];

	/* === Visibility Filtering === */
	$displaytypefilter = ['birthday', 'newpromo'];
	$displayvisible = !in_array($displaytype, $displaytypefilter)
		|| $birthdaymatch
		|| $newpromomatch;

	if (!$displayvisible) {
		continue;
	}

	if ($birthdaydisplay) {
		$sortdate = $birthdaydate ?? '';
	} elseif ($newpromotiondisplay) {
		$sortdate = $newpromodate ?? '';
	}

	/* === Locations & Tags === */
	$locationslisted = wp_get_post_terms($post->ID, 'location', ['fields' => 'ids']);
	$emplocationslisted = array_map(fn($id) => str_pad($id, 3, '0', STR_PAD_LEFT), $locationslisted);

	$stafftags_ids = wp_get_post_tags($post->ID, ['fields' => 'ids']);
	$tag_data = [];

	foreach ($stafftags_ids as $tag_id) {
		$tag = get_tag($tag_id);
		if (!$tag) continue;

		$tag_data[] = [
			'id'             => $tag_id,
			'name'           => $tag->name,
			'slug'           => $tag->slug, // ✅ Needed for anniversary tag detection
			'card_visible'   => get_term_meta($tag_id, 'card_visible', true),
			'public_visible' => get_term_meta($tag_id, 'public_visible', true),
			'public_filter'  => get_term_meta($tag_id, 'public_filter', true),
		];
	}

	/* === Build Staff Entry === */
	$staff[] = [
		'id'                => $post->ID,
		'full_name'         => $full_name,
		'first_name'        => $first_name,
		'last_name'         => $last_name,
		'department'        => $department ? $department->name : '',
		'department_id'     => $department ? $department->term_id : '',
		'department_desc'   => $department ? $department->description : '',
		'department_order'  => $department ? $department->term_order : '',
		'location'          => $location ? $location->name : '',
		'location_id'       => $location ? $location->term_id : '',
		'locations_listed'  => $emplocationslisted,
		'tags'              => $tag_data,
		'title'             => $title,
		'photo_full'        => $photofull,
		'photo_sized'       => $photosized,
		'staff_url'         => $post->guid,
		'int_info'          => $int_info,
		'customizations'    => $customizations,
		'website_visible'   => $webvisible,
		'display_visible'   => $displayvisible,
		'sort_date'         => $sortdate,
	];
}

/* === SORT RESULTS === */
if ($birthdaydisplay) {
	usort($staff, fn($a, $b) => strtotime($a['sort_date']) <=> strtotime($b['sort_date']));
} elseif ($newpromotiondisplay) {
	usort($staff, fn($a, $b) => strtotime($b['sort_date']) <=> strtotime($a['sort_date']));
}

/* === OUTPUT TEMPLATE === */
get_template_part('template-parts/display-output', null, []);
?>