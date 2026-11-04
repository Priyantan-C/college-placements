<?php
/**
 * Reads a summary workbook: one sheet per year (sheet name contains the year), one row per company.
 * Columns: company, offers, students appeared, highest package, average package.
 * This is the layout of the workbook the previous Netlify app used.
 */

defined( 'ABSPATH' ) || exit;

class CPL_Summary_Import {

	private static $roles = array(
		'company'  => array( 'companyname', 'company', 'organization', 'organisation', 'org', 'recruiter', 'name' ),
		'offers'   => array( 'placements', 'offers', 'numberofplacements', 'noofplacements', 'numberofoffers', 'noofoffers', 'count', 'placed' ),
		'appeared' => array( 'totalstudentsappeared', 'studentsappeared', 'appeared', 'totalstudentsparticipatedeligible', 'studentseligible', 'eligible', 'totalstudents' ),
		'highest'  => array( 'highestpkg', 'highestpackage', 'highest', 'highestctc' ),
		'avg'      => array( 'avgpkg', 'averagepackage', 'avgpackage', 'average', 'averagectc', 'avgctc' ),
	);

	/**
	 * @return array ['years' => [...], 'errors' => [], 'skipped' => []]
	 */
	public static function parse( $path ) {
		require_once CPL_DIR . 'lib/simplexlsx/SimpleXLSX.php';
		$out  = array( 'years' => array(), 'errors' => array(), 'skipped' => array() );
		$xlsx = \Shuchkin\SimpleXLSX::parse( $path );
		if ( ! $xlsx ) {
			$out['errors'][] = 'Could not read the file as an Excel workbook (.xlsx). ' . \Shuchkin\SimpleXLSX::parseError();
			return $out;
		}

		foreach ( $xlsx->sheetNames() as $index => $sheet ) {
			if ( ! preg_match( '/\b((?:19|20)\d{2})\b/', (string) $sheet, $m ) ) {
				$out['skipped'][] = sprintf( 'Sheet "%s" has no year in its name.', $sheet );
				continue;
			}
			$year = (int) $m[1];
			$read = self::read_sheet( $xlsx->rows( $index ) );
			if ( ! $read ) {
				$out['skipped'][] = sprintf( 'Sheet "%s" has no header row with a company column and a placements/offers column.', $sheet );
				continue;
			}
			if ( isset( $out['years'][ $year ] ) ) {
				$out['skipped'][] = sprintf( 'Sheet "%s" repeats %d and was ignored.', $sheet, $year );
				continue;
			}
			$read['year']          = $year;
			$read['sheet']         = (string) $sheet;
			$out['years'][ $year ] = $read;
		}
		krsort( $out['years'] );
		$out['years'] = array_values( $out['years'] );
		if ( ! $out['years'] && ! $out['errors'] ) {
			$out['errors'][] = 'No sheets could be read. Each sheet should be named after its year and have a header row such as companyName, placements, Total_Students_Appeared, HighestPkg, AvgPkg.';
		}
		return $out;
	}

	public static function read_sheet( array $rows ) {
		$map = null;
		foreach ( array_slice( $rows, 0, 10, true ) as $i => $cells ) {
			$found = array();
			foreach ( $cells as $c => $cell ) {
				$k = CPL_Data::key( $cell );
				foreach ( self::$roles as $role => $names ) {
					if ( ! isset( $found[ $role ] ) && in_array( $k, $names, true ) ) {
						$found[ $role ] = $c;
						break;
					}
				}
			}
			if ( isset( $found['company'], $found['offers'] ) ) {
				$map   = $found;
				$start = $i + 1;
				break;
			}
		}
		if ( ! $map ) {
			return null;
		}

		$companies = array();
		$appeared  = 0;
		$highest   = '';
		$avg       = '';
		foreach ( array_slice( $rows, $start ) as $cells ) {
			$name = CPL_Data::clean( $cells[ $map['company'] ] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$offers = (int) round( (float) ( $cells[ $map['offers'] ] ?? 0 ) );
			// The students-appeared figure may be on one row or spread over rows; add them up.
			if ( isset( $map['appeared'] ) ) {
				$appeared += (int) round( (float) ( $cells[ $map['appeared'] ] ?? 0 ) );
			}
			if ( '' === $highest && isset( $map['highest'] ) ) {
				$highest = CPL_Data::clean( $cells[ $map['highest'] ] ?? '' );
			}
			if ( '' === $avg && isset( $map['avg'] ) ) {
				$avg = CPL_Data::clean( $cells[ $map['avg'] ] ?? '' );
			}
			if ( $offers > 0 ) {
				$companies[] = array( $name, $offers );
			}
		}
		return array(
			'companies' => $companies,
			'appeared'  => $appeared,
			'highest'   => $highest,
			'avg'       => $avg,
		);
	}
}
