<?php
/**
 * llms.txt generation and repair — Task 1.8.
 *
 * The published file scores llms_present but fails llms_wellformed on two
 * counts: UTF-8 bytes served as Latin-1 (the "FranÃ§ais" / "EspaÃ±ol"
 * mojibake), and entries pointing at /fr/ and /es/, neither of which is in the
 * 119-URL sitemap inventory.
 *
 * Because the current file is physical — served by the web server before
 * WordPress sees the request — this class can either write a corrected file to
 * disk (backed up first, so the write is undoable) or serve a generated one
 * dynamically when no physical file is in the way.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_LLMS {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 1 );
	}

	/**
	 * Absolute path of the physical file, if the site uses one.
	 *
	 * @return string
	 */
	public static function physical_path() {
		return ABSPATH . 'llms.txt';
	}

	/**
	 * Serve a generated llms.txt when WordPress is handling the request.
	 *
	 * A physical file at the web root is served by the web server and never
	 * reaches this hook, which is exactly why the "write to disk" tool exists.
	 */
	public static function maybe_serve() {
		if ( ! AIM_AVT_Settings::get( 'llms.enabled' ) ) {
			return;
		}
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '/llms.txt' !== rtrim( $path, '/' ) && '/llms.txt' !== $path ) {
			return;
		}

		$body = self::generate();

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
			header( 'Cache-Control: max-age=3600' );
		}
		echo $body; // phpcs:ignore WordPress.Security.EscapingOutput -- Plain-text file body.
		exit;
	}

	/**
	 * Build the file.
	 *
	 * @return string
	 */
	public static function generate() {
		$config = (array) AIM_AVT_Settings::get( 'llms', array() );

		if ( 'custom' === ( isset( $config['mode'] ) ? $config['mode'] : 'generate' ) && ! empty( $config['custom_body'] ) ) {
			return self::force_utf8( (string) $config['custom_body'] );
		}

		$org_name = AIM_AVT_Settings::get( 'schema.org_name' );
		$lines    = array();

		$lines[] = '# ' . $org_name;
		$lines[] = '';
		$lines[] = '> ' . self::unwrap( (string) ( isset( $config['summary'] ) ? $config['summary'] : '' ) );
		$lines[] = '';

		// Facts an engine should be able to state without inference.
		$lines[] = '## Company facts';
		$founding = AIM_AVT_Settings::get( 'schema.founding_date' );
		if ( $founding ) {
			$lines[] = '- Founded: ' . $founding;
		}
		$iso = AIM_AVT_Settings::get( 'schema.iso_cert' );
		if ( $iso ) {
			$lines[] = '- Quality certification: ' . $iso;
		}
		$email = AIM_AVT_Settings::get( 'schema.org_email' );
		if ( $email ) {
			$lines[] = '- Email: ' . $email;
		}
		$lines[] = '- Website: ' . untrailingslashit( AIM_AVT_Settings::get( 'schema.org_url' ) ) . '/';
		$lines[] = '';

		// Locations — the same canonical NAP that goes into schema.
		$locations = (array) AIM_AVT_Settings::get( 'schema.locations', array() );
		if ( $locations ) {
			$lines[] = '## Locations';
			foreach ( $locations as $location ) {
				$parts = array_filter(
					array(
						isset( $location['street'] ) ? $location['street'] : '',
						isset( $location['locality'] ) ? $location['locality'] : '',
						trim( ( isset( $location['region'] ) ? $location['region'] : '' ) . ' ' . ( isset( $location['postal_code'] ) ? $location['postal_code'] : '' ) ),
						isset( $location['country'] ) ? $location['country'] : '',
					)
				);
				$label = ! empty( $location['name'] ) ? $location['name'] : ( isset( $location['locality'] ) ? $location['locality'] : '' );
				$line  = '- ' . $label . ': ' . implode( ', ', $parts );
				if ( ! empty( $location['phone'] ) ) {
					$line .= ' · ' . $location['phone'];
				}
				if ( ! empty( $location['head_office'] ) ) {
					$line .= ' (head office)';
				}
				$lines[] = $line;
			}
			$lines[] = '';
		}

		// Grouped links, drawn only from published content that is genuinely
		// reachable — nothing in this file may point at a URL the sitemap does
		// not carry, which is the second half of what llms_wellformed measures.
		$sections = self::link_sections();
		foreach ( $sections as $heading => $links ) {
			if ( ! $links ) {
				continue;
			}
			$lines[] = '## ' . $heading;
			foreach ( $links as $link ) {
				$lines[] = '- [' . self::unwrap( $link['title'] ) . '](' . $link['url'] . ')';
			}
			$lines[] = '';
		}

		// Language mirrors — Task 1.8. Only listed if they are genuinely back.
		if ( 'kept' === ( isset( $config['lang_mirrors'] ) ? $config['lang_mirrors'] : 'removed' ) ) {
			$mirrors = array();
			foreach ( array( '/fr/' => 'Site en Français', '/es/' => 'Sitio en Español' ) as $path => $label ) {
				if ( self::path_is_live( $path ) ) {
					$mirrors[] = '- [' . $label . '](' . home_url( $path ) . ')';
				}
			}
			if ( $mirrors ) {
				$lines[] = '## Other languages';
				$lines   = array_merge( $lines, $mirrors );
				$lines[] = '';
			}
		}

		$lines[] = '## Notes';
		$lines[] = '- Last generated: ' . gmdate( 'Y-m-d' );
		$lines[] = '- Encoding: UTF-8';
		$lines[] = '';

		$body = implode( "\n", $lines );

		/**
		 * Filter the generated llms.txt body.
		 *
		 * @param string $body Body.
		 */
		$body = apply_filters( 'aim_avt_llms_body', $body );

		return self::force_utf8( $body );
	}

	/**
	 * Grouped link sections drawn from published content.
	 *
	 * @return array Heading => array of {title,url}.
	 */
	public static function link_sections() {
		$config = (array) AIM_AVT_Settings::get( 'llms', array() );
		$max    = max( 1, (int) ( isset( $config['max_links_per_section'] ) ? $config['max_links_per_section'] : 25 ) );
		$wanted = (array) ( isset( $config['include_sections'] ) ? $config['include_sections'] : array() );

		$map = array(
			'services'   => array( 'heading' => 'Services', 'post_types' => array( 'service', 'services' ) ),
			'locations'  => array( 'heading' => 'Service areas', 'post_types' => array( 'location', 'locations', 'jurisdiction' ) ),
			'equipment'  => array( 'heading' => 'Equipment', 'post_types' => array( 'equipment' ) ),
			'industries' => array( 'heading' => 'Industries', 'post_types' => array( 'industry', 'industries' ) ),
			'parts'      => array( 'heading' => 'Parts', 'post_types' => array( 'product', 'parts' ) ),
			'content'    => array( 'heading' => 'Key pages', 'post_types' => array( 'page' ) ),
		);

		$sections = array();

		foreach ( $map as $key => $spec ) {
			if ( $wanted && ! in_array( $key, $wanted, true ) ) {
				continue;
			}
			$types = array_values( array_filter( $spec['post_types'], 'post_type_exists' ) );
			if ( ! $types ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $types,
					'post_status'    => 'publish',
					'posts_per_page' => $max,
					'orderby'        => 'menu_order title',
					'order'          => 'ASC',
					'has_password'   => false,
				)
			);
			$links = array();
			foreach ( $posts as $post ) {
				if ( self::is_noindex( $post->ID ) ) {
					continue;
				}
				$links[] = array(
					'title' => wp_strip_all_tags( get_the_title( $post ) ),
					'url'   => get_permalink( $post ),
				);
			}
			if ( $links ) {
				$sections[ $spec['heading'] ] = $links;
			}
		}

		// The news archive, if there is one.
		$recent = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => min( $max, 15 ),
			)
		);
		if ( $recent ) {
			$links = array();
			foreach ( $recent as $post ) {
				if ( self::is_noindex( $post->ID ) ) {
					continue;
				}
				$links[] = array(
					'title' => wp_strip_all_tags( get_the_title( $post ) ),
					'url'   => get_permalink( $post ),
				);
			}
			if ( $links ) {
				$sections['News and technical articles'] = $links;
			}
		}

		return $sections;
	}

	/**
	 * Is this post marked noindex by a common SEO plugin?
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_noindex( $post_id ) {
		foreach ( array( '_yoast_wpseo_meta-robots-noindex', 'rank_math_robots', '_aioseo_noindex' ) as $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( '1' === $value ) {
				return true;
			}
			if ( is_array( $value ) && in_array( 'noindex', $value, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does this path resolve to something published?
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	public static function path_is_live( $path ) {
		$post_id = url_to_postid( home_url( $path ) );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return true;
		}
		$response = wp_remote_head(
			home_url( $path ),
			array(
				'timeout'     => 10,
				'sslverify'   => false,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Repair mojibake and guarantee clean UTF-8 output.
	 *
	 * Handles the double-encoded sequences the assessment found — UTF-8 bytes
	 * that were read as Latin-1 and re-encoded, producing "FranÃ§ais".
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function force_utf8( $text ) {
		$text = (string) $text;

		// Repeatedly undo the Latin-1 -> UTF-8 double encoding while doing so
		// still yields valid UTF-8. Two passes is the realistic maximum.
		for ( $i = 0; $i < 2; $i++ ) {
			if ( ! preg_match( '/[ÂÃ][\x80-\xBF]/u', $text ) ) {
				break;
			}
			$decoded = @iconv( 'UTF-8', 'ISO-8859-1//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false === $decoded || '' === $decoded ) {
				break;
			}
			// Only accept the fix if the result is still valid UTF-8.
			if ( ! mb_check_encoding( $decoded, 'UTF-8' ) ) {
				break;
			}
			$text = $decoded;
		}

		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
		}

		return $text;
	}

	/**
	 * Collapse newlines inside a value so it stays on one line.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected static function unwrap( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
	}

	// -------------------------------------------------------------------
	// Physical file management.
	// -------------------------------------------------------------------

	/**
	 * Report on the physical file: does it exist, is it writable, is it broken?
	 *
	 * @return array
	 */
	public static function status() {
		$path   = self::physical_path();
		$exists = file_exists( $path );

		$status = array(
			'path'         => $path,
			'exists'       => $exists,
			'writable'     => $exists ? is_writable( $path ) : is_writable( ABSPATH ),
			'bytes'        => 0,
			'lines'        => 0,
			'mojibake'     => false,
			'dead_links'   => array(),
			'contents'     => '',
		);

		if ( ! $exists ) {
			return $status;
		}

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $contents ) {
			return $status;
		}

		$status['contents'] = $contents;
		$status['bytes']    = strlen( $contents );
		$status['lines']    = substr_count( $contents, "\n" ) + 1;
		$status['mojibake'] = (bool) preg_match( '/[ÂÃ][\x80-\xBF]/u', $contents );

		// Every URL in the file has to resolve. An entry pointing at a
		// withdrawn mirror is the second half of the llms_wellformed failure.
		if ( preg_match_all( '#\((https?://[^)\s]+)\)#i', $contents, $m ) ) {
			$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
			foreach ( array_unique( $m[1] ) as $url ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				if ( $host !== $home_host ) {
					continue;
				}
				$path_part = (string) wp_parse_url( $url, PHP_URL_PATH );
				if ( ! self::path_is_live( $path_part ) ) {
					$status['dead_links'][] = $url;
				}
			}
		}

		return $status;
	}

	/**
	 * Write the generated file to disk, backing up whatever is there now.
	 *
	 * @return array{ok:bool,message:string,snapshot:string}
	 */
	public static function write_physical() {
		$path = self::physical_path();

		if ( ! is_writable( file_exists( $path ) ? $path : ABSPATH ) ) {
			return array(
				'ok'       => false,
				'snapshot' => '',
				'message'  => sprintf(
					/* translators: %s: file path. */
					__( 'Cannot write to %s. Your host has the web root locked down — ask them to make llms.txt writable, or copy the generated text below over the file by FTP.', 'aim-avt' ),
					$path
				),
			);
		}

		$snapshot = AIM_AVT_Snapshots::create(
			'llms-write',
			__( 'Before overwriting llms.txt', 'aim-avt' )
		);
		AIM_AVT_Snapshots::backup_file( $snapshot, $path );

		$body    = self::generate();
		$written = file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $written ) {
			return array(
				'ok'       => false,
				'snapshot' => $snapshot,
				'message'  => __( 'The write failed. Nothing was changed.', 'aim-avt' ),
			);
		}

		AIM_AVT_Linkguard::flush_caches();
		AIM_AVT_Log::add(
			'llms-write',
			sprintf(
				/* translators: %d: bytes written. */
				__( 'Wrote a regenerated llms.txt (%d bytes, UTF-8).', 'aim-avt' ),
				$written
			)
		);

		return array(
			'ok'       => true,
			'snapshot' => $snapshot,
			'message'  => sprintf(
				/* translators: %d: bytes written. */
				__( 'Wrote %d bytes of clean UTF-8 to llms.txt. The previous file is saved as a restore point.', 'aim-avt' ),
				$written
			),
		);
	}
}
