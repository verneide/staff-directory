<?php 
/**
 * Template part for displaying staff card
 *
 */

// ARGS
$contactinfo = $args['data']['contactinfo'];
$birthdayinfo = $args['data']['birthdayinfo'];
$newpromoinfo = $args['data']['newpromoinfo'];
$totalstaff = intval($args['data']['totalStaff']);
$staffnumber = $args['data']['staffcountnum'];
$staffshown = $args['data']['staffshown'];
$rowcolumns = $args['settings']['rowcolumns'];

if(isset($rowcolumns) || $totalstaff <= 3){
	if($rowcolumns == 3 ){
		$rowcolumnsclass = 've-col-xl-4 ve-col-lg-4 ve-col-md-4 ve-col-sm-6 ve-col-xs-6';
	} elseif ($rowcolumns == 4 || $totalstaff <= 3){
		$rowcolumnsclass = 've-col-xl-3 ve-col-lg-3 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	} elseif ($rowcolumns == 6){
		$rowcolumnsclass = 've-col-xl-2 ve-col-lg-2 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	} else {
		$rowcolumnsclass = 've-col-xl-2-5 ve-col-lg-2-5 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	}
} else {
	$rowcolumnsclass = 've-col-xl-2-5 ve-col-lg-2-5 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
}

// CONTACT INFO VARS
$phoneprefix = $contactinfo["office_phone_prefix"];
$phoneext = $contactinfo["office_extension"];
$phoneother = $contactinfo["office_other_direct"];
$addphones = $contactinfo["additional_numbers"];
							
unset($phoneformatted); //RESET VAR DURING LOOP
if (isset($phoneprefix) || isset($phoneother)){
	if($phoneother){
		$phone = preg_replace('/\D+/', '', $phoneother);
	} else {
		$phone = preg_replace('/\D+/', '', $phoneprefix) . $phoneext;
	}
	$phoneformatted = substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6, 4);
};
							
unset($phonecellformatted); //RESET VAR DURING LOOP
unset($phonecell); //RESET VAR DURING LOOP
$cellphone = $contactinfo["office_cell_phone"];
if(isset($cellphone)){
	$phonecell = preg_replace('/\D+/', '', $cellphone);
	$phonecellformatted = substr($phonecell, 0, 3).'-'.substr($phonecell, 3, 3).'-'.substr($phonecell, 6, 4);
};

$email = $contactinfo["office_email"];	
						
// Special Staff Class Rules
if (isset($staffshown) && $staffshown != 0 && $staffnumber > $staffshown){
$stafftileclass = 've-hidden';
}
?>

