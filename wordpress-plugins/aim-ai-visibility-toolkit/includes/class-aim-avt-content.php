<?php
/**
 * Content types for the proof assets the assessment found missing.
 *
 * Task 2.1 — testimonials with Review markup and a substantiated aggregateRating.
 * Task 3.1 — case studies with Article schema and a named author.
 * Task 3.4 — a press and recognition page listing genuine third-party coverage.
 *
 * The plugin supplies the structure, the markup and the pages. The words have
 * to come from Sentrimax; nothing here invents a customer, a quote or a result.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Content {

	const TESTIMONIAL = 'aim_testimonial';
	const CASE_STUDY  = 'aim_case_study';
	const PRESS_ITEM  = 'aim_press_item';

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::TESTIMONIAL . '_posts_columns', array( __CLASS__, 'testimonial_columns' ) );
		add_action( 'manage_' . self::TESTIMONIAL . '_posts_custom_column', array( __CLASS__, 'testimonial_column' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'case_study_schema' ), 5 );
	}

	/**
	 * Register the three post types.
	 */
	public static function register_post_types() {
		register_post_type(
			self::TESTIMONIAL,
			array(
				'labels'       => array(
					'name'               => __( 'Testimonials', 'aim-avt' ),
					'singular_name'      => __( 'Testimonial', 'aim-avt' ),
					'add_new_item'       => __( 'Add Testimonial', 'aim-avt' ),
					'edit_item'          => __( 'Edit Testimonial', 'aim-avt' ),
					'menu_name'          => __( 'Testimonials', 'aim-avt' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'aim-avt',
				'supports'     => array( 'title', 'editor' ),
				'menu_icon'    => 'dashicons-format-quote',
				'has_archive'  => false,
				'rewrite'      => false,
			)
		);

		register_post_type(
			self::CASE_STUDY,
			array(
				'labels'       => array(
					'name'          => __( 'Case Studies', 'aim-avt' ),
					'singular_name' => __( 'Case Study', 'aim-avt' ),
					'add_new_item'  => __( 'Add Case Study', 'aim-avt' ),
					'edit_item'     => __( 'Edit Case Study', 'aim-avt' ),
					'menu_name'     => __( 'Case Studies', 'aim-avt' ),
				),
				'public'       => true,
				'show_in_menu' => 'aim-avt',
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'menu_icon'    => 'dashicons-analytics',
				'has_archive'  => 'case-studies',
				'rewrite'      => array( 'slug' => 'case-studies', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);

		register_post_type(
			self::PRESS_ITEM,
			array(
				'labels'       => array(
					'name'          => __( 'Press & Recognition', 'aim-avt' ),
					'singular_name' => __( 'Press Item', 'aim-avt' ),
					'add_new_item'  => __( 'Add Press Item', 'aim-avt' ),
					'edit_item'     => __( 'Edit Press Item', 'aim-avt' ),
					'menu_name'     => __( 'Press & Recognition', 'aim-avt' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'aim-avt',
				'supports'     => array( 'title', 'editor' ),
				'menu_icon'    => 'dashicons-awards',
				'has_archive'  => false,
				'rewrite'      => false,
			)
		);
	}

	/**
	 * Field definitions per post type.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function fields( $post_type ) {
		switch ( $post_type ) {
			case self::TESTIMONIAL:
				return array(
					'author_name' => array( 'label' => __( 'Customer name', 'aim-avt' ), 'type' => 'text', 'help' => __( 'Leave blank if the customer cannot be named — fill in the company characterization instead.', 'aim-avt' ) ),
					'company'     => array( 'label' => __( 'Company (or characterization)', 'aim-avt' ), 'type' => 'text', 'help' => __( 'For example: Acme Rendering, or "a Gulf Coast rendering plant".', 'aim-avt' ) ),
					'role'        => array( 'label' => __( 'Job title', 'aim-avt' ), 'type' => 'text', 'help' => '' ),
					'machine'     => array( 'label' => __( 'Machine make and model', 'aim-avt' ), 'type' => 'text', 'help' => __( 'For example: Alfa Laval NX 4550.', 'aim-avt' ) ),
					'work_done'   => array( 'label' => __( 'Work performed', 'aim-avt' ), 'type' => 'text', 'help' => '' ),
					'rating'      => array( 'label' => __( 'Rating out of 5', 'aim-avt' ), 'type' => 'number', 'help' => __( 'Only fill this in if the customer actually gave a rating. A rating you invented is a policy violation and engines cross-check it.', 'aim-avt' ) ),
				);

			case self::CASE_STUDY:
				return array(
					'industry'   => array( 'label' => __( 'Industry', 'aim-avt' ), 'type' => 'text', 'help' => __( 'For example: municipal wastewater, oilfield solids control, rendering.', 'aim-avt' ) ),
					'machine'    => array( 'label' => __( 'Machine (make, model, bowl diameter)', 'aim-avt' ), 'type' => 'text', 'help' => '' ),
					'symptom'    => array( 'label' => __( 'Symptom and diagnosis', 'aim-avt' ), 'type' => 'textarea', 'help' => '' ),
					'work_done'  => array( 'label' => __( 'Work performed', 'aim-avt' ), 'type' => 'textarea', 'help' => '' ),
					'turnaround' => array( 'label' => __( 'Turnaround, arrival to release', 'aim-avt' ), 'type' => 'text', 'help' => __( 'For example: 11 days.', 'aim-avt' ) ),
					'result'     => array( 'label' => __( 'Measured result', 'aim-avt' ), 'type' => 'textarea', 'help' => __( 'Vibration before and after, cake dryness, throughput, hours returned to service. A number is what makes this citable.', 'aim-avt' ) ),
					'permission' => array( 'label' => __( 'Written customer permission on file?', 'aim-avt' ), 'type' => 'checkbox', 'help' => __( 'Publish only when this is ticked.', 'aim-avt' ) ),
				);

			case self::PRESS_ITEM:
				return array(
					'outlet'    => array( 'label' => __( 'Outlet or organization', 'aim-avt' ), 'type' => 'text', 'help' => '' ),
					'url'       => array( 'label' => __( 'Link to the coverage', 'aim-avt' ), 'type' => 'url', 'help' => __( 'Must be a live third-party page.', 'aim-avt' ) ),
					'date'      => array( 'label' => __( 'Date', 'aim-avt' ), 'type' => 'date', 'help' => '' ),
					'item_type' => array(
						'label'   => __( 'Type', 'aim-avt' ),
						'type'    => 'select',
						'help'    => '',
						'options' => array(
							'coverage'     => __( 'Press coverage', 'aim-avt' ),
							'byline'       => __( 'Byline or contributed article', 'aim-avt' ),
							'certification'=> __( 'Certification', 'aim-avt' ),
							'membership'   => __( 'Association membership', 'aim-avt' ),
							'partner'      => __( 'OEM or distributor partner page', 'aim-avt' ),
							'directory'    => __( 'Industry directory listing', 'aim-avt' ),
						),
					),
				);
		}

		return array();
	}

	/**
	 * Register the meta boxes.
	 */
	public static function add_meta_boxes() {
		foreach ( array( self::TESTIMONIAL, self::CASE_STUDY, self::PRESS_ITEM ) as $post_type ) {
			add_meta_box(
				'aim_avt_fields',
				__( 'AI Visibility details', 'aim-avt' ),
				array( __CLASS__, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render a meta box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render_meta_box( $post ) {
		$fields = self::fields( $post->post_type );
		if ( ! $fields ) {
			return;
		}
		wp_nonce_field( 'aim_avt_save_meta', 'aim_avt_meta_nonce' );

		echo '<table class="form-table aim-avt-meta"><tbody>';
		foreach ( $fields as $key => $field ) {
			$value = get_post_meta( $post->ID, '_aim_avt_' . $key, true );
			echo '<tr><th scope="row"><label for="aim_avt_' . esc_attr( $key ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';

			switch ( $field['type'] ) {
				case 'textarea':
					echo '<textarea class="large-text" rows="3" id="aim_avt_' . esc_attr( $key ) . '" name="aim_avt_' . esc_attr( $key ) . '">' . esc_textarea( $value ) . '</textarea>';
					break;
				case 'checkbox':
					echo '<label><input type="checkbox" id="aim_avt_' . esc_attr( $key ) . '" name="aim_avt_' . esc_attr( $key ) . '" value="1" ' . checked( $value, '1', false ) . ' /> ' . esc_html__( 'Yes', 'aim-avt' ) . '</label>';
					break;
				case 'select':
					echo '<select id="aim_avt_' . esc_attr( $key ) . '" name="aim_avt_' . esc_attr( $key ) . '">';
					foreach ( $field['options'] as $option_value => $label ) {
						echo '<option value="' . esc_attr( $option_value ) . '" ' . selected( $value, $option_value, false ) . '>' . esc_html( $label ) . '</option>';
					}
					echo '</select>';
					break;
				case 'number':
					echo '<input type="number" step="0.1" min="1" max="5" class="small-text" id="aim_avt_' . esc_attr( $key ) . '" name="aim_avt_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
					break;
				default:
					echo '<input type="' . esc_attr( $field['type'] ) . '" class="regular-text" id="aim_avt_' . esc_attr( $key ) . '" name="aim_avt_' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}

			if ( ! empty( $field['help'] ) ) {
				echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Persist meta box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['aim_avt_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aim_avt_meta_nonce'] ) ), 'aim_avt_save_meta' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = self::fields( $post->post_type );
		foreach ( $fields as $key => $field ) {
			$name = 'aim_avt_' . $key;

			if ( 'checkbox' === $field['type'] ) {
				update_post_meta( $post_id, '_aim_avt_' . $key, isset( $_POST[ $name ] ) ? '1' : '' );
				continue;
			}
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}

			$raw = wp_unslash( $_POST[ $name ] );

			switch ( $field['type'] ) {
				case 'url':
					$value = esc_url_raw( $raw );
					break;
				case 'number':
					$value = '' === trim( $raw ) ? '' : max( 1, min( 5, (float) $raw ) );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $raw );
					break;
				default:
					$value = sanitize_text_field( $raw );
			}

			update_post_meta( $post_id, '_aim_avt_' . $key, $value );
		}
	}

	/**
	 * Extra list-table columns for testimonials.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function testimonial_columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aim_company'] = __( 'Company', 'aim-avt' );
				$new['aim_rating']  = __( 'Rating', 'aim-avt' );
				$new['aim_ready']   = __( 'Counts for schema', 'aim-avt' );
			}
		}
		return $new;
	}

	/**
	 * Render an extra column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function testimonial_column( $column, $post_id ) {
		switch ( $column ) {
			case 'aim_company':
				echo esc_html( get_post_meta( $post_id, '_aim_avt_company', true ) );
				break;
			case 'aim_rating':
				$rating = get_post_meta( $post_id, '_aim_avt_rating', true );
				echo $rating ? esc_html( $rating . ' / 5' ) : '—';
				break;
			case 'aim_ready':
				$post    = get_post( $post_id );
				$author  = get_post_meta( $post_id, '_aim_avt_author_name', true );
				$company = get_post_meta( $post_id, '_aim_avt_company', true );
				$ok      = $post && 'publish' === $post->post_status && trim( wp_strip_all_tags( $post->post_content ) ) && ( $author || $company );
				echo $ok
					? '<span style="color:#1a7f37;font-weight:600;">' . esc_html__( 'Yes', 'aim-avt' ) . '</span>'
					: '<span style="color:#b32d2e;">' . esc_html__( 'Not yet', 'aim-avt' ) . '</span>';
				break;
		}
	}

	/**
	 * Published testimonials.
	 *
	 * @return WP_Post[]
	 */
	public static function testimonials() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = get_posts(
			array(
				'post_type'      => self::TESTIMONIAL,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'menu_order date',
				'order'          => 'DESC',
			)
		);
		return $cache;
	}

	/**
	 * Published press items.
	 *
	 * @return WP_Post[]
	 */
	public static function press_items() {
		return get_posts(
			array(
				'post_type'      => self::PRESS_ITEM,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Published case studies.
	 *
	 * @return WP_Post[]
	 */
	public static function case_studies() {
		return get_posts(
			array(
				'post_type'      => self::CASE_STUDY,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
	}

	/**
	 * Is the current request the page that renders the testimonials?
	 *
	 * @return bool
	 */
	public static function is_testimonials_page() {
		$page_id = (int) get_option( 'aim_avt_testimonials_page', 0 );
		if ( $page_id && is_page( $page_id ) ) {
			return true;
		}
		// Any page that uses the shortcode also counts.
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'aim_testimonials' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Article schema for a case study — Task 3.1.
	 */
	public static function case_study_schema() {
		if ( ! AIM_AVT_Settings::output_active() || ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return;
		}
		if ( ! is_singular( self::CASE_STUDY ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$node = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'Article',
			'@id'           => get_permalink( $post ) . '#article',
			'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
		);

		$excerpt = get_the_excerpt( $post );
		if ( $excerpt ) {
			$node['description'] = wp_strip_all_tags( $excerpt );
		}
		if ( has_post_thumbnail( $post ) ) {
			$node['image'] = get_the_post_thumbnail_url( $post, 'full' );
		}

		$author_id = AIM_AVT_Schema::default_author_id();
		$override  = get_post_meta( $post->ID, '_aim_avt_person_id', true );
		if ( $override ) {
			$author_id = $override;
		}
		if ( $author_id ) {
			$node['author'] = array( '@id' => $author_id );
		} else {
			$node['author'] = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			);
		}

		$node['publisher'] = array( '@id' => AIM_AVT_Schema::org_reference() );

		$encoded = AIM_AVT_Schema::encode( $node );
		if ( false === $encoded ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" data-aim-avt="case-study">' . $encoded . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapingOutput
	}

	/**
	 * Create one of the plugin's supporting pages if it does not exist.
	 *
	 * @param string $key       "testimonials", "press" or "case-studies".
	 * @return array{ok:bool,message:string,page_id:int}
	 */
	public static function ensure_page( $key ) {
		$specs = array(
			'testimonials' => array(
				'option'    => 'aim_avt_testimonials_page',
				'title'     => __( 'Customer Testimonials', 'aim-avt' ),
				'slug'      => 'testimonials',
				'content'   => "[aim_testimonials]",
			),
			'press' => array(
				'option'  => 'aim_avt_press_page',
				'title'   => __( 'Press & Recognition', 'aim-avt' ),
				'slug'    => 'press',
				'content' => "[aim_press]",
			),
		);

		if ( ! isset( $specs[ $key ] ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Unknown page.', 'aim-avt' ),
				'page_id' => 0,
			);
		}

		$spec     = $specs[ $key ];
		$existing = (int) get_option( $spec['option'], 0 );

		if ( $existing && get_post( $existing ) && 'trash' !== get_post_status( $existing ) ) {
			return array(
				'ok'      => true,
				'message' => __( 'That page already exists.', 'aim-avt' ),
				'page_id' => $existing,
			);
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $spec['title'],
				'post_name'    => $spec['slug'],
				'post_content' => $spec['content'],
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return array(
				'ok'      => false,
				'message' => $page_id->get_error_message(),
				'page_id' => 0,
			);
		}

		update_option( $spec['option'], (int) $page_id, false );

		AIM_AVT_Log::add(
			'page-created',
			sprintf(
				/* translators: %s: page title. */
				__( 'Created the "%s" page as a draft.', 'aim-avt' ),
				$spec['title']
			)
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %s: page title. */
				__( 'Created "%s" as a draft. Review it, then publish when you are happy with it.', 'aim-avt' ),
				$spec['title']
			),
			'page_id' => (int) $page_id,
		);
	}
}
