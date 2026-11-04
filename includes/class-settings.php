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
	}

	/** Fill {college} and {year} into a heading. */
	public static function format( $text, $year = '' ) {
		return strtr( (string) $text, array(
			'{college}' => self::get( 'college_name' ),
			'{year}'    => (string) $year,
		) );
	}
}
