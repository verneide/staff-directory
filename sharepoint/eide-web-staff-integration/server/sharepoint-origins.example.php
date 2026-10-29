<?php
/** Copy to wp-content/mu-plugins/ve-staff-sharepoint-origins.php and set your tenant. */
add_filter('ve_staff_sharepoint_allowed_origins', function () {
    return array('https://YOUR-TENANT.sharepoint.com');
});
