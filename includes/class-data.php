<?php
/**
 * Data access and the calculations behind the charts.
 */

defined( 'ABSPATH' ) || exit;

class STCET_PL_Data {

	const CACHE_KEY   = 'stcet_pl_chart_payload';
	const OTHERS      = 'Others';
	const SOURCE_SUM  = 'summary';
	const SOURCE_REC  = 'records';

	/* ---------- Text helpers ---------- */

	/** Collapse whitespace (including non-breaking spaces) and trim. */
	public static function clean( $value ) {
		$value = (string) $value;
		$value = str_replace( array( "\xC2\xA0", "\t", "\r", "\n" ), ' ', $value );
		return trim( preg_replace( '/\s+/u', ' ', $value ) );
	}

	/** Key used to match company spellings: lower case, letters and digits only. */
	public static function key( $value ) {
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( self::clean( $value ) ) : strtolower( self::clean( $value ) );
		return preg_replace( '/[^\p{L}\p{N}]+/u', '', $value );
	}

	/**
	 * Map a header row to column roles. Returns role => column index, e.g. ['name' => 2, 'org' => 4].
	 */
	public static function map_headers( array $cells ) {
		$roles = array(
			'sl'   => array( 'sl', 'slno', 'serial', 'serialno', 'serialnumber', 'sno', 'no' ),
			'dept' => array( 'dept', 'department', 'branch', 'stream' ),
			'name' => array( 'name', 'studentname', 'nameofstudent', 'nameofthestudent', 'student' ),
			'urn'  => array( 'urn', 'universityrollnumber', 'universityrollno', 'rollno', 'rollnumber', 'universityroll' ),
			'org'  => array( 'org', 'organization', 'organisation', 'company', 'companyname', 'nameofcompany', 'nameoforganization', 'nameoforganisation', 'recruiter', 'employer' ),
		);
		$map = array();
		foreach ( $cells as $i => $cell ) {
			$k = self::key( $cell );
			if ( '' === $k ) {
				continue;
			}
			foreach ( $roles as $role => $names ) {
				if ( ! isset( $map[ $role ] ) && in_array( $k, $names, true ) ) {
					$map[ $role ] = $i;
					break;
				}
			}
		}
		return ( isset( $map['name'] ) && isset( $map['org'] ) ) ? $map : array();
	}

	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/* ---------- Company names ---------- */

