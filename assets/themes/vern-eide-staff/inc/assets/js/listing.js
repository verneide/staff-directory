// Javascript Functions //
(function(funcName, baseObj) {
    // The public function name defaults to window.docReady
    // but you can pass in your own object and own function name and those will be used
    // if you want to put them in a different namespace
    funcName = funcName || "docReady";
    baseObj = baseObj || window;
    var readyList = [];
    var readyFired = false;
    var readyEventHandlersInstalled = false;

    // call this when the document is ready
    // this function protects itself against being called more than once
    function ready() {
        if (!readyFired) {
            // this must be set to true before we start calling callbacks
            readyFired = true;
            for (var i = 0; i < readyList.length; i++) {
                // if a callback here happens to add new ready handlers,
                // the docReady() function will see that it already fired
                // and will schedule the callback to run right after
                // this event loop finishes so all handlers will still execute
                // in order and no new ones will be added to the readyList
                // while we are processing the list
                readyList[i].fn.call(window, readyList[i].ctx);
            }
            // allow any closures held by these functions to free
            readyList = [];
        }
    }

    function readyStateChange() {
        if ( document.readyState === "complete" ) {
            ready();
        }
    }

    // This is the one public interface
    // docReady(fn, context);
    // the context argument is optional - if present, it will be passed
    // as an argument to the callback
    baseObj[funcName] = function(callback, context) {
        if (typeof callback !== "function") {
            throw new TypeError("callback for docReady(fn) must be a function");
        }
        // if ready has already fired, then just schedule the callback
        // to fire asynchronously, but right away
        if (readyFired) {
            setTimeout(function() {callback(context);}, 1);
            return;
        } else {
            // add the function and context to the list
            readyList.push({fn: callback, ctx: context});
        }
        // if document already ready to go, schedule the ready function to run
        if (document.readyState === "complete") {
            setTimeout(ready, 1);
        } else if (!readyEventHandlersInstalled) {
            // otherwise if we don't have event handlers installed, install them
            if (document.addEventListener) {
                // first choice is DOMContentLoaded event
                document.addEventListener("DOMContentLoaded", ready, false);
                // backup is window load event
                window.addEventListener("load", ready, false);
            } else {
                // must be IE
                document.attachEvent("onreadystatechange", readyStateChange);
                window.attachEvent("onload", ready);
            }
            readyEventHandlersInstalled = true;
        }
    }
})("docReady", window);  
 
// // *** CREATE NEW JQUERY OBJECT TO AVOID CONFLICTS *** //
var $j = jQuery.noConflict();
	
var list;
var listid;
var locfilterenabled;
var deptfilterenabled;
var tagfilterenabled;
var selectedDepartment;
var selectedLocation;
var selectedLocationFilter;
var objdept;
var objloc;
var deptheaders;
var staffcards;

/* PAGE READY */
$j(window).on("load", function() {
	$j('.loading-section').remove();
});
	
