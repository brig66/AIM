<?php
/**
 * Admin interface.
 *
 * Written for an operator who is not a developer: every screen says what the
 * change does, what it is worth, and how to put it back.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Admin {

	const CAP  = 'manage_options';
	const SLUG = 'aim-avt';

	/** @var array Notices queued for the current page load. */
	protected static $notices = array();

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_aim_avt_action', array( __CLASS__, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notices' ) );
		add_filter( 'plugin_action_links_' . AIM_AVT_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Dashboard', 'aim-avt' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=aim-avt-undo' ) ) . '">' . esc_html__( 'Undo', 'aim-avt' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register the menu.
	 */
	public static function menu() {
		add_menu_page(
			__( 'AI Visibility', 'aim-avt' ),
			__( 'AI Visibility', 'aim-avt' ),
			self::CAP,
			self::SLUG,
			array( __CLASS__, 'route' ),
			'dashicons-visibility',
			58
		);

		$pages = array(
			self::SLUG              => __( 'Dashboard', 'aim-avt' ),
			'aim-avt-phase1'        => __( 'Phase 1 — Technical', 'aim-avt' ),
			'aim-avt-organization'  => __( 'Organization', 'aim-avt' ),
			'aim-avt-locations'     => __( 'Locations', 'aim-avt' ),
			'aim-avt-people'        => __( 'People', 'aim-avt' ),
			'aim-avt-llms'          => __( 'llms.txt', 'aim-avt' ),
			'aim-avt-redirects'     => __( 'Redirects', 'aim-avt' ),
			'aim-avt-phase2'        => __( 'Phase 2 — Trust', 'aim-avt' ),
			'aim-avt-phase3'        => __( 'Phase 3 — Authority', 'aim-avt' ),
			'aim-avt-tools'         => __( 'Health Check', 'aim-avt' ),
			'aim-avt-undo'          => __( 'Undo & Restore', 'aim-avt' ),
		);

		foreach ( $pages as $slug => $title ) {
			add_submenu_page(
				self::SLUG,
				$title,
				$title,
				self::CAP,
				$slug,
				array( __CLASS__, 'route' )
			);
		}
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'aim-avt' ) && false === strpos( $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'aim-avt-admin', AIM_AVT_URL . 'assets/admin.css', array(), AIM_AVT_VERSION );
	}

	/**
	 * Render whichever screen was requested.
	 */
	public static function route() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'aim-avt' ) );
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$views = array(
			self::SLUG             => 'dashboard',
			'aim-avt-phase1'       => 'phase1',
			'aim-avt-organization' => 'organization',
			'aim-avt-locations'    => 'locations',
			'aim-avt-people'       => 'people',
			'aim-avt-llms'         => 'llms',
			'aim-avt-redirects'    => 'redirects',
			'aim-avt-phase2'       => 'phase2',
			'aim-avt-phase3'       => 'phase3',
			'aim-avt-tools'        => 'tools',
			'aim-avt-undo'         => 'undo',
		);

		$view = isset( $views[ $page ] ) ? $views[ $page ] : 'dashboard';
		$file = AIM_AVT_DIR . 'admin/views/' . $view . '.php';

		echo '<div class="wrap aim-avt">';
		self::header();
		if ( file_exists( $file ) ) {
			include $file;
		}
		echo '</div>';
	}

	/**
	 * Shared page header: master switch state and the panic button.
	 */
	public static function header() {
		$on   = (bool) AIM_AVT_Settings::get( 'master_enabled' );
		$safe = (bool) AIM_AVT_Settings::get( 'safe_mode' );

		echo '<div class="aim-avt-masthead">';
		echo '<div class="aim-avt-masthead__title">';
		echo '<h1>' . esc_html__( 'AIM AI Visibility Toolkit', 'aim-avt' ) . '</h1>';
		echo '<p class="aim-avt-masthead__sub">' . esc_html__( 'Implements the AI Visibility Checklist for sentrimax.com · rubric v3.1.0', 'aim-avt' ) . '</p>';
		echo '</div>';

		echo '<div class="aim-avt-masthead__state">';
		if ( $on && $safe ) {
			echo '<span class="aim-avt-pill aim-avt-pill--warn">' . esc_html__( 'Preview mode — changes visible to logged-in admins only', 'aim-avt' ) . '</span>';
		} elseif ( $on ) {
			echo '<span class="aim-avt-pill aim-avt-pill--on">' . esc_html__( 'Live on the public site', 'aim-avt' ) . '</span>';
		} else {
			echo '<span class="aim-avt-pill aim-avt-pill--off">' . esc_html__( 'Switched off — the site is unchanged', 'aim-avt' ) . '</span>';
		}

		if ( $on ) {
			echo self::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
				'master_off',
				__( 'Turn everything off now', 'aim-avt' ),
				'aim-avt-panic',
				__( 'Switch every front-end change off immediately? Your settings are kept — nothing is deleted.', 'aim-avt' )
			);
		}
		echo '</div></div>';
	}

	/**
	 * Render a small self-contained POST form for one action button.
	 *
	 * @param string $action  Action name.
	 * @param string $label   Button label.
	 * @param string $class   CSS class.
	 * @param string $confirm Optional confirm text.
	 * @param array  $fields  Extra hidden fields.
	 * @return string
	 */
	public static function button_form( $action, $label, $class = 'button', $confirm = '', array $fields = array() ) {
		$out  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aim-avt-inline-form">';
		$out .= '<input type="hidden" name="action" value="aim_avt_action" />';
		$out .= '<input type="hidden" name="aim_action" value="' . esc_attr( $action ) . '" />';
		$out .= '<input type="hidden" name="aim_return" value="' . esc_attr( self::current_page() ) . '" />';
		foreach ( $fields as $key => $value ) {
			$out .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
		}
		$out .= wp_nonce_field( 'aim_avt_' . $action, '_aim_nonce', true, false );
		$out .= '<button type="submit" class="' . esc_attr( $class ) . '"';
		if ( $confirm ) {
			$out .= ' onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"';
		}
		$out .= '>' . esc_html( $label ) . '</button>';
		$out .= '</form>';
		return $out;
	}

	/**
	 * Open a settings form.
	 *
	 * @param string $action Action name.
	 */
	public static function form_open( $action ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="aim-avt-form">';
		echo '<input type="hidden" name="action" value="aim_avt_action" />';
		echo '<input type="hidden" name="aim_action" value="' . esc_attr( $action ) . '" />';
		echo '<input type="hidden" name="aim_return" value="' . esc_attr( self::current_page() ) . '" />';
		wp_nonce_field( 'aim_avt_' . $action, '_aim_nonce' );
	}

	/**
	 * Close a settings form.
	 *
	 * @param string $label Submit label.
	 */
	public static function form_close( $label = '' ) {
		$label = $label ? $label : __( 'Save changes', 'aim-avt' );
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html( $label ) . '</button> ';
		echo '<span class="description">' . esc_html__( 'A restore point is saved automatically every time you save.', 'aim-avt' ) . '</span></p>';
		echo '</form>';
	}

	/**
	 * The current admin page slug.
	 *
	 * @return string
	 */
	public static function current_page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : self::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	// -------------------------------------------------------------------
	// Action handling.
	// -------------------------------------------------------------------

	/**
	 * Handle every admin-post action for the plugin.
	 */
	public static function handle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'aim-avt' ) );
		}

		$action = isset( $_POST['aim_action'] ) ? sanitize_key( wp_unslash( $_POST['aim_action'] ) ) : '';
		check_admin_referer( 'aim_avt_' . $action, '_aim_nonce' );

		$return = isset( $_POST['aim_return'] ) ? sanitize_key( wp_unslash( $_POST['aim_return'] ) ) : self::SLUG;
		$notice = '';
		$type   = 'success';

		switch ( $action ) {

			case 'master_on':
				AIM_AVT_Snapshots::create( 'settings', __( 'Before switching output on', 'aim-avt' ) );
				$all                   = AIM_AVT_Settings::all();
				$all['master_enabled'] = true;
				AIM_AVT_Settings::save( $all );
				AIM_AVT_Linkguard::flush_caches();
				$notice = __( 'Front-end output is on. Load the site in a private window and check a few pages.', 'aim-avt' );
				AIM_AVT_Log::add( 'master-on', $notice );
				break;

			case 'master_off':
				AIM_AVT_Snapshots::create( 'settings', __( 'Before switching output off', 'aim-avt' ) );
				$all                   = AIM_AVT_Settings::all();
				$all['master_enabled'] = false;
				AIM_AVT_Settings::save( $all );
				AIM_AVT_Linkguard::flush_caches();
				$notice = __( 'Everything is switched off. The site renders exactly as it did before the plugin, and your settings are kept.', 'aim-avt' );
				AIM_AVT_Log::add( 'master-off', $notice );
				break;

			case 'safe_mode':
				$all               = AIM_AVT_Settings::all();
				$all['safe_mode']  = ! $all['safe_mode'];
				AIM_AVT_Settings::save( $all );
				AIM_AVT_Linkguard::flush_caches();
				$notice = $all['safe_mode']
					? __( 'Preview mode is on. Only logged-in administrators see the changes.', 'aim-avt' )
					: __( 'Preview mode is off. Changes are live for everyone.', 'aim-avt' );
				break;

			case 'save_schema':
				$notice = self::save_schema();
				break;

			case 'save_locations':
				$notice = self::save_locations();
				break;

			case 'save_people':
				$notice = self::save_people();
				break;

			case 'save_headings':
				$notice = self::save_headings();
				break;

			case 'save_linkguard':
				$notice = self::save_linkguard();
				break;

			case 'save_llms':
				$notice = self::save_llms();
				break;

			case 'save_redirects':
				$notice = self::save_redirects();
				break;

			case 'save_byline':
				$notice = self::save_byline();
				break;

			case 'save_pricing':
				$notice = self::save_pricing();
				break;

			case 'scan_links':
				$report = AIM_AVT_Linkguard::scan();
				$db     = count( $report['posts'] ) + count( $report['comments'] );
				$ghost  = count( $report['rendered_only'] );
				$notice = sprintf(
					/* translators: 1: database hits, 2: rendered-only domains. */
					__( 'Scan finished. %1$d database item(s) carry foreign links; %2$d domain(s) render on the page but are nowhere in the database.', 'aim-avt' ),
					$db,
					$ghost
				);
				if ( $ghost ) {
					$type = 'warning';
				}
				break;

			case 'clean_links':
				$result = AIM_AVT_Linkguard::clean(
					array(
						'clean_posts'   => ! empty( $_POST['clean_posts'] ),
						'delete_spam'   => ! empty( $_POST['delete_spam'] ),
						'lock_comments' => ! empty( $_POST['lock_comments'] ),
					)
				);
				$notice = $result['message'] . ' ' . implode( ' ', $result['details'] );
				break;

			case 'write_llms':
				$result = AIM_AVT_LLMS::write_physical();
				$notice = $result['message'];
				$type   = $result['ok'] ? 'success' : 'error';
				break;

			case 'create_page':
				$key    = isset( $_POST['page_key'] ) ? sanitize_key( wp_unslash( $_POST['page_key'] ) ) : '';
				$result = AIM_AVT_Content::ensure_page( $key );
				$notice = $result['message'];
				$type   = $result['ok'] ? 'success' : 'error';
				break;

			case 'toggle_task':
				$task_id = isset( $_POST['task_id'] ) ? sanitize_text_field( wp_unslash( $_POST['task_id'] ) ) : '';
				$all     = AIM_AVT_Settings::all();
				$manual  = (array) $all['manual_progress'];
				if ( ! empty( $manual[ $task_id ] ) ) {
					unset( $manual[ $task_id ] );
					$notice = sprintf(
						/* translators: %s: task number. */
						__( 'Task %s is no longer marked complete.', 'aim-avt' ),
						$task_id
					);
				} else {
					$manual[ $task_id ] = time();
					$notice             = sprintf(
						/* translators: %s: task number. */
						__( 'Task %s marked complete.', 'aim-avt' ),
						$task_id
					);
				}
				$all['manual_progress'] = $manual;
				AIM_AVT_Settings::save( $all );
				break;

			case 'restore':
				$id     = isset( $_POST['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) ) : '';
				$result = AIM_AVT_Snapshots::restore( $id );
				AIM_AVT_Linkguard::flush_caches();
				$notice = $result['message'] . ' ' . implode( ' ', $result['details'] );
				$type   = $result['ok'] ? 'success' : 'error';
				break;

			case 'delete_snapshot':
				$id = isset( $_POST['snapshot_id'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot_id'] ) ) : '';
				AIM_AVT_Snapshots::delete( $id );
				$notice = __( 'Restore point deleted.', 'aim-avt' );
				break;

			case 'reset_all':
				AIM_AVT_Snapshots::create( 'settings', __( 'Before resetting every setting to defaults', 'aim-avt' ) );
				AIM_AVT_Settings::reset();
				AIM_AVT_Linkguard::flush_caches();
				$notice = __( 'Every setting is back to the values the plugin shipped with, and output is off. A restore point was saved first.', 'aim-avt' );
				break;

			case 'clear_log':
				AIM_AVT_Log::clear();
				$notice = __( 'Activity log cleared.', 'aim-avt' );
				break;

			case 'health_check':
				$notice = self::run_health_check();
				break;

			default:
				$notice = __( 'Unrecognized action.', 'aim-avt' );
				$type   = 'error';
		}

		set_transient( 'aim_avt_notice_' . get_current_user_id(), array( 'type' => $type, 'text' => $notice ), 60 );

		wp_safe_redirect( admin_url( 'admin.php?page=' . $return ) );
		exit;
	}

	/**
	 * Print any queued notice.
	 */
	public static function render_notices() {
		$notice = get_transient( 'aim_avt_notice_' . get_current_user_id() );
		if ( ! $notice || empty( $notice['text'] ) ) {
			return;
		}
		delete_transient( 'aim_avt_notice_' . get_current_user_id() );

		$class = 'notice notice-' . ( in_array( $notice['type'], array( 'success', 'warning', 'error', 'info' ), true ) ? $notice['type'] : 'info' );
		echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $notice['text'] ) . '</p></div>';
	}

	// -------------------------------------------------------------------
	// Save routines.
	// -------------------------------------------------------------------

	/**
	 * Snapshot then return the settings array ready to modify.
	 *
	 * @param string $label Snapshot label.
	 * @return array
	 */
	protected static function begin_save( $label ) {
		AIM_AVT_Snapshots::create( 'settings', $label );
		return AIM_AVT_Settings::all();
	}

	/**
	 * Read a POST field as sanitized text.
	 *
	 * @param string $key     Field name.
	 * @param string $default Default.
	 * @return string
	 */
	protected static function post_text( $key, $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $default;
		}
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Read a POST field as a sanitized multi-line string.
	 *
	 * @param string $key Field name.
	 * @return string
	 */
	protected static function post_area( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return '';
		}
		return sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Read a checkbox.
	 *
	 * @param string $key Field name.
	 * @return bool
	 */
	protected static function post_bool( $key ) {
		return ! empty( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save the Organization screen.
	 *
	 * @return string
	 */
	protected static function save_schema() {
		$all = self::begin_save( __( 'Before saving the Organization settings', 'aim-avt' ) );

		$all['schema']['enabled']          = self::post_bool( 'schema_enabled' );
		$all['schema']['mode']             = in_array( self::post_text( 'schema_mode' ), array( 'auto', 'merge', 'standalone' ), true ) ? self::post_text( 'schema_mode' ) : 'auto';
		$all['schema']['org_name']         = self::post_text( 'org_name' );
		$all['schema']['org_legal_name']   = self::post_text( 'org_legal_name' );
		$all['schema']['org_url']          = esc_url_raw( self::post_text( 'org_url' ) );
		$all['schema']['org_logo']         = esc_url_raw( self::post_text( 'org_logo' ) );
		$all['schema']['org_email']        = sanitize_email( self::post_text( 'org_email' ) );
		$all['schema']['org_phone']        = self::post_text( 'org_phone' );
		$all['schema']['org_description']  = self::post_area( 'org_description' );
		$all['schema']['founding_date']    = self::post_text( 'founding_date' );
		$all['schema']['iso_cert']         = self::post_text( 'iso_cert' );
		$all['schema']['iso_registrar']    = self::post_text( 'iso_registrar' );
		$all['schema']['iso_registrar_url']= esc_url_raw( self::post_text( 'iso_registrar_url' ) );

		$same_as = array();
		foreach ( AIM_AVT_Settings::lines( self::post_area( 'same_as' ) ) as $url ) {
			$clean = esc_url_raw( $url );
			if ( $clean ) {
				$same_as[] = $clean;
			}
		}
		$all['schema']['same_as'] = $same_as;

		$all['schema']['aggregate_rating']['enabled']     = self::post_bool( 'rating_enabled' );
		$all['schema']['aggregate_rating']['min_reviews'] = max( 1, (int) self::post_text( 'rating_min', '3' ) );

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return __( 'Organization settings saved.', 'aim-avt' );
	}

	/**
	 * Save the Locations screen.
	 *
	 * @return string
	 */
	protected static function save_locations() {
		$all  = self::begin_save( __( 'Before saving the facility addresses', 'aim-avt' ) );
		$rows = isset( $_POST['loc'] ) && is_array( $_POST['loc'] ) ? wp_unslash( $_POST['loc'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		$head_choice = self::post_text( 'head_office' );
		$locations   = array();

		foreach ( $rows as $index => $row ) {
			$name   = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			$street = isset( $row['street'] ) ? sanitize_text_field( $row['street'] ) : '';
			if ( '' === $name && '' === $street ) {
				continue; // Blank row.
			}

			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( '' === $key ) {
				$key = sanitize_key( isset( $row['locality'] ) ? $row['locality'] : 'location-' . $index );
			}

			$locations[] = array(
				'key'           => $key,
				'name'          => $name,
				'head_office'   => ( (string) $head_choice === (string) $key ),
				'street'        => $street,
				'locality'      => isset( $row['locality'] ) ? sanitize_text_field( $row['locality'] ) : '',
				'region'        => isset( $row['region'] ) ? sanitize_text_field( $row['region'] ) : '',
				'postal_code'   => isset( $row['postal_code'] ) ? sanitize_text_field( $row['postal_code'] ) : '',
				'country'       => isset( $row['country'] ) ? sanitize_text_field( $row['country'] ) : '',
				'phone'         => isset( $row['phone'] ) ? sanitize_text_field( $row['phone'] ) : '',
				'email'         => isset( $row['email'] ) ? sanitize_email( $row['email'] ) : '',
				'opening_hours' => isset( $row['opening_hours'] ) ? sanitize_text_field( $row['opening_hours'] ) : '',
				'area_served'   => isset( $row['area_served'] ) ? sanitize_text_field( $row['area_served'] ) : '',
				'latitude'      => isset( $row['latitude'] ) ? sanitize_text_field( $row['latitude'] ) : '',
				'longitude'     => isset( $row['longitude'] ) ? sanitize_text_field( $row['longitude'] ) : '',
				'gbp_url'       => isset( $row['gbp_url'] ) ? esc_url_raw( $row['gbp_url'] ) : '',
				'place_id'      => isset( $row['place_id'] ) ? sanitize_text_field( $row['place_id'] ) : '',
			);
		}

		// Guarantee exactly one head office.
		$has_head = false;
		foreach ( $locations as $location ) {
			if ( $location['head_office'] ) {
				$has_head = true;
				break;
			}
		}
		if ( ! $has_head && $locations ) {
			$locations[0]['head_office'] = true;
		}

		$all['schema']['locations'] = $locations;
		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return sprintf(
			/* translators: %d: number of facilities saved. */
			__( 'Saved %d facility address(es).', 'aim-avt' ),
			count( $locations )
		);
	}

	/**
	 * Save the People screen.
	 *
	 * @return string
	 */
	protected static function save_people() {
		$all  = self::begin_save( __( 'Before saving the leadership profiles', 'aim-avt' ) );
		$rows = isset( $_POST['person'] ) && is_array( $_POST['person'] ) ? wp_unslash( $_POST['person'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		$default = self::post_text( 'default_author' );
		$people  = array();

		foreach ( $rows as $index => $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$key = isset( $row['key'] ) && $row['key'] ? sanitize_key( $row['key'] ) : sanitize_title( $name );

			$people[] = array(
				'key'               => $key,
				'name'              => $name,
				'job_title'         => isset( $row['job_title'] ) ? sanitize_text_field( $row['job_title'] ) : '',
				'description'       => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
				'image'             => isset( $row['image'] ) ? esc_url_raw( $row['image'] ) : '',
				'linkedin'          => isset( $row['linkedin'] ) ? esc_url_raw( $row['linkedin'] ) : '',
				'knows_about'       => isset( $row['knows_about'] ) ? sanitize_textarea_field( $row['knows_about'] ) : '',
				'is_default_author' => ( (string) $default === (string) $key ),
			);
		}

		if ( $people ) {
			$has_default = false;
			foreach ( $people as $person ) {
				if ( $person['is_default_author'] ) {
					$has_default = true;
					break;
				}
			}
			if ( ! $has_default ) {
				$people[0]['is_default_author'] = true;
			}
		}

		$all['schema']['people'] = $people;
		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return sprintf(
			/* translators: %d: number of people saved. */
			__( 'Saved %d leadership profile(s).', 'aim-avt' ),
			count( $people )
		);
	}

	/**
	 * Save the heading settings.
	 *
	 * @return string
	 */
	protected static function save_headings() {
		$all = self::begin_save( __( 'Before saving the heading settings', 'aim-avt' ) );

		$all['headings']['h1_enabled']        = self::post_bool( 'h1_enabled' );
		$all['headings']['h1_mode']           = 'insert' === self::post_text( 'h1_mode' ) ? 'insert' : 'promote';
		$all['headings']['h1_class']          = sanitize_html_class( self::post_text( 'h1_class', 'aim-avt-h1' ) );
		$all['headings']['h1_home_text']      = self::post_text( 'h1_home_text' );
		$all['headings']['h1_overrides']      = self::post_area( 'h1_overrides' );
		$all['headings']['h1_exclusions']     = self::post_area( 'h1_exclusions' );
		$all['headings']['hierarchy_enabled'] = self::post_bool( 'hierarchy_enabled' );
		$all['headings']['hierarchy_mode']    = 'fix' === self::post_text( 'hierarchy_mode' ) ? 'fix' : 'report';

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return __( 'Heading settings saved.', 'aim-avt' );
	}

	/**
	 * Save the link guard settings.
	 *
	 * @return string
	 */
	protected static function save_linkguard() {
		$all = self::begin_save( __( 'Before saving the link guard settings', 'aim-avt' ) );

		$all['linkguard']['live_enabled'] = self::post_bool( 'live_enabled' );
		$all['linkguard']['scope']        = self::post_area( 'scope' );
		$all['linkguard']['allowlist']    = self::post_area( 'allowlist' );
		$all['linkguard']['strip_mode']   = 'remove' === self::post_text( 'strip_mode' ) ? 'remove' : 'unlink';

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return __( 'Link guard settings saved.', 'aim-avt' );
	}

	/**
	 * Save the llms.txt settings.
	 *
	 * @return string
	 */
	protected static function save_llms() {
		$all = self::begin_save( __( 'Before saving the llms.txt settings', 'aim-avt' ) );

		$all['llms']['enabled']      = self::post_bool( 'llms_enabled' );
		$all['llms']['mode']         = 'custom' === self::post_text( 'llms_mode' ) ? 'custom' : 'generate';
		$all['llms']['summary']      = self::post_area( 'llms_summary' );
		$all['llms']['custom_body']  = self::post_area( 'llms_custom_body' );
		$all['llms']['lang_mirrors'] = 'kept' === self::post_text( 'lang_mirrors' ) ? 'kept' : 'removed';
		$all['llms']['max_links_per_section'] = max( 1, (int) self::post_text( 'max_links', '25' ) );

		$sections = isset( $_POST['llms_sections'] ) && is_array( $_POST['llms_sections'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['llms_sections'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$all['llms']['include_sections'] = $sections;

		AIM_AVT_Settings::save( $all );

		return __( 'llms.txt settings saved. Use "Write the file to disk" to replace the published file.', 'aim-avt' );
	}

	/**
	 * Save the redirect rules.
	 *
	 * @return string
	 */
	protected static function save_redirects() {
		$all  = self::begin_save( __( 'Before saving the redirect rules', 'aim-avt' ) );
		$rows = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		$rules = array();
		foreach ( $rows as $row ) {
			$from = isset( $row['from'] ) ? sanitize_text_field( $row['from'] ) : '';
			$to   = isset( $row['to'] ) ? sanitize_text_field( $row['to'] ) : '';
			if ( '' === $from || '' === $to ) {
				continue;
			}
			$code = isset( $row['code'] ) ? (int) $row['code'] : 301;
			$rules[] = array(
				'from' => $from,
				'to'   => $to,
				'code' => in_array( $code, array( 301, 302, 307, 308 ), true ) ? $code : 301,
				'note' => isset( $row['note'] ) ? sanitize_text_field( $row['note'] ) : '',
			);
		}

		$all['redirects']['enabled'] = self::post_bool( 'redirects_enabled' );
		$all['redirects']['rules']   = $rules;

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return sprintf(
			/* translators: %d: number of rules. */
			__( 'Saved %d redirect rule(s).', 'aim-avt' ),
			count( $rules )
		);
	}

	/**
	 * Save the byline settings.
	 *
	 * @return string
	 */
	protected static function save_byline() {
		$all = self::begin_save( __( 'Before saving the author attribution settings', 'aim-avt' ) );

		$all['byline']['enabled']      = self::post_bool( 'byline_enabled' );
		$all['byline']['show_visible'] = self::post_bool( 'byline_visible' );
		$all['byline']['label']        = self::post_text( 'byline_label', 'By' );

		$types = isset( $_POST['byline_types'] ) && is_array( $_POST['byline_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['byline_types'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$all['byline']['post_types'] = $types;

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return __( 'Author attribution settings saved.', 'aim-avt' );
	}

	/**
	 * Save the pricing bands.
	 *
	 * @return string
	 */
	protected static function save_pricing() {
		$all  = self::begin_save( __( 'Before saving the pricing bands', 'aim-avt' ) );
		$rows = isset( $_POST['band'] ) && is_array( $_POST['band'] ) ? wp_unslash( $_POST['band'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		$bands = array();
		foreach ( $rows as $row ) {
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$bands[] = array(
				'name'        => $name,
				'min'         => isset( $row['min'] ) ? preg_replace( '/[^0-9.]/', '', $row['min'] ) : '',
				'max'         => isset( $row['max'] ) ? preg_replace( '/[^0-9.]/', '', $row['max'] ) : '',
				'turnaround'  => isset( $row['turnaround'] ) ? sanitize_text_field( $row['turnaround'] ) : '',
				'assumptions' => isset( $row['assumptions'] ) ? sanitize_text_field( $row['assumptions'] ) : '',
			);
		}

		$all['pricing']['enabled']  = self::post_bool( 'pricing_enabled' );
		$all['pricing']['currency'] = in_array( self::post_text( 'currency' ), array( 'USD', 'CAD' ), true ) ? self::post_text( 'currency' ) : 'USD';
		$all['pricing']['as_of']    = self::post_text( 'as_of' );
		$all['pricing']['note']     = self::post_area( 'pricing_note' );
		$all['pricing']['bands']    = $bands;

		AIM_AVT_Settings::save( $all );
		AIM_AVT_Linkguard::flush_caches();

		return sprintf(
			/* translators: %d: number of bands. */
			__( 'Saved %d pricing band(s).', 'aim-avt' ),
			count( $bands )
		);
	}

	/**
	 * Fetch a few representative pages and record what they now contain.
	 *
	 * @return string
	 */
	protected static function run_health_check() {
		$urls = array( home_url( '/' ) );

		foreach ( array( 'about', 'about-us', 'contact', 'news', 'equipment' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page ) {
				$urls[] = get_permalink( $page );
			}
		}
		$urls = array_slice( array_unique( $urls ), 0, 6 );

		$results = array();
		foreach ( $urls as $url ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'    => 25,
					'sslverify'  => false,
					'user-agent' => 'AIM-AVT-Health/' . AIM_AVT_VERSION,
				)
			);
			if ( is_wp_error( $response ) ) {
				$results[ $url ] = array( 'error' => $response->get_error_message() );
				continue;
			}
			$results[ $url ] = AIM_AVT_Output::summarize( wp_remote_retrieve_body( $response ) );
			$results[ $url ]['status'] = (int) wp_remote_retrieve_response_code( $response );
		}

		update_option(
			'aim_avt_health',
			array(
				'ran_at'  => time(),
				'results' => $results,
			),
			false
		);

		return sprintf(
			/* translators: %d: number of pages checked. */
			__( 'Health check finished across %d page(s).', 'aim-avt' ),
			count( $results )
		);
	}
}
