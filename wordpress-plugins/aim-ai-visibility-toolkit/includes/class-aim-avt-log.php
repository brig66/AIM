<?php
/**
 * Plain-language activity log.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Log {

	const MAX_ENTRIES = 300;

	/**
	 * Append an entry.
	 *
	 * @param string $type    Machine type.
	 * @param string $message Human-readable message.
	 * @param array  $meta    Optional extra data.
	 */
	public static function add( $type, $message, array $meta = array() ) {
		$log = self::all();

		$user = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : null;

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'type'    => (string) $type,
				'message' => (string) $message,
				'user'    => ( $user && $user->exists() ) ? $user->user_login : 'system',
				'meta'    => $meta,
			)
		);

		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::MAX_ENTRIES );
		}

		update_option( AIM_AVT_LOG_OPTION, $log, false );
	}

	/**
	 * Read the log, newest first.
	 *
	 * @param int $limit Maximum entries.
	 * @return array
	 */
	public static function all( $limit = 0 ) {
		$log = get_option( AIM_AVT_LOG_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		if ( $limit > 0 ) {
			$log = array_slice( $log, 0, $limit );
		}
		return $log;
	}

	/**
	 * Empty the log.
	 */
	public static function clear() {
		delete_option( AIM_AVT_LOG_OPTION );
	}
}