$j( window ).on("load", function() {
  /* FILTERS AVAILABLE */
	if($j('#departmentfilterselect').length){deptfilterenabled = true;}
	if($j('#locationfilterselect').length){locfilterenabled = true;}
	if($j('#tagfilterselect').length){tagfilterenabled = true;}

	/* RESET ALL FILTERS */
		$j.fn.veResetFilters = function(list){ 
				listid = list.data("list-id");
				$j('.ve-hidden').removeClass('ve-hidden');
				if(deptfilterenabled){
					$j('#departmentfilterselect').val($j('#departmentfilterselect option:first').val());
				}
				if(locfilterenabled){
					$j('#locationfilterselect').val($j('#locationfilterselect option:first').val());
				}
				if(tagfilterenabled){
					$j("#tagfilterselect").val($j("#tagfilterselect option:first").val());
				};
				if ($j('#employeenamesearch').length){
					$j('#employeenamesearch').val('');
				}
				if ($j('#employeeextsearch').length){
					$j('#employeeextsearch').val('');
				}
				if ($j('.ve-no-results').length){
					$j('.ve-no-results').remove();
				}
				if ($j('.department-list').length){
					$j('.department-list').remove();
				}
		};

	/* RESET TEXT INPUT FILTERS */
		$j.fn.veResetTextFilters = function(){ 
			if ($j('.ve-no-results').length){
				$j(".ve-no-results").remove();
			}
			if ($j('#employeenamesearch').length){
				$j('#employeenamesearch').val('');
			}
			if ($j('#employeeextsearch').length){
				$j('#employeeextsearch').val('');
			}
			if ($j('.department-list').length){
				$j(".department-list").remove();
			}
		};

	/* RESET SELECT FILTERS */
		$j.fn.veResetSelectFilters = function(){ 
			if ($j('.ve-no-results').length){
				$j(".ve-no-results").remove();
			}
			if(deptfilterenabled){
				$j("#departmentfilterselect").val($j("#departmentfilterselect option:first").val());
			};
			if(locfilterenabled){
				$j("#locationfilterselect").val($j("#locationfilterselect option:first").val());
			};
			if(tagfilterenabled){
				$j("#tagfilterselect").val($j("#tagfilterselect option:first").val());
			};
		};
	
	/* RESET LOC/DEPT FILTERS */
		$j.fn.veResetLocDeptSelectFilters = function(){ 
			if ($j('.ve-no-results').length){
				$j(".ve-no-results").remove();
			}
			if(deptfilterenabled){
				$j("#departmentfilterselect").val($j("#departmentfilterselect option:first").val());
			};
			if(locfilterenabled){
				$j("#locationfilterselect").val($j("#locationfilterselect option:first").val());
			};
		};
					   
	/* RESET BUTTON */
		$j('#resetFilterBtn').click(function() {
			list = $j(this).parents("div #partialView").last();
			$j.fn.veResetFilters(list);
		});

	/* NO RESULTS */
		$j.fn.veNoResults = function(){ 
			deptheaders = $j('.department-header').not('.ve-hidden').length;
			staffcards = $j('.employee-tile').not('.ve-hidden').length;
			if (deptheaders == 0 && staffcards == 0){
				$j(".employee-list .ve-row").append( '<h3 class="ve-no-results"><center>No Results Found</center></h3>' );
			};
		};

    /* DEPARTMENT / LOCATION SELECTOR CHANGE */
    $j('#departmentfilterselect, #locationfilterselect').change(function() {
		$j.fn.veResetTextFilters();
		if (deptfilterenabled){
			selectedDepartment = $j('#departmentfilterselect').val();
		}
		if (locfilterenabled && deptfilterenabled){
			/* Location & Department Filter Enabled */
			selectedLocation = $j('#locationfilterselect').val();
			if (selectedLocation == "all" && selectedDepartment == "all") {
				$j('.employee-tile, .department-header').removeClass('ve-hidden');
			} else if (selectedLocation == "all" && selectedDepartment != "all") {
				$j('.employee-tile, .department-header').not('.ve-hidden').addClass('ve-hidden');	 
				$j('.employee-tile[data-dept="' + selectedDepartment + '"], .department-header[data-dept="' + selectedDepartment + '"]').removeClass('ve-hidden');
			} else {
				$j('.employee-tile, .department-header').not('.ve-hidden').addClass('ve-hidden');
				$j('.department-header[data-loc*="' + selectedLocation + '"], .employee-tile[data-loc*="' + selectedLocation + '"]').each( function( index, element){
					objdept = $j(this).attr("data-dept");
					if(objdept == selectedDepartment || selectedDepartment == "all"){
						$j(this).removeClass('ve-hidden');
					} else {
						$j(this).addClass('ve-hidden');
					};
				});
				selectedLocationFilter = $j('#locationfilterselect option[value='+selectedLocation+']').data('filter');
				if (selectedLocationFilter == 0){
					$j('.department-header').not('.ve-hidden').addClass('ve-hidden');
				}
			};
		} else if (locfilterenabled && !deptfilterenabled){
			/* Location Filter Only Enabled*/
			selectedLocation = $j('#locationfilterselect').val();
			if (selectedLocation == "all") {
				$j('.employee-tile').removeClass('ve-hidden');
			} else {
				$j('.employee-tile').not('.ve-hidden').addClass('ve-hidden');
				$j('.employee-tile[data-loc*="' + selectedLocation + '"]').removeClass('ve-hidden');
			};
		} else {
			if (selectedDepartment == "all") {
				$j('.employee-tile, .department-header').removeClass('ve-hidden');
			} else {
				$j('.employee-tile, .department-header').not('.ve-hidden').addClass('ve-hidden');
				$j('.employee-tile[data-dept="' + selectedDepartment + '"], .department-header[data-dept="' + selectedDepartment + '"]').removeClass('ve-hidden');
			};
		};
		$j.fn.veNoResults();
    });
    
    /* EMPLOYEE SEARCH */
		$j('#employeenamesearch').keyup(function() {
			var searchedName = this.value.toUpperCase();
			$j.fn.veResetSelectFilters();
			if (searchedName.length === 0) {
				$j('.employee-tile, .department-header').removeClass('ve-hidden');
			} else {
				$j('.employee-tile, .department-header').addClass('ve-hidden');
				$j('.employee-tile[data-employee-name*="' + searchedName + '"]').removeClass('ve-hidden');
			}
			$j.fn.veNoResults();
		});

    /* EXT SEARCH */
		$j('#employeeextsearch').keyup(function() {
			var searchedExt = this.value.toUpperCase();
			$j.fn.veResetSelectFilters();
			if (searchedExt.length === 0) {
				$j('.employee-tile').removeClass('ve-hidden');
				$j('.department-header').removeClass('ve-hidden');
			} else {
				$j('.employee-tile').addClass('ve-hidden');
				$j('.department-header').addClass('ve-hidden');
				$j('.employee-tile[data-ext*="' + searchedExt + '"]').removeClass('ve-hidden');
			}
			$j.fn.veNoResults();
		});
	
	/* TAG SEARCH */
	$j('#tagfilterselect').change(function() {
		$j.fn.veResetLocDeptSelectFilters();
		$j.fn.veResetTextFilters();
		var selectedTag = $j(this).val();
		if (selectedTag == "all") {
			$j('.employee-tile, .department-header').removeClass('ve-hidden');
		} else {
			$j('.employee-tile').addClass('ve-hidden');
			$j('.department-header').addClass('ve-hidden');
			$j('.employee-tile[data-tags*="' + selectedTag + '"]').removeClass('ve-hidden');
		}
		$j.fn.veNoResults();
	});

	/* PREVENT ENTER ON EMPLOYEE SEARCH */
	$j('#employeenamesearch').keypress(
	  function(event){
		if (event.which == '13') {
		  event.preventDefault();
		}
	});

	/* PREVENT ENTER ON INPUT BOX ENTIRE PAGE*/
	$j(document).keypress(
	  function(event){
		if (event.which == '13') {
		  event.preventDefault();
		}
	});

	/* VIEW MORE BUTTON */
		$j('#viewMoreBtn').click(function() {
			$j('.employee-tile').removeClass('ve-hidden');
			$j('.viewMoreWrapper').addClass('ve-hidden');
		});
		
}); /* END Main */


