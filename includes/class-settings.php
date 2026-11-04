<?php
/**
 * Site-wide settings: the college name and the headings used on the front end.
 */

defined( 'ABSPATH' ) || exit;

class CPL_Settings {

	const OPTION = 'cpl_settings';

	public static function defaults() {
		return array(
			'college_name'    => get_bloginfo( 'name' ),
			'trend_title'     => 'Placements in {college} over the years',
			'year_title'      => 'Placement Data Year wise',
			'records_heading' => '{year} Placement Data',
			'dept_order'      => '',
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? array_filter( $saved, 'strlen' ) : array() );
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? '';
	}

	public static function save( array $values ) {
		$clean = array();
		foreach ( array_keys( self::defaults() ) as $key ) {
			$clean[ $key ] = sanitize_text_field( $values[ $key ] ?? '' );
		}
		update_option( self::OPTION, $clean );
		CPL_Data::flush_cache();
	}

	/** Department codes in the order the college lists them, e.g. ['CSE', 'AIML', 'EE', 'ECE', 'IT']. */
	public static function dept_order() {
		return array_values( array_filter( array_map( function ( $d ) {
			return strtoupper( trim( $d ) );
		}, explode( ',', (string) self::get( 'dept_order' ) ) ), 'strlen' ) );
	}

	/** Sort department codes: the configured order first, then any others alphabetically. */
	public static function sort_depts( array $depts ) {
		$order = array_flip( self::dept_order() );
		usort( $depts, function ( $a, $b ) use ( $order ) {
			$ra = $order[ strtoupper( $a ) ] ?? PHP_INT_MAX;
			$rb = $order[ strtoupper( $b ) ] ?? PHP_INT_MAX;
			return $ra === $rb ? strcmp( $a, $b ) : $ra <=> $rb;
		} );
		return $depts;
	}

	/** Fill {college} and {year} into a heading. */
	public static function format( $text, $year = '' ) {
		return strtr( (string) $text, array(
			'{college}' => self::get( 'college_name' ),
			'{year}'    => (string) $year,
		) );
	}
}
