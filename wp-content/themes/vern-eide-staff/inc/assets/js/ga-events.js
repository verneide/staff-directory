window.addEventListener('load', function() {
	function startEventTracking(){
		console.log('Event Tracking Started');
		staffFilterEvents();
		staffContactInfoEvents();
	}
	startEventTracking();
});

function staffFilterEvents(){
  var isSelecting = false; // Flag to track if the user is currently selecting
  var lastInteractionTime = 0; // Timestamp of the last interaction

  // Debounce function for input field events
  function debounce(func, wait) {
    var timeout;
    return function() {
      var context = this, args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(function() { func.apply(context, args); }, wait);
    };
  }

  // Function to handle select field events
  function handleSelectEvent(selectElement) {
    var selectText = selectElement.options[selectElement.selectedIndex].text;
    dataLayer.push({
      'event': 'staff_filter_select',
      'staff_filter_type': selectElement.id,
      'staff_filter_value': selectText
    });
  }

  // Combined location and department filter event handling
  var locationSelect = document.querySelector('#employeeHeadingWrapper #locationfilterselect');
  var departmentSelect = document.querySelector('#employeeHeadingWrapper #departmentfilterselect');
  function handleCombinedFilterEvent() {
    var now = Date.now();
    // Check if sufficient time has passed since last interaction
    if (!isSelecting && now - lastInteractionTime >= 1000) {
      var locationText = locationSelect.options[locationSelect.selectedIndex].text;
      var departmentText = departmentSelect.options[departmentSelect.selectedIndex].text;
      var combinedValue = locationText + ' - ' + departmentText;

      dataLayer.push({
        'event': 'staff_filter_select',
        'staff_filter_type': 'locationdepartmentfilterselect',
        'staff_filter_value': combinedValue
      });
    }
  }

  // Set up a debounce function to handle the combined event
  var debounceCombinedEvent = function() {
    isSelecting = false; // User is no longer selecting
    lastInteractionTime = Date.now(); // Update the timestamp
    setTimeout(handleCombinedFilterEvent, 1000); // Wait for 1000ms before firing the event
  };

  // Event listener for focus and blur on the select elements
  function setupSelectListeners(selectElement) {
    selectElement.addEventListener('focus', function() { isSelecting = true; });
    selectElement.addEventListener('blur', function() { isSelecting = false; debounceCombinedEvent(); });
    selectElement.addEventListener('change', function() { lastInteractionTime = Date.now(); });
  }

  if (locationSelect && departmentSelect) {
    setupSelectListeners(locationSelect);
    setupSelectListeners(departmentSelect);
  } else {
    // Handle individual select elements if they exist
    if (locationSelect) {
      locationSelect.addEventListener('change', function() { handleSelectEvent(locationSelect); });
    }
    if (departmentSelect) {
      departmentSelect.addEventListener('change', function() { handleSelectEvent(departmentSelect); });
    }
  }

  // Handle other select fields within the employeeHeadingWrapper div
  var otherSelectFields = document.querySelectorAll('#employeeHeadingWrapper select:not(#locationfilterselect):not(#departmentfilterselect)');
  otherSelectFields.forEach(function(select) {
    select.addEventListener('change', function() { handleSelectEvent(select); });
  });

  // Event handler for input fields within the employeeHeadingWrapper div
  var handleInputEvent = function(event) {
    var value = event.target.value;
    var filterType = event.target.id;
    dataLayer.push({
      'event': 'staff_filter_input',
      'staff_filter_type': filterType,
      'staff_filter_value': value
    });
  };

  // Attach event listeners to input fields within the employeeHeadingWrapper div
  var inputFields = document.querySelectorAll('#employeeHeadingWrapper input[type="text"]');
  inputFields.forEach(function(input) {
    input.addEventListener('input', debounce(handleInputEvent, 1000));
  });
  
  console.log('Staff Filter Events Complete');
}

function staffContactInfoEvents() {
	// Listen for the shown event on all collapse elements
    var collapseElements = document.querySelectorAll('.collapse');
    
    collapseElements.forEach(function(collapseElement) {
      collapseElement.addEventListener('veshown.bs.collapse', function() {
        // Check if the collapse element has the veshow class
        if (this.classList.contains('veshow')) {
          // Find the closest employee tile and get the employee name
          var employeeName = this.closest('.employee-tile').getAttribute('data-employee-name');
          // Push the event to the dataLayer
          dataLayer.push({
            'event': 'staff_contact_info_view',
            'employee_name': employeeName
          });
        }
      });
    });
	console.log('Staff Contact Info Events Complete');
}