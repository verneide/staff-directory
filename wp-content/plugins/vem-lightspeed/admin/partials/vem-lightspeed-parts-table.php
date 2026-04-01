<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Vem_Lightspeed_Parts_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'part',
            'plural'   => 'parts',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
		return [
			'cmf'                 => 'Dealer (CMF)',
			'PartNumber'          => 'Part Number',
			'Description'         => 'Description',
			'OnHand'              => 'On Hand',
			'Cost'                => 'Cost',
			'Retail'              => 'Retail',
			'dms_lastupdateddate' => 'Last Updated',
		];
	}

    public function prepare_items() {
		global $wpdb;
		$table = $wpdb->prefix . 've_lightspeed_parts';

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ($current_page - 1) * $per_page;

		// Filters
		$filters = [];
		if ( isset($_GET['cmf']) && $_GET['cmf'] !== '' ) {
			$filters[] = $wpdb->prepare("cmf = %s", sanitize_text_field($_GET['cmf']));
		}
		if ( isset($_GET['onhand']) && $_GET['onhand'] === 'in_stock' ) {
			$filters[] = "OnHand > 0";
		}
		$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
		if ( $search ) {
			$filters[] = $wpdb->prepare("(PartNumber LIKE %s OR Description LIKE %s)", "%$search%", "%$search%");
		}
		$where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

		// Sorting
		$orderby = isset($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'dms_lastupdateddate';
		$order   = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC','DESC']) ? strtoupper($_GET['order']) : 'DESC';

		// Total
		$total_items = $wpdb->get_var("SELECT COUNT(*) FROM {$table} {$where}");

		// Data
		$results = $wpdb->get_results("
			SELECT PartNumber, Description, OnHand, Cost, Retail, dms_lastupdateddate, cmf
			FROM {$table}
			{$where}
			ORDER BY {$orderby} {$order}
			LIMIT {$per_page} OFFSET {$offset}
		", ARRAY_A);

		$this->items = $results;

		$this->set_pagination_args([
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items / $per_page),
		]);

		$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'PartNumber'];
	}

    public function column_default($item, $column_name) {
		if (!isset($item[$column_name])) {
			return '<span style="color:red;">Missing: ' . esc_html($column_name) . '</span>';
		}

		switch ($column_name) {
			case 'Cost':
			case 'Retail':
				return '$' . number_format((float)$item[$column_name], 2);
			case 'dms_lastupdateddate':
				return date('Y-m-d H:i', strtotime($item[$column_name]));
			default:
				return esc_html($item[$column_name]);
		}
	}
	
	public function display_rows() {
		foreach ( $this->items as $item ) {
			echo '<tr>';
			foreach ( $this->get_columns() as $column_name => $column_display_name ) {
				echo '<td>';
				echo $this->column_default( $item, $column_name );
				echo '</td>';
			}
			echo '</tr>';
		}
	}
	
	

    public function get_table_classes() {
        return ['widefat', 'striped', 'table-view-list', 'fixed'];
    }
	
	public function get_sortable_columns() {
		return [
			'PartNumber'          => ['PartNumber', true],
			'OnHand'              => ['OnHand', false],
			'Cost'                => ['Cost', false],
			'Retail'              => ['Retail', false],
			'dms_lastupdateddate' => ['dms_lastupdateddate', false],
			'cmf'                 => ['cmf', false],
		];
	}
	
	public function extra_tablenav($which) {
		if ( $which !== 'top' ) return;

		$cmfs = $this->get_unique_cmfs();
		$current_cmf = $_GET['cmf'] ?? '';
		echo '<div class="alignleft actions">';
		echo '<select name="cmf">';
		echo '<option value="">All Dealers</option>';
		foreach ( $cmfs as $cmf ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr($cmf),
				selected($current_cmf, $cmf, false),
				esc_html($cmf)
			);
		}
		echo '</select>';
		submit_button('Filter', '', 'filter_action', false);
		echo '</div>';
	}

	private function get_unique_cmfs() {
		global $wpdb;
		$table = $wpdb->prefix . 've_lightspeed_parts';
		return $wpdb->get_col("SELECT DISTINCT cmf FROM {$table} ORDER BY cmf ASC");
	}
	
	public function display() {
		echo '<form method="get">';

		// Preserve query vars so filters/search persist
		foreach ($_GET as $key => $value) {
			if (in_array($key, ['page', 'orderby', 'order', 'cmf', 'onhand'])) {
				printf('<input type="hidden" name="%s" value="%s" />', esc_attr($key), esc_attr($value));
			}
		}

		$this->search_box('Search Parts', 'search_parts');
		$this->display_tablenav('top');

		echo '<table class="wp-list-table ' . implode(' ', $this->get_table_classes()) . '">';
		echo '<thead>';
		$this->print_column_headers();
		echo '</thead>';
		echo '<tbody id="the-list">';
		$this->display_rows();
		echo '</tbody>';
		echo '<tfoot>';
		$this->print_column_headers();
		echo '</tfoot>';
		echo '</table>';

		$this->display_tablenav('bottom');

		echo '</form>';
	}
}