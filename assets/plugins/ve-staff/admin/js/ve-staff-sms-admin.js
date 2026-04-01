jQuery(document).ready( function() {

   jQuery("#purge-sms-subscribers-btn").click( function(e) {
      e.preventDefault(); 

      jQuery.ajax({
         dataType: 'JSON',
         url : veAjax.ajaxurl,
         data : {
            action: "ve_sms_purge"
         },
         success: function(response) {
            if(response.type == "success") {
               jQuery("#purge-sms-subscribers-response").html(response.purge_data);
            }
            else {
               jQuery("#purge-sms-subscribers-response").html(response.purge_data);
               alert("SMS Purge Error");
            }
         }
      })   

   })

})

jQuery(document).ready(function () {
	// Helper function to get selected checkbox values
    function getCheckboxValues(fieldName) {
		var values = [];

		// Find the parent div by data-name attribute
		var parentDiv = jQuery('[data-name="' + fieldName + '"]').closest('.acf-field');

		// Get the data-key attribute value from the parent div
		var dataKey = parentDiv.data('key');

		// Find all checkboxes with IDs starting with the data-key value
		parentDiv.find('input[id^="acf-' + dataKey + '"]').each(function () {
			if (jQuery(this).is(':checked')) {
				values.push(jQuery(this).val());
			}
		});
		return values;
	}

	
    // Trigger the AJAX call when the checkboxes change
    jQuery('input[type="checkbox"]').change(function () {
        var locations = getCheckboxValues('sms_msg_location');
        var departments = getCheckboxValues('sms_msg_department');

        // Check if both locations and departments have values
        if (locations.length > 0 && departments.length > 0) {
            jQuery.ajax({
                type: 'POST',
                url: veAjax.ajaxurl,
                data: {
                    action: 'get_sms_staff_count', 
                    nonce: veAjax.nonce,
                    locations: locations,
                    departments: departments,
                },
                success: function (response) {
                    var responseData = JSON.parse(response);
					//console.log(responseData);
                    var count = responseData.count;
                    var staffTitles = responseData.staffTitles.join(', ');

					jQuery('[data-name="sms_recipients_number"] input').val(count);
					jQuery('[data-name="sms_recipients"] textarea').val(staffTitles);
                },
            });
        } else {
            // If either locations or departments is empty, clear the result container
            jQuery('[data-name="sms_recipients_number"] input').val(0);
			jQuery('[data-name="sms_recipients"] textarea').val('');
        }
    });
});

