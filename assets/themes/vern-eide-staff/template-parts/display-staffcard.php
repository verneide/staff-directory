<?php
/**
 * Template part for displaying staff card
 */

// Helper functions that don’t break existing $args references.
if (!function_exists('ve_staff_render_tags')) {
	function ve_staff_render_tags($stafftags, $displaytype = '') {
		if (empty($stafftags)) return '';

		$filtered = [];

		foreach ($stafftags as $tag) {
			// Handle both object and array types gracefully
			$slug = '';
			$name = '';

			if (is_object($tag)) {
				$slug = $tag->slug ?? '';
				$name = $tag->name ?? '';
			} elseif (is_array($tag)) {
				if (isset($tag['slug'])) {
					$slug = $tag['slug'];
					$name = $tag['name'] ?? '';
				} elseif (isset($tag['term']['slug'])) {
					$slug = $tag['term']['slug'];
					$name = $tag['term']['name'] ?? '';
				}
			}

			if (empty($slug)) continue;

			// Display type filtering
			if ($displaytype === 'anniversary') {
				if (preg_match('/^\d{1,2}-year$/', $slug)) {
					$filtered[] = $name;
				}
			} else {
				if (isset($tag['card_visible']) && $tag['card_visible'] == '1') {
					$filtered[] = $name;
				}
			}
		}

		if (empty($filtered)) return '';
		return '<span class="ve-badge bg-primary">' . esc_html(implode(', ', $filtered)) . '</span>';
	}
}

