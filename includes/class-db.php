<?php
/**
 * Database tables.
 */

defined( 'ABSPATH' ) || exit;

class CPL_DB {

	const DB_VERSION = '2';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cpl_' . $name;
	}

	public static function activate() {
		self::create_tables();
	}

	public static function maybe_upgrade() {
		$installed = get_option( 'cpl_db_version' );
		if ( $installed !== self::DB_VERSION ) {
			self::create_tables();
			if ( $installed && version_compare( $installed, '2', '<' ) ) {
				self::migrate_to_2();
			}
		}
	}

	/**
	 * 1 → 2: the "On-going Data, Updated till …" note becomes the Ongoing switch, and every year with a
	 * student list gets a "table last updated" date (the date in that note if there is one, otherwise the
	 * date the year was last saved).
	 */
	private static function migrate_to_2() {
		global $wpdb;
		$table   = self::table( 'years' );
		$records = self::table( 'records' );
		$rows    = $wpdb->get_results( "SELECT y.year, y.records_note, y.updated_at, (SELECT COUNT(*) FROM $records r WHERE r.year = y.year) AS n FROM $table y", ARRAY_A );
		foreach ( (array) $rows as $r ) {
			$data = array();
			$note = (string) $r['records_note'];
			if ( preg_match( '/on-?\s?going/i', $note ) ) {
				$data['ongoing']      = 1;
				$data['records_note'] = '';
			}
			if ( (int) $r['n'] > 0 ) {
				$date = null;
				if ( preg_match( '/(\d{1,2})(?:st|nd|rd|th)?\s+([A-Za-z]+),?\s+(\d{4})/', $note, $m ) ) {
					$ts = strtotime( $m[1] . ' ' . $m[2] . ' ' . $m[3] );
					if ( $ts ) {
						$date = gmdate( 'Y-m-d 00:00:00', $ts );
					}
				}
				$data['records_updated'] = $date ?: ( $r['updated_at'] ?: current_time( 'mysql' ) );
			}
			if ( $data ) {
				$wpdb->update( $table, $data, array( 'year' => (int) $r['year'] ) );
			}
		}
		delete_transient( 'cpl_chart_payload' );
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$years    = self::table( 'years' );
		$summary  = self::table( 'summary' );
		$records  = self::table( 'records' );
		$aliases  = self::table( 'aliases' );

		dbDelta( "CREATE TABLE $years (
			year smallint(5) unsigned NOT NULL,
			students_appeared int(10) unsigned NOT NULL DEFAULT 0,
			highest_pkg varchar(100) NOT NULL DEFAULT '',
			avg_pkg varchar(100) NOT NULL DEFAULT '',
			chart_source varchar(10) NOT NULL DEFAULT 'records',
			featured longtext NULL,
			footnote text NULL,
			records_note text NULL,
			ongoing tinyint(1) unsigned NOT NULL DEFAULT 0,
			records_updated datetime NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (year)
		) $charset;" );

		dbDelta( "CREATE TABLE $summary (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			year smallint(5) unsigned NOT NULL,
			position int(10) unsigned NOT NULL DEFAULT 0,
			company varchar(255) NOT NULL DEFAULT '',
			offers int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY year (year)
		) $charset;" );

		dbDelta( "CREATE TABLE $records (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			year smallint(5) unsigned NOT NULL,
			position int(10) unsigned NOT NULL DEFAULT 0,
			sl varchar(20) NOT NULL DEFAULT '',
			dept varchar(50) NOT NULL DEFAULT '',
			name varchar(255) NOT NULL DEFAULT '',
			urn varchar(50) NOT NULL DEFAULT '',
			org varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY year (year)
		) $charset;" );

		dbDelta( "CREATE TABLE $aliases (
			alias_key varchar(191) NOT NULL,
			alias varchar(255) NOT NULL DEFAULT '',
			company varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (alias_key)
		) $charset;" );

		update_option( 'cpl_db_version', self::DB_VERSION );
	}
}
