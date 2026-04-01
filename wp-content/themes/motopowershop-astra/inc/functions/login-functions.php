<?php
// Function to change the WordPress login logo
function mps_custom_login_logo() {
    ?>
    <style type="text/css">
        #login h1 a {
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/inc/imgs/custom-logo.png);
            width: 100%; /* Adjust the width based on your logo */
            height: 80px; /* Adjust the height based on your logo */
            background-size: contain;
            background-repeat: no-repeat;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'mps_custom_login_logo');