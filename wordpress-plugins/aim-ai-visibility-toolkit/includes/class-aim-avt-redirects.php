<?php
/**
 * Redirect manager — Tasks 1.9 and 1.8.
 *
 * Task 1.9 asks for the duplicate /equipment/alfa-laval-g2-100-2/ to be
 * resolved. Redirecting rather than deleting is the reversible way to do it:
 * the duplicate keeps whatever equity it has, the canonical URL wins, and
 * switching the rule off puts the original URL straight back.
 *
 * Task 1.8 asks that /fr/ and /es/ 301 to their English equivalents rather
 * than returning a 404 or soft-404.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Redirects {

	/**
	 * Hook in early enough to beat the theme, late enough to know the request.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * Send the redirect if the current path matches a rule.
	 */
	public static function maybe_redirect() {
		if ( ! AIM_AVT_Settings::get( 'master_enabled' ) || ! AIM_AVT_Settings::get( 'redirects.enabled' ) ) {
			return;
		}
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$current = AIM_AVT_Headings::current_path();

		foreach ( self::rules() as $rule ) {
			$from = self::normalize( $rule['from'] );
			if ( '' === $from || $from !== $current ) {
				continue;
			}

			$to = trim( (string) $rule['to'] );
			if ( '' === $to ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $to ) ) {
				$to = home_url( '/' . ltrim( $to, '/' ) );
			}

			// Never redirect a URL to itself.
			if ( self::normalize( (string) wp_parse_url( $to, PHP_URL_PATH ) ) === $current ) {
				continue;
			}

			$code = (int) $rule['code'];
			if ( ! in_array( $code, array( 301, 302, 307, 308 ), true ) ) {
				$code = 301;
			}

			wp_safe_redirect( $to, $code, 'AIM AVT' );
			exit;
		}
	}

	/**
	 * Configured rules.
	 *
	 * @return array
	 */
	public static function rules() {
		$rules = (array) AIM_AVT_Settings::get( 'redirects.rules', array() );
		$out   = array();
		foreach ( $rules as $rule ) {
			if ( empty( $rule['from'] ) || empty( $rule['to'] ) ) {
				continue;
			}
			$out[] = wp_parse_args(
				$rule,
				array(
					'code' => 301,
					'note' => '',
				)
			);
		}
		return $out;
	}

	/**
	 * Normalize a path for comparison.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		}
		$path = '/' . trim( $path, '/' );
		return ( '/' === $path ) ? '/' : $path . '/';
	}

	/**
	 * Check each rule against the live site so the admin screen can show
	 * whether the source URL still exists and whether the target resolves.
	 *
	 * @return array
	 */
	public static function test() {
		$results = array();

		foreach ( self::rules() as $rule ) {
			$from_url = home_url( self::normalize( $rule['from'] ) );
			$to_path  = self::normalize( $rule['to'] );
			$to_url   = preg_match( '#^https?://#i', $rule['to'] ) ? $rule['to'] : home_url( $to_path );

			$results[] = array(
				'from'        => $rule['from'],
				'to'          => $rule['to'],
				'note'        => $rule['note'],
				'from_status' => self::status_code( $from_url ),
				'to_status'   => self::status_code( $to_url ),
			);
		}

		return $results;
	}

	/**
	 * HTTP status for a URL, without following redirects.
	 *
	 * @param string $url URL.
	 * @return int|string
	 */
	protected static function status_code( $url ) {
		$response = wp_remote_head(
			$url,
			array(
				'timeout'     => 12,
				'sslverify'   => false,
				'redirection' => 0,
				'user-agent'  => 'AIM-AVT-Checker/' . AIM_AVT_VERSION,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}
		return (int) wp_remote_retrieve_response_code( $response );
	}
}
