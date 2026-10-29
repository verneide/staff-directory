<?php
/** Optional override: copy to wp-content/mu-plugins/ve-staff-sharepoint-origins.php. */
add_filter('ve_staff_sharepoint_allowed_origins', function () {
    return array('https://verneide.sharepoint.com');
});
