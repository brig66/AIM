<?php
/**
 * Settings store, defaults and sanitization.
 *
 * Defaults are pre-filled with the values verified in the AIM AI Visibility
 * Assessment for sentrimax.com (2026-09-12). Anything the assessment could not
 * confirm is left blank on purpose so it shows up as an outstanding field on
 * the dashboard rather than being guessed at.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Settings {

	/** @var array|null Runtime cache. */
	protected static $cache = null;

	/**
	 * Factory defaults.
	 *
	 * Every front-end behaviour defaults to OFF. Turning the plugin on does not
	 * change a single byte of the rendered site until the operator opts in.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(

			// ---------------------------------------------------------------
			// Global.
			// ---------------------------------------------------------------
			'master_enabled' => false,
			'safe_mode'      => false, // When true, front-end filters run for logged-in admins only.

			// ---------------------------------------------------------------
			// Tasks 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2 — the schema graph.
			// ---------------------------------------------------------------
			'schema' => array(
				'enabled'          => false,
				'mode'             => 'auto', // auto | merge | standalone.
				'org_name'         => 'Sentrimax Centrifuges',
				'org_legal_name'   => '',
				'org_url'          => 'https://sentrimax.com/',
				'org_logo'         => '',
				'org_email'        => '', // Not evidenced in the assessment — confirm before publishing.
				'org_phone'        => '+1-780-434-1781',
				'org_description'  => 'Sentrimax Centrifuges repairs, rebuilds, balances and rents industrial decanter and tricanter centrifuges for municipal wastewater, oilfield solids control, rendering, ethanol and chemical processing operations across North America, from ISO 9001:2015 certified plants in Edmonton, Alberta; Mansfield, Texas; and Kitchener, Ontario.',
				'founding_date'    => '2002',
				'iso_cert'         => 'ISO 9001:2015',
				'iso_registrar'    => '',
				'iso_registrar_url'=> '',

				// Task 1.3 / 1.4 — canonical NAP for each facility.
				'locations' => array(
					array(
						'key'          => 'edmonton',
						'name'         => 'Sentrimax Centrifuges — Edmonton',
						'head_office'  => true,
						'street'       => '9440 60 Ave NW',
						'locality'     => 'Edmonton',
						'region'       => 'AB',
						'postal_code'  => 'T6E 0C1',
						'country'      => 'CA',
						'phone'        => '+1-780-434-1781',
						'email'        => '',
						'opening_hours'=> 'Mo-Fr 08:00-17:00',
						'area_served'  => 'Alberta, Saskatchewan, British Columbia, Western Canada',
						'latitude'     => '',
						'longitude'    => '',
						'gbp_url'      => '', // Task 2.4 — paste the g.page/r/ or maps?cid= URL once claimed.
						'place_id'     => '', // Task 1.4 — enables the place-linked map embed.
					),
					array(
						'key'          => 'mansfield',
						'name'         => 'Sentrimax Centrifuges — Mansfield',
						'head_office'  => false,
						'street'       => '108 Sentry Dr',
						'locality'     => 'Mansfield',
						'region'       => 'TX',
						'postal_code'  => '76063',
						'country'      => 'US',
						'phone'        => '+1-817-453-8112',
						'email'        => '',
						'opening_hours'=> 'Mo-Fr 08:00-17:00',
						'area_served'  => 'Texas, Oklahoma, Louisiana, Gulf Coast, United States',
						'latitude'     => '',
						'longitude'    => '',
						'gbp_url'      => '',
						'place_id'     => '',
					),
					array(
						'key'          => 'kitchener',
						'name'         => 'Sentrimax Centrifuges — Kitchener',
						'head_office'  => false,
						'street'       => '2300 Shirley Dr',
						'locality'     => 'Kitchener',
						'region'       => 'ON',
						'postal_code'  => 'N2B 3Y2',
						'country'      => 'CA',
						'phone'        => '+1-877-741-0118',
						'email'        => '',
						'opening_hours'=> 'Mo-Fr 08:00-17:00',
						'area_served'  => 'Ontario, Quebec, Eastern Canada',
						'latitude'     => '',
						'longitude'    => '',
						'gbp_url'      => '',
						'place_id'     => '',
					),
				),

				// Task 1.5 — sameAs. Confirmed live in the assessment.
				'same_as' => array(
					'https://www.linkedin.com/company/sentrimax-centrifuges',
					'https://www.facebook.com/p/Sentrimax-Centrifuges-100056096558761/',
					'https://www.instagram.com/sentrimax/',
					'https://www.yelp.ca/biz/sentrimax-centrifuges-edmonton',
				),

				// Task 2.2 — leadership Person nodes.
				'people' => array(
					array(
						'key'         => 'leadership-1',
						'name'        => '',
						'job_title'   => '',
						'description' => '',
						'image'       => '',
						'linkedin'    => '',
						'knows_about' => "Decanter centrifuge repair\nTricanter centrifuge repair\nDynamic balancing to ISO G 0.5\nGearbox and backdrive overhaul\nHardfacing",
						'is_default_author' => true,
					),
				),

				// Task 2.1 — aggregateRating. Never emitted unless substantiated.
				'aggregate_rating' => array(
					'enabled'     => false,
					'min_reviews' => 3,
				),
			),

			// ---------------------------------------------------------------
			// Task 1.7 / 2.5 — heading structure.
			// ---------------------------------------------------------------
			'headings' => array(
				'h1_enabled'         => false,
				'h1_mode'            => 'promote', // promote | insert.
				'h1_class'           => 'aim-avt-h1',
				'h1_home_text'       => 'Industrial Centrifuge Repair, Rebuilds, Parts and Rentals',
				'h1_overrides'       => '', // One "path|Heading text" pair per line.
				'h1_exclusions'      => '', // One URL path per line.
				'hierarchy_enabled'  => false,
				'hierarchy_mode'     => 'report', // report | fix.
			),

			// ---------------------------------------------------------------
			// Task 1.1 — the /news/ link farm.
			// ---------------------------------------------------------------
			'linkguard' => array(
				'live_enabled'     => false,
				'scope'            => "/news/",
				'allowlist'        => "sentrimax.com\nlinkedin.com\nfacebook.com\ninstagram.com\nyoutube.com\ntwitter.com\nx.com\nyelp.ca\nyelp.com\nyellowpages.ca\ngoogle.com\nmaps.google.com\nethanolproducer.com\ncossd.com\nalfalaval.com\nflottweg.com\nandritz.com\ngea.com",
				'strip_mode'       => 'unlink', // unlink (keep the words) | remove (delete the whole anchor).
				'comments_lockdown'=> false,
			),

			// ---------------------------------------------------------------
			// Task 1.8 — llms.txt.
			// ---------------------------------------------------------------
			'llms' => array(
				'enabled'        => false,
				'mode'           => 'generate', // generate | custom.
				'summary'        => "Sentrimax Centrifuges is an independent industrial centrifuge service company founded in 2002, operating ISO 9001:2015 certified repair plants in Edmonton, Alberta; Mansfield, Texas; and Kitchener, Ontario. Sentrimax repairs, rebuilds, dynamically balances and rents decanter and tricanter centrifuges, overhauls gearboxes and backdrives, and applies hardfacing for municipal wastewater, oilfield solids control, rendering, ethanol and chemical processing operations across North America.",
				'custom_body'    => '',
				'include_sections' => array( 'services', 'locations', 'equipment', 'industries', 'content' ),
				'max_links_per_section' => 25,
				'lang_mirrors'   => 'removed', // removed | kept.
			),

			// ---------------------------------------------------------------
			// Task 1.9 + Task 1.8 language mirrors — redirects.
			// ---------------------------------------------------------------
			'redirects' => array(
				'enabled' => false,
				'rules'   => array(
					array(
						'from' => '/equipment/alfa-laval-g2-100-2/',
						'to'   => '/equipment/alfa-laval-g2-100/',
						'code' => 301,
						'note' => 'Task 1.9 — duplicate equipment URL',
					),
					array(
						'from' => '/fr/',
						'to'   => '/',
						'code' => 301,
						'note' => 'Task 1.8 — withdrawn French mirror',
					),
					array(
						'from' => '/es/',
						'to'   => '/',
						'code' => 301,
						'note' => 'Task 1.8 — withdrawn Spanish mirror',
					),
				),
			),

			// ---------------------------------------------------------------
			// Task 2.3 — author attribution on news content.
			// ---------------------------------------------------------------
			'byline' => array(
				'enabled'      => false,
				'post_types'   => array( 'post', 'aim_case_study' ),
				'show_visible' => true,
				'label'        => 'By',
			),

			// ---------------------------------------------------------------
			// Task 3.3 — pricing transparency.
			// ---------------------------------------------------------------
			'pricing' => array(
				'enabled'  => false,
				'currency' => 'USD',
				'as_of'    => '',
				'note'     => 'Ranges are typical for the work described and assume the machine is delivered to a Sentrimax plant. Field mobilization, freight, severe wear and long-lead OEM parts are quoted separately. Contact us for a firm quotation.',
				'bands'    => array(
					array( 'name' => 'Gearbox rebuild', 'min' => '', 'max' => '', 'turnaround' => '', 'assumptions' => 'Typical decanter gearbox, bench rebuild, standard bearings and seals.' ),
					array( 'name' => 'Full centrifuge overhaul', 'min' => '', 'max' => '', 'turnaround' => '', 'assumptions' => 'Complete teardown, inspection, wear-part replacement, reassembly and balance.' ),
					array( 'name' => 'Dynamic balancing', 'min' => '', 'max' => '', 'turnaround' => '', 'assumptions' => 'Bowl and conveyor balanced to ISO G 0.5.' ),
					array( 'name' => 'Emergency mobilization', 'min' => '', 'max' => '', 'turnaround' => '', 'assumptions' => 'Field service crew dispatched outside normal scheduling.' ),
				),
			),

			// ---------------------------------------------------------------
			// Progress tracking for the tasks a plugin cannot perform.
			// ---------------------------------------------------------------
			'manual_progress' => array(),
		);
	}

	/**
	 * Read the full settings array, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$stored = get_option( AIM_AVT_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		self::$cache = self::merge_deep( self::defaults(), $stored );
		return self::$cache;
	}

	/**
	 * Read one value using dot notation, e.g. "schema.org_name".
	 *
	 * @param string $path    Dot path.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $path, $default = null ) {
		$value = self::all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( is_array( $value ) && array_key_exists( $segment, $value ) ) {
				$value = $value[ $segment ];
			} else {
				return $default;
			}
		}
		return $value;
	}

	/**
	 * Persist the whole settings array.
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function save( array $settings ) {
		self::$cache = null;
		return update_option( AIM_AVT_OPTION, $settings, false );
	}

	/**
	 * Persist a single top-level section, leaving the rest untouched.
	 *
	 * @param string $section Section key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	public static function save_section( $section, $value ) {
		$all             = self::all();
		$all[ $section ] = $value;
		return self::save( $all );
	}

	/**
	 * Write defaults on activation without clobbering an existing configuration.
	 */
	public static function install_defaults() {
		$stored = get_option( AIM_AVT_OPTION, null );
		if ( null === $stored || ! is_array( $stored ) ) {
			add_option( AIM_AVT_OPTION, self::defaults(), '', false );
		}
	}

	/**
	 * Reset everything to factory defaults. A snapshot is taken by the caller.
	 */
	public static function reset() {
		self::save( self::defaults() );
	}

	/**
	 * Recursive array merge where the stored value wins for scalars and lists,
	 * and associative arrays are merged key by key.
	 *
	 * @param array $defaults Defaults.
	 * @param array $stored   Stored.
	 * @return array
	 */
	public static function merge_deep( array $defaults, array $stored ) {
		$out = $defaults;
		foreach ( $stored as $key => $value ) {
			if ( isset( $out[ $key ] ) && is_array( $out[ $key ] ) && is_array( $value ) && self::is_assoc( $out[ $key ] ) && self::is_assoc( $value ) ) {
				$out[ $key ] = self::merge_deep( $out[ $key ], $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Is this an associative array (as opposed to a list)?
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	public static function is_assoc( array $arr ) {
		if ( array() === $arr ) {
			return false;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	/**
	 * Split a textarea into a clean list of trimmed, non-empty lines.
	 *
	 * @param string $text Raw textarea value.
	 * @return array
	 */
	public static function lines( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		$out   = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Should front-end output run for the current request?
	 *
	 * Honours the master switch, safe mode, and the ?aim_avt=off escape hatch
	 * that lets an administrator load the raw page for comparison.
	 *
	 * @return bool
	 */
	public static function output_active() {
		if ( ! self::get( 'master_enabled' ) ) {
			return false;
		}
		// Escape hatch: /any-page/?aim_avt=off renders the site as if the plugin
		// were switched off. Restricted to administrators so it cannot be used
		// to serve a different page to a crawler.
		if ( isset( $_GET['aim_avt'] ) && 'off' === sanitize_key( wp_unslash( $_GET['aim_avt'] ) ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
		if ( self::get( 'safe_mode' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return true;
	}
}
