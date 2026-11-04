<?php
/**
 * Reads the student placement list from an uploaded .xlsx file.
 */

defined( 'ABSPATH' ) || exit;

class CPL_Xlsx_Import {

	/**
	 * @return array ['rows' => [...], 'sheet' => string, 'errors' => [], 'warnings' => []]
	 */
	public static function parse( $path ) {
		require_once CPL_DIR . 'lib/simplexlsx/SimpleXLSX.php';

		$result = array( 'rows' => array(), 'sheet' => '', 'title' => '', 'errors' => array(), 'warnings' => array() );

		$xlsx = \Shuchkin\SimpleXLSX::parse( $path );
		if ( ! $xlsx ) {
			$result['errors'][] = 'Could not read the file as an Excel workbook (.xlsx). ' . \Shuchkin\SimpleXLSX::parseError();
			return $result;
		}

		foreach ( $xlsx->sheetNames() as $index => $sheet_name ) {
			$rows = $xlsx->rows( $index );
			$found = self::read_sheet( $rows );
			if ( $found ) {
				$found['sheet'] = $sheet_name;
				return array_merge( $result, $found );
			}
		}

		$result['errors'][] = 'No sheet has a header row with at least a "Name" column and an "Org" / "Organization" / "Company" column.';
		return $result;
	}

	/**
	 * Find the header row in the first 15 rows, then read every student row below it.
	 */
	public static function read_sheet( array $rows ) {
		$header_at = null;
		$map       = array();
		$title     = '';
		foreach ( array_slice( $rows, 0, 15, true ) as $i => $cells ) {
			$map = CPL_Data::map_headers( $cells );
			if ( $map ) {
				$header_at = $i;
				break;
			}
			if ( '' === $title ) {
				$title = CPL_Data::clean( implode( ' ', array_filter( array_map( 'strval', $cells ) ) ) );
			}
		}
		if ( null === $header_at ) {
			return null;
		}

		$out      = array();
		$warnings = array();
		$line     = $header_at + 1;
		foreach ( array_slice( $rows, $header_at + 1 ) as $cells ) {
			$line++;
			$row = array();
			foreach ( array( 'sl', 'dept', 'name', 'urn', 'org' ) as $role ) {
				$row[ $role ] = isset( $map[ $role ] ) ? self::cell( $cells[ $map[ $role ] ] ?? '' ) : '';
			}
			if ( '' === $row['name'] && '' === $row['org'] ) {
				continue; // Blank line.
			}
			if ( '' === $row['org'] ) {
				$warnings[] = sprintf( 'Row %d (%s) has no organization and was skipped.', $line, $row['name'] );
				continue;
			}
			if ( '' === $row['name'] ) {
				$warnings[] = sprintf( 'Row %d has an organization (%s) but no name.', $line, $row['org'] );
			}
			$out[] = $row;
		}

		return array( 'rows' => $out, 'title' => $title, 'warnings' => $warnings, 'columns' => array_keys( $map ) );
	}

	/** Cell value as clean text. Whole numbers stored as floats (e.g. roll numbers) lose the ".0". */
	private static function cell( $value ) {
		if ( is_float( $value ) || ( is_string( $value ) && preg_match( '/^\d+(\.0+)?$|^\d(\.\d+)?E\+\d+$/i', trim( $value ) ) ) ) {
			$num = (float) $value;
			if ( floor( $num ) === $num ) {
				return sprintf( '%.0f', $num );
			}
		}
		return CPL_Data::clean( $value );
	}
}
