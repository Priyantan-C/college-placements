<?php
/**
 * Database tables.
 */

defined( 'ABSPATH' ) || exit;

class CPL_DB {

	const DB_VERSION = '1';

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'cpl_' . $name;
	}

	public static function activate() {
		self::create_tables();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'cpl_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
		}
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
