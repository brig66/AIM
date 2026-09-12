<?php
/**
 * Front-end output pipeline.
 *
 * One output buffer runs the enabled page transforms in order: heading repair,
 * outbound-link filtering, then schema merge. Everything is a targeted string
 * edit — the document is never parsed and re-serialized, so markup the theme
 * emits cannot be rewritten or lost by this plugin.
 *
 * The buffer refuses to run on anything that is not a front-end HTML page.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Output {

	/** @var bool Whether the buffer was started for this request. */
	protected static $buffering = false;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_buffer' ), 99 );
	}

	/**
	 * Decide whether to buffer this request, and start if so.
	 */
	public static function maybe_start_buffer() {
		if ( ! self::should_run() ) {
			return;
		}
		self::$buffering = true;
		ob_start( array( __CLASS__, 'filter' ) );
	}

	/**
	 * Is this a request we are willing to rewrite?
	 *
	 * @return bool
	 */
	public static function should_run() {
		if ( ! AIM_AVT_Settings::output_active() ) {
			return false;
		}
		if ( is_admin() || is_feed() || is_trackback() || is_robots() ) {
			return false;
		}
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}
		// XML sitemaps and any other non-HTML template.
		if ( function_exists( 'is_sitemap' ) && is_sitemap() ) {
			return false;
		}
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( preg_match( '/\.(xml|txt|json|xsl|rss|atom)$/i', $path ) ) {
				return false;
			}
		}

		// Is anything actually switched on?
		if ( ! self::any_transform_enabled() ) {
			return false;
		}

		/**
		 * Filter whether the output buffer runs on this request.
		 *
		 * @param bool $run Whether to run.
		 */
		return (bool) apply_filters( 'aim_avt_should_buffer', true );
	}

	/**
	 * Are any page transforms switched on?
	 *
	 * @return bool
	 */
	public static function any_transform_enabled() {
		return (bool) AIM_AVT_Settings::get( 'schema.enabled' )
			|| (bool) AIM_AVT_Settings::get( 'headings.h1_enabled' )
			|| (bool) AIM_AVT_Settings::get( 'headings.hierarchy_enabled' )
			|| (bool) AIM_AVT_Settings::get( 'linkguard.live_enabled' );
	}

	/**
	 * Transform the buffered page.
	 *
	 * Wrapped so that any unexpected failure returns the original HTML rather
	 * than a broken or blank page.
	 *
	 * @param string $html Buffered output.
	 * @return string
	 */
	public static function filter( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 200 ) {
			return $html;
		}
		// Only touch documents that are actually HTML.
		if ( ! preg_match( '#<html[\s>]#i', $html ) && ! preg_match( '#<body[\s>]#i', $html ) ) {
			return $html;
		}

		$original = $html;

		try {
			if ( AIM_AVT_Settings::get( 'linkguard.live_enabled' ) ) {
				$html = AIM_AVT_Linkguard::filter_html( $html );
			}

			if ( AIM_AVT_Settings::get( 'headings.h1_enabled' ) || AIM_AVT_Settings::get( 'headings.hierarchy_enabled' ) ) {
				$html = AIM_AVT_Headings::apply_to_html( $html );
			}

			if ( AIM_AVT_Settings::get( 'schema.enabled' ) ) {
				$result = AIM_AVT_Schema::apply_to_html( $html );
				$html   = $result['html'];
				if ( self::debug_requested() ) {
					$html .= "\n<!-- AIM AVT schema: " . esc_html( $result['result'] ) . ' org=' . esc_html( $result['org_id'] ) . " -->\n";
				}
			}
		} catch ( \Throwable $e ) {
			// A transform failing must never take the page down with it.
			return $original;
		}

		// Safety net: a placeholder reference must never reach the browser,
		// whichever path the schema step took.
		if ( false !== strpos( $html, AIM_AVT_Schema::ORG_PLACEHOLDER ) ) {
			$html = AIM_AVT_Schema::resolve_placeholders( $html, '' );
		}

		if ( ! is_string( $html ) || '' === $html ) {
			return $original;
		}

		return $html;
	}

	/**
	 * Administrators can append ?aim_avt=debug to see what the pipeline did.
	 *
	 * @return bool
	 */
	public static function debug_requested() {
		if ( ! isset( $_GET['aim_avt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( 'debug' !== sanitize_key( wp_unslash( $_GET['aim_avt'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Render a page through the pipeline for the admin preview tool.
	 *
	 * @param string $url URL on this site.
	 * @return array{ok:bool,message:string,before:array,after:array}
	 */
	public static function preview( $url ) {
		$response = wp_remote_get(
			add_query_arg( 'aim_avt_preview', '1', $url ),
			array(
				'timeout'    => 20,
				'sslverify'  => false,
				'user-agent' => 'AIM-AVT-Preview/' . AIM_AVT_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => $response->get_error_message(),
				'before'  => array(),
				'after'   => array(),
			);
		}

		$body = wp_remote_retrieve_body( $response );

		return array(
			'ok'      => true,
			'message' => '',
			'before'  => array(),
			'after'   => self::summarize( $body ),
		);
	}

	/**
	 * Summarize what a rendered page looks like, for the health check.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	public static function summarize( $html ) {
		$blocks = AIM_AVT_Schema::find_jsonld_blocks( $html );
		$types  = array();
		$valid  = 0;

		foreach ( $blocks as $block ) {
			$data = json_decode( $block['json'], true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$valid++;
			$nodes = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : array( $data );
			foreach ( $nodes as $node ) {
				if ( is_array( $node ) && isset( $node['@type'] ) ) {
					foreach ( (array) $node['@type'] as $type ) {
						if ( is_string( $type ) ) {
							$types[] = $type;
						}
					}
				}
			}
		}

		return array(
			'h1_count'      => preg_match_all( '#<h1\b#i', $html ),
			'jsonld_blocks' => count( $blocks ),
			'jsonld_valid'  => $valid,
			'node_types'    => array_count_values( $types ),
			'bytes'         => strlen( $html ),
		);
	}
}