if (!function_exists('ve_staff_render_location')) {
	function ve_staff_render_location($args, $showloclogo, $location_id) {
		if (empty($args['data']['location']) && empty($args['data']['deptname'])) return '';

		ob_start(); ?>
		<div class="employee-location-wrap">
			<hr>
			<?php if ($args['data']['location']) :
				if ($showloclogo) :
					$logo = get_field('location_brand_logo', 'location_' . $location_id);
					if ($logo) : ?>
						<div class="location-logo <?php echo 'location_' . $location_id; ?>">
							<img src="<?php echo esc_url($logo['sizes']['medium']); ?>" alt="<?php echo esc_attr($args['data']['location']); ?>">
						</div>
					<?php else : ?>
						<h6 class="ve-black ve-block location"><i><?php echo esc_html($args['data']['location']); ?></i></h6>
					<?php endif;
				else : ?>
					<h6 class="ve-black ve-block location"><i><?php echo esc_html($args['data']['location']); ?></i></h6>
				<?php endif;
			endif;

			if ($args['data']['deptname']) : ?>
				<h6 class="ve-black ve-block department"><i><?php echo esc_html($args['data']['deptname']); ?></i></h6>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

// Variables
$stafftags          = $args['data']['stafftags'];
$staffcardcss       = $args['settings']['staffcardcss'];
$stafftileclass     = '';
$displaytype        = $args['type']['type'];
$displayseccolor    = $args['settings']['secondarycolor'];
$showloclogo        = $args['settings']['showloclogo'];
$location_id        = $args['data']['location_id'];

// CARD TYPES
switch ($displaytype) {
	case 'birthday':
		$birthdayinfo     = $args['data']['birthdayinfo'];
		$birthdaytoday    = ($birthdayinfo['birthday_date'] == date('Y-m-d'));
		$birthdayformatted = $birthdayinfo['birthday_month_day'];
		?>
		<div class="<?php echo $staffcardcss ?> employee-tile ve-width-100 <?php echo $stafftileclass; ?>"
			data-total-staff="<?php echo $args['data']['staffcountnum']; ?>"
			data-displaytype="<?php echo esc_attr($displaytype); ?>">
			<div class="ve-row border-radius-2em employee-wrapper">
				<div class="ve-col-md-12 employee-tile-img ve-text-center ve-pad-none ve-margin-bottom-sm ve-margin-top-sm">
					<?php echo ve_staff_render_tags($stafftags, $displaytype); ?>
					<img class="skip-lazy" alt="<?php echo esc_attr($args['data']['name']); ?> at <?php echo esc_attr($args['data']['location']); ?>" src="<?php echo esc_url($args['data']['photourl']); ?>">
				</div>
				<div class="ve-col-md-12 employee-details-wrapper ve-bg-grey ve-width-100 <?php echo $birthdaytoday ? '' : 'border-radius-bottom-2em'; ?>">
					<div class="employee-details ve-pad-md">
						<h2 class="ve-boldest ve-black"><?php echo esc_html($args['data']['name']); ?></h2>
						<h4 class="ve-black"><?php echo esc_html($args['data']['title']); ?></h4>
						<hr>
						<?php echo ve_staff_render_location($args, $showloclogo, $location_id); ?>
						<h4 class="ve-black ve-bold ve-pad-top-sm"><?php echo esc_html($birthdayformatted); ?></h4>
					</div>
				</div>
				<?php if ($birthdaytoday): ?>
					<div class="ve-col-md-12 ve-text-center ve-pad-none">
						<div class="ve-birthday" style="background-color: <?php echo esc_attr($displayseccolor); ?>">
							<h3 class="ve-boldest">Happy Birthday <?php echo esc_html(trim($args['data']['fname'])); ?>!</h3>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		break;

	case 'newpromo':
		$newpromoinfo = $args['data']['newpromoinfo'];
		$message = $newpromoinfo['newpromo_type'] === 'promotion' ? 'Congrats' : 'Welcome';
		?>
		<div class="<?php echo $staffcardcss ?> employee-tile ve-width-100 <?php echo $stafftileclass; ?>"
			data-total-staff="<?php echo $args['data']['staffcountnum']; ?>"
			data-displaytype="<?php echo esc_attr($displaytype); ?>">
			<div class="ve-row border-radius-2em employee-wrapper">
				<div class="ve-col-md-12 employee-tile-img ve-text-center ve-pad-none ve-margin-bottom-sm ve-margin-top-sm">
					<?php echo ve_staff_render_tags($stafftags, $displaytype); ?>
					<img class="skip-lazy" alt="<?php echo esc_attr($args['data']['name']); ?>" src="<?php echo esc_url($args['data']['photourl']); ?>">
				</div>
				<div class="ve-col-md-12 employee-details-wrapper ve-bg-grey ve-width-100">
					<div class="employee-details ve-pad-md">
						<h2 class="ve-boldest ve-black"><?php echo esc_html($args['data']['name']); ?></h2>
						<h4 class="ve-black"><?php echo esc_html($args['data']['title']); ?></h4>
						<?php echo ve_staff_render_location($args, $showloclogo, $location_id); ?>
						<h4 class="ve-black ve-bold ve-pad-top-sm"><?php echo date('F Y', strtotime($newpromoinfo['newpromo_date'])); ?></h4>
					</div>
				</div>
				<div class="ve-col-xs-12 ve-text-center ve-pad-none">
					<div class="ve-message" style="background-color: <?php echo esc_attr($displayseccolor); ?>">
						<h3 class="ve-boldest"><?php echo esc_html($message . ' ' . trim($args['data']['fname'])) ?>!</h3>
					</div>
				</div>
			</div>
		</div>
		<?php
		break;

	case 'anniversary':
		// Anniversary display
		$anniv_tags = array_filter($stafftags, fn($t) => preg_match('/^\d{1,2}-year$/', $t['slug']));
		$anniv_label = implode(', ', array_column($anniv_tags, 'name'));
		?>
		<div class="<?php echo $staffcardcss ?> employee-tile ve-width-100 <?php echo $stafftileclass; ?>"
			data-total-staff="<?php echo $args['data']['staffcountnum']; ?>"
			data-displaytype="<?php echo esc_attr($displaytype); ?>">
			<div class="ve-row border-radius-2em employee-wrapper">
				<div class="ve-col-md-12 employee-tile-img ve-text-center ve-pad-none ve-margin-bottom-sm ve-margin-top-sm">
					<?php echo ve_staff_render_tags($stafftags, $displaytype); ?>
					<img class="skip-lazy" alt="<?php echo esc_attr($args['data']['name']); ?>" src="<?php echo esc_url($args['data']['photourl']); ?>">
				</div>
				<div class="ve-col-md-12 employee-details-wrapper ve-bg-grey border-radius-bottom-2em">
					<div class="employee-details ve-pad-md">
						<h2 class="ve-boldest ve-black"><?php echo esc_html($args['data']['name']); ?></h2>
						<h4 class="ve-black"><?php echo esc_html($args['data']['title']); ?></h4>
						<?php echo ve_staff_render_location($args, $showloclogo, $location_id); ?>
						<?php if ($anniv_label): ?>
							<?php
							// Extract the first numeric value (e.g., "5" from "5-Year Anniversary")
							preg_match('/\d+/', $anniv_label, $matches);
							$years = isset($matches[0]) ? (int)$matches[0] : 0;

							if ($years === 1) {
								$message = "Congrats on Your First Year!";
							} else {
								$message = "Congrats on {$years} Years!";
							}
							?>
							<h4 class="ve-black ve-bold ve-pad-top-sm"><?php echo esc_html($message); ?></h4>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
		break;

	default:
		$btmmessage = $args['settings']['customizations']['display_bottom_message'] ?? '';
		?>
		<div class="<?php echo $staffcardcss ?> employee-tile ve-width-100 <?php echo $stafftileclass; ?>"
			data-total-staff="<?php echo $args['data']['staffcountnum']; ?>"
			data-displaytype="<?php echo esc_attr($displaytype); ?>">
			<div class="ve-row border-radius-2em employee-wrapper">
				<div class="ve-col-md-12 employee-tile-img ve-text-center ve-pad-none ve-margin-bottom-sm ve-margin-top-sm">
					<?php echo ve_staff_render_tags($stafftags, $displaytype); ?>
					<img class="skip-lazy" alt="<?php echo esc_attr($args['data']['name']); ?>" src="<?php echo esc_url($args['data']['photourl']); ?>">
				</div>
				<div class="ve-col-md-12 employee-details-wrapper ve-bg-grey <?php echo empty($btmmessage) ? 'border-radius-bottom-2em' : ''; ?>">
					<div class="employee-details ve-pad-md">
						<h2 class="ve-boldest ve-black"><?php echo esc_html($args['data']['name']); ?></h2>
						<h4 class="ve-black"><?php echo esc_html($args['data']['title']); ?></h4>
						<?php echo ve_staff_render_location($args, $showloclogo, $location_id); ?>
					</div>
					<?php if (!empty($btmmessage)): ?>
						<div class="ve-text-center ve-pad-none">
							<div class="ve-message" style="background-color: <?php echo esc_attr($displayseccolor); ?>">
								<h3 class="ve-boldest"><?php echo esc_html($btmmessage); ?></h3>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		break;
}
?>