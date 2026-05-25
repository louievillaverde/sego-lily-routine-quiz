<?php
/**
 * Local persistence + reporting for quiz completions.
 *
 * Survives Mautic outages: every completion is written here BEFORE the
 * Mautic call. If Mautic fails, the record remains locally with
 * mautic_synced=0 and the error message captured, ready for re-sync from
 * the admin dashboard.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Completions {

	const TABLE = 'slrq_completions';
	const SCHEMA_VERSION_OPTION = 'slrq_completions_schema_version';
	const SCHEMA_VERSION = '1';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function install() {
		global $wpdb;
		$installed = get_option( self::SCHEMA_VERSION_OPTION );
		if ( $installed === self::SCHEMA_VERSION ) {
			return;
		}
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
email VARCHAR(255) NOT NULL,
firstname VARCHAR(60) NOT NULL DEFAULT '',
skin_concern VARCHAR(80) NOT NULL DEFAULT '',
product_count VARCHAR(80) NOT NULL DEFAULT '',
frustration VARCHAR(80) NOT NULL DEFAULT '',
recommendation_slug VARCHAR(160) NOT NULL DEFAULT '',
mautic_synced TINYINT(1) NOT NULL DEFAULT 0,
mautic_error TEXT NULL,
source_url VARCHAR(500) NULL,
user_ip VARCHAR(45) NULL,
completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY  (id),
KEY email_idx (email),
KEY completed_at_idx (completed_at),
KEY synced_idx (mautic_synced)
) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Insert a completion row. Returns insert id or 0 on failure.
	 */
	public static function store( $data ) {
		global $wpdb;
		try {
			$row = array(
				'email'               => substr( (string) ( $data['email'] ?? '' ), 0, 255 ),
				'firstname'           => substr( (string) ( $data['firstname'] ?? '' ), 0, 60 ),
				'skin_concern'        => substr( (string) ( $data['skin_concern'] ?? '' ), 0, 80 ),
				'product_count'       => substr( (string) ( $data['product_count'] ?? '' ), 0, 80 ),
				'frustration'         => substr( (string) ( $data['frustration'] ?? '' ), 0, 80 ),
				'recommendation_slug' => substr( (string) ( $data['recommendation_slug'] ?? '' ), 0, 160 ),
				'source_url'          => substr( (string) ( $data['source_url'] ?? '' ), 0, 500 ),
				'user_ip'             => substr( (string) ( $data['user_ip'] ?? '' ), 0, 45 ),
				'completed_at'        => current_time( 'mysql' ),
			);
			$ok = $wpdb->insert( self::table_name(), $row );
			if ( $ok === false ) {
				error_log( '[SLRQ] Completion DB insert failed: ' . $wpdb->last_error );
				return 0;
			}
			return (int) $wpdb->insert_id;
		} catch ( \Throwable $e ) {
			error_log( '[SLRQ] Completion store exception: ' . $e->getMessage() );
			return 0;
		}
	}

	public static function mark_synced( $id, $success, $error_msg = '' ) {
		global $wpdb;
		if ( ! $id ) {
			return;
		}
		try {
			$wpdb->update(
				self::table_name(),
				array(
					'mautic_synced' => $success ? 1 : 0,
					'mautic_error'  => $success ? null : substr( (string) $error_msg, 0, 2000 ),
				),
				array( 'id' => (int) $id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		} catch ( \Throwable $e ) {
			error_log( '[SLRQ] Completion mark_synced exception: ' . $e->getMessage() );
		}
	}

	public static function get_stats() {
		global $wpdb;
		$table = self::table_name();
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$last7  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" );
		$last30 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" );
		$synced = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE mautic_synced=1" );
		$rate   = $total > 0 ? round( ( $synced / $total ) * 100, 1 ) : 100.0;
		return array(
			'total'        => $total,
			'last_7_days'  => $last7,
			'last_30_days' => $last30,
			'synced'       => $synced,
			'sync_rate'    => $rate,
			'failed'       => $total - $synced,
		);
	}

	public static function get_distribution( $field ) {
		global $wpdb;
		$allowed = array( 'skin_concern', 'product_count', 'frustration', 'recommendation_slug' );
		if ( ! in_array( $field, $allowed, true ) ) {
			return array();
		}
		$table = self::table_name();
		$sql = "SELECT {$field} AS k, COUNT(*) AS c FROM {$table} WHERE {$field} != '' GROUP BY {$field} ORDER BY c DESC LIMIT 20";
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return $rows ?: array();
	}

	public static function get_recent( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table  = self::table_name();
		$limit  = max( 1, min( 500, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY completed_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Daily completion counts for the last N days. Returns array keyed by
	 * Y-m-d with int counts (zeros included for empty days).
	 */
	public static function get_daily_counts( $days = 30 ) {
		global $wpdb;
		$table = self::table_name();
		$days  = max( 1, min( 365, (int) $days ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(completed_at) AS d, COUNT(*) AS c FROM {$table}
				 WHERE completed_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
				 GROUP BY DATE(completed_at) ORDER BY d ASC",
				$days
			),
			ARRAY_A
		);
		$by_date = array();
		foreach ( (array) $rows as $r ) {
			$by_date[ $r['d'] ] = (int) $r['c'];
		}
		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$key       = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days', current_time( 'timestamp' ) ) );
			$out[ $key ] = isset( $by_date[ $key ] ) ? (int) $by_date[ $key ] : 0;
		}
		return $out;
	}

	/**
	 * Filtered + sorted query for the Results tab.
	 *
	 * @param array $args { date_from, date_to, skin_concern, frustration, sync_status, search, orderby, order, paged, per_page }
	 * @return array { rows, total, pages }
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = self::table_name();
		$defaults = array(
			'date_from'    => '',
			'date_to'      => '',
			'skin_concern' => '',
			'frustration'  => '',
			'sync_status'  => '',
			'search'       => '',
			'orderby'      => 'completed_at',
			'order'        => 'DESC',
			'paged'        => 1,
			'per_page'     => 25,
		);
		$a = array_merge( $defaults, $args );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $a['date_from'] ) ) {
			$where[]  = 'completed_at >= %s';
			$params[] = $a['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $a['date_to'] ) ) {
			$where[]  = 'completed_at <= %s';
			$params[] = $a['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $a['skin_concern'] ) ) {
			$where[]  = 'skin_concern = %s';
			$params[] = $a['skin_concern'];
		}
		if ( ! empty( $a['frustration'] ) ) {
			$where[]  = 'frustration = %s';
			$params[] = $a['frustration'];
		}
		if ( $a['sync_status'] === 'synced' ) {
			$where[] = 'mautic_synced = 1';
		} elseif ( $a['sync_status'] === 'failed' ) {
			$where[] = 'mautic_synced = 0';
		}
		if ( ! empty( $a['search'] ) ) {
			$where[]  = '(email LIKE %s OR firstname LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$allowed_orderby = array( 'completed_at', 'email', 'firstname', 'skin_concern', 'frustration', 'mautic_synced' );
		$orderby = in_array( $a['orderby'], $allowed_orderby, true ) ? $a['orderby'] : 'completed_at';
		$order   = strtoupper( $a['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$per_page = max( 1, min( 200, (int) $a['per_page'] ) );
		$paged    = max( 1, (int) $a['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( empty( $params )
			? $wpdb->get_var( $count_sql )
			: $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );

		$rows_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );

		return array(
			'rows'  => $rows ?: array(),
			'total' => $total,
			'pages' => max( 1, (int) ceil( $total / $per_page ) ),
		);
	}

	/**
	 * Join completions to WC orders by email. Returns conversion metrics for
	 * the given date window. Gracefully degrades if WC is not active.
	 */
	public static function conversion_metrics( $date_from = null, $date_to = null ) {
		global $wpdb;
		$out = array(
			'wc_active'       => function_exists( 'wc_get_orders' ),
			'completions'     => 0,
			'converted'       => 0,
			'conversion_rate' => 0.0,
			'revenue'         => 0.0,
			'avg_days'        => null,
		);
		if ( ! $out['wc_active'] ) {
			return $out;
		}
		$table = self::table_name();

		$range_sql = '';
		$params    = array();
		if ( $date_from ) {
			$range_sql .= ' AND completed_at >= %s';
			$params[]   = $date_from . ' 00:00:00';
		}
		if ( $date_to ) {
			$range_sql .= ' AND completed_at <= %s';
			$params[]   = $date_to . ' 23:59:59';
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1{$range_sql}";
		$completions = (int) ( empty( $params )
			? $wpdb->get_var( $count_sql )
			: $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) );
		$out['completions'] = $completions;
		if ( $completions === 0 ) {
			return $out;
		}

		$rows_sql = "SELECT id, email, completed_at FROM {$table} WHERE 1=1{$range_sql} ORDER BY completed_at ASC";
		$rows = empty( $params )
			? $wpdb->get_results( $rows_sql, ARRAY_A )
			: $wpdb->get_results( $wpdb->prepare( $rows_sql, $params ), ARRAY_A );

		$converted_emails = array();
		$revenue          = 0.0;
		$day_deltas       = array();

		foreach ( (array) $rows as $row ) {
			$email = $row['email'];
			if ( isset( $converted_emails[ $email ] ) ) {
				continue;
			}
			$orders = wc_get_orders( array(
				'limit'         => 1,
				'orderby'       => 'date',
				'order'         => 'ASC',
				'billing_email' => $email,
				'status'        => array( 'wc-processing', 'wc-completed' ),
				'date_after'    => $row['completed_at'],
				'return'        => 'objects',
			) );
			if ( ! empty( $orders ) ) {
				$order             = $orders[0];
				$converted_emails[ $email ] = true;
				$revenue          += (float) $order->get_total();
				$completed_ts      = strtotime( $row['completed_at'] );
				$order_ts          = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : null;
				if ( $completed_ts && $order_ts && $order_ts >= $completed_ts ) {
					$day_deltas[] = ( $order_ts - $completed_ts ) / 86400;
				}
			}
		}

		$converted = count( $converted_emails );
		$out['converted']       = $converted;
		$out['conversion_rate'] = $completions > 0 ? round( ( $converted / $completions ) * 100, 1 ) : 0.0;
		$out['revenue']         = round( $revenue, 2 );
		$out['avg_days']        = ! empty( $day_deltas ) ? round( array_sum( $day_deltas ) / count( $day_deltas ), 1 ) : null;

		return $out;
	}

	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::table_name();
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id=%d", (int) $id ),
			ARRAY_A
		);
	}

	public static function resync( $id ) {
		$row = self::get_by_id( $id );
		if ( ! $row ) {
			return array( 'success' => false, 'message' => 'Record not found.' );
		}
		$result = SLRQ_Mautic::send_quiz_lead( array(
			'email'         => $row['email'],
			'firstname'     => $row['firstname'],
			'skin_concern'  => $row['skin_concern'],
			'product_count' => $row['product_count'],
			'frustration'   => $row['frustration'],
		) );
		self::mark_synced( $id, $result['success'], $result['success'] ? '' : ( $result['message'] ?? '' ) );
		return $result;
	}

	public static function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table_name() );
	}

	public static function export_csv() {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM " . self::table_name() . " ORDER BY completed_at DESC", ARRAY_A );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'id', 'completed_at', 'email', 'firstname', 'skin_concern', 'product_count', 'frustration', 'recommendation_slug', 'mautic_synced', 'mautic_error', 'source_url', 'user_ip' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array(
				$r['id'], $r['completed_at'], $r['email'], $r['firstname'],
				$r['skin_concern'], $r['product_count'], $r['frustration'],
				$r['recommendation_slug'], $r['mautic_synced'], $r['mautic_error'],
				$r['source_url'], $r['user_ip'],
			) );
		}
		fclose( $out );
	}
}
