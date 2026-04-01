<?php 
/**
 * Template part for displaying simple list filter
 *
 */
 ?>

 <div style="margin-top: 10px">
    <?php if(!$args['type']['birthday']) {?>
        <div class="ve-row ve-pad-none ve-text-left ve-pad-bottom-mdlg ve-margin-bottom-lg ve-border-bottom-light ve-sticky-top ve-bg-white" id="employeeHeadingWrapper" style="margin-top: 10px">
			<?php //EMPLOYEE SEARCH ?>
                <div class="ve-col-auto employeeFilterWrapper ve-form ve-pull-left ve-pad-right-md">
                    <div class="ve-pull-left ve-text-right ve-valign-middle ve-margin-right-md" id="employeeFilter">
                        <span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Employee Name</span>
						<input type="text" id="employeenamesearch" name="searchname" placeholder="Type name..">
                    </div>
                </div>
            <?php //END EMPLOYEE SEARCH ?>

        <?php //SHOWS LOCATON FILTER IF USED ON GROUP SITE
        if($args['embedloc']['group'] || $args['embedloc']['internal']){?>
        <div class="ve-col-auto locationFilterWrapper ve-form ve-pull-left ve-pad-right-md">
                 <div class="ve-pull-left ve-text-right ve-valign-middle ve-margin-right-md" id="locationFilter">
                    <span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Location</span>
                        <select id="locationfilterselect" class="ve-font-16 ve-bold" style="min-width: 230px; border: 1px solid #e6e7e8;">
                            <option value="all">All Locations</option>
                            <?php 
                                $locationsarray = array(); //Create array for all locations
                                $locstaff = $args['data']['staff'];
                                    foreach ($locstaff as $k => $v) {
										$locationslistedarray = $v["locations_listed"]; //Get all listed locations from user
										if (is_array($locationslistedarray)){
											foreach ($locationslistedarray as $k => $v) {
												$locationsarray[] = $v; //Fill array with all listed locations to be used in filter.
											}
										}else{
											$locationsarray[] = $v["location"]; //Fill array with all locations
										}
                                    }
				
                                $uniquelocations = array_unique($locationsarray); //Make array unique, removing duplicate listings
								// Loop through term ids and get information for filter
								$locationterms = array();
								foreach($uniquelocations as $uniquelocation){
									$loctermid = $uniquelocation;
									$loctermname = get_term( $loctermid, 'location' )->name;
									$locationterms[] = array(
														'id' => $loctermid,
														'name' => $loctermname,
														'filter' => $loctermfilterable
													);
								}
								
								// Sort locations in array
								$locationtermnames = array_column($locationterms,'name'); //Generated for sorting
								array_multisort($locationtermnames, SORT_ASC, $locationterms); //Sort locations by name
										
								// Loop through locations for filter
                                foreach($locationterms as $locterm) {
										$loctermid = str_pad($locterm["id"], 3, '0', STR_PAD_LEFT); //Makes the locations at least 3 characters in length for search
                                ?>
                                    	<option value="<?php echo $loctermid ?>"><?php echo $locterm["name"] ?></option>
                                <?php 
								} ?>
                                

                        </select>
                    </div>
                </div>
		<?php } //END LOCATION FILTER?>
		<?php //RESET FILTERS SECTION ?>
		<div class="ve-col-auto resetFilterWrapper ve-form ve-align-self-end">
				<div class="ve-text-left ve-valign-middle ve-margin-right-md" id="resetFilter">
					<button class="ve-primary-button-black ve-reset-btn" type="button" id="resetFilterBtn">RESET</button>
				</div>
			</div>
        </div>
	<?php } //END SHOW FILTERS IF STATEMENT ?>
</div>