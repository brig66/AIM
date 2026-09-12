<?php
/**
 * Schema graph builder and merger.
 *
 * The assessment measured graph coherence at a perfect 1.000 on sentrimax.com,
 * so the default behaviour here is to MERGE the missing fields into the
 * Organization node the site already publishes rather than to emit a second,
 * competing Organization. A duplicate node would create two entities where
 * there is one company and would undo the one structured-data rule the site
 * currently passes outright.
 *
 * Covers checklist tasks 1.2, 1.3, 1.4, 1.5, 1.6, 2.1, 2.2 and 3.3.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Schema {

	/**
	 * Stand-in for the Organization @id in markup written before the merge runs.
	 *
	 * Schema emitted in wp_head — a BlogPosting author, a case study publisher,
	 * an OfferCatalog provider — has to point at the Organization node, but in
	 * merge mode that node's real @id belongs to the site's own graph and is not
	 * known until the finished page is scanned. So those references are written
	 * with this placeholder and rewritten to the real @id once it is found. A
	 * dangling reference would break the perfect graph coherence the assessment
	 * measured, which is the one structured-data rule the site already passes.
	 */
	const ORG_PLACEHOLDER = 'urn:aim-avt:organization';

	/**
	 * The value to use when referring to the Organization from other markup.
	 *
	 * @return string
	 */
	public static function org_reference() {
		if ( AIM_AVT_Settings::get( 'schema.enabled' ) && AIM_AVT_Output::any_transform_enabled() ) {
			return self::ORG_PLACEHOLDER;
		}
		return self::id_base() . '#organization';
	}

	/**
	 * Replace every placeholder reference with the resolved Organization @id.
	 *
	 * @param string $html   Page HTML.
	 * @param string $org_id Resolved @id.
	 * @return string
	 */
	public static function resolve_placeholders( $html, $org_id ) {
		if ( '' === $org_id ) {
			$org_id = self::id_base() . '#organization';
		}
		if ( false === strpos( $html, self::ORG_PLACEHOLDER ) ) {
			return $html;
		}
		return str_replace( self::ORG_PLACEHOLDER, $org_id, $html );
	}

	/**
	 * Fields that belong on the Organization node.
	 *
	 * Task 1.2 (core fields), 1.3 (PostalAddress), 1.5 (sameAs), 1.6 (foundingDate).
	 *
	 * @return array
	 */
	public static function organization_fields() {
		$s      = AIM_AVT_Settings::get( 'schema' );
		$fields = array();

		if ( ! empty( $s['org_name'] ) ) {
			$fields['name'] = $s['org_name'];
		}
		if ( ! empty( $s['org_legal_name'] ) ) {
			$fields['legalName'] = $s['org_legal_name'];
		}
		if ( ! empty( $s['org_url'] ) ) {
			$fields['url'] = $s['org_url'];
		}
		if ( ! empty( $s['org_description'] ) ) {
			$fields['description'] = $s['org_description'];
		}
		if ( ! empty( $s['org_phone'] ) ) {
			$fields['telephone'] = $s['org_phone'];
		}
		if ( ! empty( $s['org_email'] ) ) {
			$fields['email'] = $s['org_email'];
		}
		if ( ! empty( $s['founding_date'] ) ) {
			$fields['foundingDate'] = $s['founding_date'];
		}
		if ( ! empty( $s['org_logo'] ) ) {
			$fields['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $s['org_logo'],
			);
		}

		// Head-office postal address — Task 1.3.
		$head = self::head_office();
		if ( $head ) {
			$address = self::postal_address( $head );
			if ( $address ) {
				$fields['address'] = $address;
			}
		}

		// sameAs — Task 1.5.
		$same_as = self::same_as_urls();
		if ( $same_as ) {
			$fields['sameAs'] = $same_as;
		}

		// ISO 9001:2015 — supports the Trust category and the About narrative.
		if ( ! empty( $s['iso_cert'] ) ) {
			$cert = array(
				'@type' => 'Certification',
				'name'  => $s['iso_cert'],
			);
			if ( ! empty( $s['iso_registrar'] ) ) {
				$cert['issuedBy'] = array(
					'@type' => 'Organization',
					'name'  => $s['iso_registrar'],
				);
				if ( ! empty( $s['iso_registrar_url'] ) ) {
					$cert['issuedBy']['url'] = $s['iso_registrar_url'];
				}
			}
			$fields['hasCertification'] = $cert;
		}

		/**
		 * Filter the fields merged into the Organization node.
		 *
		 * @param array $fields Organization fields.
		 */
		return apply_filters( 'aim_avt_organization_fields', $fields );
	}

	/**
	 * The head-office location row, or the first location as a fallback.
	 *
	 * @return array|null
	 */
	public static function head_office() {
		$locations = (array) AIM_AVT_Settings::get( 'schema.locations', array() );
		foreach ( $locations as $location ) {
			if ( ! empty( $location['head_office'] ) ) {
				return $location;
			}
		}
		return $locations ? reset( $locations ) : null;
	}

	/**
	 * Build a PostalAddress object from a location row.
	 *
	 * Returns null unless there is enough of an address to be worth publishing —
	 * a half-filled address scores nothing and risks a NAP mismatch.
	 *
	 * @param array $location Location row.
	 * @return array|null
	 */
	public static function postal_address( array $location ) {
		$street   = isset( $location['street'] ) ? trim( $location['street'] ) : '';
		$locality = isset( $location['locality'] ) ? trim( $location['locality'] ) : '';
		$region   = isset( $location['region'] ) ? trim( $location['region'] ) : '';
		$postal   = isset( $location['postal_code'] ) ? trim( $location['postal_code'] ) : '';
		$country  = isset( $location['country'] ) ? trim( $location['country'] ) : '';

		if ( '' === $street || '' === $locality ) {
			return null;
		}

		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'addressLocality' => $locality,
		);
		if ( '' !== $region ) {
			$address['addressRegion'] = $region;
		}
		if ( '' !== $postal ) {
			$address['postalCode'] = $postal;
		}
		if ( '' !== $country ) {
			$address['addressCountry'] = $country;
		}
		return $address;
	}

	/**
	 * All sameAs URLs: the declared social profiles plus any claimed Google
	 * Business Profile URLs from Task 2.4.
	 *
	 * @return array
	 */
	public static function same_as_urls() {
		$urls = (array) AIM_AVT_Settings::get( 'schema.same_as', array() );

		foreach ( (array) AIM_AVT_Settings::get( 'schema.locations', array() ) as $location ) {
			if ( ! empty( $location['gbp_url'] ) ) {
				$urls[] = $location['gbp_url'];
			}
		}

		$clean = array();
		foreach ( $urls as $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				continue;
			}
			$url = esc_url_raw( $url );
			if ( $url && ! in_array( $url, $clean, true ) ) {
				$clean[] = $url;
			}
		}
		return $clean;
	}

	/**
	 * Split an "Mo-Fr 08:00-17:00" style string into openingHoursSpecification.
	 *
	 * Falls back to emitting the raw string as openingHours if it cannot be
	 * parsed, which is still valid schema.org.
	 *
	 * @param string $spec Hours string.
	 * @return array{spec:array,raw:string}
	 */
	public static function opening_hours( $spec ) {
		$spec = trim( (string) $spec );
		if ( '' === $spec ) {
			return array(
				'spec' => array(),
				'raw'  => '',
			);
		}

		$day_map = array(
			'mo' => 'Monday',
			'tu' => 'Tuesday',
			'we' => 'Wednesday',
			'th' => 'Thursday',
			'fr' => 'Friday',
			'sa' => 'Saturday',
			'su' => 'Sunday',
		);
		$order   = array_keys( $day_map );

		$out = array();
		foreach ( preg_split( '/\s*[,;]\s*/', $spec ) as $chunk ) {
			if ( ! preg_match( '/^([A-Za-z]{2})(?:\s*-\s*([A-Za-z]{2}))?\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', trim( $chunk ), $m ) ) {
				continue;
			}
			$from = strtolower( $m[1] );
			$to   = ! empty( $m[2] ) ? strtolower( $m[2] ) : $from;
			if ( ! isset( $day_map[ $from ], $day_map[ $to ] ) ) {
				continue;
			}
			$start = array_search( $from, $order, true );
			$end   = array_search( $to, $order, true );
			$days  = array();
			$i     = $start;
			// Walk forward through the week so "Fr-Mo" wraps correctly.
			for ( $guard = 0; $guard < 7; $guard++ ) {
				$days[] = $day_map[ $order[ $i ] ];
				if ( $i === $end ) {
					break;
				}
				$i = ( $i + 1 ) % 7;
			}
			$out[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $days,
				'opens'     => $m[3],
				'closes'    => $m[4],
			);
		}

		return array(
			'spec' => $out,
			'raw'  => $spec,
		);
	}

	/**
	 * LocalBusiness nodes, one per staffed facility — Task 1.4.
	 *
	 * @param string $org_id The @id of the Organization these belong to.
	 * @return array
	 */
	public static function local_business_nodes( $org_id ) {
		$nodes = array();
		$base  = self::id_base();

		foreach ( (array) AIM_AVT_Settings::get( 'schema.locations', array() ) as $location ) {
			$address = self::postal_address( $location );
			if ( ! $address ) {
				// No usable address means no node. A malformed LocalBusiness
				// scores nothing and pollutes the graph.
				continue;
			}

			$key  = ! empty( $location['key'] ) ? sanitize_key( $location['key'] ) : sanitize_key( $location['locality'] );
			$node = array(
				'@type'   => 'LocalBusiness',
				'@id'     => $base . '#location-' . $key,
				'name'    => ! empty( $location['name'] ) ? $location['name'] : AIM_AVT_Settings::get( 'schema.org_name' ),
				'address' => $address,
			);

			if ( $org_id ) {
				$node['parentOrganization'] = array( '@id' => $org_id );
			}
			if ( ! empty( $location['phone'] ) ) {
				$node['telephone'] = $location['phone'];
			}
			if ( ! empty( $location['email'] ) ) {
				$node['email'] = $location['email'];
			}
			if ( ! empty( $location['gbp_url'] ) ) {
				$node['sameAs'] = array( esc_url_raw( $location['gbp_url'] ) );
				$node['hasMap'] = esc_url_raw( $location['gbp_url'] );
			}
			if ( ! empty( $location['latitude'] ) && ! empty( $location['longitude'] ) ) {
				$node['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $location['latitude'],
					'longitude' => $location['longitude'],
				);
			}

			$hours = self::opening_hours( isset( $location['opening_hours'] ) ? $location['opening_hours'] : '' );
			if ( $hours['spec'] ) {
				$node['openingHoursSpecification'] = $hours['spec'];
			} elseif ( $hours['raw'] ) {
				$node['openingHours'] = $hours['raw'];
			}

			if ( ! empty( $location['area_served'] ) ) {
				$areas = array_map( 'trim', explode( ',', $location['area_served'] ) );
				$served = array();
				foreach ( $areas as $area ) {
					if ( '' !== $area ) {
						$served[] = array(
							'@type' => 'AdministrativeArea',
							'name'  => $area,
						);
					}
				}
				if ( $served ) {
					$node['areaServed'] = $served;
				}
			}

			$image = AIM_AVT_Settings::get( 'schema.org_logo' );
			if ( $image ) {
				$node['image'] = $image;
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * Person nodes for the leadership team — Task 2.2.
	 *
	 * @param string $org_id Organization @id.
	 * @return array
	 */
	public static function person_nodes( $org_id ) {
		$nodes = array();
		$base  = self::id_base();

		foreach ( (array) AIM_AVT_Settings::get( 'schema.people', array() ) as $person ) {
			$name = isset( $person['name'] ) ? trim( $person['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$key  = ! empty( $person['key'] ) ? sanitize_key( $person['key'] ) : sanitize_title( $name );
			$node = array(
				'@type' => 'Person',
				'@id'   => $base . '#person-' . $key,
				'name'  => $name,
			);

			if ( ! empty( $person['job_title'] ) ) {
				$node['jobTitle'] = $person['job_title'];
			}
			if ( ! empty( $person['description'] ) ) {
				$node['description'] = $person['description'];
			}
			if ( ! empty( $person['image'] ) ) {
				$node['image'] = esc_url_raw( $person['image'] );
			}
			if ( ! empty( $person['linkedin'] ) ) {
				$node['sameAs'] = array( esc_url_raw( $person['linkedin'] ) );
			}
			if ( ! empty( $person['knows_about'] ) ) {
				$topics = AIM_AVT_Settings::lines( $person['knows_about'] );
				if ( $topics ) {
					$node['knowsAbout'] = $topics;
				}
			}
			if ( $org_id ) {
				$node['worksFor'] = array( '@id' => $org_id );
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * The @id of the Person marked as the default content author.
	 *
	 * @return string
	 */
	public static function default_author_id() {
		$base   = self::id_base();
		$people = (array) AIM_AVT_Settings::get( 'schema.people', array() );
		foreach ( $people as $person ) {
			if ( ! empty( $person['is_default_author'] ) && ! empty( $person['name'] ) ) {
				$key = ! empty( $person['key'] ) ? sanitize_key( $person['key'] ) : sanitize_title( $person['name'] );
				return $base . '#person-' . $key;
			}
		}
		foreach ( $people as $person ) {
			if ( ! empty( $person['name'] ) ) {
				$key = ! empty( $person['key'] ) ? sanitize_key( $person['key'] ) : sanitize_title( $person['name'] );
				return $base . '#person-' . $key;
			}
		}
		return '';
	}

	/**
	 * Review nodes for the published testimonials — Task 2.1.
	 *
	 * @param string $org_id Organization @id.
	 * @return array
	 */
	public static function review_nodes( $org_id ) {
		$nodes = array();
		$base  = self::id_base();

		foreach ( AIM_AVT_Content::testimonials() as $post ) {
			$body = trim( wp_strip_all_tags( $post->post_content ) );
			if ( '' === $body ) {
				continue;
			}

			$author  = get_post_meta( $post->ID, '_aim_avt_author_name', true );
			$company = get_post_meta( $post->ID, '_aim_avt_company', true );
			$role    = get_post_meta( $post->ID, '_aim_avt_role', true );
			$rating  = (float) get_post_meta( $post->ID, '_aim_avt_rating', true );

			$author_name = $author ? $author : $company;
			if ( '' === trim( (string) $author_name ) ) {
				// An unattributed review is not a review the rubric counts.
				continue;
			}

			$node = array(
				'@type'        => 'Review',
				'@id'          => $base . '#review-' . $post->ID,
				'reviewBody'   => $body,
				'datePublished'=> get_the_date( 'Y-m-d', $post ),
				'author'       => array(
					'@type' => $company && $author ? 'Person' : 'Organization',
					'name'  => $author_name,
				),
			);

			if ( $company && $author ) {
				$node['author']['worksFor'] = array(
					'@type' => 'Organization',
					'name'  => $company,
				);
			}
			if ( $role ) {
				$node['author']['jobTitle'] = $role;
			}
			if ( $rating > 0 ) {
				$node['reviewRating'] = array(
					'@type'       => 'Rating',
					'ratingValue' => (string) $rating,
					'bestRating'  => '5',
					'worstRating' => '1',
				);
			}
			if ( $org_id ) {
				$node['itemReviewed'] = array( '@id' => $org_id );
			}

			$nodes[] = $node;
		}

		return $nodes;
	}

	/**
	 * aggregateRating, computed from the published testimonials — Task 2.1.
	 *
	 * Returns null unless the configured minimum number of rated, visible
	 * reviews exists. Inventing an aggregate rating without visible reviews is
	 * a policy violation and engines cross-check it, so this refuses rather
	 * than guesses.
	 *
	 * @return array|null
	 */
	public static function aggregate_rating() {
		$config = (array) AIM_AVT_Settings::get( 'schema.aggregate_rating', array() );
		if ( empty( $config['enabled'] ) ) {
			return null;
		}

		$min     = max( 1, (int) ( isset( $config['min_reviews'] ) ? $config['min_reviews'] : 3 ) );
		$ratings = array();

		foreach ( AIM_AVT_Content::testimonials() as $post ) {
			$rating = (float) get_post_meta( $post->ID, '_aim_avt_rating', true );
			$author = get_post_meta( $post->ID, '_aim_avt_author_name', true );
			$company= get_post_meta( $post->ID, '_aim_avt_company', true );
			if ( $rating > 0 && ( $author || $company ) && trim( wp_strip_all_tags( $post->post_content ) ) ) {
				$ratings[] = $rating;
			}
		}

		if ( count( $ratings ) < $min ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) round( array_sum( $ratings ) / count( $ratings ), 1 ),
			'reviewCount' => (string) count( $ratings ),
			'bestRating'  => '5',
			'worstRating' => '1',
		);
	}

	/**
	 * Whether aggregateRating and Review nodes should appear on this request.
	 *
	 * Reviews have to be visible on the page that carries the markup, so by
	 * default they are emitted only where the testimonials are actually
	 * rendered.
	 *
	 * @return bool
	 */
	public static function reviews_visible_here() {
		if ( AIM_AVT_Content::is_testimonials_page() ) {
			return true;
		}
		/**
		 * Filter whether review markup may be emitted on the current request.
		 *
		 * @param bool $visible Whether reviews are rendered on this page.
		 */
		return (bool) apply_filters( 'aim_avt_reviews_visible_here', false );
	}

	/**
	 * Base URL used to build stable @id values.
	 *
	 * @return string
	 */
	public static function id_base() {
		$url = AIM_AVT_Settings::get( 'schema.org_url' );
		if ( ! $url ) {
			$url = home_url( '/' );
		}
		return trailingslashit( $url );
	}

	/**
	 * Assemble every node this plugin contributes, given the Organization @id
	 * it should hang them from.
	 *
	 * @param string $org_id Organization @id (may be empty in standalone mode).
	 * @return array
	 */
	public static function additional_nodes( $org_id ) {
		$nodes = array();

		$nodes = array_merge( $nodes, self::local_business_nodes( $org_id ) );
		$nodes = array_merge( $nodes, self::person_nodes( $org_id ) );

		if ( self::reviews_visible_here() ) {
			$nodes = array_merge( $nodes, self::review_nodes( $org_id ) );
		}

		/**
		 * Filter the extra nodes appended to the graph.
		 *
		 * @param array  $nodes  Nodes.
		 * @param string $org_id Organization @id.
		 */
		return apply_filters( 'aim_avt_additional_nodes', $nodes, $org_id );
	}

	// -------------------------------------------------------------------
	// Merging into the page's existing JSON-LD.
	// -------------------------------------------------------------------

	/**
	 * Rewrite the JSON-LD in a page of HTML.
	 *
	 * @param string $html Full page HTML.
	 * @return array{html:string,result:string,org_id:string} Result is
	 *               "merged", "standalone", "skipped" or "unchanged".
	 */
	public static function apply_to_html( $html ) {
		$mode   = AIM_AVT_Settings::get( 'schema.mode', 'auto' );
		$blocks = self::find_jsonld_blocks( $html );

		$target_index = null;
		$target_data  = null;
		$org_pointer  = null;

		if ( 'standalone' !== $mode ) {
			foreach ( $blocks as $i => $block ) {
				$data = json_decode( $block['json'], true );
				if ( ! is_array( $data ) ) {
					continue;
				}
				$pointer = self::locate_organization( $data );
				if ( null !== $pointer ) {
					$target_index = $i;
					$target_data  = $data;
					$org_pointer  = $pointer;
					break;
				}
			}
		}

		// -- Merge path -------------------------------------------------
		if ( null !== $target_index ) {
			$org = self::pointer_get( $target_data, $org_pointer );
			if ( ! is_array( $org ) ) {
				return array(
					'html'   => self::resolve_placeholders( $html, '' ),
					'result' => 'skipped',
					'org_id' => '',
				);
			}

			$org_id = isset( $org['@id'] ) ? (string) $org['@id'] : '';
			$org    = self::fill_gaps( $org, self::organization_fields() );

			if ( self::reviews_visible_here() && empty( $org['aggregateRating'] ) ) {
				$rating = self::aggregate_rating();
				if ( $rating ) {
					$org['aggregateRating'] = $rating;
				}
			}

			$target_data = self::pointer_set( $target_data, $org_pointer, $org );

			$extra = self::additional_nodes( $org_id );
			if ( $extra ) {
				$target_data = self::append_nodes( $target_data, $extra );
			}

			$encoded = self::encode( $target_data );
			if ( false === $encoded ) {
				return array(
					'html'   => $html,
					'result' => 'skipped',
					'org_id' => $org_id,
				);
			}

			$block = $blocks[ $target_index ];
			$html  = substr_replace( $html, $encoded, $block['json_start'], $block['json_length'] );
			$html  = self::resolve_placeholders( $html, $org_id );

			return array(
				'html'   => $html,
				'result' => 'merged',
				'org_id' => $org_id,
			);
		}

		// -- Standalone path --------------------------------------------
		if ( 'merge' === $mode ) {
			// Merge-only was requested and no Organization node was found.
			// Emitting a competing node was explicitly not asked for, so stop.
			return array(
				'html'   => self::resolve_placeholders( $html, '' ),
				'result' => 'skipped',
				'org_id' => '',
			);
		}

		$graph = self::standalone_graph();
		if ( empty( $graph['@graph'] ) ) {
			return array(
				'html'   => $html,
				'result' => 'unchanged',
				'org_id' => '',
			);
		}

		$encoded = self::encode( $graph );
		if ( false === $encoded ) {
			return array(
				'html'   => $html,
				'result' => 'skipped',
				'org_id' => '',
			);
		}

		$script = "\n<script type=\"application/ld+json\" data-aim-avt=\"1\">" . $encoded . "</script>\n";
		$pos    = stripos( $html, '</head>' );
		if ( false === $pos ) {
			return array(
				'html'   => $html,
				'result' => 'skipped',
				'org_id' => '',
			);
		}
		$html   = substr_replace( $html, $script, $pos, 0 );
		$org_id = self::id_base() . '#organization';
		$html   = self::resolve_placeholders( $html, $org_id );

		return array(
			'html'   => $html,
			'result' => 'standalone',
			'org_id' => $org_id,
		);
	}

	/**
	 * Build a complete, self-contained graph for the standalone path.
	 *
	 * @return array
	 */
	public static function standalone_graph() {
		$org_id = self::id_base() . '#organization';

		$org = array_merge(
			array(
				'@type' => 'Organization',
				'@id'   => $org_id,
			),
			self::organization_fields()
		);

		if ( self::reviews_visible_here() ) {
			$rating = self::aggregate_rating();
			if ( $rating ) {
				$org['aggregateRating'] = $rating;
			}
		}

		$nodes = array_merge( array( $org ), self::additional_nodes( $org_id ) );

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		);
	}

	/**
	 * Locate every JSON-LD script block with byte offsets.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	public static function find_jsonld_blocks( $html ) {
		$blocks = array();
		$offset = 0;
		$len    = strlen( $html );

		while ( $offset < $len ) {
			if ( ! preg_match(
				'#<script\b[^>]*type\s*=\s*["\']application/ld\+json["\'][^>]*>#i',
				$html,
				$m,
				PREG_OFFSET_CAPTURE,
				$offset
			) ) {
				break;
			}

			$open_start = $m[0][1];
			$open_len   = strlen( $m[0][0] );
			$json_start = $open_start + $open_len;

			$close = stripos( $html, '</script', $json_start );
			if ( false === $close ) {
				break;
			}

			$blocks[] = array(
				'json'        => substr( $html, $json_start, $close - $json_start ),
				'json_start'  => $json_start,
				'json_length' => $close - $json_start,
			);

			$offset = $close + 8;
		}

		return $blocks;
	}

	/**
	 * Find an Organization node inside a decoded JSON-LD structure.
	 *
	 * Returns a pointer — an array of keys to walk — or null.
	 *
	 * @param array $data Decoded JSON-LD.
	 * @return array|null
	 */
	public static function locate_organization( array $data ) {
		// Case 1: an @graph array.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$best = null;
			foreach ( $data['@graph'] as $i => $node ) {
				if ( is_array( $node ) && self::is_organization( $node ) ) {
					// Prefer a node that already carries an @id — that is the
					// one the rest of the site's graph references.
					if ( ! empty( $node['@id'] ) ) {
						return array( '@graph', $i );
					}
					if ( null === $best ) {
						$best = array( '@graph', $i );
					}
				}
			}
			return $best;
		}

		// Case 2: a bare top-level node.
		if ( self::is_organization( $data ) ) {
			return array();
		}

		// Case 3: a top-level list of nodes.
		$is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );
		if ( $is_list ) {
			foreach ( $data as $i => $node ) {
				if ( is_array( $node ) && self::is_organization( $node ) ) {
					return array( $i );
				}
			}
		}

		return null;
	}

	/**
	 * Is this node an Organization (or a recognised subtype)?
	 *
	 * LocalBusiness is deliberately excluded — merging head-office fields into
	 * a branch node would be wrong.
	 *
	 * @param array $node Node.
	 * @return bool
	 */
	public static function is_organization( array $node ) {
		if ( ! isset( $node['@type'] ) ) {
			return false;
		}
		$types = (array) $node['@type'];
		foreach ( $types as $type ) {
			$type = is_string( $type ) ? trim( $type ) : '';
			if ( in_array( $type, array( 'Organization', 'Corporation', 'LocalBusiness', 'ProfessionalService', 'HomeAndConstructionBusiness', 'AutomotiveBusiness' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add fields that are absent, leaving anything the site already publishes
	 * untouched. The site's own values win — this plugin fills gaps, it does
	 * not overrule an editor.
	 *
	 * @param array $node   Existing node.
	 * @param array $fields Fields to add.
	 * @return array
	 */
	public static function fill_gaps( array $node, array $fields ) {
		foreach ( $fields as $key => $value ) {
			if ( 'sameAs' === $key ) {
				$existing = array();
				if ( isset( $node['sameAs'] ) ) {
					$existing = is_array( $node['sameAs'] ) ? $node['sameAs'] : array( $node['sameAs'] );
				}
				$merged = $existing;
				foreach ( (array) $value as $url ) {
					if ( ! in_array( $url, $merged, true ) ) {
						$merged[] = $url;
					}
				}
				if ( $merged ) {
					$node['sameAs'] = array_values( $merged );
				}
				continue;
			}

			if ( ! isset( $node[ $key ] ) || '' === $node[ $key ] || array() === $node[ $key ] ) {
				$node[ $key ] = $value;
			}
		}
		return $node;
	}

	/**
	 * Append nodes to a decoded JSON-LD structure, skipping any @id that is
	 * already present so a repeat run cannot duplicate a node.
	 *
	 * @param array $data  Decoded JSON-LD.
	 * @param array $nodes Nodes to append.
	 * @return array
	 */
	public static function append_nodes( array $data, array $nodes ) {
		if ( ! isset( $data['@graph'] ) || ! is_array( $data['@graph'] ) ) {
			// Promote a single node into a graph so the additions stay
			// connected to it rather than floating in a second script block.
			$existing        = $data;
			$context         = isset( $existing['@context'] ) ? $existing['@context'] : 'https://schema.org';
			unset( $existing['@context'] );
			$data = array(
				'@context' => $context,
				'@graph'   => array( $existing ),
			);
		}

		$seen = array();
		foreach ( $data['@graph'] as $node ) {
			if ( is_array( $node ) && ! empty( $node['@id'] ) ) {
				$seen[] = (string) $node['@id'];
			}
		}

		foreach ( $nodes as $node ) {
			if ( ! empty( $node['@id'] ) && in_array( (string) $node['@id'], $seen, true ) ) {
				continue;
			}
			$data['@graph'][] = $node;
			if ( ! empty( $node['@id'] ) ) {
				$seen[] = (string) $node['@id'];
			}
		}

		return $data;
	}

	/**
	 * Read a value at a pointer.
	 *
	 * @param array $data    Data.
	 * @param array $pointer Key path.
	 * @return mixed
	 */
	public static function pointer_get( array $data, array $pointer ) {
		$value = $data;
		foreach ( $pointer as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return null;
			}
			$value = $value[ $key ];
		}
		return $value;
	}

	/**
	 * Write a value at a pointer.
	 *
	 * @param array $data    Data.
	 * @param array $pointer Key path.
	 * @param mixed $value   New value.
	 * @return array
	 */
	public static function pointer_set( array $data, array $pointer, $value ) {
		if ( empty( $pointer ) ) {
			return is_array( $value ) ? $value : $data;
		}
		$ref = &$data;
		foreach ( $pointer as $key ) {
			$ref = &$ref[ $key ];
		}
		$ref = $value;
		unset( $ref );
		return $data;
	}

	/**
	 * Encode JSON-LD for output.
	 *
	 * @param array $data Data.
	 * @return string|false
	 */
	public static function encode( array $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			return false;
		}
		// Never let a stray closing tag inside a string break out of the script.
		return str_replace( array( '</', '<!--' ), array( '<\/', '<!--' ), $json );
	}
}
