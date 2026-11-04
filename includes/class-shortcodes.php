<?php
/**
 * Front-end shortcodes.
 */

defined( 'ABSPATH' ) || exit;

class CPL_Shortcodes {

	private static $data_printed = false;

	public static function init() {
		add_shortcode( 'placement_trend', array( __CLASS__, 'trend' ) );
		add_shortcode( 'placement_year', array( __CLASS__, 'year' ) );
		add_shortcode( 'placement_records', array( __CLASS__, 'records' ) );

		wp_register_style( 'cpl', CPL_URL . 'assets/css/college-placements.css', array(), CPL_VERSION );
		wp_register_script( 'cpl-chartjs', CPL_URL . 'assets/js/chart.umd.js', array(), '4.4.1', true );
		wp_register_script( 'cpl', CPL_URL . 'assets/js/college-placements.js', array( 'cpl-chartjs' ), CPL_VERSION, true );
	}

	private static function enqueue_charts() {
		wp_enqueue_style( 'cpl' );
		wp_enqueue_script( 'cpl' );
		if ( ! self::$data_printed ) {
			self::$data_printed = true;
			wp_add_inline_script( 'cpl', 'window.CPL_DATA = ' . wp_json_encode( CPL_Data::chart_payload() ) . ';', 'before' );
		}
	}

	public static function trend( $atts ) {
		$atts = shortcode_atts( array(
			'height' => '500',
			'title'  => CPL_Settings::get( 'trend_title' ),
		), $atts, 'placement_trend' );
		$atts['title'] = CPL_Settings::format( $atts['title'] );
		self::enqueue_charts();
		return sprintf(
			'<div class="cpl-widget cpl-trend" data-title="%s"><div class="cpl-trend-canvas" style="height:%dpx"><canvas aria-label="%s" role="img"></canvas></div></div>',
			esc_attr( $atts['title'] ),
			max( 200, (int) $atts['height'] ),
			esc_attr( $atts['title'] )
		);
	}

	public static function year( $atts ) {
		$atts = shortcode_atts( array(
			'year'  => '',
			'title' => CPL_Settings::get( 'year_title' ),
		), $atts, 'placement_year' );
		$atts['title'] = CPL_Settings::format( $atts['title'] );
		self::enqueue_charts();
		return sprintf(
			'<div class="cpl-widget cpl-year" data-year="%s" data-title="%s"></div>',
			esc_attr( absint( $atts['year'] ) ?: '' ),
			esc_attr( $atts['title'] )
		);
	}

	public static function records( $atts ) {
		$atts = shortcode_atts( array(
			'years' => '',
			'open'  => 'first',
		), $atts, 'placement_records' );
		wp_enqueue_style( 'cpl' );

		$counts = CPL_Data::record_counts_by_year();
		$wanted = array_filter( array_map( 'absint', explode( ',', (string) $atts['years'] ) ) );
		$years  = array();
		foreach ( CPL_Data::get_years() as $y ) {
			if ( empty( $counts[ $y['year'] ] ) || ( $wanted && ! in_array( $y['year'], $wanted, true ) ) ) {
				continue;
			}
			$years[] = $y;
		}
		if ( ! $years ) {
			return '';
		}

		ob_start();
		echo '<div class="cpl-records">';
		foreach ( $years as $i => $y ) {
			$open = 'all' === $atts['open'] || ( 'first' === $atts['open'] && 0 === $i );
			printf( '<details class="cpl-acc"%s><summary>%s</summary><div class="cpl-acc-body">', $open ? ' open' : '', esc_html( CPL_Settings::format( CPL_Settings::get( 'records_heading' ), $y['year'] ) ) );
			if ( '' !== $y['records_note'] ) {
				printf( '<p class="cpl-note">%s</p>', esc_html( $y['records_note'] ) );
			}
			echo '<figure class="wp-block-table is-style-stripes cpl-table"><table><thead><tr><th>Sl No</th><th>Dept</th><th>Name</th><th>U R N</th><th>Organization</th></tr></thead><tbody>';
			foreach ( CPL_Data::get_records( $y['year'] ) as $n => $r ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( '' !== $r['sl'] ? $r['sl'] : $n + 1 ),
					esc_html( $r['dept'] ),
					esc_html( $r['name'] ),
					esc_html( $r['urn'] ),
					esc_html( $r['org'] )
				);
			}
			echo '</tbody></table></figure></div></details>';
		}
		echo '</div>';
		return ob_get_clean();
	}
}
