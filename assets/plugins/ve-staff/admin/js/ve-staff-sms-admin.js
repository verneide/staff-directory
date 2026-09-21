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

jQuery(document).ready(function ($) {
	var $recipients = $('#ve-sms-recipients');
	if (!$recipients.length) return;
	if ($.fn.select2) {
		$recipients.select2({width: '100%', ajax: {url: veAjax.ajaxurl, dataType: 'json', delay: 250, data: function (params) {
			return {action: 've_sms_search_recipients', nonce: veAjax.nonce, q: params.term || ''};
		}}});
	}
	$('#ve-sms-load-recipients').on('click', function () {
		var values = function (name) { return $('[data-name="' + name + '"] input:checked').map(function () { return this.value; }).get(); };
		$.post(veAjax.ajaxurl, {action: 've_sms_load_recipients', nonce: veAjax.nonce, locations: values('sms_msg_location'), departments: values('sms_msg_department')}).done(function (response) {
			if (!response.success) { window.alert(response.data.message || 'Recipients could not be loaded.'); return; }
			$recipients.empty(); response.data.forEach(function (recipient) { $recipients.append(new Option(recipient.text, recipient.id, true, true)); }); $recipients.trigger('change');
		});
	});
});
