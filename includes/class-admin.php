<?php
/**
 * Admin screens: years, upload, company names, import from page.
 */

defined( 'ABSPATH' ) || exit;

class STCET_PL_Admin {

	const SLUG = 'stcet-placements';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save_year', 'delete_year', 'add_year', 'upload', 'upload_confirm', 'save_names', 'import_scan', 'import_confirm' ) as $action ) {
			add_action( 'admin_post_stcet_pl_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
		add_filter( 'plugin_action_links_' . plugin_basename( STCET_PL_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">Manage</a>' );
			return $links;
		} );
	}

	public static function cap() {
		return apply_filters( 'stcet_placements_capability', 'manage_options' );
	}

	public static function url( $page = '', $args = array() ) {
		$slug = $page ? self::SLUG . '-' . $page : self::SLUG;
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$cap = self::cap();
		add_menu_page( 'Placements', 'Placements', $cap, self::SLUG, array( __CLASS__, 'page_years' ), 'dashicons-chart-bar', 26 );
		add_submenu_page( self::SLUG, 'Placement years', 'Years', $cap, self::SLUG, array( __CLASS__, 'page_years' ) );
		add_submenu_page( self::SLUG, 'Upload student list', 'Upload student list', $cap, self::SLUG . '-upload', array( __CLASS__, 'page_upload' ) );
		add_submenu_page( self::SLUG, 'Company names', 'Company names', $cap, self::SLUG . '-names', array( __CLASS__, 'page_names' ) );
		add_submenu_page( self::SLUG, 'Import tables from a page', 'Import from page', $cap, self::SLUG . '-import', array( __CLASS__, 'page_import' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'stcet-pl-admin', STCET_PL_URL . 'assets/css/admin.css', array(), STCET_PL_VERSION );
		wp_enqueue_script( 'stcet-pl-admin', STCET_PL_URL . 'assets/js/admin.js', array(), STCET_PL_VERSION, true );
	}

	/* ---------- Helpers ---------- */

	private static function check( $action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( 'You do not have permission to do this.' );
		}
		check_admin_referer( 'stcet_pl_' . $action );
	}

	private static function flash( $message, $type = 'success' ) {
		set_transient( 'stcet_pl_flash_' . get_current_user_id(), array( $type, $message ), 120 );
	}

