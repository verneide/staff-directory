<?php
global $excludeddepartments, $listinglocations, $output, $showdepartments, $showfilters, $showtitles, $showbios, $type, $internal, $group, $debug, $showpgtitle, $rowcolumns, $showfullname, $showfulllastname, $webvisiblebypass, $loggedin, $locationtaxonomy, $pgtypecssid, $htmlcontent, $listid, $listingtype, $listingmodals;


/* START GENERATING HTML FOR SCRIPT */
ob_start();
	
// LISTING VARIABLES
// Staff Card Number
global $staffCardNum; // USED TO NUMBER EACH STAFF CARD
$staffCardNum = 1;
	
	//DEBUG INFO SHOWN IF DEBUG SET IN URL
	if ($debug == true){ ?>
	<div id="debugInfo" style="width: 100%; display: block;">
		<p><strong>DEBUG MODE INFO:</strong></p>
		<?php 
		echo "User Can Manage Options: ";
		echo $loggedin ? 'true' : 'false'; 
		echo '<br>';
		echo 'Show Departments: '.$showdepartments;
		echo '<br>';
		echo 'Show Page Title: '.$showpgtitle;
		?>
	</div>
	<?php } // END DEBUG INFO
	
?>
<div class="ve velist-<?php echo $listid ?>" data-list-type="<?php echo $listingtype ?>" data-list-id="<?php echo $listid ?>">
    <div class="ve-container-fluid ve-pad-none ve-block ve-clear-fix" id="pageContainer">
        <div class="section-container ve-pad-none ve-pad-bottom-lg ve-clear-fix ve-text-left ve-col-sm-12" <?php echo $pgtypecssid ?> style="background-color: white;">
            <?php // List Filter
			if ($showfilters !== FALSE){
			?>
				<!-- Button to toggle offcanvas on mobile -->
				<button class="ve-secondary-button-grey close-filters-btn d-block d-md-none ve-sticky-top" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasFiltersTop" aria-controls="offcanvasFiltersTop">View Filters</button>

				<!-- Offcanvas Component -->
				<div class="offcanvas offcanvas-top ve-sticky-top filters" tabindex="-1" id="offcanvasFiltersTop" aria-labelledby="offcanvasTopLabel">
				  <div class="offcanvas-header ve-pad-none d-block d-md-none">
					<h5 id="offcanvasTopLabel">Staff Filters</h5>
				  </div>
				  <div class="offcanvas-body ve-pad-none">
					<?php
								get_template_part( 
									'template-parts/list-filter', 
									null, 
									array( 
										'data' 	   => array(
													'output' => $output,
										),
										'type'	   => array(
													'internal' => $internal,
													'group' => $group,
										),
									)
								); 
					 ?>
					  <button type="button" class="ve-secondary-button-grey close-filters-btn d-block d-md-none" data-bs-dismiss="offcanvas" aria-label="Close">Close Filters</button>
				  </div>
				</div>
			<?php
			}
			?>
        <div class="ve-pad-none ve-pad-top-md" style="margin: auto;">
            <div class="ve-pad-none employee-list" style="margin: auto;">
                <div class="ve-row">
        <?php /** DEPARTMENT LOOP **/
        foreach ($output as $k => $v){
              $deptorder = $v["dept_order"];
              $deptid = $v["dept_id"];
              $deptname = $v["dept_name"];
              $deptstaff = $v["dept_staff"];
			  $deptlocs = array();
			
			if ($showdepartments !== FALSE){
				// Load Department Headers with Locations Included
				foreach ($deptstaff as $k => $v) {
					// SKIP EMPLOYEE IF WEB VISIBLE FALSE BUT NOT INTERNAL
					$webvisible = $v["website_visible"];
					$webvisiblestatus = $webvisible;
					if ($internal && $webvisiblestatus == TRUE){
						$webvisibleclass = 'webvisible';
					} else {
						$webvisibleclass = '';
					}
					if (is_null($webvisible) || isset($internal) || isset($webvisiblebypass)){
						$webvisible = TRUE; //SET INITIAL STAFF WITH NULL TO TRUE TO MAKE VISIBLE
					}
					if ($webvisible == FALSE){
						continue; //SKIP TO NEXT
					}

					// CONTINUED LOOP
					$locationsall = $v["locations_listed"];
					$emplocationslisted = array();
					foreach ($locationsall as $emplocation){
						// Add locations to department locations array
						$deptlocs[] = str_pad($emplocation, 3, '0', STR_PAD_LEFT);
					}

				}

				// Get unique locations for department
				$deptlocs = array_values(array_unique($deptlocs));

				?>
				<div class="ve-pad-sm ve-width-100 ve-margin-bottom-lg department-header ve-col-sm-12 dept-<?php echo $deptid ?>" style="background: #f5f5f5; border-radius: 10px;" data-loc="<?php echo htmlspecialchars(json_encode($deptlocs), ENT_QUOTES, 'UTF-8'); ?>" data-dept="<?php echo $deptname ?>" data-dept-id="<?php echo $deptid ?>">
					<h3 class="ve-margin-none ve-pad-sm ve-bolder ve-uppercase">
						<strong><?php echo $deptname;  ?></strong>
					</h3>
				</div>
			<?php
			}
			?>
			
			<?php // STAFF CARD LOOP
			
			get_template_part( 
				'template-parts/list-staffcard', 
				null, 
				array( 
					'data' 	   => array(
								'dept' => $deptname,
								'staff' => $deptstaff,
					),
					'type'	   => array(
								'internal' => $internal,
								'group' => $group,
					),
					'settings'	=> array(
								'debug' => $debug,
								'rowcolumns' => $rowcolumns,
								'showfullname' => $showfullname,
								'showfulllastname' => $showfulllastname,
								'showbios' => $showbios,
								'webvisiblebypass' => $webvisiblebypass,
					),
				)
			); ?>
	<!-- </div> <!-- END DEPARTMENT EMPLOYEE COLUMNS ROW -->

        <?php
        } //END DEPARTMENT LOOP
        ?>
                </div> <!-- END DEPARTMENT LIST -->
        </div> <!-- END EMPLOYEE LIST CONTAINER-->
    </div> <!-- END EMPLOYEE LIST WRAP -->
</div> <!-- END SECTION CONTAINER -->
	<p class="ve-text-center ve-block ve-poweredby"><small>Powered by Vern Eide Marketing</small></p>

</div> <!-- END PAGE CONTAINER -->
</div> <!-- END PARTIAL VIEW WRAP -->
<?php
$htmlcontent = ob_get_clean();

if(!empty($listingmodals)){
	$htmlmodals = '<div id="listingmodals">';
	$htmlmodals .= $listingmodals;
	$htmlmodals .= '</div>';
	$htmlcontent .= $htmlmodals; //Adds modal html to the bottom.
}