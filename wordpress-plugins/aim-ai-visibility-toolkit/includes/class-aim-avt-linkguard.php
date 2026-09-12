<?php
/**
 * Outbound link guard — Task 1.1.
 *
 * The assessment found 51 outbound links on /news/ pointing to 48 unrelated
 * third-party domains, and recorded that they appear in neither the WordPress
 * post body nor the February 2026 archive capture. That combination points at
 * injection rather than authoring, so this class does three separate things:
 *
 *   1. Live filter  — strips the links from the rendered page immediately,
 *                     without touching the database. Instantly reversible.
 *   2. Scan         — reports where the links actually live: post content,
 *                     comments, or neither (which means injection).
 *   3. Clean        — removes them from the database, with a full snapshot
 *                     taken first so the edit can be undone.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Linkguard {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'aim_avt_after_clean', array( __CLASS__, 'flush_caches' ) );
	}

	/**
	 * Domains that are never stripped.
	 *
	 * @return array
	 */
	public static function allowlist() {
		$list = AIM_AVT_Settings::lines( (string) AIM_AVT_Settings::get( 'linkguard.allowlist', '' ) );

		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $home ) {
			$list[] = $home;
		}

		$clean = array();
		foreach ( $list as $domain ) {
			$domain = strtolower( trim( $domain ) );
			$domain = preg_replace( '#^https?://#', '', $domain );
			$domain = preg_replace( '#^www\.#', '', $domain );
			$domain = trim( $domain, '/' );
			if ( '' !== $domain ) {
				$clean[ $domain ] = true;
			}
		}
		return array_keys( $clean );
	}

	/**
	 * Paths the guard applies to. Empty means the whole site.
	 *
	 * @return array
	 */
	public static function scope_paths() {
		return AIM_AVT_Settings::lines( (string) AIM_AVT_Settings::get( 'linkguard.scope', '' ) );
	}

	/**
	 * Does the current request fall inside the configured scope?
	 *
	 * @return bool
	 */
	public static function in_scope() {
		$paths = self::scope_paths();
		if ( ! $paths ) {
			return true;
		}
		$current = AIM_AVT_Headings::current_path();
		foreach ( $paths as $path ) {
			$path = '/' . trim( $path, "/ \t" ) . '/';
			if ( '//' === $path ) {
				$path = '/';
			}
			if ( $current === $path || 0 === strpos( $current, rtrim( $path, '/' ) . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is this URL pointing somewhere the allowlist does not cover?
	 *
	 * @param string $url URL from an href.
	 * @return bool
	 */
	public static function is_foreign( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		// Relative, anchor, mailto, tel and protocol-less internal links.
		if ( preg_match( '#^(/|\#|\?|mailto:|tel:|javascript:|data:)#i', $url ) ) {
			return false;
		}
		if ( ! preg_match( '#^(https?:)?//#i', $url ) ) {
			return false;
		}

		$host = wp_parse_url( ( 0 === strpos( $url, '//' ) ? 'https:' . $url : $url ), PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}
		$host = strtolower( preg_replace( '#^www\.#', '', $host ) );

		foreach ( self::allowlist() as $allowed ) {
			if ( $host === $allowed || substr( $host, -( strlen( $allowed ) + 1 ) ) === '.' . $allowed ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Strip foreign links from rendered HTML — the live, non-destructive fix.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public static function filter_html( $html ) {
		if ( ! self::in_scope() ) {
			return $html;
		}

		$mode    = AIM_AVT_Settings::get( 'linkguard.strip_mode', 'unlink' );
		$stripped = 0;

		$html = preg_replace_callback(
			'#<a\b([^>]*?)href\s*=\s*(["\'])(.*?)\2([^>]*)>(.*?)</a\s*>#is',
			static function ( $m ) use ( $mode, &$stripped ) {
				if ( ! self::is_foreign( $m[3] ) ) {
					return $m[0];
				}
				$stripped++;
				if ( 'remove' === $mode ) {
					return '';
				}
				// Keep the words, drop the link.
				return '<span data-aim-avt="unlinked">' . $m[5] . '</span>';
			},
			$html
		);

		if ( $stripped && AIM_AVT_Output::debug_requested() ) {
			$html .= "\n<!-- AIM AVT linkguard: stripped {$stripped} foreign link(s) from this page -->\n";
		}

		return $html;
	}

	// -------------------------------------------------------------------
	// Scanning.
	// -------------------------------------------------------------------

	/**
	 * Scan the database and the rendered page for foreign outbound links.
	 *
	 * @param array $paths Paths to scan; defaults to the configured scope.
	 * @return array
	 */
	public static function scan( array $paths = array() ) {
		if ( ! $paths ) {
			$paths = self::scope_paths();
		}
		if ( ! $paths ) {
			$paths = array( '/news/' );
		}

		$report = array(
			'paths'        => $paths,
			'posts'        => array(),
			'comments'     => array(),
			'rendered'     => array(),
			'db_domains'   => array(),
			'rendered_only'=> array(),
			'injected'     => false,
			'scanned_at'   => time(),
		);

		// Scan every post and page once — a link farm is rarely confined to
		// the page it was noticed on.
		$candidates = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		foreach ( $paths as $path ) {
			$post_id = url_to_postid( home_url( $path ) );
			if ( $post_id ) {
				$candidates[] = $post_id;
			}
		}

		foreach ( array_unique( array_map( 'intval', $candidates ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}

			$found = self::find_in_text( $post->post_content );
			if ( $found ) {
				$report['posts'][ $id ] = array(
					'title' => get_the_title( $id ),
					'url'   => get_permalink( $id ),
					'links' => $found,
				);
			}

			$comments = get_comments(
				array(
					'post_id' => $id,
					'status'  => 'all',
					'number'  => 500,
				)
			);
			foreach ( $comments as $comment ) {
				$found = self::find_in_text( $comment->comment_content . ' ' . $comment->comment_author_url );
				if ( $found ) {
					$report['comments'][ $comment->comment_ID ] = array(
						'post_id' => $id,
						'author'  => $comment->comment_author,
						'links'   => $found,
					);
				}
			}
		}

		// What does each scoped page actually render?
		foreach ( $paths as $path ) {
			$rendered = self::fetch_rendered( home_url( $path ) );
			if ( is_array( $rendered ) ) {
				$report['rendered'][ $path ] = $rendered;
			}
		}

		// Domains present in the database.
		foreach ( $report['posts'] as $row ) {
			foreach ( $row['links'] as $link ) {
				$report['db_domains'][ $link['host'] ] = true;
			}
		}
		foreach ( $report['comments'] as $row ) {
			foreach ( $row['links'] as $link ) {
				$report['db_domains'][ $link['host'] ] = true;
			}
		}
		$report['db_domains'] = array_keys( $report['db_domains'] );

		// Domains that render but are nowhere in the database — the injection signature.
		foreach ( $report['rendered'] as $hosts ) {
			foreach ( $hosts as $host => $count ) {
				if ( ! in_array( $host, $report['db_domains'], true ) ) {
					$report['rendered_only'][ $host ] = $count;
				}
			}
		}
		$report['injected'] = ! empty( $report['rendered_only'] );

		update_option( 'aim_avt_linkguard_report', $report, false );

		return $report;
	}

	/**
	 * Find foreign links in a blob of text or HTML.
	 *
	 * @param string $text Text.
	 * @return array
	 */
	public static function find_in_text( $text ) {
		$found = array();
		if ( ! is_string( $text ) || '' === $text ) {
			return $found;
		}

		if ( preg_match_all( '#<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']#i', $text, $m ) ) {
			foreach ( $m[1] as $url ) {
				if ( ! self::is_foreign( $url ) ) {
					continue;
				}
				$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
				$found[] = array(
					'url'  => $url,
					'host' => preg_replace( '#^www\.#', '', $host ),
				);
			}
		}

		return $found;
	}

	/**
	 * Fetch a URL with the plugin's filters bypassed and count foreign hosts.
	 *
	 * @param string $url URL.
	 * @return array|false Host => count.
	 */
	public static function fetch_rendered( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 25,
				'sslverify'  => false,
				'user-agent' => 'AIM-AVT-Scanner/' . AIM_AVT_VERSION,
				'headers'    => array( 'X-AIM-AVT-Scan' => '1' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return false;
		}

		$hosts = array();
		foreach ( self::find_in_text( $body ) as $link ) {
			if ( ! isset( $hosts[ $link['host'] ] ) ) {
				$hosts[ $link['host'] ] = 0;
			}
			$hosts[ $link['host'] ]++;
		}
		return $hosts;
	}

	/**
	 * The last scan report.
	 *
	 * @return array|false
	 */
	public static function last_report() {
		$report = get_option( 'aim_avt_linkguard_report', false );
		return is_array( $report ) ? $report : false;
	}

	// -------------------------------------------------------------------
	// Cleaning.
	// -------------------------------------------------------------------

	/**
	 * Remove foreign links from the database, taking a restore point first.
	 *
	 * @param array $args {
	 *     @type bool $clean_posts    Strip links from post content.
	 *     @type bool $delete_spam    Delete comments that carry foreign links.
	 *     @type bool $lock_comments  Close comments on the affected posts.
	 * }
	 * @return array{ok:bool,snapshot:string,message:string,details:array}
	 */
	public static function clean( array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'clean_posts'   => true,
				'delete_spam'   => true,
				'lock_comments' => false,
			)
		);

		$report = self::last_report();
		if ( ! $report ) {
			$report = self::scan();
		}

		$snapshot = AIM_AVT_Snapshots::create(
			'linkguard-clean',
			__( 'Before removing foreign outbound links from the database', 'aim-avt' )
		);

		$details        = array();
		$posts_changed  = 0;
		$links_removed  = 0;
		$comments_gone  = 0;
		$locked         = 0;
		$mode           = AIM_AVT_Settings::get( 'linkguard.strip_mode', 'unlink' );

		if ( $args['clean_posts'] ) {
			foreach ( array_keys( $report['posts'] ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}
				AIM_AVT_Snapshots::backup_post( $snapshot, $post_id );

				$removed = 0;
				$content = preg_replace_callback(
					'#<a\b([^>]*?)href\s*=\s*(["\'])(.*?)\2([^>]*)>(.*?)</a\s*>#is',
					static function ( $m ) use ( $mode, &$removed ) {
						if ( ! self::is_foreign( $m[3] ) ) {
							return $m[0];
						}
						$removed++;
						return ( 'remove' === $mode ) ? '' : $m[5];
					},
					$post->post_content
				);

				if ( $removed > 0 && $content !== $post->post_content ) {
					// Preserve the editor's own markup: strip the links without
					// letting kses rewrite everything else on the way through.
					// Only restore the filter if it was registered to begin with.
					$kses_was_on = has_filter( 'content_save_pre', 'wp_filter_post_kses' );
					if ( false !== $kses_was_on ) {
						remove_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_was_on );
					}
					$result = wp_update_post(
						wp_slash(
							array(
								'ID'           => $post_id,
								'post_content' => $content,
							)
						),
						true
					);
					if ( false !== $kses_was_on ) {
						add_filter( 'content_save_pre', 'wp_filter_post_kses', $kses_was_on );
					}

					if ( ! is_wp_error( $result ) ) {
						$posts_changed++;
						$links_removed += $removed;
					}
				}
			}
			if ( $posts_changed ) {
				$details[] = sprintf(
					/* translators: 1: number of links, 2: number of pages. */
					__( 'Removed %1$d foreign link(s) from %2$d page(s) or post(s).', 'aim-avt' ),
					$links_removed,
					$posts_changed
				);
			}
		}

		if ( $args['delete_spam'] && ! empty( $report['comments'] ) ) {
			$objects = array();
			foreach ( array_keys( $report['comments'] ) as $comment_id ) {
				$comment = get_comment( $comment_id );
				if ( $comment ) {
					$objects[] = $comment;
				}
			}
			if ( $objects ) {
				AIM_AVT_Snapshots::backup_comments( $snapshot, $objects );
				foreach ( $objects as $comment ) {
					if ( wp_delete_comment( $comment->comment_ID, true ) ) {
						$comments_gone++;
					}
				}
				$details[] = sprintf(
					/* translators: %d: number of comments. */
					__( 'Deleted %d comment(s) carrying foreign links. They can be put back from the restore point.', 'aim-avt' ),
					$comments_gone
				);
			}
		}

		if ( $args['lock_comments'] ) {
			$targets = array_keys( $report['posts'] );
			$targets = array_merge(
				$targets,
				get_posts(
					array(
						'post_type'      => 'post',
						'post_status'    => 'any',
						'posts_per_page' => 500,
						'fields'         => 'ids',
					)
				)
			);
			foreach ( array_unique( $targets ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post || 'closed' === $post->comment_status ) {
					continue;
				}
				AIM_AVT_Snapshots::backup_post( $snapshot, $post_id );
				wp_update_post(
					array(
						'ID'             => $post_id,
						'comment_status' => 'closed',
						'ping_status'    => 'closed',
					)
				);
				$locked++;
			}
			if ( $locked ) {
				$details[] = sprintf(
					/* translators: %d: number of posts. */
					__( 'Closed comments and pingbacks on %d post(s).', 'aim-avt' ),
					$locked
				);
			}
		}

		if ( ! $details ) {
			$details[] = __( 'Nothing needed removing from the database. If the links still render on the page, they are being injected by a plugin, the theme or a compromised file — see the security note on the Task 1.1 screen.', 'aim-avt' );
		}

		do_action( 'aim_avt_after_clean' );

		AIM_AVT_Log::add( 'linkguard-clean', implode( ' ', $details ) );

		return array(
			'ok'       => true,
			'snapshot' => $snapshot,
			'message'  => __( 'Cleanup finished.', 'aim-avt' ),
			'details'  => $details,
		);
	}

	/**
	 * Ask the common caching layers to drop what they are holding.
	 */
	public static function flush_caches() {
		// LiteSpeed Cache — the assessment recorded this stack in use.
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( class_exists( '\LiteSpeed\Purge' ) && method_exists( '\LiteSpeed\Purge', 'purge_all' ) ) {
			\LiteSpeed\Purge::purge_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		wp_cache_flush();
	}
}
