<?php
// ADMIN FUNCTIONS FOR USE IN THEME FILES & BACKEND

// Minify html data (safe-ish)
function veMinifyHtml($data) {
	if (!is_string($data) || $data === '') return $data;

	$search = [
		'/\>[^\S ]+/s',
		'/[^\S ]+\</s',
		'/(\s)+/s',
		'/<!--(?!\[if).*?-->/s'
	];
	$replace = ['>', '<', '\\1', ''];

	return preg_replace($search, $replace, $data);
}
// STAFF OPTIONS MENU & PAGES
function veStaff_options_parent_menu() {
	// A dummy page to allow attaching submenus of both regular and acf submenus.
    add_menu_page(
        __('Staff Settings'),        // Page title
        __('Staff Settings'),        // Menu title
        'manage_options',            // Capability required
        've-staff-settings',         // Menu slug for the dummy menu
        '',                          // No callback needed for dummy
        '',                          // No icon needed
        81                           // Position below the Settings menu
    );
}
add_action('admin_menu', 'veStaff_options_parent_menu');

// ACF OPTIONS PAGES
add_action('acf/init', 'veStaff_acf_options_pages');
function veStaff_acf_options_pages() {

    // Register the main ACF options page as a submenu under the dummy parent
    if (function_exists('acf_add_options_sub_page')) {
        acf_add_options_sub_page(array(
            'page_title'    => __('Staff Settings'),
            'menu_title'    => __('Staff Settings'),
            'menu_slug'     => 've-staff-settings',
            'capability'    => 'manage_options',
            'parent_slug'   => 've-staff-settings',  // Attach to dummy parent
            'redirect'      => false
        ));

        // Add the SMS ACF options subpage as a submenu
        acf_add_options_sub_page(array(
            'page_title'    => __('Staff SMS Settings'),
            'menu_title'    => __('SMS'),
            'parent_slug'   => 've-staff-settings',   // Attach to dummy parent
            'menu_slug'     => 've-staff-sms-settings',
            'capability'    => 'manage_options'
        ));
    }
}

// Add CSS to hide the dummy/duplicate top-level menu
add_action('admin_head', 'hide_dummy_parent_menu_css');
function hide_dummy_parent_menu_css() {
    echo '<style>#toplevel_page_ve-staff-settings .wp-first-item { display: none; }</style>';
}

add_action('acf/render_field_settings/type=text', 'add_readonly_and_disabled_to_text_field');
add_action('acf/render_field_settings/type=textarea', 'add_readonly_and_disabled_to_text_field');
function add_readonly_and_disabled_to_text_field($field) {
    acf_render_field_setting( $field, array(
      'label'      => __('Read Only?','acf'),
      'instructions'  => '',
      'type'      => 'radio',
      'name'      => 'readonly',
      'choices'    => array(
        1        => __("Yes",'acf'),
        0        => __("No",'acf'),
      ),
      'value' => 0,
      'layout'  =>  'horizontal',
    ));
    acf_render_field_setting( $field, array(
      'label'      => __('Disabled?','acf'),
      'instructions'  => '',
      'type'      => 'radio',
      'name'      => 'disabled',
      'choices'    => array(
        1        => __("Yes",'acf'),
        0        => __("No",'acf'),
      ),
      'value' => 0,  
      'layout'  =>  'horizontal',
    ));
  }

/**
 * Merge duplicate terms in a taxonomy by name (case-insensitive).
 */
function merge_duplicate_terms_by_name($taxonomy) {
    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms) || empty($terms)) return;

    $grouped = [];

    foreach ($terms as $term) {
        $key = strtolower(trim($term->name));
        $grouped[$key][] = $term;
    }

    foreach ($grouped as $name => $dupes) {
        if (count($dupes) < 2) continue;

        // Keep the oldest (smallest term_id)
        usort($dupes, fn($a, $b) => $a->term_id - $b->term_id);
        $primary = array_shift($dupes);

        foreach ($dupes as $dup) {
            $posts = get_objects_in_term($dup->term_id, $taxonomy);
            if (!empty($posts)) {
                foreach ($posts as $post_id) {
                    wp_remove_object_terms($post_id, $dup->term_id, $taxonomy);
                    wp_add_object_terms($post_id, $primary->term_id, $taxonomy);
                }
            }
            wp_delete_term($dup->term_id, $taxonomy);
        }
    }
}


/**
 * Trigger merge on demand via admin URL.
 * Example: /wp-admin/?merge_staff_titles=1
 */
add_action('admin_init', function () {
    if ( is_admin() &&
		current_user_can('manage_options') &&
		isset($_GET['merge_staff_titles']) &&
		check_admin_referer('merge_staff_titles') &&
		isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'staff-title' &&
		isset($_GET['post_type']) && $_GET['post_type'] === 'staff'
	) {
		merge_duplicate_terms_by_name('staff-title');
		wp_die('Staff Title terms merged. <a href="' . admin_url('edit-tags.php?taxonomy=staff-title&post_type=staff') . '">Return to Staff Titles</a>');
	}
});

// Generate nonce-protected admin URL for triggering merge
add_action('admin_notices', function () {
    global $pagenow;

    if (
        $pagenow !== 'edit-tags.php' ||
        !current_user_can('manage_options') ||
        $_GET['taxonomy'] !== 'staff-title' ||
        $_GET['post_type'] !== 'staff'
    ) {
        return;
    }

    $url = wp_nonce_url(
        admin_url('edit-tags.php?taxonomy=staff-title&post_type=staff&merge_staff_titles=1'),
        'merge_staff_titles'
    );

    echo '<div class="notice notice-info"><p>';
    echo 'To ensure clean data, you can <strong>merge duplicate Staff Title terms</strong>: <a href="' . esc_url($url) . '">Click here to run merge</a>';
    echo '</p></div>';
});


/**
 * Automatically merge if a duplicate term is added.
 */
add_action('created_term', function ($term_id, $tt_id, $taxonomy) {
    if ($taxonomy !== 'staff-title') return;

    $term = get_term($term_id, $taxonomy);
    if (!$term || is_wp_error($term)) return;

    $name_key = strtolower(trim($term->name));

    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    $matches = array_filter($terms, function ($t) use ($name_key, $term_id) {
        return strtolower(trim($t->name)) === $name_key && $t->term_id !== $term_id;
    });

    if (!empty($matches)) {
        merge_duplicate_terms_by_name($taxonomy);
    }
}, 10, 3);