	private static function show_flash() {
		$key   = 'stcet_pl_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( $flash ) {
			delete_transient( $key );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $flash[0] ), wp_kses_post( $flash[1] ) );
		}
	}

	private static function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	private static function form_start( $action, $multipart = false ) {
		printf( '<form method="post" action="%s"%s>', esc_url( admin_url( 'admin-post.php' ) ), $multipart ? ' enctype="multipart/form-data"' : '' );
		printf( '<input type="hidden" name="action" value="stcet_pl_%s">', esc_attr( $action ) );
		wp_nonce_field( 'stcet_pl_' . $action );
	}

	private static function post( $key, $default = '' ) {
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default; // phpcs:ignore WordPress.Security
	}

	private static function year_param( $key = 'year' ) {
		$year = isset( $_REQUEST[ $key ] ) ? absint( $_REQUEST[ $key ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		return ( $year >= 1990 && $year <= 2100 ) ? $year : 0;
	}

	private static function source_label( $source ) {
		return STCET_PL_Data::SOURCE_SUM === $source ? 'Summary numbers' : 'Calculated from student list';
	}

	/* ---------- Years list ---------- */

	public static function page_years() {
		if ( self::year_param() ) {
			self::page_year_edit( self::year_param() );
			return;
		}
		$years   = STCET_PL_Data::get_years();
		$counts  = STCET_PL_Data::record_counts_by_year();
		$aliases = STCET_PL_Data::get_aliases();
		?>
		<div class="wrap stcet-pl">
			<h1 class="wp-heading-inline">Placement years</h1>
			<a class="page-title-action" href="<?php echo esc_url( self::url( 'upload' ) ); ?>">Upload student list</a>
			<hr class="wp-header-end">
			<?php self::show_flash(); ?>

			<div class="stcet-pl-columns">
				<div class="stcet-pl-main">
					<table class="widefat striped">
						<thead><tr>
							<th>Year</th><th>Charts use</th><th class="num">Offers</th><th class="num">Students appeared</th>
							<th>Highest pkg</th><th>Average pkg</th><th class="num">Student list rows</th><th></th>
						</tr></thead>
						<tbody>
						<?php if ( ! $years ) : ?>
							<tr><td colspan="8">No years yet.</td></tr>
						<?php endif; ?>
						<?php foreach ( $years as $y ) :
							$total = array_sum( array_column( STCET_PL_Data::breakdown( $y, $aliases ), 1 ) );
							?>
							<tr>
								<td><strong><a href="<?php echo esc_url( self::url( '', array( 'year' => $y['year'] ) ) ); ?>"><?php echo esc_html( $y['year'] ); ?></a></strong></td>
								<td><?php echo esc_html( self::source_label( $y['chart_source'] ) ); ?></td>
								<td class="num"><?php echo esc_html( $total ); ?></td>
								<td class="num"><?php echo esc_html( $y['students_appeared'] ); ?></td>
								<td><?php echo esc_html( $y['highest_pkg'] ); ?></td>
								<td><?php echo esc_html( $y['avg_pkg'] ); ?></td>
								<td class="num"><?php echo esc_html( $counts[ $y['year'] ] ?? 0 ); ?></td>
								<td><a class="button button-small" href="<?php echo esc_url( self::url( '', array( 'year' => $y['year'] ) ) ); ?>">Edit</a></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="stcet-pl-side">
					<div class="card">
						<h2>Add a year</h2>
						<?php self::form_start( 'add_year' ); ?>
							<p><input type="number" name="year" min="1990" max="2100" value="<?php echo esc_attr( (int) gmdate( 'Y' ) + 1 ); ?>" class="small-text"> <?php submit_button( 'Add year', 'secondary', 'submit', false ); ?></p>
							<p class="description">Or just upload a student list for a new year; the year is created automatically.</p>
						</form>
					</div>
					<div class="card">
						<h2>Shortcodes</h2>
						<p><code>[stcet_placement_trend]</code><br>Placements over the years (stacked bars and total lines).</p>
						<p><code>[stcet_placement_year]</code><br>Year picker, pie chart, totals and company table.</p>
						<p><code>[stcet_placement_records]</code><br>Year-wise student tables. Options: <code>years="2026,2025"</code>, <code>open="first"</code> (or <code>all</code> / <code>none</code>).</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_add_year() {
		self::check( 'add_year' );
		$year = self::year_param();
		if ( ! $year ) {
			self::flash( 'Enter a year between 1990 and 2100.', 'error' );
			self::redirect( self::url() );
		}
		STCET_PL_Data::ensure_year( $year );
		self::flash( sprintf( 'Year %d added. Upload its student list next.', $year ) );
		self::redirect( self::url( 'upload', array( 'year' => $year ) ) );
	}

	/* ---------- Year settings ---------- */

	private static function page_year_edit( $year ) {
		$y = STCET_PL_Data::get_year( $year );
		if ( ! $y ) {
			echo '<div class="wrap"><h1>Year not found</h1><p><a href="' . esc_url( self::url() ) . '">Back to years</a></p></div>';
			return;
		}
		$aliases   = STCET_PL_Data::get_aliases();
		$records   = STCET_PL_Data::get_records( $year );
		$counts    = STCET_PL_Data::company_counts( $records, $aliases );
		$summary   = STCET_PL_Data::get_summary( $year );
		$featured  = $y['featured'];
		$all_shown = empty( $featured );
		$feat_keys = array_map( array( 'STCET_PL_Data', 'key' ), $featured );
		$calc_total = count( $records );
		$sum_total  = array_sum( array_column( $summary, 1 ) );
		$is_summary = STCET_PL_Data::SOURCE_SUM === $y['chart_source'];
		?>
		<div class="wrap stcet-pl">
			<h1><?php echo esc_html( $year ); ?> placement settings</h1>
			<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; All years</a></p>
			<?php self::show_flash(); ?>

			<?php self::form_start( 'save_year' ); ?>
			<input type="hidden" name="year" value="<?php echo esc_attr( $year ); ?>">

			<h2>Figures for this year</h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="appeared">Students appeared / eligible</label></th>
					<td><input type="number" min="0" id="appeared" name="students_appeared" value="<?php echo esc_attr( $y['students_appeared'] ); ?>" class="small-text"></td></tr>
				<tr><th><label for="highest">Highest package</label></th>
					<td><input type="text" id="highest" name="highest_pkg" value="<?php echo esc_attr( $y['highest_pkg'] ); ?>" class="regular-text" placeholder="e.g. 11.1 LPA"></td></tr>
				<tr><th><label for="avg">Average package</label></th>
					<td><input type="text" id="avg" name="avg_pkg" value="<?php echo esc_attr( $y['avg_pkg'] ); ?>" class="regular-text" placeholder="e.g. 6.12 LPA"></td></tr>
				<tr><th><label for="records_note">Note above the student table</label></th>
					<td><input type="text" id="records_note" name="records_note" value="<?php echo esc_attr( $y['records_note'] ); ?>" class="large-text" placeholder="e.g. * On-going Data, Updated till 3rd November 2026"></td></tr>
				<tr><th><label for="footnote">Line under the company table</label></th>
					<td><input type="text" id="footnote" name="footnote" value="<?php echo esc_attr( $y['footnote'] ); ?>" class="large-text"><p class="description">Optional. Shown as the last row of the company table in the year chart.</p></td></tr>
			</table>

			<h2>What the charts use</h2>
			<fieldset class="stcet-pl-source">
				<label><input type="radio" name="chart_source" value="records" <?php checked( ! $is_summary ); ?> <?php disabled( ! $records && ! $is_summary ); ?>>
					<strong>Calculated from the student list</strong> (<?php echo esc_html( $calc_total ); ?> offers in <?php echo esc_html( count( $records ) ); ?> rows)</label><br>
				<label><input type="radio" name="chart_source" value="summary" <?php checked( $is_summary ); ?>>
					<strong>Summary numbers</strong> typed in below (<?php echo esc_html( $sum_total ); ?> offers)</label>
				<?php if ( ! $records ) : ?>
					<p class="description">No student list for this year yet. <a href="<?php echo esc_url( self::url( 'upload', array( 'year' => $year ) ) ); ?>">Upload one</a>.</p>
				<?php elseif ( $summary && $calc_total !== $sum_total ) : ?>
					<p class="description stcet-pl-warn">The student list has <?php echo esc_html( $calc_total ); ?> offers but the summary has <?php echo esc_html( $sum_total ); ?>.</p>
				<?php endif; ?>
			</fieldset>

			<?php if ( $records ) : ?>
			<div class="stcet-pl-panel" data-panel="records">
				<h3>Companies shown separately</h3>
				<p class="description">Tick the companies that get their own row. Everything unticked is added up into <strong>Others</strong>.
					<a href="#" data-stcet-check="all">Tick all</a> · <a href="#" data-stcet-check="none">Untick all</a></p>
				<table class="widefat striped stcet-pl-featured">
					<thead><tr><th class="check-column"></th><th>Company</th><th class="num">Offers</th><th>Spellings in the list</th></tr></thead>
					<tbody>
					<?php foreach ( $counts as $company => $c ) :
						$is_others = 'others' === STCET_PL_Data::key( $company );
						$checked   = ! $is_others && ( $all_shown || in_array( STCET_PL_Data::key( $company ), $feat_keys, true ) );
						$spell     = array();
						foreach ( $c['spellings'] as $s => $n ) {
							$spell[] = $s . ' (' . $n . ')';
						}
						?>
						<tr>
							<td><input type="checkbox" name="featured[]" value="<?php echo esc_attr( $company ); ?>" data-offers="<?php echo esc_attr( $c['offers'] ); ?>" <?php checked( $checked ); ?> <?php disabled( $is_others ); ?>></td>
							<td><?php echo esc_html( $company ); ?></td>
							<td class="num"><?php echo esc_html( $c['offers'] ); ?></td>
							<td class="description"><?php echo esc_html( implode( ', ', $spell ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot><tr><td></td><td colspan="3" class="stcet-pl-totals" data-total="<?php echo esc_attr( $calc_total ); ?>"></td></tr></tfoot>
				</table>
				<p class="description">Names look wrong or split? Fix them under <a href="<?php echo esc_url( self::url( 'names' ) ); ?>">Company names</a>.</p>
			</div>
			<?php endif; ?>

			<div class="stcet-pl-panel" data-panel="summary">
				<h3>Summary numbers</h3>
				<p class="description">Used only when the charts are set to “Summary numbers”. Rows with an empty company name are removed.</p>
				<table class="widefat stcet-pl-summary">
					<thead><tr><th>Company</th><th class="num">Offers</th><th></th></tr></thead>
					<tbody>
					<?php foreach ( array_merge( $summary, array( array( '', '' ) ) ) as $row ) : ?>
						<tr>
							<td><input type="text" name="summary_company[]" value="<?php echo esc_attr( $row[0] ); ?>" class="regular-text"></td>
							<td class="num"><input type="number" min="0" name="summary_offers[]" value="<?php echo esc_attr( $row[1] ); ?>" class="small-text"></td>
							<td><button type="button" class="button-link stcet-pl-remove-row">Remove</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
					<tfoot><tr><td><button type="button" class="button stcet-pl-add-row">Add row</button></td><td class="num stcet-pl-sum-total"></td><td></td></tr></tfoot>
				</table>
			</div>

			<?php submit_button( 'Save year' ); ?>
			</form>

			<hr>
			<h2>Student list</h2>
			<p><?php echo esc_html( count( $records ) ); ?> rows.
				<a class="button" href="<?php echo esc_url( self::url( 'upload', array( 'year' => $year ) ) ); ?>"><?php echo $records ? 'Replace with a new upload' : 'Upload student list'; ?></a></p>

			<h2>Delete this year</h2>
			<?php self::form_start( 'delete_year' ); ?>
				<input type="hidden" name="year" value="<?php echo esc_attr( $year ); ?>">
				<p class="description">Removes the year’s figures, summary numbers and student list from the site.</p>
				<?php submit_button( 'Delete ' . $year, 'delete', 'submit', false, array( 'onclick' => "return confirm('Delete all " . esc_js( $year ) . " placement data?');" ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_year() {
		self::check( 'save_year' );
		$year = self::year_param();
		if ( ! $year || ! STCET_PL_Data::get_year( $year ) ) {
			self::redirect( self::url() );
		}

		$source = STCET_PL_Data::SOURCE_SUM === self::post( 'chart_source' ) ? STCET_PL_Data::SOURCE_SUM : STCET_PL_Data::SOURCE_REC;

		// Featured companies: if every company is ticked, store an empty list so new companies show up too.
		$featured = array_map( array( 'STCET_PL_Data', 'clean' ), (array) self::post( 'featured', array() ) );
		$counts   = STCET_PL_Data::company_counts( STCET_PL_Data::get_records( $year ) );
		$all      = array_filter( array_keys( $counts ), function ( $c ) {
			return 'others' !== STCET_PL_Data::key( $c );
		} );
		if ( count( array_intersect( array_map( array( 'STCET_PL_Data', 'key' ), $all ), array_map( array( 'STCET_PL_Data', 'key' ), $featured ) ) ) === count( $all ) ) {
			$featured = array();
		} elseif ( ! $featured ) {
			$featured = array( STCET_PL_Data::OTHERS ); // Nothing ticked: everything goes into Others.
		}

		STCET_PL_Data::update_year( $year, array(
			'students_appeared' => absint( self::post( 'students_appeared', 0 ) ),
			'highest_pkg'       => sanitize_text_field( self::post( 'highest_pkg' ) ),
			'avg_pkg'           => sanitize_text_field( self::post( 'avg_pkg' ) ),
			'records_note'      => sanitize_text_field( self::post( 'records_note' ) ),
			'footnote'          => sanitize_text_field( self::post( 'footnote' ) ),
			'chart_source'      => $source,
			'featured'          => $featured,
		) );

		$names  = (array) self::post( 'summary_company', array() );
		$offers = (array) self::post( 'summary_offers', array() );
		$rows   = array();
		foreach ( $names as $i => $name ) {
			$name = sanitize_text_field( $name );
			if ( '' !== trim( $name ) ) {
				$rows[] = array( $name, absint( $offers[ $i ] ?? 0 ) );
			}
		}
		STCET_PL_Data::save_summary( $year, $rows );

		self::flash( sprintf( '%d saved. The charts on the site are updated.', $year ) );
		self::redirect( self::url( '', array( 'year' => $year ) ) );
	}

	public static function handle_delete_year() {
		self::check( 'delete_year' );
		$year = self::year_param();
		if ( $year ) {
			STCET_PL_Data::delete_year( $year );
			self::flash( sprintf( '%d deleted.', $year ) );
		}
		self::redirect( self::url() );
	}

	/* ---------- Upload ---------- */

	public static function page_upload() {
		$token = isset( $_GET['token'] ) ? sanitize_key( $_GET['token'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $token ) {
			self::page_upload_preview( $token );
			return;
		}
		$year     = self::year_param() ?: (int) gmdate( 'Y' );
		$existing = STCET_PL_Data::get_year( $year );
		?>
		<div class="wrap stcet-pl">
			<h1>Upload student list</h1>
			<?php self::show_flash(); ?>
			<div class="card stcet-pl-wide">
				<?php self::form_start( 'upload', true ); ?>
				<table class="form-table" role="presentation">
					<tr><th><label for="year">Placement year</label></th>
						<td><input type="number" id="year" name="year" min="1990" max="2100" value="<?php echo esc_attr( $year ); ?>" class="small-text" required>
						<p class="description">The year the placements count towards (the list titled “Placement Record 2025-26” is 2026).</p></td></tr>
					<tr><th><label for="file">Excel file (.xlsx)</label></th>
						<td><input type="file" id="file" name="file" accept=".xlsx" required></td></tr>
					<tr><th><label for="note">Note above the table</label></th>
						<td><input type="text" id="note" name="records_note" class="large-text" value="<?php echo esc_attr( $existing ? $existing['records_note'] : '' ); ?>" placeholder="e.g. * On-going Data, Updated till 3rd November 2026"></td></tr>
				</table>
				<?php submit_button( 'Read file and preview' ); ?>
				</form>
			</div>
			<div class="card stcet-pl-wide">
				<h2>What the file needs</h2>
				<p>One row per offer, with a header row containing at least <strong>Name</strong> and <strong>Org</strong> (or Organization / Company). <strong>Sl No</strong>, <strong>Dept</strong> and <strong>U R N</strong> (or University Roll Number) are picked up when present. A title row above the header is fine.</p>
				<p>Nothing is saved until you confirm on the next screen. Confirming replaces that year’s whole student list.</p>
			</div>
		</div>
		<?php
	}

	public static function handle_upload() {
		self::check( 'upload' );
		$year = self::year_param();
		$back = self::url( 'upload', $year ? array( 'year' => $year ) : array() );
		if ( ! $year ) {
			self::flash( 'Enter a year between 1990 and 2100.', 'error' );
			self::redirect( $back );
		}
		$file = $_FILES['file'] ?? null; // phpcs:ignore WordPress.Security
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::flash( 'The file did not upload. Please try again.', 'error' );
			self::redirect( $back );
		}
		if ( 'xlsx' !== strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) ) ) {
			self::flash( 'Please upload an .xlsx file (Excel). Older .xls files can be re-saved as .xlsx from Excel.', 'error' );
			self::redirect( $back );
		}

		$parsed = STCET_PL_Xlsx_Import::parse( $file['tmp_name'] );
		if ( $parsed['errors'] || ! $parsed['rows'] ) {
			self::flash( implode( '<br>', array_map( 'esc_html', $parsed['errors'] ?: array( 'No student rows were found in the file.' ) ) ), 'error' );
			self::redirect( $back );
		}

		$token = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'stcet_pl_upload_' . $token, array(
			'user'     => get_current_user_id(),
			'year'     => $year,
			'file'     => sanitize_file_name( $file['name'] ),
			'note'     => sanitize_text_field( self::post( 'records_note' ) ),
			'parsed'   => $parsed,
		), HOUR_IN_SECONDS );
		self::redirect( self::url( 'upload', array( 'token' => $token ) ) );
	}

	private static function get_upload( $token ) {
		$data = get_transient( 'stcet_pl_upload_' . $token );
		return ( $data && (int) $data['user'] === get_current_user_id() ) ? $data : null;
	}

	private static function page_upload_preview( $token ) {
		$data = self::get_upload( $token );
		if ( ! $data ) {
			echo '<div class="wrap"><h1>Upload expired</h1><p><a href="' . esc_url( self::url( 'upload' ) ) . '">Upload the file again</a>.</p></div>';
			return;
		}
		$year     = $data['year'];
		$rows     = $data['parsed']['rows'];
		$aliases  = STCET_PL_Data::get_aliases();
		$existing = STCET_PL_Data::get_records( $year );
		$year_row = STCET_PL_Data::get_year( $year );

		$depts = array();
		foreach ( $rows as $r ) {
			$d           = '' !== $r['dept'] ? $r['dept'] : '(blank)';
			$depts[ $d ] = ( $depts[ $d ] ?? 0 ) + 1;
		}
		$urns = array_count_values( array_filter( array_column( $rows, 'urn' ) ) );
		$multi = count( array_filter( $urns, function ( $n ) {
			return $n > 1;
		} ) );

		$counts = STCET_PL_Data::company_counts( $rows, $aliases );
		?>
		<div class="wrap stcet-pl">
			<h1>Check the <?php echo esc_html( $year ); ?> student list</h1>
			<?php self::show_flash(); ?>

			<div class="stcet-pl-facts">
				<div><span>File</span><strong><?php echo esc_html( $data['file'] ); ?></strong></div>
				<div><span>Sheet</span><strong><?php echo esc_html( $data['parsed']['sheet'] ); ?></strong></div>
				<?php if ( $data['parsed']['title'] ) : ?><div><span>Title row</span><strong><?php echo esc_html( $data['parsed']['title'] ); ?></strong></div><?php endif; ?>
				<div><span>Offers (rows)</span><strong><?php echo esc_html( count( $rows ) ); ?></strong></div>
				<div><span>Different students</span><strong><?php echo esc_html( count( $urns ) ); ?></strong></div>
				<div><span>Companies</span><strong><?php echo esc_html( count( $counts ) ); ?></strong></div>
			</div>

			<p>By department: <?php
				$parts = array();
				foreach ( $depts as $d => $n ) {
					$parts[] = esc_html( $d ) . ' ' . (int) $n;
				}
				echo implode( ' · ', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>. <?php if ( $multi ) : ?><?php echo esc_html( $multi ); ?> students have more than one offer.<?php endif; ?></p>

			<?php if ( array_diff( array( 'sl', 'dept', 'urn' ), $data['parsed']['columns'] ) ) : ?>
				<div class="notice notice-warning inline"><p>Columns not found in the file: <?php echo esc_html( implode( ', ', array_diff( array( 'sl', 'dept', 'urn' ), $data['parsed']['columns'] ) ) ); ?>. They will be blank in the table.</p></div>
			<?php endif; ?>
			<?php if ( $data['parsed']['warnings'] ) : ?>
				<div class="notice notice-warning inline"><p><?php echo implode( '<br>', array_map( 'esc_html', $data['parsed']['warnings'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p></div>
			<?php endif; ?>
			<?php if ( $existing ) : ?>
				<div class="notice notice-info inline"><p>Confirming replaces the <?php echo esc_html( count( $existing ) ); ?> rows already saved for <?php echo esc_html( $year ); ?>.</p></div>
			<?php endif; ?>
			<?php if ( $year_row && STCET_PL_Data::SOURCE_SUM === $year_row['chart_source'] ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $year ); ?> charts currently use summary numbers, so this upload updates the student table only. You can switch the charts to the student list in the year’s settings.</p></div>
			<?php endif; ?>

			<?php self::form_start( 'upload_confirm' ); ?>
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">

			<h2>Company names</h2>
			<p class="description">Each spelling in the file and the company it counts towards. Change the company to merge spellings (for example CTS → Cognizant). Matches are remembered for future uploads.</p>
			<div class="stcet-pl-columns">
				<div class="stcet-pl-main">
					<table class="widefat striped stcet-pl-names" data-live-totals="1">
						<thead><tr><th>Spelling in file</th><th class="num">Rows</th><th>Counts as company</th><th></th></tr></thead>
						<tbody>
						<?php foreach ( $counts as $company => $c ) :
							foreach ( $c['spellings'] as $spelling => $n ) :
								$matched = STCET_PL_Data::is_matched( $spelling, $aliases );
								?>
								<tr class="<?php echo $matched ? '' : 'stcet-pl-new'; ?>">
									<td><?php echo esc_html( $spelling ); ?><input type="hidden" name="spelling[]" value="<?php echo esc_attr( $spelling ); ?>"></td>
									<td class="num" data-rows="<?php echo esc_attr( $n ); ?>"><?php echo esc_html( $n ); ?></td>
									<td><input type="text" name="company[]" value="<?php echo esc_attr( $company ); ?>" class="regular-text" list="stcet-pl-companies"></td>
									<td><?php echo $matched ? '<span class="stcet-pl-tag">saved match</span>' : '<span class="stcet-pl-tag stcet-pl-tag-new">new name</span>'; ?></td>
								</tr>
							<?php endforeach;
						endforeach; ?>
						</tbody>
					</table>
					<?php self::company_datalist( $aliases ); ?>
				</div>
				<div class="stcet-pl-side">
					<div class="card">
						<h2>Offers per company</h2>
						<table class="widefat stcet-pl-live"><tbody></tbody></table>
					</div>
				</div>
			</div>

			<h2>First rows</h2>
			<table class="widefat striped">
				<thead><tr><th>Sl No</th><th>Dept</th><th>Name</th><th>U R N</th><th>Org</th></tr></thead>
				<tbody>
				<?php foreach ( array_slice( $rows, 0, 8 ) as $r ) : ?>
					<tr><td><?php echo esc_html( $r['sl'] ); ?></td><td><?php echo esc_html( $r['dept'] ); ?></td><td><?php echo esc_html( $r['name'] ); ?></td><td><?php echo esc_html( $r['urn'] ); ?></td><td><?php echo esc_html( $r['org'] ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p class="submit">
				<?php submit_button( sprintf( 'Save %d rows for %d', count( $rows ), $year ), 'primary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( self::url( 'upload', array( 'year' => $year ) ) ); ?>">Cancel</a>
			</p>
			</form>
		</div>
		<?php
	}

	private static function company_datalist( $aliases ) {
		$companies = array_unique( array_column( $aliases, 'company' ) );
		sort( $companies );
		echo '<datalist id="stcet-pl-companies">';
		foreach ( $companies as $c ) {
			echo '<option value="' . esc_attr( $c ) . '">';
		}
		echo '</datalist>';
	}

	/** Save spelling → company matches posted from a names table. Returns how many changed. */
	private static function save_name_matches( $aliases ) {
		$spellings = (array) self::post( 'spelling', array() );
		$companies = (array) self::post( 'company', array() );
		$changed   = 0;
		foreach ( $spellings as $i => $spelling ) {
			$spelling = STCET_PL_Data::clean( sanitize_text_field( $spelling ) );
			$company  = STCET_PL_Data::clean( sanitize_text_field( $companies[ $i ] ?? '' ) );
			$key      = STCET_PL_Data::key( $spelling );
			if ( '' === $key ) {
				continue;
			}
			$current = $aliases[ $key ]['company'] ?? null;
			if ( '' === $company ) {
				if ( null !== $current ) {
					STCET_PL_Data::delete_alias( $key );
					$changed++;
				}
				continue;
			}
			if ( $current === $company ) {
				continue;
			}
			if ( null === $current && $company === $spelling ) {
				continue; // Plain name, nothing to remember.
			}
			STCET_PL_Data::save_alias( $spelling, $company );
			$aliases[ $key ] = array( 'company' => $company );
			$changed++;
		}
		return $changed;
	}

	public static function handle_upload_confirm() {
		self::check( 'upload_confirm' );
		$token = sanitize_key( self::post( 'token' ) );
		$data  = self::get_upload( $token );
		if ( ! $data ) {
			self::flash( 'That upload expired. Please upload the file again.', 'error' );
			self::redirect( self::url( 'upload' ) );
		}
		$year = (int) $data['year'];
		self::save_name_matches( STCET_PL_Data::get_aliases() );
		STCET_PL_Data::ensure_year( $year );
		STCET_PL_Data::replace_records( $year, $data['parsed']['rows'] );
		if ( '' !== $data['note'] ) {
			STCET_PL_Data::update_year( $year, array( 'records_note' => $data['note'] ) );
		}
		delete_transient( 'stcet_pl_upload_' . $token );

		self::flash( sprintf( 'Saved %d rows for %d. Now check the figures and choose which companies are shown separately.', count( $data['parsed']['rows'] ), $year ) );
		self::redirect( self::url( '', array( 'year' => $year ) ) );
	}

	/* ---------- Company names ---------- */

	public static function page_names() {
		global $wpdb;
		$aliases = STCET_PL_Data::get_aliases();
		$rows    = $wpdb->get_results( 'SELECT org, year, COUNT(*) AS n FROM ' . STCET_PL_DB::table( 'records' ) . ' GROUP BY org, year ORDER BY org', ARRAY_A );

		// Group spellings (case/space/punctuation-insensitive) with the years they appear in.
		$spellings = array();
		foreach ( (array) $rows as $r ) {
			$key = STCET_PL_Data::key( $r['org'] );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $spellings[ $key ] ) ) {
				$spellings[ $key ] = array( 'spelling' => $r['org'], 'years' => array(), 'n' => 0 );
			}
			$spellings[ $key ]['years'][ (int) $r['year'] ] = true;
			$spellings[ $key ]['n']                         += (int) $r['n'];
		}
		foreach ( $aliases as $key => $a ) {
			if ( ! isset( $spellings[ $key ] ) ) {
				$spellings[ $key ] = array( 'spelling' => $a['alias'], 'years' => array(), 'n' => 0 );
			}
		}
		$seen = array();
		foreach ( $spellings as $key => &$s ) {
			$s['company'] = STCET_PL_Data::resolve( $s['spelling'], $aliases, $seen );
			$s['matched'] = isset( $aliases[ $key ] );
		}
		unset( $s );
		uasort( $spellings, function ( $a, $b ) {
			return strcasecmp( $a['company'] . ' ' . $a['spelling'], $b['company'] . ' ' . $b['spelling'] );
		} );
		?>
		<div class="wrap stcet-pl">
			<h1>Company names</h1>
			<?php self::show_flash(); ?>
			<p>Every company spelling used in the student lists, and the company it is counted as in the charts. Spellings that differ only in capitals, spaces or brackets (TCS(D) and TCS (D)) are already treated as the same. Clear the company box to forget a match.</p>
			<?php self::form_start( 'save_names' ); ?>
			<table class="widefat striped stcet-pl-names">
				<thead><tr><th>Spelling</th><th>Used in</th><th class="num">Rows</th><th>Counts as company</th></tr></thead>
				<tbody>
				<?php foreach ( $spellings as $s ) :
					$years = array_keys( $s['years'] );
					rsort( $years );
					?>
					<tr>
						<td><?php echo esc_html( $s['spelling'] ); ?><input type="hidden" name="spelling[]" value="<?php echo esc_attr( $s['spelling'] ); ?>"></td>
						<td><?php echo esc_html( implode( ', ', $years ) ); ?></td>
						<td class="num"><?php echo esc_html( $s['n'] ); ?></td>
						<td><input type="text" name="company[]" value="<?php echo esc_attr( $s['matched'] ? $s['company'] : '' ); ?>" placeholder="<?php echo esc_attr( $s['company'] ); ?>" class="regular-text" list="stcet-pl-companies"></td>
					</tr>
				<?php endforeach; ?>
				<tr class="stcet-pl-add">
					<td><input type="text" name="spelling[]" placeholder="New spelling, e.g. TCS Digital" class="regular-text"></td>
					<td></td><td></td>
					<td><input type="text" name="company[]" placeholder="Company, e.g. TCS" class="regular-text" list="stcet-pl-companies"></td>
				</tr>
				</tbody>
			</table>
			<?php self::company_datalist( $aliases ); ?>
			<?php submit_button( 'Save company names' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_save_names() {
		self::check( 'save_names' );
		$changed = self::save_name_matches( STCET_PL_Data::get_aliases() );
		self::flash( $changed ? sprintf( '%d name match(es) saved. The charts are updated.', $changed ) : 'No changes.' );
		self::redirect( self::url( 'names' ) );
	}

	/* ---------- Import from an existing page ---------- */

	public static function page_import() {
		$token = isset( $_GET['token'] ) ? sanitize_key( $_GET['token'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$scan  = $token ? get_transient( 'stcet_pl_scan_' . $token ) : null;
		if ( $scan && (int) $scan['user'] !== get_current_user_id() ) {
			$scan = null;
		}
		$default = get_page_by_path( 'placements/placement-data' ) ?: get_page_by_path( 'placement-data' );
		$counts  = STCET_PL_Data::record_counts_by_year();
		?>
		<div class="wrap stcet-pl">
			<h1>Import tables from a page</h1>
			<?php self::show_flash(); ?>
			<p>Reads the student tables already typed into a WordPress page (such as Placement Data) and saves them as student lists. Each table takes the year from the “2026 Placement Data” style heading above it. The page itself is not changed.</p>

			<?php self::form_start( 'import_scan' ); ?>
				<p><label for="page_id">Page:</label>
				<?php wp_dropdown_pages( array( 'name' => 'page_id', 'id' => 'page_id', 'selected' => $scan ? $scan['page_id'] : ( $default ? $default->ID : 0 ) ) ); ?>
				<?php submit_button( 'Find tables', 'secondary', 'submit', false ); ?></p>
			</form>

			<?php if ( $scan ) : ?>
				<h2>Tables found on “<?php echo esc_html( get_the_title( $scan['page_id'] ) ); ?>”</h2>
				<?php if ( ! $scan['tables'] ) : ?>
					<p>No student tables found on that page.</p>
				<?php else : ?>
				<?php self::form_start( 'import_confirm' ); ?>
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<table class="widefat striped">
					<thead><tr><th>Import</th><th>#</th><th>Year</th><th class="num">Rows</th><th>First and last row</th><th>Note</th><th>Already saved</th></tr></thead>
					<tbody>
					<?php
					$used = array();
					foreach ( $scan['tables'] as $i => $t ) :
						$first = $t['rows'][0];
						$last  = end( $t['rows'] );
						$tick  = $t['year'] && ! isset( $used[ $t['year'] ] );
						if ( $t['year'] ) {
							$used[ $t['year'] ] = true;
						}
						?>
						<tr>
							<td><input type="checkbox" name="import[]" value="<?php echo esc_attr( $i ); ?>" <?php checked( $tick ); ?>></td>
							<td><?php echo esc_html( $i + 1 ); ?></td>
							<td><input type="number" name="year[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $t['year'] ); ?>" class="small-text" min="1990" max="2100">
								<?php if ( ! $t['year'] ) : ?><br><span class="stcet-pl-warn">No year heading</span><?php endif; ?></td>
							<td class="num"><?php echo esc_html( count( $t['rows'] ) ); ?></td>
							<td class="description"><?php echo esc_html( $first['name'] . ' – ' . $first['org'] ); ?><br><?php echo esc_html( $last['name'] . ' – ' . $last['org'] ); ?></td>
							<td class="description"><?php echo esc_html( $t['note'] ); ?></td>
							<td><?php echo $t['year'] && ! empty( $counts[ $t['year'] ] ) ? esc_html( $counts[ $t['year'] ] . ' rows (will be replaced)' ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">Tables without a year heading are left unticked. Tick one and type its year only if it really is a separate list.</p>
				<?php submit_button( 'Import ticked tables' ); ?>
				</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_import_scan() {
		self::check( 'import_scan' );
		$page_id = absint( self::post( 'page_id', 0 ) );
		$post    = $page_id ? get_post( $page_id ) : null;
		if ( ! $post ) {
			self::flash( 'Choose a page.', 'error' );
			self::redirect( self::url( 'import' ) );
		}
		$token = strtolower( wp_generate_password( 16, false ) );
		set_transient( 'stcet_pl_scan_' . $token, array(
			'user'    => get_current_user_id(),
			'page_id' => $page_id,
			'tables'  => STCET_PL_Page_Import::scan( $post->post_content ),
		), HOUR_IN_SECONDS );
		self::redirect( self::url( 'import', array( 'token' => $token ) ) );
	}

	public static function handle_import_confirm() {
		self::check( 'import_confirm' );
		$token = sanitize_key( self::post( 'token' ) );
		$scan  = get_transient( 'stcet_pl_scan_' . $token );
		if ( ! $scan || (int) $scan['user'] !== get_current_user_id() ) {
			self::flash( 'That scan expired. Please find the tables again.', 'error' );
			self::redirect( self::url( 'import' ) );
		}
		$years  = (array) self::post( 'year', array() );
		$done   = array();
		$errors = array();
		foreach ( (array) self::post( 'import', array() ) as $i ) {
			$i     = (int) $i;
			$table = $scan['tables'][ $i ] ?? null;
			$year  = absint( $years[ $i ] ?? 0 );
			if ( ! $table ) {
				continue;
			}
			if ( $year < 1990 || $year > 2100 ) {
				$errors[] = sprintf( 'Table %d has no year, so it was skipped.', $i + 1 );
				continue;
			}
			if ( isset( $done[ $year ] ) ) {
				$errors[] = sprintf( 'Table %d is also marked %d, so it was skipped.', $i + 1, $year );
				continue;
			}
			$source = STCET_PL_Data::get_summary( $year ) ? STCET_PL_Data::SOURCE_SUM : STCET_PL_Data::SOURCE_REC;
			STCET_PL_Data::ensure_year( $year, $source );
			STCET_PL_Data::replace_records( $year, $table['rows'] );
			$y = STCET_PL_Data::get_year( $year );
			if ( '' !== $table['note'] && '' === $y['records_note'] ) {
				STCET_PL_Data::update_year( $year, array( 'records_note' => $table['note'] ) );
			}
			$done[ $year ] = count( $table['rows'] );
		}
		delete_transient( 'stcet_pl_scan_' . $token );

		$parts = array();
		foreach ( $done as $year => $n ) {
			$parts[] = "$year ($n rows)";
		}
		$msg = $parts ? 'Imported ' . implode( ', ', $parts ) . '.' : 'Nothing imported.';
		if ( $errors ) {
			$msg .= '<br>' . implode( '<br>', array_map( 'esc_html', $errors ) );
		}
		self::flash( $msg, $errors ? 'warning' : 'success' );
		self::redirect( self::url() );
	}
}
