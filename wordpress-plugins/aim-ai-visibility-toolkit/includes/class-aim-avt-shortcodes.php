<?php
/**
 * Front-end shortcodes.
 *
 * Each of these renders content a human can read AND the markup that describes
 * it. That pairing is deliberate: rating markup has to be substantiated by
 * visible reviews, and priced Offer markup has to be substantiated by visible
 * prices, or the rule does not score and the markup is a policy problem.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Shortcodes {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_shortcode( 'aim_testimonials', array( __CLASS__, 'testimonials' ) );
		add_shortcode( 'aim_press', array( __CLASS__, 'press' ) );
		add_shortcode( 'aim_case_studies', array( __CLASS__, 'case_studies' ) );
		add_shortcode( 'aim_pricing', array( __CLASS__, 'pricing' ) );
		add_shortcode( 'aim_map', array( __CLASS__, 'map' ) );
		add_shortcode( 'aim_locations', array( __CLASS__, 'locations' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_styles' ) );
	}

	/**
	 * Register the small front-end stylesheet.
	 */
	public static function register_styles() {
		wp_register_style( 'aim-avt', AIM_AVT_URL . 'assets/front.css', array(), AIM_AVT_VERSION );
	}

	/**
	 * Testimonials — Task 2.1.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function testimonials( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'   => 50,
				'heading' => '',
			),
			$atts,
			'aim_testimonials'
		);

		$items = AIM_AVT_Content::testimonials();
		if ( ! $items ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'No testimonials have been published yet. Add them under AI Visibility → Testimonials. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_style( 'aim-avt' );

		$items = array_slice( $items, 0, max( 1, (int) $atts['limit'] ) );

		$out = '<div class="aim-avt-testimonials">';
		if ( $atts['heading'] ) {
			$out .= '<h2 class="aim-avt-testimonials__heading">' . esc_html( $atts['heading'] ) . '</h2>';
		}

		foreach ( $items as $post ) {
			$author  = get_post_meta( $post->ID, '_aim_avt_author_name', true );
			$company = get_post_meta( $post->ID, '_aim_avt_company', true );
			$role    = get_post_meta( $post->ID, '_aim_avt_role', true );
			$machine = get_post_meta( $post->ID, '_aim_avt_machine', true );
			$work    = get_post_meta( $post->ID, '_aim_avt_work_done', true );
			$rating  = (float) get_post_meta( $post->ID, '_aim_avt_rating', true );

			$out .= '<figure class="aim-avt-testimonial">';
			$out .= '<blockquote class="aim-avt-testimonial__quote">' . wp_kses_post( wpautop( $post->post_content ) ) . '</blockquote>';
			$out .= '<figcaption class="aim-avt-testimonial__meta">';

			$who = array_filter( array( $author, $role, $company ) );
			if ( $who ) {
				$out .= '<span class="aim-avt-testimonial__who">' . esc_html( implode( ', ', $who ) ) . '</span>';
			}

			$detail = array_filter( array( $machine, $work ) );
			if ( $detail ) {
				$out .= '<span class="aim-avt-testimonial__detail">' . esc_html( implode( ' · ', $detail ) ) . '</span>';
			}

			if ( $rating > 0 ) {
				$out .= '<span class="aim-avt-testimonial__rating" aria-label="' . esc_attr(
					sprintf(
						/* translators: %s: rating out of five. */
						__( 'Rated %s out of 5', 'aim-avt' ),
						$rating
					)
				) . '">' . esc_html( sprintf( '%s / 5', $rating ) ) . '</span>';
			}

			$out .= '</figcaption></figure>';
		}

		$rating_node = AIM_AVT_Schema::aggregate_rating();
		if ( $rating_node ) {
			$out .= '<p class="aim-avt-testimonials__aggregate">' . esc_html(
				sprintf(
					/* translators: 1: average rating, 2: number of reviews. */
					__( 'Average rating %1$s out of 5 from %2$s published customer reviews.', 'aim-avt' ),
					$rating_node['ratingValue'],
					$rating_node['reviewCount']
				)
			) . '</p>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Press and recognition — Task 3.4.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function press( $atts ) {
		$items = AIM_AVT_Content::press_items();
		if ( ! $items ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'No press items have been published yet. Add them under AI Visibility → Press & Recognition. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_style( 'aim-avt' );

		$labels = AIM_AVT_Content::fields( AIM_AVT_Content::PRESS_ITEM )['item_type']['options'];
		$groups = array();

		foreach ( $items as $post ) {
			$type = get_post_meta( $post->ID, '_aim_avt_item_type', true );
			$type = $type ? $type : 'coverage';
			$groups[ $type ][] = $post;
		}

		$out = '<div class="aim-avt-press">';
		foreach ( $labels as $type => $label ) {
			if ( empty( $groups[ $type ] ) ) {
				continue;
			}
			$out .= '<h3 class="aim-avt-press__heading">' . esc_html( $label ) . '</h3><ul class="aim-avt-press__list">';
			foreach ( $groups[ $type ] as $post ) {
				$outlet = get_post_meta( $post->ID, '_aim_avt_outlet', true );
				$url    = get_post_meta( $post->ID, '_aim_avt_url', true );
				$date   = get_post_meta( $post->ID, '_aim_avt_date', true );

				$out .= '<li class="aim-avt-press__item">';
				$title = wp_strip_all_tags( get_the_title( $post ) );
				if ( $url ) {
					$out .= '<a href="' . esc_url( $url ) . '" rel="noopener">' . esc_html( $title ) . '</a>';
				} else {
					$out .= esc_html( $title );
				}
				$meta = array_filter( array( $outlet, $date ) );
				if ( $meta ) {
					$out .= ' <span class="aim-avt-press__meta">— ' . esc_html( implode( ', ', $meta ) ) . '</span>';
				}
				if ( trim( wp_strip_all_tags( $post->post_content ) ) ) {
					$out .= '<div class="aim-avt-press__summary">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div>';
				}
				$out .= '</li>';
			}
			$out .= '</ul>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Case study index — Task 3.1.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function case_studies( $atts ) {
		$atts  = shortcode_atts( array( 'limit' => 12 ), $atts, 'aim_case_studies' );
		$items = AIM_AVT_Content::case_studies();

		if ( ! $items ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'No case studies have been published yet. Add them under AI Visibility → Case Studies. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_style( 'aim-avt' );
		$items = array_slice( $items, 0, max( 1, (int) $atts['limit'] ) );

		$out = '<div class="aim-avt-case-studies">';
		foreach ( $items as $post ) {
			$industry   = get_post_meta( $post->ID, '_aim_avt_industry', true );
			$machine    = get_post_meta( $post->ID, '_aim_avt_machine', true );
			$turnaround = get_post_meta( $post->ID, '_aim_avt_turnaround', true );

			$out .= '<article class="aim-avt-case-study">';
			$out .= '<h3 class="aim-avt-case-study__title"><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) . '</a></h3>';

			$meta = array_filter( array( $industry, $machine, $turnaround ) );
			if ( $meta ) {
				$out .= '<p class="aim-avt-case-study__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
			}
			$excerpt = get_the_excerpt( $post );
			if ( $excerpt ) {
				$out .= '<p class="aim-avt-case-study__excerpt">' . esc_html( wp_strip_all_tags( $excerpt ) ) . '</p>';
			}
			$out .= '</article>';
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Pricing bands with Offer markup — Task 3.3.
	 *
	 * Renders nothing at all unless real figures have been entered. A pricing
	 * table with empty numbers is exactly the defect the assessment recorded.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function pricing( $atts ) {
		if ( ! AIM_AVT_Settings::get( 'pricing.enabled' ) ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'Pricing output is switched off. Turn it on under AI Visibility → Phase 3 → Pricing. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		$config   = (array) AIM_AVT_Settings::get( 'pricing', array() );
		$currency = ! empty( $config['currency'] ) ? $config['currency'] : 'USD';
		$symbol   = ( 'CAD' === $currency ) ? 'C$' : '$';

		$bands = array();
		foreach ( (array) $config['bands'] as $band ) {
			$min = isset( $band['min'] ) ? trim( (string) $band['min'] ) : '';
			$max = isset( $band['max'] ) ? trim( (string) $band['max'] ) : '';
			if ( '' === $min && '' === $max ) {
				continue; // No figure means nothing to publish.
			}
			$bands[] = $band;
		}

		if ( ! $bands ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'No price figures have been entered yet, so nothing is shown. Add them under AI Visibility → Phase 3 → Pricing. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_style( 'aim-avt' );

		$offers = array();
		$rows   = '';

		foreach ( $bands as $band ) {
			$name = isset( $band['name'] ) ? $band['name'] : '';
			$min  = trim( (string) $band['min'] );
			$max  = trim( (string) $band['max'] );

			if ( '' !== $min && '' !== $max && $min !== $max ) {
				$display = $symbol . number_format( (float) $min ) . ' – ' . $symbol . number_format( (float) $max );
			} else {
				$single  = '' !== $min ? $min : $max;
				$display = __( 'from', 'aim-avt' ) . ' ' . $symbol . number_format( (float) $single );
			}

			$rows .= '<tr>';
			$rows .= '<th scope="row">' . esc_html( $name ) . '</th>';
			$rows .= '<td class="aim-avt-pricing__figure">' . esc_html( $display ) . '</td>';
			$rows .= '<td>' . esc_html( isset( $band['turnaround'] ) ? $band['turnaround'] : '' ) . '</td>';
			$rows .= '<td class="aim-avt-pricing__assumptions">' . esc_html( isset( $band['assumptions'] ) ? $band['assumptions'] : '' ) . '</td>';
			$rows .= '</tr>';

			$spec = array(
				'@type'         => 'PriceSpecification',
				'priceCurrency' => $currency,
			);
			if ( '' !== $min ) {
				$spec['minPrice'] = (float) $min;
			}
			if ( '' !== $max ) {
				$spec['maxPrice'] = (float) $max;
			}

			$offer = array(
				'@type'            => 'Offer',
				'name'             => $name,
				'priceCurrency'    => $currency,
				'priceSpecification' => $spec,
				'availability'     => 'https://schema.org/InStock',
			);
			if ( ! empty( $band['assumptions'] ) ) {
				$offer['description'] = $band['assumptions'];
			}
			if ( ! empty( $band['turnaround'] ) ) {
				$offer['deliveryLeadTime'] = array(
					'@type' => 'QuantitativeValue',
					'name'  => $band['turnaround'],
				);
			}
			if ( ! empty( $config['as_of'] ) ) {
				$offer['priceValidUntil'] = gmdate( 'Y-m-d', strtotime( $config['as_of'] . ' +1 year' ) );
			}

			$offers[] = $offer;
		}

		$out  = '<div class="aim-avt-pricing">';
		$out .= '<table class="aim-avt-pricing__table"><thead><tr>';
		$out .= '<th scope="col">' . esc_html__( 'Work', 'aim-avt' ) . '</th>';
		$out .= '<th scope="col">' . esc_html__( 'Typical range', 'aim-avt' ) . '</th>';
		$out .= '<th scope="col">' . esc_html__( 'Typical turnaround', 'aim-avt' ) . '</th>';
		$out .= '<th scope="col">' . esc_html__( 'What moves the number', 'aim-avt' ) . '</th>';
		$out .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

		if ( ! empty( $config['note'] ) ) {
			$out .= '<p class="aim-avt-pricing__note">' . esc_html( $config['note'] ) . '</p>';
		}
		if ( ! empty( $config['as_of'] ) ) {
			$out .= '<p class="aim-avt-pricing__asof">' . esc_html(
				sprintf(
					/* translators: %s: date. */
					__( 'Pricing as of %s.', 'aim-avt' ),
					$config['as_of']
				)
			) . '</p>';
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@type'    => 'OfferCatalog',
			'@id'      => ( is_singular() ? get_permalink() : home_url( '/' ) ) . '#pricing',
			'name'     => __( 'Centrifuge repair pricing', 'aim-avt' ),
			'itemListElement' => $offers,
			'provider' => array( '@id' => AIM_AVT_Schema::org_reference() ),
		);

		$encoded = AIM_AVT_Schema::encode( $graph );
		if ( false !== $encoded ) {
			$out .= '<script type="application/ld+json" data-aim-avt="pricing">' . $encoded . '</script>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Place-linked Google Maps embed — Task 1.4.
	 *
	 * The homepage currently uses address-query embeds, which carry no place
	 * entity. Once a Place ID is filled in this emits the place-linked form.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function map( $atts ) {
		$atts = shortcode_atts(
			array(
				'location' => '',
				'height'   => '320',
				'key'      => '',
			),
			$atts,
			'aim_map'
		);

		$location = null;
		foreach ( (array) AIM_AVT_Settings::get( 'schema.locations', array() ) as $row ) {
			if ( ! empty( $row['key'] ) && $row['key'] === $atts['location'] ) {
				$location = $row;
				break;
			}
		}
		if ( ! $location ) {
			return '';
		}

		wp_enqueue_style( 'aim-avt' );

		$title  = ! empty( $location['name'] ) ? $location['name'] : $location['locality'];
		$height = max( 200, (int) $atts['height'] );

		// Place-linked embed when a Place ID and API key are available.
		if ( ! empty( $location['place_id'] ) && ! empty( $atts['key'] ) ) {
			$src = add_query_arg(
				array(
					'key' => rawurlencode( $atts['key'] ),
					'q'   => 'place_id:' . rawurlencode( $location['place_id'] ),
				),
				'https://www.google.com/maps/embed/v1/place'
			);
		} elseif ( ! empty( $location['gbp_url'] ) ) {
			// Fall back to a link to the claimed profile — still a real place
			// entity, unlike an address query.
			return '<p class="aim-avt-map-link"><a href="' . esc_url( $location['gbp_url'] ) . '" rel="noopener">' . esc_html(
				sprintf(
					/* translators: %s: location name. */
					__( 'See %s on Google Maps', 'aim-avt' ),
					$title
				)
			) . '</a></p>';
		} else {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="aim-avt-notice">' . esc_html__( 'This map needs a Google Place ID or a claimed Business Profile URL (Task 2.4). Add it under AI Visibility → Locations. Only you can see this message.', 'aim-avt' ) . '</p>';
			}
			return '';
		}

		return '<div class="aim-avt-map"><iframe title="' . esc_attr( $title ) . '" src="' . esc_url( $src ) . '" width="100%" height="' . esc_attr( $height ) . '" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
	}

	/**
	 * Render the canonical NAP block — the human-readable half of Task 1.3.
	 *
	 * The rendered text has to match the schema character for character, so
	 * printing both from one source is the reliable way to keep them in step.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function locations( $atts ) {
		$atts = shortcode_atts( array( 'location' => '' ), $atts, 'aim_locations' );

		$rows = (array) AIM_AVT_Settings::get( 'schema.locations', array() );
		if ( $atts['location'] ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $atts ) {
						return ! empty( $row['key'] ) && $row['key'] === $atts['location'];
					}
				)
			);
		}
		if ( ! $rows ) {
			return '';
		}

		wp_enqueue_style( 'aim-avt' );

		$out = '<div class="aim-avt-locations">';
		foreach ( $rows as $row ) {
			$out .= '<div class="aim-avt-location">';
			$out .= '<h3 class="aim-avt-location__name">' . esc_html( ! empty( $row['name'] ) ? $row['name'] : $row['locality'] ) . '</h3>';
			$out .= '<address class="aim-avt-location__address">';
			$out .= esc_html( $row['street'] ) . '<br />';
			$out .= esc_html( trim( $row['locality'] . ', ' . $row['region'] . ' ' . $row['postal_code'] ) ) . '<br />';
			$out .= esc_html( 'CA' === $row['country'] ? 'Canada' : ( 'US' === $row['country'] ? 'United States' : $row['country'] ) );
			$out .= '</address>';
			if ( ! empty( $row['phone'] ) ) {
				$out .= '<p class="aim-avt-location__phone"><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $row['phone'] ) ) . '">' . esc_html( $row['phone'] ) . '</a></p>';
			}
			if ( ! empty( $row['opening_hours'] ) ) {
				$out .= '<p class="aim-avt-location__hours">' . esc_html( $row['opening_hours'] ) . '</p>';
			}
			$out .= '</div>';
		}
		$out .= '</div>';

		return $out;
	}
}