	public static function get_aliases() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT alias_key, alias, company FROM ' . STCET_PL_DB::table( 'aliases' ) . ' ORDER BY company, alias', ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ $r['alias_key'] ] = $r;
		}
		return $out;
	}

	public static function save_alias( $alias, $company, $flush = true ) {
		global $wpdb;
		$alias   = self::clean( $alias );
		$company = self::clean( $company );
		$key     = self::key( $alias );
		if ( '' === $key || '' === $company ) {
			return;
		}
		$wpdb->replace( STCET_PL_DB::table( 'aliases' ), array(
			'alias_key' => substr( $key, 0, 191 ),
			'alias'     => $alias,
			'company'   => $company,
		) );
		if ( $flush ) {
			self::flush_cache();
		}
	}

	public static function delete_alias( $alias_key ) {
		global $wpdb;
		$wpdb->delete( STCET_PL_DB::table( 'aliases' ), array( 'alias_key' => $alias_key ) );
		self::flush_cache();
	}

	/**
	 * Company name for a spelling: the saved match if there is one, otherwise the spelling itself.
	 * Spellings that differ only in case, spaces or punctuation count as the same.
	 *
	 * @param array $aliases  From get_aliases().
	 * @param array $seen     Key => first spelling seen in this batch, so unmatched variants merge.
	 */
	public static function resolve( $raw, array $aliases, array &$seen = array() ) {
		$raw = self::clean( $raw );
		$key = self::key( $raw );
		if ( '' === $key ) {
			return '';
		}
		if ( isset( $aliases[ $key ] ) ) {
			return $aliases[ $key ]['company'];
		}
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = $raw;
		}
		return $seen[ $key ];
	}

	/** True when a spelling has a saved match. */
	public static function is_matched( $raw, array $aliases ) {
		return isset( $aliases[ self::key( $raw ) ] );
	}

	/* ---------- Years ---------- */

	public static function get_years() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . STCET_PL_DB::table( 'years' ) . ' ORDER BY year DESC', ARRAY_A );
		return array_map( array( __CLASS__, 'hydrate_year' ), (array) $rows );
	}

	public static function get_year( $year ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . STCET_PL_DB::table( 'years' ) . ' WHERE year = %d', $year ), ARRAY_A );
		return $row ? self::hydrate_year( $row ) : null;
	}

	private static function hydrate_year( $row ) {
		$row['year']              = (int) $row['year'];
		$row['students_appeared'] = (int) $row['students_appeared'];
		$featured                 = json_decode( (string) $row['featured'], true );
		$row['featured']          = is_array( $featured ) ? $featured : array();
		$row['footnote']          = (string) $row['footnote'];
		$row['records_note']      = (string) $row['records_note'];
		return $row;
	}

	/** Create the year if it does not exist yet. */
	public static function ensure_year( $year, $source = self::SOURCE_REC ) {
		global $wpdb;
		if ( self::get_year( $year ) ) {
			return;
		}
		$wpdb->insert( STCET_PL_DB::table( 'years' ), array(
			'year'         => (int) $year,
			'chart_source' => $source,
			'featured'     => wp_json_encode( array() ),
			'footnote'     => '',
			'records_note' => '',
			'updated_at'   => current_time( 'mysql' ),
		) );
		self::flush_cache();
	}

	public static function update_year( $year, array $fields ) {
		global $wpdb;
		$allowed = array( 'students_appeared', 'highest_pkg', 'avg_pkg', 'chart_source', 'featured', 'footnote', 'records_note' );
		$data    = array_intersect_key( $fields, array_flip( $allowed ) );
		if ( isset( $data['featured'] ) && is_array( $data['featured'] ) ) {
			$data['featured'] = wp_json_encode( array_values( $data['featured'] ) );
		}
		$data['updated_at'] = current_time( 'mysql' );
		$wpdb->update( STCET_PL_DB::table( 'years' ), $data, array( 'year' => (int) $year ) );
		self::flush_cache();
	}

	public static function delete_year( $year ) {
		global $wpdb;
		foreach ( array( 'years', 'summary', 'records' ) as $t ) {
			$wpdb->delete( STCET_PL_DB::table( $t ), array( 'year' => (int) $year ) );
		}
		self::flush_cache();
	}

	/* ---------- Summary rows (v5 data or typed in) ---------- */

	public static function get_summary( $year ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT company, offers FROM ' . STCET_PL_DB::table( 'summary' ) . ' WHERE year = %d ORDER BY position, id', $year ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( $r['company'], (int) $r['offers'] );
		}
		return $out;
	}

	/** @param array $companies List of [company, offers]. */
	public static function save_summary( $year, array $companies, $flush = true ) {
		global $wpdb;
		$table = STCET_PL_DB::table( 'summary' );
		$wpdb->delete( $table, array( 'year' => (int) $year ) );
		$pos = 0;
		foreach ( $companies as $c ) {
			$name = self::clean( $c[0] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$wpdb->insert( $table, array(
				'year'     => (int) $year,
				'position' => $pos++,
				'company'  => $name,
				'offers'   => max( 0, (int) ( $c[1] ?? 0 ) ),
			) );
		}
		if ( $flush ) {
			self::flush_cache();
		}
	}

	/* ---------- Student records ---------- */

	public static function get_records( $year ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT sl, dept, name, urn, org FROM ' . STCET_PL_DB::table( 'records' ) . ' WHERE year = %d ORDER BY position, id', $year ), ARRAY_A );
	}

	public static function record_counts_by_year() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT year, COUNT(*) AS n FROM ' . STCET_PL_DB::table( 'records' ) . ' GROUP BY year', ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['year'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Replace a year's student list. Each row: sl, dept, name, urn, org. */
	public static function replace_records( $year, array $rows ) {
		global $wpdb;
		$table = STCET_PL_DB::table( 'records' );
		$wpdb->query( 'START TRANSACTION' );
		$wpdb->delete( $table, array( 'year' => (int) $year ) );
		$pos = 0;
		foreach ( $rows as $r ) {
			$wpdb->insert( $table, array(
				'year'     => (int) $year,
				'position' => $pos++,
				'sl'       => substr( self::clean( $r['sl'] ?? '' ), 0, 20 ),
				'dept'     => substr( self::clean( $r['dept'] ?? '' ), 0, 50 ),
				'name'     => substr( self::clean( $r['name'] ?? '' ), 0, 255 ),
				'urn'      => substr( self::clean( $r['urn'] ?? '' ), 0, 50 ),
				'org'      => substr( self::clean( $r['org'] ?? '' ), 0, 255 ),
			) );
		}
		$wpdb->query( 'COMMIT' );
		self::flush_cache();
	}

	/**
	 * Offers per company from the student list, in order of first appearance.
	 *
	 * @return array company => ['offers' => int, 'spellings' => [spelling => count]]
	 */
	public static function company_counts( array $records, ?array $aliases = null ) {
		$aliases = null === $aliases ? self::get_aliases() : $aliases;
		$seen    = array();
		$out     = array();
		foreach ( $records as $r ) {
			$raw     = self::clean( $r['org'] ?? '' );
			$company = self::resolve( $raw, $aliases, $seen );
			if ( '' === $company ) {
				continue;
			}
			if ( ! isset( $out[ $company ] ) ) {
				$out[ $company ] = array( 'offers' => 0, 'spellings' => array() );
			}
			$out[ $company ]['offers']++;
			$out[ $company ]['spellings'][ $raw ] = ( $out[ $company ]['spellings'][ $raw ] ?? 0 ) + 1;
		}
		return $out;
	}

	/**
	 * Rows shown for a year: list of [company, offers].
	 * For calculated years, companies not ticked as "shown separately" are added up into Others.
	 */
	public static function breakdown( array $year_row, ?array $aliases = null ) {
		if ( self::SOURCE_SUM === $year_row['chart_source'] ) {
			return array_values( array_filter( self::get_summary( $year_row['year'] ), function ( $c ) {
				return $c[1] > 0;
			} ) );
		}
		$counts   = self::company_counts( self::get_records( $year_row['year'] ), $aliases );
		$featured = $year_row['featured'];
		if ( empty( $featured ) ) {
			$rows = array();
			foreach ( $counts as $company => $c ) {
				$rows[] = array( $company, $c['offers'] );
			}
			return $rows;
		}
		$featured_keys = array_map( array( __CLASS__, 'key' ), $featured );
		$rows          = array();
		$others        = 0;
		$others_name   = self::OTHERS;
		foreach ( $counts as $company => $c ) {
			$k = self::key( $company );
			if ( 'others' === $k ) {
				$others     += $c['offers'];
				$others_name = $company;
			} elseif ( in_array( $k, $featured_keys, true ) ) {
				$rows[] = array( $company, $c['offers'] );
			} else {
				$others += $c['offers'];
			}
		}
		if ( $others > 0 ) {
			$rows[] = array( $others_name, $others );
		}
		return $rows;
	}

	/**
	 * Everything the chart shortcodes need, oldest year first. Cached until data changes.
	 */
	public static function chart_payload() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$aliases = self::get_aliases();
		$years   = array();
		foreach ( array_reverse( self::get_years() ) as $y ) {
			$rows = self::breakdown( $y, $aliases );
			if ( empty( $rows ) ) {
				continue;
			}
			$years[] = array(
				'year'     => $y['year'],
				'appeared' => $y['students_appeared'],
				'highest'  => $y['highest_pkg'],
				'avg'      => $y['avg_pkg'],
				'footnote' => $y['footnote'],
				'rows'     => $rows,
			);
		}
		$payload = array( 'years' => $years );
		set_transient( self::CACHE_KEY, $payload, WEEK_IN_SECONDS );
		return $payload;
	}
}
