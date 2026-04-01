<?php
class Ve_Staff_SMS_Background_Process extends WP_Background_Process {
	
	public function __construct() {
		parent::__construct();
	}
	
	protected $prefix = 've-staff';
    protected $action = 'sms_msg_background_process';

    protected function task($item) {
        // Process each item from the queue
        $post_id = $item['post_id'];
        $staff_array = $item['staff_array'];
		//error_log('Processing task for post ID: ' . $item['post_id']);

		if (class_exists('Ve_Staff_SMS') && method_exists('Ve_Staff_SMS', 'send_sms_msg_post')) {
			$ve_staff_sms_instance = new Ve_Staff_SMS();
			$ve_staff_sms_instance->send_sms_msg_post($post_id, $staff_array);
			//error_log('SMS Message Sent: ');
		} else {
			//error_log('Class or method not found.');
		}

        // Mark the task as complete
        return false;
    }
	
	/**
	 * Complete processing.
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		parent::complete();

		// Show notice to user or perform some other arbitrary task...
	}
}