window.addEventListener('load', function() {
	/* ADD TAG MAANGER TO HEAD */
	$j("head").append("<!-- VE Staff Google Tag Manager -->\r\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\r\nnew Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\r\nj=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\r\n'https:\/\/www.googletagmanager.com\/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\r\n})(window,document,'script','dataLayer','GTM-MJTG6FV');<\/script>\r\n<!-- End Google Tag Manager -->");

	/* ADD TAG MAANGER BODY TO BODY */
	$j("body").prepend("<!-- VE Staff Google Tag Manager (noscript) -->\r\n<noscript><iframe src=\"https:\/\/www.googletagmanager.com\/ns.html?id=GTM-MJTG6FV\"\r\nheight=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"><\/iframe><\/noscript>\r\n<!-- End Google Tag Manager (noscript) -->");
});

/* Checks if the site hosted by Dealerspike, then adds a css class to the body if it is */
document.addEventListener('DOMContentLoaded', function () {
   // Check if the page contains the specified meta tag with "dealerspike.com" in its content
   const metaTag = document.querySelector('meta[name="author"][content*="dealerspike.com"]');
   if (metaTag) {
      // If the meta tag is found, add the class "ve-dealerspike" to the body's classList
      document.body.classList.add('ve-dealerspike');
   }
});

document.addEventListener('DOMContentLoaded', function () {
var velistingModals = document.getElementById("listingmodals");
// Get a reference to the body element
var pgbody = document.body;
// Loop through and move each child element of listingModals to the bottom of the body
if (velistingModals){
	while (velistingModals.firstChild) {
		pgbody.appendChild(velistingModals.firstChild);
	}
}
});

/* document.addEventListener("DOMContentLoaded", function () {
    // Check if we're on desktop
    if (window.innerWidth >= 768) {
        // Show the offcanvas content by default
        var veoffcanvasElement = document.getElementById('offcanvasFiltersTop');
        var veoffcanvasInstance = new bootstrap.Offcanvas(veoffcanvasElement);
        veoffcanvasInstance.veshow();
    }
}); */

// Get the current site's domain, ensuring "www" is removed if applicable
function getDomainWithoutWWW() {
    var hostname = window.location.hostname;

    // Remove "www." if it exists at the start of the hostname
    if (hostname.startsWith("www.")) {
        hostname = hostname.substring(4);
    }

    return hostname;
}

// Replace {{sitedomain}} in href attributes within .employee-list divs
function replaceSiteDomainInLinks() {
    // Get the current domain without www
    var currentDomain = getDomainWithoutWWW();
    
    // Get all a elements within .employee-list div
    var links = document.querySelectorAll('.employee-list a');

    // Iterate through each link and replace {{sitedomain}} with the current domain
    links.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && href.includes('{{sitedomain}}')) {
            link.setAttribute('href', href.replace('{{sitedomain}}', currentDomain));
        }
    });
}

// Run the replaceSiteDomainInLinks function after the window has loaded
window.addEventListener('load', replaceSiteDomainInLinks);

