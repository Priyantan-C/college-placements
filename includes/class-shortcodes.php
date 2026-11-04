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
		wp_register_script( 'cpl-records', CPL_URL . 'assets/js/records.js', array(), CPL_VERSION, true );
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
			'title' => CPL_Settings::get( 'trend_title' ),
		), $atts, 'placement_trend' );
		self::enqueue_charts();
		return sprintf(
			'<div class="cpl-widget cpl-trend" data-title="%s"></div>',
			esc_attr( CPL_Settings::format( $atts['title'] ) )
		);
	}

	public static function year( $atts ) {
		$atts = shortcode_atts( array(
			'year'  => '',
			'title' => CPL_Settings::get( 'year_title' ),
		), $atts, 'placement_year' );
		self::enqueue_charts();
		return sprintf(
			'<div class="cpl-widget cpl-year" data-year="%s" data-title="%s"></div>',
			esc_attr( absint( $atts['year'] ) ?: '' ),
			esc_attr( CPL_Settings::format( $atts['title'] ) )
		);
	}

	public static function records( $atts ) {
		$atts = shortcode_atts( array(
			'years' => '',
			'open'  => 'first',
		), $atts, 'placement_records' );
		wp_enqueue_style( 'cpl' );
		wp_enqueue_script( 'cpl-records' );

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

		$aliases   = CPL_Data::get_aliases();
		$dept_rank = array_flip( CPL_Settings::dept_order() );
		$blocks    = array();
		$depts   = array();
		foreach ( $years as $y ) {
			$records = CPL_Data::get_records( $y['year'] );
			foreach ( $records as $r ) {
				if ( '' !== $r['dept'] ) {
					$depts[ strtoupper( $r['dept'] ) ] = true;
				}
			}
			$companies = count( CPL_Data::company_counts( $records, $aliases ) );
			if ( $dept_rank ) {
				// Group rows by department in the college's order; keep the file's order within a department.
				foreach ( $records as $n => &$r ) {
					$r['_pos'] = $n;
				}
				unset( $r );
				usort( $records, function ( $a, $b ) use ( $dept_rank ) {
					$ra = $dept_rank[ strtoupper( $a['dept'] ) ] ?? PHP_INT_MAX;
					$rb = $dept_rank[ strtoupper( $b['dept'] ) ] ?? PHP_INT_MAX;
					return $ra === $rb ? $a['_pos'] <=> $b['_pos'] : $ra <=> $rb;
				} );
			}
			$blocks[] = array( $y, $records, $companies );
		}
		$depts = CPL_Settings::sort_depts( array_keys( $depts ) );
		$uid = 'cpl-rec-' . wp_rand( 1000, 9999 );

		ob_start();
		?>
		<div class="cpl-widget cpl-records" data-cpl-records>
			<div class="cpl-toolbar cpl-rec-toolbar" hidden>
				<label class="cpl-search">
					<span class="screen-reader-text">Search students</span>
					<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M8.5 3a5.5 5.5 0 1 0 3.32 9.88l3.65 3.65 1.06-1.06-3.65-3.65A5.5 5.5 0 0 0 8.5 3Zm0 1.5a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z"/></svg>
					<input type="search" id="<?php echo esc_attr( $uid ); ?>" placeholder="Search name, roll number or company" autocomplete="off">
				</label>
				<?php if ( count( $depts ) > 1 ) : ?>
				<div class="cpl-chips" role="group" aria-label="Filter by department">
					<button type="button" class="cpl-chip is-active" data-dept="" aria-pressed="true">All</button>
					<?php foreach ( $depts as $d ) : ?>
						<button type="button" class="cpl-chip" data-dept="<?php echo esc_attr( $d ); ?>" aria-pressed="false"><?php echo esc_html( $d ); ?></button>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<p class="cpl-rec-count" aria-live="polite"></p>
			</div>
			<?php foreach ( $blocks as $i => $b ) :
				list( $y, $records, $companies ) = $b;
				$open = 'all' === $atts['open'] || ( 'first' === $atts['open'] && 0 === $i );
				?>
				<details class="cpl-acc" data-year="<?php echo esc_attr( $y['year'] ); ?>" <?php echo $open ? 'open' : ''; ?>>
					<summary>
						<span class="cpl-acc-title"><?php echo esc_html( CPL_Settings::format( CPL_Settings::get( 'records_heading' ), $y['year'] ) ); ?></span>
						<?php if ( $y['ongoing'] ) :
							$upd = CPL_Data::updated_label( $y );
							?>
							<span class="cpl-live"><span class="cpl-live-dot" aria-hidden="true"></span>Ongoing<?php echo $upd ? '<span class="cpl-live-date"> · Updated ' . esc_html( $upd ) . '</span>' : ''; ?></span>
						<?php endif; ?>
						<span class="cpl-acc-meta" data-total="<?php echo esc_attr( count( $records ) ); ?>"><?php echo esc_html( sprintf( '%d offers · %d companies', count( $records ), $companies ) ); ?></span>
						<svg class="cpl-acc-chevron" viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5 1 1L10 14 4.5 8.5z"/></svg>
					</summary>
					<div class="cpl-acc-body">
						<?php if ( '' !== $y['records_note'] ) : ?>
							<p class="cpl-note"><?php echo esc_html( $y['records_note'] ); ?></p>
						<?php endif; ?>
						<div class="cpl-table-scroll">
						<table class="cpl-rec-table">
							<thead><tr><th scope="col">Sl No</th><th scope="col">Dept</th><th scope="col">Name</th><th scope="col">U R N</th><th scope="col">Organization</th></tr></thead>
							<tbody>
							<?php
							$seen = array();
							foreach ( $records as $n => $r ) :
								$company = CPL_Data::resolve( $r['org'], $aliases, $seen );
								?>
								<tr data-dept="<?php echo esc_attr( strtoupper( $r['dept'] ) ); ?>" data-company="<?php echo esc_attr( $company !== $r['org'] ? $company : '' ); ?>">
									<td data-label="Sl No" class="cpl-num"><?php echo esc_html( $dept_rank || '' === $r['sl'] ? $n + 1 : $r['sl'] ); ?></td>
									<td data-label="Dept"><span class="cpl-dept"><?php echo esc_html( $r['dept'] ); ?></span></td>
									<td data-label="Name" class="cpl-name"><?php echo esc_html( $r['name'] ); ?></td>
									<td data-label="U R N" class="cpl-id"><?php echo esc_html( $r['urn'] ); ?></td>
									<td data-label="Organization"><?php echo esc_html( $r['org'] ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						</div>
						<p class="cpl-empty" hidden>No students in <?php echo esc_html( $y['year'] ); ?> match the search.</p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
