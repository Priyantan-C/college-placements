<?php
/**
 * Front-end shortcodes.
 */

defined( 'ABSPATH' ) || exit;

class STCET_PL_Shortcodes {

	private static $data_printed = false;

	public static function init() {
		add_shortcode( 'stcet_placement_trend', array( __CLASS__, 'trend' ) );
		add_shortcode( 'stcet_placement_year', array( __CLASS__, 'year' ) );
		add_shortcode( 'stcet_placement_records', array( __CLASS__, 'records' ) );

		wp_register_style( 'stcet-pl', STCET_PL_URL . 'assets/css/stcet-placements.css', array(), STCET_PL_VERSION );
		wp_register_script( 'stcet-pl-chartjs', STCET_PL_URL . 'assets/js/chart.umd.js', array(), '4.4.1', true );
		wp_register_script( 'stcet-pl', STCET_PL_URL . 'assets/js/stcet-placements.js', array( 'stcet-pl-chartjs' ), STCET_PL_VERSION, true );
	}

	private static function enqueue_charts() {
		wp_enqueue_style( 'stcet-pl' );
		wp_enqueue_script( 'stcet-pl' );
		if ( ! self::$data_printed ) {
			self::$data_printed = true;
			wp_add_inline_script( 'stcet-pl', 'window.STCETPL = ' . wp_json_encode( STCET_PL_Data::chart_payload() ) . ';', 'before' );
		}
	}

	public static function trend( $atts ) {
		$atts = shortcode_atts( array(
			'height' => '500',
			'title'  => "Placements in St. Thomas' over the years",
		), $atts, 'stcet_placement_trend' );
		self::enqueue_charts();
		return sprintf(
			'<div class="stcet-pl-widget stcet-pl-trend" data-title="%s"><div class="stcet-pl-trend-canvas" style="height:%dpx"><canvas aria-label="%s" role="img"></canvas></div></div>',
			esc_attr( $atts['title'] ),
			max( 200, (int) $atts['height'] ),
			esc_attr( $atts['title'] )
		);
	}

	public static function year( $atts ) {
		$atts = shortcode_atts( array(
			'year'  => '',
			'title' => 'Placement Data Year wise',
		), $atts, 'stcet_placement_year' );
		self::enqueue_charts();
		return sprintf(
			'<div class="stcet-pl-widget stcet-pl-year" data-year="%s" data-title="%s"></div>',
			esc_attr( absint( $atts['year'] ) ?: '' ),
			esc_attr( $atts['title'] )
		);
	}

	public static function records( $atts ) {
		$atts = shortcode_atts( array(
			'years' => '',
			'open'  => 'first',
		), $atts, 'stcet_placement_records' );
		wp_enqueue_style( 'stcet-pl' );

		$counts = STCET_PL_Data::record_counts_by_year();
		$wanted = array_filter( array_map( 'absint', explode( ',', (string) $atts['years'] ) ) );
		$years  = array();
		foreach ( STCET_PL_Data::get_years() as $y ) {
			if ( empty( $counts[ $y['year'] ] ) || ( $wanted && ! in_array( $y['year'], $wanted, true ) ) ) {
				continue;
			}
			$years[] = $y;
		}
		if ( ! $years ) {
			return '';
		}

		ob_start();
		echo '<div class="stcet-pl-records">';
		foreach ( $years as $i => $y ) {
			$open = 'all' === $atts['open'] || ( 'first' === $atts['open'] && 0 === $i );
			printf( '<details class="stcet-pl-acc"%s><summary>%s Placement Data</summary><div class="stcet-pl-acc-body">', $open ? ' open' : '', esc_html( $y['year'] ) );
			if ( '' !== $y['records_note'] ) {
				printf( '<p class="stcet-pl-note">%s</p>', esc_html( $y['records_note'] ) );
			}
			echo '<figure class="wp-block-table is-style-stripes stcet-pl-table"><table><thead><tr><th>Sl No</th><th>Dept</th><th>Name</th><th>U R N</th><th>Organization</th></tr></thead><tbody>';
			foreach ( STCET_PL_Data::get_records( $y['year'] ) as $n => $r ) {
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
