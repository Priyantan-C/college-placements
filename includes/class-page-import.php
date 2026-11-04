<?php
/**
 * One-time import of the hand-made student tables already on a WordPress page.
 */

defined( 'ABSPATH' ) || exit;

class STCET_PL_Page_Import {

	/**
	 * Find the tables on a page and the year each one belongs to.
	 * A table gets a year only when a "YYYY Placement Data" heading comes after the previous table.
	 *
	 * @return array List of ['year' => int|null, 'note' => string, 'rows' => [...], 'columns' => [...]]
	 */
	public static function scan( $html ) {
		if ( '' === trim( (string) $html ) ) {
			return array();
		}
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );

		$tables       = array();
		$pending_year = null;
		$pending_note = array();
		$nodes        = $xpath->query( '//table | //text()[normalize-space()][not(ancestor::table)][not(ancestor::script)][not(ancestor::style)]' );

		foreach ( $nodes as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType ) {
				$text = STCET_PL_Data::clean( $node->textContent );
				if ( preg_match( '/\b((?:19|20)\d{2})\s*Placement\s+(?:Data|Record)/i', $text, $m ) ) {
					$pending_year = (int) $m[1];
					$pending_note = array();
				} elseif ( null !== $pending_year && ( $pending_note || 0 === strpos( $text, '*' ) ) ) {
					// A note such as "* On-going Data, Updated till ..." may be split over several text nodes.
					$pending_note[] = $text;
				}
				continue;
			}

			$rows = array();
			foreach ( $xpath->query( './/tr', $node ) as $tr ) {
				$cells = array();
				foreach ( $xpath->query( './th|./td', $tr ) as $cell ) {
					$cells[] = STCET_PL_Data::clean( $cell->textContent );
				}
				$rows[] = $cells;
			}
			$read = STCET_PL_Xlsx_Import::read_sheet( $rows );
			if ( ! $read || empty( $read['rows'] ) ) {
				continue;
			}
			$tables[] = array(
				'year'    => $pending_year,
				'note'    => implode( ' ', $pending_note ),
				'rows'    => $read['rows'],
				'columns' => $read['columns'],
			);
			$pending_year = null;
			$pending_note = array();
		}
		return $tables;
	}
}
