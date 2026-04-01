<?php
// vem-lightspeed-logs-table.php
if (!class_exists('Vem_Lightspeed_Logs_Table')) {
	class Vem_Lightspeed_Logs_Table extends WP_List_Table {
		public function __construct() {
			parent::__construct([
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false
			]);
		}

		public function get_columns() {
			return [
				'created_at' => 'Date',
				'dealer_name' => 'Dealer',
				'run_type' => 'Type',
				'status' => 'Status',
				'records_updated' => 'Updated',
				'records_added' => 'Added',
				'message' => 'Message',
			];
		}

		public function prepare_items() {
			global $wpdb;
			$table = $wpdb->prefix . 've_lightspeed_logs';

			$per_page = 25;
			$current_page = $this->get_pagenum();
			$offset = ($current_page - 1) * $per_page;

			$where_clauses = [];
			$params = [];

			if (!empty($_GET['status'])) {
				$where_clauses[] = 'status = %s';
				$params[] = sanitize_text_field(stripslashes($_GET['status']));
			}

			if (!empty($_GET['run_type'])) {
				$where_clauses[] = 'run_type = %s';
				$params[] = sanitize_text_field(stripslashes($_GET['run_type']));
			}

			$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

			// Total count
			$count_sql = "SELECT COUNT(*) FROM $table $where_sql";
			$total_items = $wpdb->get_var($wpdb->prepare($count_sql, ...$params));

			// Data query
			$data_sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
			$params[] = $per_page;
			$params[] = $offset;
			$this->items = $wpdb->get_results($wpdb->prepare($data_sql, ...$params), ARRAY_A);

			$this->set_pagination_args([
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil($total_items / $per_page),
			]);
			$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'created_at'];
		}
		
		public function display_rows() {
			foreach ( $this->items as $item ) {
				echo '<tr>';
				$this->single_row_columns( $item ); // ✅ This will use column_status, column_records_updated, etc.
				echo '</tr>';
			}
		}
		
		public function get_sortable_columns() {
			return [
				'created_at' => ['created_at', true],
				'dealer_name' => ['dealer_name', false],
				'run_type' => ['run_type', false],
				'status' => ['status', false],
			];
		}

		public function column_default($item, $column_name) {
			switch ($column_name) {
				case 'created_at':
				case 'dealer_name':
				case 'run_type':
				case 'status':
				case 'records_updated':
				case 'records_added':
					return esc_html($item[$column_name]);
				case 'message':
					return '<pre style="white-space:pre-wrap">' . esc_html($item['message']) . '</pre>';
				default:
					return print_r($item, true);
			}
		}
		
		public function column_status($item) {
			$status = strtolower($item['status']);
			$label  = ucfirst($status);

			$badge_class = match ($status) {
				'success' => 'badge-success',  // green
				'failure' => 'badge-danger',   // red
				'info'    => 'badge-secondary',// gray/blue
				default   => 'badge-light',    // fallback
			};

			return sprintf(
				'<span style="display:inline-block; width:80px;" class="badge %s">%s</span>',
				esc_attr($badge_class),
				esc_html($label)
			);
		}


	}
}
