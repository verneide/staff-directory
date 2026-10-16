<?php
/** Shared staff tag presentation. Read settings here so cached staff data stays current. */
function ve_staff_tag_pills($tags, $placement = 'photo') {
	$pills = '';
	foreach ((array) $tags as $tag) {
		$tag = (array) $tag;
		$id = (int) ($tag['id'] ?? $tag['term_id'] ?? 0);
		$visible = $id ? get_term_meta($id, 'card_visible', true) : ($tag['card_visible'] ?? false);
		if (!$visible || empty($tag['name'])) continue;
		$setting = $id ? get_term_meta($id, 'tag_placement', true) : ($tag['tag_placement'] ?? 'auto');
		$celebration = preg_match('/birthday|new|anniversary/i', $tag['name']);
		$position = $celebration || $setting === 'photo' ? 'photo' : 'title';
		if ($position !== $placement) continue;
		$color = sanitize_hex_color($id ? get_term_meta($id, 'tag_color', true) : ($tag['tag_color'] ?? ''));
		$icon = $id ? get_term_meta($id, 'tag_icon', true) : ($tag['tag_icon'] ?? '');
		$style = $color ? ' style="--ve-tag-color:' . esc_attr($color) . '"' : '';
		$pills .= '<span class="ve-staff-tag"' . $style . '>';
		if ($icon) $pills .= '<span class="ve-staff-tag-icon" aria-hidden="true">' . esc_html($icon) . '</span>';
		$pills .= '<span>' . esc_html($tag['name']) . '</span></span>';
	}
	return $pills ? '<div class="ve-staff-tags ve-staff-tags--' . esc_attr($placement) . '">' . $pills . '</div>' : '';
}
