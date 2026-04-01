<?php
class Vem_Lightspeed_Parts {

    const TABLE_NAME = 've_lightspeed_parts';
    const LOG_TABLE = 've_lightspeed_logs';
    const OPTION_KEY = 'vem_lightspeed_dealers';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_vem_lightspeed_test_connection', [$this, 'handle_test_connection']);
        add_action('wp_ajax_vem_lightspeed_run_import', [$this, 'handle_ajax_import']);
		add_action('wp_ajax_vem_lightspeed_schedule_full_import', [$this, 'schedule_full_import']);
		add_action('wp_ajax_vem_lightspeed_schedule_recent_import', [$this, 'schedule_recent_import']);
		add_action('wp_ajax_vem_lightspeed_sync_to_woocommerce', [$this, 'handle_sync_to_woocommerce']);
		
		// CRON JOBS
		add_action('vem_lightspeed_cron_update_recent_parts', [$this, 'run_recent_import_cron']);
		add_action('vem_lightspeed_cron_sync_woocommerce', [$this, 'run_sync_woocommerce_cron']);
		add_action('update_option_vem_lightspeed_recent_cron_frequency', [$this, 'reschedule_recent_cron'], 10, 2);
		//// Initial Cron if Not Set
		if (!wp_next_scheduled('vem_lightspeed_cron_update_recent_parts')) {
			$this->reschedule_recent_cron('', get_option('vem_lightspeed_recent_cron_frequency', 'every_6_hours'));
		}
		
		// wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_1pm');
		// wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_7pm');
		// wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_9am');
		
    }

    public function add_settings_page() {
        add_menu_page(
            'VEM Lightspeed',
            'VEM Lightspeed',
            'manage_options',
            'vem_lightspeed',
            function () {
                wp_safe_redirect(admin_url('admin.php?page=vem_lightspeed_settings'));
                exit;
            },
            'dashicons-admin-tools'
        );

        add_submenu_page(
            'vem_lightspeed',
            'Lightspeed Settings',
            'Settings',
            'manage_options',
            'vem_lightspeed_settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'vem_lightspeed',
            'Import Logs',
            'Import Logs',
            'manage_options',
            'vem_lightspeed_logs',
            [$this, 'render_logs_page']
        );
		
		add_submenu_page(
			'vem_lightspeed',
			'View Parts',
			'Parts Table',
			'manage_options',
			'vem_lightspeed_parts_table',
			[$this, 'render_parts_table_page']
		);
    }

	public function render_settings_page() {
		global $wpdb;

		$latest_logs = $wpdb->get_results("
			SELECT run_type, MAX(created_at) as last_run
			FROM {$wpdb->prefix}ve_lightspeed_logs
			WHERE status = 'success'
			GROUP BY run_type
		", OBJECT_K);

		// Format Full Import timestamp in CST
		if (isset($latest_logs['full'])) {
			$dt_full = new DateTime($latest_logs['full']->last_run, new DateTimeZone('UTC'));
			$dt_full->setTimezone(new DateTimeZone('America/Chicago'));
			$last_full = $dt_full->format('M j, Y @ g:i A') . ' CST';
		} else {
			$last_full = 'Never';
		}

		// Format Recent Import timestamp in CST
		$recent_timestamp = get_option('vem_lightspeed_recent_last_run');
		if ($recent_timestamp) {
			$dt_recent = new DateTime($recent_timestamp, new DateTimeZone('UTC'));
			$dt_recent->setTimezone(new DateTimeZone('America/Chicago'));
			$last_recent = $dt_recent->format('M j, Y @ g:i A') . ' CST';
		} else {
			$last_recent = 'Never';
		}

		$dealers = get_option(self::OPTION_KEY, []);
		?>
		<div class="wrap">
			<h1>Lightspeed API Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields('vem_lightspeed_settings_group');
				do_settings_sections('vem_lightspeed_settings');
				submit_button();
				?>
			</form>
			<div style="margin-top:20px; background:#f9f9f9; border:1px solid #ccc; padding:10px;">
				<h2>Last Import Runs</h2>
				<ul>
					<li>🕒 <strong>Full Import:</strong> <?php echo esc_html($last_full); ?></li>
					<li>🕒 <strong>Recent Updates:</strong> <?php echo esc_html($last_recent); ?></li>
					<?php
					$timestamp = wp_next_scheduled('vem_lightspeed_cron_update_recent_parts');
					if ($timestamp) {
						$dt = new DateTime("@$timestamp"); // timestamp from WordPress cron is UTC
						$dt->setTimezone(new DateTimeZone('America/Chicago'));
						$next_run = $dt->format('M j, Y @ g:i A') . ' CST';
					} else {
						$next_run = 'Not scheduled';
					}
					?>
					<li>🗓️ <strong>Next Scheduled Recent Update:</strong> <?php echo esc_html($next_run); ?></li>
				</ul>
			</div>
            <hr>
            <h2>On-Demand Product Import</h2>
            <p><strong>Update All Parts</strong> will fetch <em>all parts</em> from every dealer configured above. Use this when importing for the first time or when rebuilding the product database. Note: This may take a while if you have many parts.</p>
            <button class="button button-primary" onclick="runLightspeedImport('all')">Update All Parts</button>
            <p><strong>Update Recent Product Updates</strong> will fetch only those parts that have been modified <em>since the last successful recent update</em> according to Lightspeed. If no previous run is found, it will default to importing updates from the past 7 days. Use this for scheduled updates or quick refreshes.</p>
            <button class="button" onclick="runLightspeedImport('recent')">Update Recent Product Updates</button>
            <pre id="import-status" style="margin-top:1em;background:#f7f7f7;padding:1em;"></pre>
			
			<h2>WooCommerce Sync</h2>
			<p>This will sync the current inventory table to WooCommerce based on SKU matches. It will update stock per location and prices if found.</p>
			<button class="button" onclick="runWooCommerceSync()">Sync WooCommerce SKUs (Async)</button>
			<pre id="sync-status" style="margin-top:1em;background:#f7f7f7;padding:1em;"></pre>

            <script>
            function testConnection(index) {
                const btn = document.getElementById('test-btn-' + index);
                btn.innerText = 'Testing...';
                btn.disabled = true;
                const data = new FormData();
                data.append('action', 'vem_lightspeed_test_connection');
                data.append('index', index);
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(res => res.json())
                    .then(res => {
                        const msg = res?.message || (res.success ? 'Success' : 'Failed');
                        alert(msg);
                    })
                    .catch(err => alert('Error: ' + err))
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerText = 'Test Connection';
                    });
            }

            function runLightspeedImport(type) {
				const status = document.getElementById('import-status');
				status.innerHTML = `<em>Running <strong>${type}</strong> import...</em> ⏳`;

				let action = '';

				if (type === 'all') {
					action = 'vem_lightspeed_schedule_full_import';
				} else if (type === 'recent') {
					action = 'vem_lightspeed_schedule_recent_import';
				} else {
					status.innerHTML = `❌ <strong>Unknown import type:</strong> ${type}`;
					return;
				}

				// Schedule via cron using AJAX
				fetch(`${ajaxurl}?action=${action}`)
					.then(r => r.json())
					.then(res => {
						if (res.success) {
							const label = type === 'all' ? 'Full' : 'Recent';
							status.innerHTML = `
								📅 <strong>${label} import scheduled</strong><br>
								The import will run in the background and update parts via cron.<br>
								You will receive an email or see it logged in the import history once complete.
							`;
						} else {
							status.innerHTML = `❌ <strong>Error:</strong> ${res.message}`;
						}
					})
					.catch(err => {
						status.innerHTML = `❌ <strong>Fetch error:</strong> ${err}`;
					});
			}
			function runWooCommerceSync() {
				const status = document.getElementById('sync-status');
				status.innerHTML = `<em>Syncing WooCommerce SKUs in background...</em> ⏳`;

				fetch(ajaxurl + '?action=vem_lightspeed_sync_to_woocommerce')
					.then(r => r.json())
					.then(res => {
						if (res.success) {
							const msg = res.message || 'WooCommerce sync scheduled successfully.';
							status.innerHTML = `
								✅ <strong>${msg}</strong><br>
								ℹ️ You can view the log below for progress updates.
							`;
						} else {
							const msg = res.message || 'Unknown error occurred.';
							status.innerHTML = `❌ <strong>Error:</strong> ${msg}`;
						}
					})
					.catch(err => {
						status.innerHTML = `❌ <strong>Fetch error:</strong> ${err}`;
					});
			}
            </script>
        </div>
        <?php
    }

	public function handle_ajax_import() {
		$type = sanitize_text_field($_GET['type'] ?? 'recent');
		$dealers = get_option(self::OPTION_KEY, []);
		$filter = '';

		if ($type === 'recent') {
			global $wpdb;
			$last_run = $wpdb->get_var("
				SELECT MAX(created_at)
				FROM {$wpdb->prefix}ve_lightspeed_logs
				WHERE run_type = 'recent' AND status = 'success'
			");

			if ($last_run) {
				$last_iso = gmdate("Y-m-d\TH:i:s", strtotime($last_run));
				$filter = "lastupdatedate ge datetime'$last_iso'";
			}
		}

		$totalAdded = 0;
		$totalUpdated = 0;

		foreach ($dealers as $dealer) {
			$result = $this->fetch_parts($dealer, $filter);
			$totalAdded += $result['added'];
			$totalUpdated += $result['updated'];
			$this->log_result($dealer, $type, 'success', "Completed import", $result['updated'], $result['added']);
		}
		
		// Trigger WooCommerce sync right after import
		if (!wp_next_scheduled('vem_lightspeed_cron_sync_woocommerce')) {
			wp_schedule_single_event(time() + 10, 'vem_lightspeed_cron_sync_woocommerce');
		}

		wp_send_json_success([
			'message' => "Import complete. Added: $totalAdded | Updated: $totalUpdated",
			'added' => $totalAdded,
			'updated' => $totalUpdated,
		]);
	}
	
	public function handle_sync_to_woocommerce() {
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}

		if (!wp_next_scheduled('vem_lightspeed_cron_sync_woocommerce')) {
			wp_schedule_single_event(time() + 5, 'vem_lightspeed_cron_sync_woocommerce');
		}

		wp_send_json_success(['message' => 'WooCommerce sync scheduled to run shortly in the background.']);
	}

	public function run_sync_woocommerce_cron() {
		$this->log_result(['name' => 'System', 'cmf' => ''], 'sync', 'info', '🔄 WooCommerce sync started via scheduled cron', 0, 0);
		$this->sync_all_parts_to_woocommerce();
	}
	
	public function schedule_full_import() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}

		// Store who initiated this
		update_option('vem_lightspeed_cron_user_id', get_current_user_id());

		// Schedule the cron to run now or in 1 minute
		if (!wp_next_scheduled('vem_lightspeed_cron_update_all_parts')) {
			wp_schedule_single_event(time() + 30, 'vem_lightspeed_cron_update_all_parts');
		}

		wp_send_json_success(['message' => 'Import scheduled']);
	}
	
	public function schedule_recent_import() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized']);
		}

		// Store user ID for logging/email (if needed)
		update_option('vem_lightspeed_cron_user_id', get_current_user_id());

		// Schedule recent update cron
		if (!wp_next_scheduled('vem_lightspeed_cron_update_recent_parts')) {
			wp_schedule_single_event(time() + 30, 'vem_lightspeed_cron_update_recent_parts');
		}

		wp_send_json_success(['message' => 'Recent product update scheduled']);
	}
	
	public function run_full_import_cron() {
		$user_id = get_option('vem_lightspeed_cron_user_id');
		$user = get_user_by('ID', $user_id);
		$dealers = get_option(self::OPTION_KEY, []);

		$totalAdded = 0;
		$totalUpdated = 0;
		$errors = [];

		foreach ($dealers as $dealer) {
			$attempts = 0;
			$max_attempts = 5;
			$delay = 10;

			do {
				$result = $this->fetch_parts($dealer);

				if (isset($result['error'])) {
					$attempts++;
					if ($attempts < $max_attempts) {
						sleep($delay);
						$delay *= 2; // exponential backoff
					} else {
						$errorMessage = "❌ Dealer '{$dealer['name']}' failed after {$max_attempts} attempts: {$result['error']}";
						$errors[] = $errorMessage;
						$this->log_result($dealer, 'full', 'failure', $result['error']);
						break;
					}
				} else {
					$totalAdded += $result['added'];
					$totalUpdated += $result['updated'];
					$this->log_result($dealer, 'full', 'success', 'Completed full cron import', $result['updated'], $result['added']);
					break;
				}
			} while ($attempts < $max_attempts);
		}

		if ($user && $user->user_email) {
			$body = "✅ The full parts import has completed.\n\n";
			$body .= "Total Added: $totalAdded\nTotal Updated: $totalUpdated\n\n";

			if (!empty($errors)) {
				$body .= "⚠️ Errors:\n" . implode("\n", $errors);
			}

			wp_mail(
				$user->user_email,
				'Lightspeed Full Import Complete',
				$body
			);
		}

		delete_option('vem_lightspeed_cron_user_id'); // cleanup
	}

    public function render_logs_page() {
		require_once plugin_dir_path(__FILE__) . 'vem-lightspeed-logs-table.php';

		$table = new Vem_Lightspeed_Logs_Table();
		$table->prepare_items();

		echo '<div class="wrap"><h1>Import Logs</h1>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="vem_lightspeed_logs" />';
		echo '<label for="run_type">Type:</label> ';
		echo '<select name="run_type">';
		echo '<option value="">All</option>';
		foreach (['full', 'recent', 'sync', 'test'] as $type) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr($type),
				selected($_GET['run_type'] ?? '', $type, false),
				ucfirst($type)
			);
		}
		echo '</select> ';

		echo '<label for="status">Status:</label> ';
		echo '<select name="status">';
		echo '<option value="">All</option>';
		foreach (['success', 'failure', 'info'] as $status) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr($status),
				selected($_GET['status'] ?? '', $status, false),
				ucfirst($status)
			);
		}
		echo '</select> ';

		// ✅ Fixed the input submit button
		echo '<input type="submit" class="button" value="Filter">';
		echo '</form><br>';

		$table->display();
		echo '</div>';
	}

    public function register_settings() {
		register_setting('vem_lightspeed_settings_group', self::OPTION_KEY);
		register_setting('vem_lightspeed_settings_group', 'vem_lightspeed_recent_cron_frequency'); // ✅ Add frequency setting

		add_settings_section('dealer_section', 'Dealer Credentials', null, 'vem_lightspeed_settings');

		$dealers = get_option(self::OPTION_KEY, []);
		$dealer_count = max(5, count($dealers));

		for ($i = 0; $i < $dealer_count; $i++) {
			add_settings_field("dealer_{$i}", "Dealer #" . ($i + 1), function () use ($dealers, $i) {
				$dealer = $dealers[$i] ?? ['username' => '', 'password' => '', 'cmf' => '', 'name' => ''];
				echo "<p><input type='text' name='" . self::OPTION_KEY . "[$i][name]' placeholder='Dealer Name' value='" . esc_attr($dealer['name']) . "' /> ";
				echo "<input type='text' name='" . self::OPTION_KEY . "[$i][cmf]' placeholder='CMF' value='" . esc_attr($dealer['cmf']) . "' /> ";
				echo "<input type='number' name='" . self::OPTION_KEY . "[$i][wc_location_id]' placeholder='Stock Location ID' value='" . esc_attr($dealer['wc_location_id'] ?? '') . "' />";
				echo "<input type='text' name='" . self::OPTION_KEY . "[$i][username]' placeholder='Username' value='" . esc_attr($dealer['username']) . "' /> ";
				echo "<input type='password' name='" . self::OPTION_KEY . "[$i][password]' placeholder='Password' value='" . esc_attr($dealer['password']) . "' /> ";
				echo "<button type='button' id='test-btn-$i' onclick='testConnection($i)'>Test Connection</button></p>";
			}, 'vem_lightspeed_settings', 'dealer_section');
		}

		// ✅ Add cron frequency dropdown setting field
		add_settings_field('vem_lightspeed_recent_cron_frequency', 'Recent Import Frequency', function () {
			$value = get_option('vem_lightspeed_recent_cron_frequency', 'every_6_hours');
			?>
			<select name="vem_lightspeed_recent_cron_frequency">
				<?php
				$options = [
					'every_1_hour'   => 'Every Hour',
					'every_2_hours'  => 'Every 2 Hours',
					'every_4_hours'  => 'Every 4 Hours',
					'every_6_hours'  => 'Every 6 Hours',
					'every_12_hours' => 'Every 12 Hours',
					'every_24_hours' => 'Every 24 Hours',
				];
				foreach ($options as $key => $label) {
					$selected = selected($value, $key, false);
					echo "<option value=\"$key\" $selected>$label</option>";
				}
				?>
			</select>
			<p class="description">Controls how often recent parts are synced from Lightspeed (runs between 6AM–10PM CST).</p>
			<?php
		}, 'vem_lightspeed_settings', 'dealer_section');
	}

    public function create_table() {
        global $wpdb;

        $parts = $wpdb->prefix . self::TABLE_NAME;
        $logs = $wpdb->prefix . self::LOG_TABLE;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE IF NOT EXISTS $parts (
            Cmf VARCHAR(20),
            DealerId VARCHAR(50),
            PartNumber VARCHAR(50),
            SupplierCode VARCHAR(20),
            DateGathered DATETIME,
            Description TEXT,
            OnHand INT,
            Avail INT,
            OnOrder INT,
            OnOrderAvail INT,
            LastSoldDate DATETIME,
            LastReceivedDate DATETIME,
            LastAdjustmentDate DATETIME,
            ReOrderMethod VARCHAR(10),
            MinimumQty INT,
            MaximumQty INT,
            Cost DECIMAL(10,2),
            CurrentActivePrice DECIMAL(10,2),
            OrderUnit VARCHAR(20),
            OrderUnitQty INT,
            Retail DECIMAL(10,2),
            LastCountDate DATETIME,
            SupersededTo VARCHAR(50),
            UPC VARCHAR(50),
            Bin1 VARCHAR(50),
            Bin2 VARCHAR(50),
            Bin3 VARCHAR(50),
            category VARCHAR(50),
            similarparttype VARCHAR(50),
            activepricetype VARCHAR(50),
            escalationtype VARCHAR(50),
            reordertype VARCHAR(50),
            comments TEXT,
            indealerpricebook VARCHAR(5),
            glaccountcode VARCHAR(50),
            movementcode VARCHAR(50),
            nodiscountpart TINYINT(1),
            secondarypartnum VARCHAR(50),
            oemreplenishment TINYINT(1),
            reorderpoint INT,
            suggestedsellqty INT,
            saleprice DECIMAL(10,2),
            specialprice1 DECIMAL(10,2),
            specialprice2 DECIMAL(10,2),
            specialprice3 DECIMAL(10,2),
            usepkgcost TINYINT(1),
            usepkgprice TINYINT(1),
            dms_lastupdateddate DATETIME,
            dateCreated DATETIME,
            dateUpdated DATETIME,
            PRIMARY KEY (Cmf, PartNumber)
        ) $charset;");

        dbDelta("CREATE TABLE IF NOT EXISTS $logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dealer_name VARCHAR(100),
            cmf VARCHAR(20),
            run_type ENUM('full','recent','test') NOT NULL,
            status ENUM('success','failure') NOT NULL,
            message TEXT,
            records_updated INT DEFAULT 0,
            records_added INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;");
    }
				
	public function log_result($dealer, $type, $status, $message, $updated = 0, $added = 0) {
		global $wpdb;

		$dealer_name = isset($dealer['dealer_name']) ? $dealer['dealer_name'] : (isset($dealer['name']) ? $dealer['name'] : 'Unknown');
		$cmf         = isset($dealer['cmf']) ? $dealer['cmf'] : 'unknown';

		$inserted = $wpdb->insert(
			$wpdb->prefix . self::LOG_TABLE,
			[
				'dealer_name'      => $dealer_name,
				'cmf'              => $cmf,
				'run_type'         => $type,
				'status'           => $status,
				'message'          => $message,
				'records_updated'  => (int) $updated,
				'records_added'    => (int) $added,
				'created_at'       => current_time('mysql')
			],
			[
				'%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s'
			]
		);

		// Optional: log to PHP error log if something goes wrong
		if ($inserted === false) {
			error_log('⚠️ Lightspeed log insert failed: ' . $wpdb->last_error);
		}

		// Cleanup logs older than 90 days
		$wpdb->query("DELETE FROM {$wpdb->prefix}" . self::LOG_TABLE . " WHERE created_at < NOW() - INTERVAL 90 DAY");
	}

    public function handle_test_connection() {
		ob_clean();
		header('Content-Type: application/json');

		$index = intval($_POST['index'] ?? -1);
		$dealers = get_option(self::OPTION_KEY, []);

		if (!isset($dealers[$index])) {
			$this->log_result(['name' => 'Unknown', 'cmf' => ''], 'test', 'failure', 'Invalid dealer index.');
			wp_send_json_error(['message' => 'Invalid dealer index.']);
		}

		$dealer = $dealers[$index];
		$url = 'https://int.lightspeeddataservices.com/lsapi/Part/' . $dealer['cmf'] . '?$top=1';
		$auth = base64_encode($dealer['username'] . ':' . $dealer['password']);

		$response = wp_remote_get($url, [
			'headers' => [
				'Authorization' => 'Basic ' . $auth,
				'Accept' => 'application/json'
			],
			'timeout' => 10
		]);

		if (is_wp_error($response)) {
			$this->log_result($dealer, 'test', 'failure', $response->get_error_message());
			wp_send_json_error(['message' => 'Error: ' . $response->get_error_message()]);
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		if (is_array($body)) {
			$this->log_result($dealer, 'test', 'success', 'Connected successfully.');
			wp_send_json_success(['message' => 'Connection successful!']);
		} else {
			$this->log_result($dealer, 'test', 'failure', 'Invalid response received.');
			wp_send_json_error(['message' => 'Failed to parse response.']);
		}
	}

	private function fetch_parts($dealer, $filter = '') {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$base_url = 'https://int.lightspeeddataservices.com/lsapi/Part/';
		$auth = base64_encode($dealer['username'] . ':' . $dealer['password']);
		$top = 75;
		$skip = 0;

		$added = 0;
		$updated = 0;
		
		$max_iterations = 1500; // ~112,500 parts
		$iterations = 0;

		set_time_limit(0);
		@ini_set('memory_limit', '512M');

		do {
			$url = $base_url . $dealer['cmf'] . "?\$top=$top&\$skip=$skip" . ($filter ? "&\$filter=$filter" : '');

			$response = wp_remote_get($url, [
				'headers' => [
					'Authorization' => 'Basic ' . $auth,
					'Accept'        => 'application/json',
				],
				'timeout' => 30
			]);

			if (is_wp_error($response)) {
				// Return error for retry logic
				return ['error' => $response->get_error_message()];
			}

			$body = wp_remote_retrieve_body($response);
			$parts = json_decode($body, true);

			if (!is_array($parts)) {
				return ['error' => 'Invalid or malformed response: ' . substr($body, 0, 200)];
			}

			foreach ($parts as $part) {
				if (empty($part['PartNumber']) || empty($dealer['cmf'])) continue;

				$part['Cmf'] = $dealer['cmf'];
				$part['dms_lastupdateddate'] = $part['lastupdatedate'] ?? null;
				unset($part['lastupdatedate'], $part['mapflag']);

				$part['dateCreated'] = current_time('mysql');
				$part['dateUpdated'] = current_time('mysql');

				foreach ($part as $key => $value) {
					if (is_null($value)) {
						$part[$key] = is_numeric($value) ? 0 : '';
					}
				}

				$result = $wpdb->replace($table, $part);
				$action = ($result === 1) ? 'added' : (($result === 2) ? 'updated' : 'none');

				if ($action !== 'none') {
					${$action}++; // increments $added or $updated

					// Call sync to WooCommerce
					$this->sync_part_to_woocommerce($part, $dealer, $filter, $added, $updated);
				}
			}

			$skip += $top;
			sleep(1);
			if (++$iterations >= $max_iterations) {
				return ['error' => 'Exceeded maximum pagination limit'];
			}
		} while (count($parts) === $top);

		return ['added' => $added, 'updated' => $updated];
	}
	
	public function reschedule_recent_cron($old_value, $value) {
		wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts');

		$interval_map = [
			'every_1_hour'   => 1,
			'every_2_hours'  => 2,
			'every_4_hours'  => 4,
			'every_6_hours'  => 6,
			'every_12_hours' => 12,
			'every_24_hours' => 24,
		];

		$hours = $interval_map[$value] ?? 6; // Default to every 6 hours

		$start_hour = 6;
		$end_hour   = 22;
		$now_cst = new DateTime('now', new DateTimeZone('America/Chicago'));
		$next_run = null;

		for ($hour = $start_hour; $hour <= $end_hour; $hour += $hours) {
			$candidate = (clone $now_cst)->setTime($hour, 0);
			if ($candidate > $now_cst) {
				$next_run = $candidate;
				break;
			}
		}

		// Fallback to next day 6AM if nothing left today
		if (!$next_run) {
			$next_run = (clone $now_cst)->modify('+1 day')->setTime($start_hour, 0);
		}

		// Convert to UTC for WordPress cron
		$next_run->setTimezone(new DateTimeZone('UTC'));
		wp_schedule_single_event($next_run->getTimestamp(), 'vem_lightspeed_cron_update_recent_parts');
	}

    public function update_all_parts() {
        check_admin_referer('vem_lightspeed_update_all');
        $dealers = get_option(self::OPTION_KEY, []);
        foreach ($dealers as $dealer) {
            $this->fetch_parts($dealer);
        }
        wp_redirect(admin_url('admin.php?page=vem_lightspeed_settings&updated=all'));
        exit;
    }

    public function update_recent_parts() {
		check_admin_referer('vem_lightspeed_update_recent');

		$last_run = get_option('vem_lightspeed_recent_last_run');

		// Fallback to 7 days ago in UTC if not set or empty
		if (empty($last_run)) {
			$last_run = gmdate("Y-m-d\TH:i:s\Z", strtotime("-7 days"));
		}

		$filter = "lastupdatedate ge datetime'$last_run'";

		$dealers = get_option(self::OPTION_KEY, []);
		foreach ($dealers as $dealer) {
			$this->fetch_parts($dealer, $filter);
		}

		// ✅ Update the last run timestamp to current UTC time
		update_option('vem_lightspeed_recent_last_run', gmdate("Y-m-d\TH:i:s\Z"));

		wp_redirect(admin_url('admin.php?page=vem_lightspeed_settings&updated=recent'));
		exit;
	}
	
	private $highest_prices = [];
	
	public function run_recent_import_cron() {
		$dealers = get_option(self::OPTION_KEY, []);
		$last_run = get_option('vem_lightspeed_recent_last_run');

		if (empty($last_run)) {
			$last_run = gmdate("Y-m-d\TH:i:s\Z", strtotime("-7 days"));
		}
		$filter = "lastupdatedate ge datetime'$last_run'";

		$totalAdded = 0;
		$totalUpdated = 0;

		foreach ($dealers as $dealer) {
			$result = $this->fetch_parts($dealer, $filter);
			if (isset($result['error'])) {
				$this->log_result($dealer, 'recent', 'failure', $result['error']);
			} else {
				$totalAdded += $result['added'];
				$totalUpdated += $result['updated'];
				$this->log_result($dealer, 'recent', 'success', 'Completed recent cron import', $result['updated'], $result['added']);
			}
		}

		// ✅ Update the last run time
		update_option('vem_lightspeed_recent_last_run', gmdate("Y-m-d\TH:i:s\Z"));

		// ✅ Schedule WooCommerce sync
		if (!wp_next_scheduled('vem_lightspeed_cron_sync_woocommerce')) {
			wp_schedule_single_event(time() + 10, 'vem_lightspeed_cron_sync_woocommerce');
		}

		// ✅ Schedule the next recent import
		$this->reschedule_recent_cron('', get_option('vem_lightspeed_recent_cron_frequency', 'every_6_hours'));
	}
	
	private function sync_part_to_woocommerce($part, $dealer, $filter, &$log_added, &$log_updated) {
		static $price_memory = [];

		$sku = $part['PartNumber'];
		$product_id = wc_get_product_id_by_sku($sku);
		if (!$product_id) return;

		$product = wc_get_product($product_id);
		if (!$product) return;

		$existing_price = $product->get_regular_price();
		$existing_stock = $product->get_stock_quantity();

		$location_id = isset($dealer['wc_location_id']) && is_numeric($dealer['wc_location_id']) && (int) $dealer['wc_location_id'] > 0
			? (int) $dealer['wc_location_id']
			: null;

		if ($location_id === null) {
			$this->log_result(
				$dealer,
				$filter ?: 'full',
				'info',
				"⚠️ No WooCommerce stock location ID set for dealer {$dealer['dealer_name']}. Default WooCommerce stock will be used."
			);
		}

		$retail      = isset($part['Retail']) && is_numeric($part['Retail']) ? (float) $part['Retail'] : null;
		$active_sale = isset($part['CurrentActivePrice']) && is_numeric($part['CurrentActivePrice']) ? (float) $part['CurrentActivePrice'] : null;

		// Save highest prices seen per SKU
		if (!isset($price_memory[$sku])) {
			$price_memory[$sku] = [
				'regular' => $retail ?? 0,
				'sale'    => $active_sale ?? 0,
				'applied' => false
			];
		} else {
			if ($retail !== null && $retail > $price_memory[$sku]['regular']) {
				$price_memory[$sku]['regular'] = $retail;
			}
			if ($active_sale !== null && $active_sale > $price_memory[$sku]['sale']) {
				$price_memory[$sku]['sale'] = $active_sale;
			}
		}

		$changed = false;
		$location_stock_changed = false;

		// Handle Stock Logic
		if ($location_id !== null) {
			$meta_key = '_stock_at_' . $location_id;
			$existing_location_stock = get_post_meta($product_id, $meta_key, true);
			$new_location_stock = (int) $part['OnHand'];

			// If the meta key doesn't exist, create it and log it
			if ($existing_location_stock === '') {
				add_post_meta($product_id, $meta_key, $new_location_stock, true);

				$this->log_result(
					$dealer,
					$filter ?: 'full',
					'info',
					sprintf(
						"🆕 Created missing stock meta key %s for SKU %s (product ID %d) with initial value %d.",
						$meta_key,
						$sku,
						$product_id,
						$new_location_stock
					)
				);

				$location_stock_changed = true;
				$changed = true;
			} elseif ((int)$existing_location_stock !== $new_location_stock) {
				update_post_meta($product_id, $meta_key, $new_location_stock);
				$location_stock_changed = true;
				$changed = true;
			}

			// Recalculate total stock from all _stock_at_* values
			$meta = get_post_meta($product_id);
			$total_stock = 0;
			foreach ($meta as $key => $value) {
				if (preg_match('/^_stock_at_\d+$/', $key)) {
					$total_stock += (int) $value[0];
				}
			}

			if ((int)$product->get_stock_quantity() !== $total_stock) {
				$product->set_manage_stock(true);
				$product->set_stock_quantity($total_stock);
				$changed = true;
			}
		} else {
			// Fallback to global WooCommerce stock
			$new_stock = (int) $part['OnHand'];
			if ((int)$existing_stock !== $new_stock) {
				$product->set_manage_stock(true);
				$product->set_stock_quantity($new_stock);
				$changed = true;
			}
		}

		// Apply pricing only once (when highest values have been discovered)
		if (!$price_memory[$sku]['applied']) {
			$final_regular = $price_memory[$sku]['regular'];
			$final_sale    = $price_memory[$sku]['sale'];
			$current_sale  = $product->get_sale_price();

			if ((float)$existing_price !== $final_regular) {
				$product->set_regular_price($final_regular);
				$changed = true;
			}

			if ($final_sale !== null && $final_sale < $final_regular) {
				if ((float)$current_sale !== $final_sale) {
					$product->set_sale_price($final_sale);
					$changed = true;
				}
			} elseif (!empty($current_sale)) {
				$product->set_sale_price('');
				$changed = true;
			}

			$price_memory[$sku]['applied'] = true;
		}

		try {
			if ($changed) {
				$product->save();
				$log_updated++;

				$stock_log = $location_id !== null
					? sprintf('Location %d: %s → %s | Total: %s', $location_id, $existing_location_stock ?? '—', $part['OnHand'], $product->get_stock_quantity())
					: sprintf('%s → %s', $existing_stock ?? '—', $product->get_stock_quantity());

				$log_message = sprintf(
					"✅ Synced SKU %s to product ID %d | Stock: %s | Regular: %s → %s | Sale: → %s | Dealer: %s",
					$sku,
					$product_id,
					$stock_log,
					$existing_price ?? '—',
					$price_memory[$sku]['regular'] ?? '—',
					$price_memory[$sku]['sale'] ?? '—',
					$dealer['dealer_name'] ?? $dealer['cmf'] ?? 'Unknown Dealer'
				);

				$this->log_result(
					$dealer,
					$filter ?: 'full',
					'success',
					$log_message,
					1,
					0
				);
			}
		} catch (Exception $e) {
			$this->log_result(
				$dealer,
				$filter ?: 'full',
				'failure',
				"❌ Failed to save SKU {$sku}: " . $e->getMessage(),
				0,
				0
			);
		}
	}
	
	public function sync_all_parts_to_woocommerce() {
		global $wpdb;

		$table = $wpdb->prefix . Vem_Lightspeed_Parts::TABLE_NAME;
		$log_updated = 0;
		$log_added = 0;
		$processed = 0;

		// Load all dealer settings from options
		$dealers = get_option('vem_lightspeed_dealers', []);

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'paged'          => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_sku',
					'compare' => 'EXISTS'
				]
			]
		];

		do {
			$query = new WP_Query($args);

			foreach ($query->posts as $product_id) {
				$sku = get_post_meta($product_id, '_sku', true);
				if (!$sku) continue;

				// Pull all matching parts for that SKU across all CMFs
				$parts = $wpdb->get_results(
					$wpdb->prepare("SELECT * FROM {$table} WHERE PartNumber = %s", $sku),
					ARRAY_A
				);

				foreach ($parts as $part) {
					$cmf = $part['Cmf'];

					// Match CMF to dealer
					$dealer = array_filter($dealers, fn($d) => isset($d['cmf']) && $d['cmf'] === $cmf);
					$dealer = array_values($dealer)[0] ?? [
						'dealer_name'   => $cmf,
						'cmf'           => $cmf,
						'wc_location_id'=> null
					];

					$this->sync_part_to_woocommerce(
						$part,
						$dealer,
						'manual',
						$log_added,
						$log_updated
					);

					$processed++;
				}
			}

			$args['paged']++;
			wp_reset_postdata();
		} while ($query->max_num_pages >= $args['paged']);

		// ✅ Final summary log
		$this->log_result(
			[
				'dealer_name' => 'All Dealers',
				'cmf'         => 'multi'
			],
			'manual',
			'info',
			sprintf('✅ WooCommerce sync complete | Processed: %d | Updated: %d | Added: %d', $processed, $log_updated, $log_added),
			$log_updated,
			$log_added
		);

		return [
			'success'   => true,
			'message'   => "✅ Synced {$processed} WooCommerce SKUs from Lightspeed table",
			'added'     => $log_added,
			'updated'   => $log_updated,
			'processed' => $processed,
		];
	}
	
	public function render_parts_table_page() {
		require_once plugin_dir_path(__FILE__) . 'vem-lightspeed-parts-table.php';

		$selected_cmf = isset($_GET['cmf']) ? sanitize_text_field($_GET['cmf']) : '';
		$onhand_filter = isset($_GET['onhand']) ? sanitize_text_field($_GET['onhand']) : '';

		// Build CMF dropdown
		global $wpdb;
		$cmfs = $wpdb->get_col("SELECT DISTINCT cmf FROM {$wpdb->prefix}ve_lightspeed_parts ORDER BY cmf ASC");

		echo '<div class="wrap"><h1>Lightspeed Parts</h1>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="vem_lightspeed_parts_table" />';
		echo '<label for="cmf">Dealer (CMF):</label> ';
		echo '<select name="cmf" id="cmf">';
		echo '<option value="">All Dealers</option>';
		foreach ( $cmfs as $cmf ) {
			printf('<option value="%s"%s>%s</option>', esc_attr($cmf), selected($selected_cmf, $cmf, false), esc_html($cmf));
		}
		echo '</select> ';

		echo '<label for="onhand">Stock:</label> ';
		echo '<select name="onhand" id="onhand">';
		echo '<option value="">All</option>';
		echo '<option value="in_stock"' . selected($onhand_filter, 'in_stock', false) . '>In Stock</option>';
		echo '</select> ';

		echo '<input type="submit" class="button" value="Filter">';
		echo '</form><br>';

		$table = new Vem_Lightspeed_Parts_Table();
		$table->prepare_items();
		$table->views();
		$table->display();

		echo '</div>';
	}
}