<?php // BIRTHDAY LIST
if($args['type']['birthday']){ 
   // BIRTHDAY VARS
    $birthdayformatted = $birthdayinfo["birthday_month_day"];
    if($birthdayinfo["birthday_date"] == date('Y-m-d')){
        $birthdaytoday = TRUE;
    } else {
        $birthdaytoday = FALSE;
    } 
?>

 <div class="<?php echo $rowcolumnsclass ?> ve-margin-bottom-xl employee-tile ve-width-100 <?php echo $stafftileclass; ?>" data-staff-number="<?php echo $staffnumber; ?>" data-loc="<?php echo htmlspecialchars(json_encode($args['data']['locationsall']), ENT_QUOTES, 'UTF-8'); ?>" data-dept="<?php echo $args['data']['deptname'] ?>" data-employee-name="<?php echo strtoupper($args['data']['name']) ?>" >
    <div class="ve-col-sm-12 ve-rounded-3 border-radius-20 ve-pad-none ve-bg-grey">
        <div class="ve-col-sm-12 ve-pad-left-none ve-pad-right-none border-thick border-radius-20">
            <div class="ve-text-left ve-col-sm-12 ve-pad-left-none ve-pad-right-none employee-wrapper">
                <div class="employee-tile-img ve-text-center ve-valign-top ve-margin-bottom-sm">
                    <img alt="<?php echo $args['data']['name'] ?> at <?php echo $args['data']['location'] ?>" src="<?php echo $args['data']['photourl'] ?>">
                </div>
                <div class="employee-details-wrapper">
                    <div class="ve-col-sm-12 employee-details ve-pad-top-md ve-pad-bottom-none ve-pad-right-md" >
                        <h3 class="employee-name ve-boldest ve-font-16 ve-black ve-block"><?php echo $args['data']['name'] ?></h3>
                        <span class="employee-title ve-font-14 ve-black ve-block"><?php echo $title ?></span>
                        <?php if ($args['embedloc']['group'] || $args['embedloc']['internal']){?>
                        <hr>
                        <span class="ve-font-12 ve-black ve-block" style="padding-top: 5px;"><i><?php echo $args['data']['location'] ?></i></span>
                        <?php } ?>
                    </div>
                </div>

				<?php 
				//SHOW INTERNAL INFO 
				if($args['embedloc']['internal']){ ?>
                
					<div class="ve-md-block ve-text-center">
						<?php if($args['type']['birthday']){ //SHOW IF BIRTHDAY LIST ?>
						<div class="ve-pad-bottom-md">
							<span class="ve-font-18 ve-black ve-block ve-bold" style="padding-top: 5px;"><?php echo $birthdayformatted; ?></span>
						</div>
						<?php 
						// SHOWS IF BIRTHDAY IS TODAY
						if($birthdaytoday){ ?>
						<div class="ve-birthday">
							<span class="ve-font-15 ve-block ve-boldest" style="">Happy Birthday <?php echo trim($args['data']['fname']) ?>!</span>
						</div>
						<?php } //END BIRTHDAY TODAY ?>
						<?php } //END BIRTHDAY INFO ?>
					<?php if(!$args['type']['birthday']){ //SHOW OR HIDE CONTACT INFO BASED ON CONDITION ?>
						
					<button class="ve-primary-button-black ve-contact-info-btn" type="button" data-toggle="collapse" data-target="#collapse<?php echo $args['data']['staffid'] ?>" aria-expanded="false" aria-controls="collapse<?php echo $args['data']['staffid'] ?>">View Contact Info</button>
					<div id="collapse<?php echo $args['data']['staffid'] ?>" class="collapse ve-pad-none">
					<div class="ve-col-sm-12 ve-pad-top-md ve-pad-bottom-md ve-contact-wrap">
					
						<?php if (strlen($args['data']['phone']) >= 10) { //SHOW IF DIRECT PHONE LISTED ?>
							<span class="ve-block">
								<span class="ve-bolder">Direct: </span><a target="_self" href="tel:<?php echo $args['data']['phone'] ?>" class="" style="text-decoration: none !important;"><?php echo $args['data']['phoneformatted'] ?></a>
							</span>
						<?php } ?>
						<?php if ($phoneext) { //SHOW IF EXTENSION LISTED ?>
							<span class="ve-block">
								<span class="ve-bolder">Ext: </span><?php echo $phoneext ?>
							</span>
						<?php } ?>
						<?php //ADDITIONAL PHONES LOOP
							unset($addphonetype);
							unset($addphonelabel);
							unset($addphonenumber);
							unset($addphoneext);
							  
							if ($addphones) {
							foreach ($addphones as $k => $v){
							$addphonetype = $v["type"];
							$addphonelabel = $v["phone_label"];
							$addphonenumber = $v["phone"];
							$addphoneext = $v["extension"];
								
						    if ($addphonetype == 'Extension'){ 
								//FOR LISTED EXTENSION
							?>
								<span class="ve-block">
									<span class="ve-bolder"><?php echo $addphonelabel ?> Ext: </span><?php echo $addphoneext?>
								</span>
						
							<?php } elseif ($addphonetype == 'Phone') {
								//FOR LISTED ADDITIONAL PHONE
								$addphonenum = preg_replace('/\D+/', '', $addphonenumber); //REMOVES DASHES PREPARES FOR LINK
								$addphonenumformatted = substr($addphonenum, 0, 3).'-'.substr($addphonenum, 3, 3).'-'.substr($addphonenum, 6, 4); //ADDS DASHES TO NUMBER
							?>
								<span class="ve-block">
									<span class="ve-bolder"><?php echo $addphonelabel ?>:</span> <br><a target="_self" href="tel:<?php echo $addphonenum ?>" class="" style="text-decoration: none !important;"><?php echo $addphonenumformatted ?></a>
								</span>
							<?php } // END PHONE TYPE IF ?>
						<?php } // END ADDITIONAL PHONE LOOP 
							} // END IF ADDPHONES ?>
						<?php if ($phonecell) { //SHOW IF CELL PHONE LISTED ?>
							<span class="ve-block">
								<span class="ve-bolder">Cell: </span><a target="_self" href="tel:<?php echo $phonecell ?>" class="" style="text-decoration: none !important;"><?php echo $phonecellformatted ?></a>
							</span>
						<?php } ?>
						<?php if ($args['data']['email']) { //SHOW IF EMAIL LISTED ?>
							<span class="ve-block">
								<span class="ve-bolder">Email: </span><a target="_self" href="mailto:<?php echo $args['data']['email'] ?>" class="" style="text-decoration: none !important;"><?php echo $args['data']['email'] ?></a>
							</span>
						<?php } ?>
					</div>
					</div>
						<?php }?>
					</div>
				<?php }?>
            </div>
        </div>
    </div>
</div>

<?php // NEW OR PROMOTED LIST
} elseif($args['type']['newpromo']){ 
// NEW PROMO VARS
$newpromodate = $newpromoinfo['newpromo_date'];
$newpromotype = $newpromoinfo['newpromo_type'];
if ($newpromotype == 'new'){
    $message = 'Welcome';
} elseif ($newpromotype == 'promotion'){
    $message = 'Congrats';
}

?>

<div class="<?php echo $rowcolumnsclass ?> ve-margin-bottom-xl employee-tile ve-width-100 <?php echo $stafftileclass; ?>" data-staff-number="<?php echo $staffnumber; ?>" data-loc="<?php echo htmlspecialchars(json_encode($args['data']['locationsall']), ENT_QUOTES, 'UTF-8'); ?>" data-dept="<?php echo $args['data']['deptname'] ?>" data-employee-name="<?php echo strtoupper($args['data']['name']) ?>" >
    <div class="ve-col-sm-12 ve-rounded-3 border-radius-20 ve-pad-none ve-bg-grey">
        <div class="ve-col-sm-12 ve-pad-left-none ve-pad-right-none border-thick border-radius-20">
            <div class="ve-text-left ve-col-sm-12 ve-pad-left-none ve-pad-right-none employee-wrapper">
                <div class="employee-tile-img ve-text-center ve-valign-top ve-margin-bottom-sm">
                    <img alt="<?php echo $args['data']['name'] ?> at <?php echo $args['data']['location'] ?>" src="<?php echo $args['data']['photourl'] ?>">
                </div>
                <div class="employee-details-wrapper">
                    <div class="ve-col-sm-12 employee-details ve-pad-top-md ve-pad-bottom-none ve-pad-right-md" >
                        <h3 class="employee-name ve-margin-none ve-boldest ve-font-16 ve-black ve-block"><?php echo $args['data']['name'] ?></h3>
                        <span class="employee-title ve-font-14 ve-black ve-block"><?php echo $args['data']['title'] ?></span>
                        <hr>
                        <span class="ve-font-12 ve-black ve-block" style="padding-top: 5px;"><i><?php echo $args['data']['location'] ?></i></span>
                    </div>
                </div>
                
				<div class="ve-md-block ve-text-center">
					<div class="ve-pad-bottom-md">
						<span class="ve-font-14 ve-black ve-block ve-bold" style="padding-top: 5px;"><?php echo date('F Y',strtotime($newpromodate)); ?></span>
					</div>

					<?php // SHOWS MESSAGE ?>
					<div class="ve-birthday">
						<span class="ve-font-15 ve-block ve-boldest" style=""><?php echo $message . ' ' . trim($args['data']['fname']) ?>!</span>
					</div>

                </div>
            </div>
        </div>    
    </div>
</div>

<?php } ?>