<?php
/**
 * Heading structure repair.
 *
 * Task 1.7 — 31 of 119 pages render without an H1, including the homepage.
 * Task 2.5 — roughly 33 pages skip a heading level.
 *
 * Both are page-builder template defects. Rather than ask an operator to edit
 * a theme template, this repairs the rendered HTML: it promotes the page's own
 * title heading to H1 where one is missing, and optionally closes level gaps.
 *
 * All edits are surgical string operations on heading tags only. The document
 * is never re-serialized, so nothing else in the markup can shift.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Headings {

	/** @var array Diagnostics from the most recent run, for the admin screens. */
	public static $last_report = array();

	/**
	 * Apply the enabled heading fixes to a page of HTML.
	 *
	 * @param string $html Full page HTML.
	 * @return string
	 */
	public static function apply_to_html( $html ) {
		$config = (array) AIM_AVT_Settings::get( 'headings', array() );

		self::$last_report = array(
			'h1_before'   => self::count_h1( $html ),
			'h1_action'   => 'none',
			'hierarchy'   => array( 'gaps' => 0, 'fixed' => 0 ),
		);

		if ( self::is_excluded() ) {
			self::$last_report['h1_action'] = 'excluded';
			return $html;
		}

		if ( ! empty( $config['h1_enabled'] ) ) {
			$html = self::ensure_h1( $html, $config );
		}

		if ( ! empty( $config['hierarchy_enabled'] ) ) {
			$html = self::fix_hierarchy( $html, ( isset( $config['hierarchy_mode'] ) ? $config['hierarchy_mode'] : 'report' ) );
		}

		self::$last_report['h1_after'] = self::count_h1( $html );

		return $html;
	}

	/**
	 * Is the current URL on the exclusion list?
	 *
	 * @return bool
	 */
	public static function is_excluded() {
		$raw = (string) AIM_AVT_Settings::get( 'headings.h1_exclusions', '' );
		if ( '' === trim( $raw ) ) {
			return false;
		}
		$current = self::current_path();
		foreach ( AIM_AVT_Settings::lines( $raw ) as $path ) {
			$path = '/' . trim( $path, "/ \t" ) . '/';
			if ( '//' === $path ) {
				$path = '/';
			}
			if ( $current === $path ) {
				return true;
			}
			// Trailing * matches a prefix.
			if ( '*/' === substr( $path, -2 ) && 0 === strpos( $current, substr( $path, 0, -2 ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The current request path, normalized with a leading and trailing slash.
	 *
	 * @return string
	 */
	public static function current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$uri = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! $uri ) {
			return '/';
		}
		return '/' . trim( $uri, '/' ) . ( '/' === $uri ? '' : '/' );
	}

	/**
	 * Count H1 elements in the document body.
	 *
	 * @param string $html HTML.
	 * @return int
	 */
	public static function count_h1( $html ) {
		return preg_match_all( '#<h1\b#i', $html );
	}

	/**
	 * Guarantee exactly one H1 on the page.
	 *
	 * @param string $html   HTML.
	 * @param array  $config Heading settings.
	 * @return string
	 */
	public static function ensure_h1( $html, array $config ) {
		$region = self::content_region( $html );
		if ( ! $region ) {
			self::$last_report['h1_action'] = 'no-region';
			return $html;
		}

		$inner = substr( $html, $region['start'], $region['length'] );

		$existing = preg_match_all( '#<h1\b#i', $inner );

		if ( $existing === 1 ) {
			self::$last_report['h1_action'] = 'already-present';
			return $html;
		}

		if ( $existing > 1 ) {
			// More than one H1 is its own defect: keep the first, demote the rest.
			$count = 0;
			$inner = preg_replace_callback(
				'#<(/?)h1(\b[^>]*)>#i',
				static function ( $m ) use ( &$count ) {
					$is_close = '/' === $m[1];
					if ( ! $is_close ) {
						$count++;
					}
					if ( $count <= 1 ) {
						return $m[0];
					}
					return '<' . $m[1] . 'h2' . $m[2] . '>';
				},
				$inner
			);
			self::$last_report['h1_action'] = 'demoted-extras';
			return substr_replace( $html, $inner, $region['start'], $region['length'] );
		}

		$title = self::intended_h1_text();

		// Preferred fix: promote the heading that already carries the page
		// title. Nothing moves on the page and nothing is added.
		if ( 'insert' !== ( isset( $config['h1_mode'] ) ? $config['h1_mode'] : 'promote' ) ) {
			$promoted = self::promote_matching_heading( $inner, $title );
			if ( null === $promoted ) {
				$promoted = self::promote_first_heading( $inner );
			}
			if ( null !== $promoted ) {
				self::$last_report['h1_action'] = 'promoted';
				return substr_replace( $html, $promoted, $region['start'], $region['length'] );
			}
		}

		// Fallback: insert an H1 at the top of the content region.
		if ( '' === $title ) {
			self::$last_report['h1_action'] = 'no-title';
			return $html;
		}

		$class = isset( $config['h1_class'] ) ? trim( $config['h1_class'] ) : 'aim-avt-h1';
		$class = $class ? ' class="' . esc_attr( $class ) . '"' : '';

		$h1    = '<h1' . $class . ' data-aim-avt="inserted">' . esc_html( $title ) . '</h1>';
		$inner = $h1 . $inner;

		self::$last_report['h1_action'] = 'inserted';

		return substr_replace( $html, $inner, $region['start'], $region['length'] );
	}

	/**
	 * The text this page's H1 should carry.
	 *
	 * @return string
	 */
	public static function intended_h1_text() {
		// Per-path overrides take priority.
		$overrides = AIM_AVT_Settings::lines( (string) AIM_AVT_Settings::get( 'headings.h1_overrides', '' ) );
		$current   = self::current_path();
		foreach ( $overrides as $line ) {
			$parts = explode( '|', $line, 2 );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$path = '/' . trim( $parts[0], "/ \t" ) . '/';
			if ( '//' === $path ) {
				$path = '/';
			}
			if ( $path === $current ) {
				return trim( $parts[1] );
			}
		}

		if ( is_front_page() ) {
			$home = trim( (string) AIM_AVT_Settings::get( 'headings.h1_home_text', '' ) );
			if ( '' !== $home ) {
				return $home;
			}
		}

		if ( is_singular() ) {
			$title = get_the_title( get_queried_object_id() );
			if ( $title ) {
				return wp_strip_all_tags( $title );
			}
		}

		if ( is_post_type_archive() ) {
			return wp_strip_all_tags( post_type_archive_title( '', false ) );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return wp_strip_all_tags( single_term_title( '', false ) );
		}

		if ( is_search() ) {
			return sprintf(
				/* translators: %s: search term. */
				__( 'Search results for %s', 'aim-avt' ),
				get_search_query()
			);
		}

		return '';
	}

	/**
	 * Promote the heading whose text matches the page title.
	 *
	 * @param string $inner Content region HTML.
	 * @param string $title Expected title text.
	 * @return string|null Modified HTML, or null if no match.
	 */
	protected static function promote_matching_heading( $inner, $title ) {
		if ( '' === trim( $title ) ) {
			return null;
		}
		$needle = self::normalize_text( $title );

		if ( ! preg_match_all( '#<(h[2-4])(\b[^>]*)>(.*?)</\1\s*>#is', $inner, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		foreach ( $matches as $m ) {
			$text = self::normalize_text( wp_strip_all_tags( $m[3][0] ) );
			if ( '' === $text || $text !== $needle ) {
				continue;
			}
			$whole  = $m[0][0];
			$offset = $m[0][1];
			$tag    = strtolower( $m[1][0] );
			$rebuilt = '<h1' . $m[2][0] . ' data-aim-avt="promoted-from-' . $tag . '">' . $m[3][0] . '</h1>';
			return substr_replace( $inner, $rebuilt, $offset, strlen( $whole ) );
		}

		return null;
	}

	/**
	 * Promote the first H2 in the content region.
	 *
	 * @param string $inner Content region HTML.
	 * @return string|null
	 */
	protected static function promote_first_heading( $inner ) {
		if ( ! preg_match( '#<(h2)(\b[^>]*)>(.*?)</\1\s*>#is', $inner, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$text = trim( wp_strip_all_tags( $m[3][0] ) );
		if ( '' === $text ) {
			return null;
		}
		$rebuilt = '<h1' . $m[2][0] . ' data-aim-avt="promoted-from-h2">' . $m[3][0] . '</h1>';
		return substr_replace( $inner, $rebuilt, $m[0][1], strlen( $m[0][0] ) );
	}

	/**
	 * Close heading-level gaps — Task 2.5.
	 *
	 * A heading that jumps more than one level below the previous heading is
	 * demoted to exactly one level below it. Levels are never raised, so a
	 * document's outline can only get shallower and better formed.
	 *
	 * @param string $html HTML.
	 * @param string $mode "report" or "fix".
	 * @return string
	 */
	public static function fix_hierarchy( $html, $mode = 'report' ) {
		$region = self::content_region( $html );
		if ( ! $region ) {
			return $html;
		}

		$inner = substr( $html, $region['start'], $region['length'] );

		if ( ! preg_match_all( '#<(/?)(h[1-6])(\b[^>]*)>#i', $inner, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$previous_level = 0;
		$gaps           = 0;
		$edits          = array();
		$open_map       = array(); // Maps an open tag's new level so the close tag matches.

		foreach ( $matches as $m ) {
			$is_close = '/' === $m[1][0];
			$level    = (int) substr( strtolower( $m[2][0] ), 1 );
			$offset   = $m[0][1];
			$length   = strlen( $m[0][0] );

			if ( $is_close ) {
				if ( isset( $open_map[ $level ] ) && ! empty( $open_map[ $level ] ) ) {
					$new_level = array_pop( $open_map[ $level ] );
					if ( $new_level !== $level ) {
						$edits[] = array( $offset, $length, '</h' . $new_level . '>' );
					}
				}
				continue;
			}

			$new_level = $level;
			if ( $previous_level > 0 && $level > $previous_level + 1 ) {
				$gaps++;
				$new_level = $previous_level + 1;
			}

			if ( ! isset( $open_map[ $level ] ) ) {
				$open_map[ $level ] = array();
			}
			$open_map[ $level ][] = $new_level;

			if ( $new_level !== $level ) {
				$edits[] = array( $offset, $length, '<h' . $new_level . $m[3][0] . ' data-aim-avt="level-' . $level . '-to-' . $new_level . '">' );
			}

			$previous_level = $new_level;
		}

		self::$last_report['hierarchy']['gaps'] = $gaps;

		if ( 'fix' !== $mode || ! $edits ) {
			return $html;
		}

		// Apply from the end so earlier offsets stay valid.
		usort(
			$edits,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		foreach ( $edits as $edit ) {
			$inner = substr_replace( $inner, $edit[2], $edit[0], $edit[1] );
		}

		self::$last_report['hierarchy']['fixed'] = count( $edits );

		return substr_replace( $html, $inner, $region['start'], $region['length'] );
	}

	/**
	 * Resolve the region of the document that holds the page's own content.
	 *
	 * Preference order: <main>, then <article>, then <body>. Navigation and
	 * footer headings inside <header>/<nav> are outside <main> on any
	 * reasonably built theme, which keeps a menu label from being promoted to
	 * the page's H1.
	 *
	 * @param string $html HTML.
	 * @return array{start:int,length:int}|null
	 */
	public static function content_region( $html ) {
		$region = self::element_inner_region( $html, 'main' );
		if ( $region ) {
			return $region;
		}

		// <article> is only a safe stand-in on a single-item page. On an archive
		// it would scope everything to the first post in the list.
		if ( is_singular() ) {
			$region = self::element_inner_region( $html, 'article' );
			if ( $region ) {
				return $region;
			}
		}

		return self::element_inner_region( $html, 'body' );
	}

	/**
	 * Byte offsets of the inner HTML of the first element with this tag name,
	 * handling nesting.
	 *
	 * @param string $html HTML.
	 * @param string $tag  Tag name.
	 * @return array{start:int,length:int}|null
	 */
	public static function element_inner_region( $html, $tag ) {
		$tag = preg_quote( $tag, '#' );

		if ( ! preg_match( '#<' . $tag . '(\s[^>]*)?>#i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$start = $m[0][1] + strlen( $m[0][0] );

		$depth  = 1;
		$offset = $start;
		$len    = strlen( $html );

		while ( $offset < $len && $depth > 0 ) {
			if ( ! preg_match( '#<(/?)' . $tag . '(\s[^>]*)?>#i', $html, $m2, PREG_OFFSET_CAPTURE, $offset ) ) {
				return null;
			}
			$depth += ( '/' === $m2[1][0] ) ? -1 : 1;
			$offset = $m2[0][1] + strlen( $m2[0][0] );
			if ( 0 === $depth ) {
				return array(
					'start'  => $start,
					'length' => $m2[0][1] - $start,
				);
			}
		}

		return null;
	}

	/**
	 * Normalize heading text for comparison.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected static function normalize_text( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( mb_strtolower( $text, 'UTF-8' ) );
	}
}
