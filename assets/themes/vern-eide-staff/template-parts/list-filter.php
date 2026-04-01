<?php
/** LIST FILTER **/

/** ARGS **/
$output = $args['data']['output'];
$internal = $args['type']['internal'];
$group = $args['type']['group'];

?>
<div id="employeeHeadingWrapper" class="">
	<div class="ve-row ve-pad-none ve-text-left ve-pad-bottom-mdlg ve-margin-bottom-md ve-bg-white" style="margin-top: 5px; margin-left: -5px; margin-right: -5px;">
				<?php //EMPLOYEE SEARCH ?>
                    <div class="ve-col-auto employeeFilterWrapper ve-form">
                        <div class="ve-pull-left ve-text-right ve-valign-middle" id="employeeFilter">
                            <span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Employee Name</span>
							<input type="text" id="employeenamesearch" name="searchname" placeholder="Search name..">
                        </div>
                    </div>
                <?php //END EMPLOYEE SEARCH ?>
				<?php 
				//EXTENSION SEARCH 
				if($internal){?>
                    <div class="ve-col-auto extFilterWrapper ve-form">
                        <div class="ve-pull-left ve-text-right ve-valign-middle" id="extFilter">
                            <span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Extension</span>
							<input type="text" id="employeeextsearch" name="searchext" placeholder="Ext #">
                        </div>
                    </div>
                <?php } 
				//END EXTENSION SEARCH ?>
                <?php 
                //SHOWS LOCATON FILTER IF USED ON GROUP SITE
                if($group || $internal){?>
                    <div class="ve-col-auto locationFilterWrapper ve-form ve-pull-left">
                        <div class="ve-pull-left ve-text-right ve-valign-middle" id="locationFilter">
							<?php if($internal){?>
								<span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Location/Group</span>
							<?php }else{ ?>
								<span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Location</span>
							<?php } ?>
                            <select id="locationfilterselect" class="ve-font-15 ve-bold" style="min-width: 200px; border: 1px solid #e6e7e8;">
                                    <option value="all">All Locations</option>
                                <?php 
                                $locationsarray = array(); //Create array for all locations
                                foreach ($output as $k => $v){
                                        $deptstaff = $v["dept_staff"];
                                    foreach ($deptstaff as $k => $v) {
										$locationslistedarray = $v["locations_listed"]; //Get all listed locations from user
										if (is_array($locationslistedarray)){
											foreach ($locationslistedarray as $k => $v) {
												$locationsarray[] = $v; //Fill array with all listed locations to be used in filter.
											}
										}else{
											$locationsarray[] = $v["location"]; //Fill array with all locations
										}
					
                                    }
                                }
				
                                $uniquelocations = array_unique($locationsarray); //Make array unique, removing duplicate listings
								// Loop through term ids and get information for filter
								$locationterms = array();
								foreach($uniquelocations as $uniquelocation){
									$loctermid = $uniquelocation;
									$loctermname = get_term( $loctermid, 'location' )->name;
									$loctermfilterable = get_term_meta( $loctermid, 'public_filter', true);
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
									$locfilter = $locterm["filter"];
									// Add location option to filter if filter is true
									if ($locfilter != "0" || $internal) {
										$loctermid = str_pad($locterm["id"], 3, '0', STR_PAD_LEFT); //Makes the locations at least 3 characters in length for search
                                ?>
                                    	<option value="<?php echo $loctermid ?>" data-filter="<?php echo $locfilter ?>"><?php echo $locterm["name"] ?></option>
                                <?php 
									}
                                }?>
                            </select>
                        </div>
                    </div>
                <?php }?>
					
                    <div class="ve-col-auto departmentFilterWrapper ve-form">
                        <div class="ve-pull-left ve-text-right ve-valign-middle" id="departmentFilter">
                            <span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Department</span>
                            <select id="departmentfilterselect" class="ve-font-15 ve-bold" style="min-width: 200px; border: 1px solid #e6e7e8;">
                                    <option value="all">All Departments</option>
                                <?php 
                                foreach ($output as $k => $v){
                                        $deptorder = $v["dept_order"];
                                        $deptid = $v["dept_id"];
                                        $deptname = $v["dept_name"];
                                ?>
                                    <option value="<?php echo $deptname ?>"><?php echo $deptname ?></option>
                                <?php }?>
                            </select>
                        </div>
                    </div>
				<?php
				//SHOWS TAGS FILTER INTERNALLY
				?>
						<div class="ve-col-auto tagFilterWrapper ve-form ve-pull-left">
						<div class="ve-pull-left ve-text-right ve-valign-middle" id="tagFilter">
							<span class="filter-label ve-uppercase ve-lt-grey ve-font-14 ve-boldest ve-letter-spacing-1">Tags</span>
							<select id="tagfilterselect" class="ve-font-15 ve-bold" style="min-width: 200px; border: 1px solid #e6e7e8;">
								<option value="all">All Tags</option>
								<?php 
								$tags_array = array();

								// Combine all dept_staff arrays within $output
								$combined_dept_staff = array_reduce($output, function ($carry, $item) {
									return array_merge($carry, $item['dept_staff']);
								}, []);

								// Extracting 'tags' values into a new array
								$tags_array = array_column($combined_dept_staff, 'tags');

								// Flatten the array to get a single-dimensional array
								$flat_tags_array = array_merge(...$tags_array);

								// Remove duplicates based on the 'id' field
								$seen_ids = [];
								$unique_tags = [];
								foreach ($flat_tags_array as $tag) {
									if (!in_array($tag['id'], $seen_ids)) {
										$unique_tags[] = $tag;
										$seen_ids[] = $tag['id'];
									}
								}

								// Sort the unique array by 'name' field
								// Sort tags naturally (1, 3, 5, 10...) while keeping non-numeric tags alphabetically
								usort($unique_tags, function($a, $b) {
									// Extract leading numbers if present (e.g. "10 Year Anniversary" → 10)
									preg_match('/^\d+/', $a['name'], $a_num);
									preg_match('/^\d+/', $b['name'], $b_num);

									$has_a_num = !empty($a_num);
									$has_b_num = !empty($b_num);

									if ($has_a_num && $has_b_num) {
										return (int)$a_num[0] <=> (int)$b_num[0]; // numerical compare
									} elseif ($has_a_num) {
										return -1; // numbers come before non-numeric names
									} elseif ($has_b_num) {
										return 1;
									}

									// Fallback to case-insensitive string compare
									return strcasecmp($a['name'], $b['name']);
								});

								// Store the sorted array in $staff_tags
								$staff_tags = $unique_tags;

								// Loop through tags for filter
								foreach($staff_tags as $tag) {
									// Check $internal and $tag['public_filter']
									if ($internal || ($tag['public_filter'] == '1')) {
										?>
										<option value="<?php echo $tag['id'] ?>" ><?php echo $tag['name'] ?></option>
										<?php 
									}
								} 
								?>
							</select>
						</div>
					</div>

					<?php //RESET FILTERS SECTION ?>
					<div class="ve-col-auto resetFilterWrapper ve-form ve-align-self-end">
						<div class="ve-text-left ve-valign-middle" id="resetFilter">
							<button class="ve-primary-button-black ve-reset-btn" type="button" id="resetFilterBtn">RESET</button>
						</div>
					</div>
		</div>
</div>