jQuery(document).ready(function ($) {
    function disableReadonlyFields() {
        // Disable standard input, textarea, and checkbox fields
        $('.acf-readonly-field input[type="text"], .acf-readonly-field textarea, .acf-readonly-field input[type="checkbox"]').prop('readonly', true).prop('disabled', true);

        // Handle <select> fields
        $('.acf-readonly-field select').each(function () {
            const $select = $(this);

            // Check if it's a Select2-enhanced field
            if ($select.hasClass('select2-hidden-accessible')) {
                // Disable Select2 visually without breaking functionality
                $select.prop('disabled', false); // Ensure value is not lost
                $select.next('.select2-container').css({
                    'pointer-events': 'none',
                    'opacity': '0.5',
                });
            } else {
                // Disable standard <select> fields
                $select.prop('disabled', true).css({
                    'pointer-events': 'none',
                    'background-color': '#f9f9f9',
                });
            }
        });

        // Disable WYSIWYG editor by making content non-editable
        $('.acf-readonly-field .acf-editor-wrap iframe').each(function () {
            const iframe = this;
            const contentDocument = iframe.contentDocument || iframe.contentWindow.document;
            $(contentDocument.body).attr('contenteditable', false);
        });

        // Taxonomy fields (checkbox/radio)
        $('.acf-readonly-field .acf-checkbox-list input[type="checkbox"], .acf-readonly-field .acf-radio-list input[type="radio"]').prop('disabled', true);

        // True/False fields
        $('.acf-readonly-field.acf-field-true-false input[type="checkbox"]').prop('disabled', true);
        $('.acf-readonly-field.acf-field-true-false .acf-switch').css({
            'pointer-events': 'none',
            'opacity': '0.5',
        });

        // Relationship fields
        $('.acf-readonly-field .acf-relationship').each(function () {
            const $relationshipField = $(this);
            $relationshipField.find('input[type="search"]').prop('disabled', true).hide(); // Hide search bar
            $relationshipField.find('.choices').hide(); // Hide choices list
            $relationshipField.find('.selection, .values').css('pointer-events', 'none'); // Disable interaction
        });

        // Image upload fields
        $('.acf-readonly-field.acf-field-image-aspect-ratio-crop .acf-image-uploader-aspect-ratio-crop').css('pointer-events', 'none');

        // Repeater fields
        $('.acf-readonly-field .acf-repeater .acf-button').hide(); // Hide Add button
        $('.acf-readonly-field .acf-repeater .acf-row-handle.remove').hide(); // Hide Remove buttons
    }

    // Run disableReadonlyFields on page load
    disableReadonlyFields();

    // Run disableReadonlyFields each time ACF initializes or refreshes fields
    acf.add_action('load', disableReadonlyFields); // Initial load
    acf.add_action('append', disableReadonlyFields); // For dynamically added/re-rendered fields
    acf.add_action('ready', disableReadonlyFields); // For reloaded fields after interactions
    acf.add_action('acf/setup_fields', disableReadonlyFields); // Triggers when fields are refreshed
});