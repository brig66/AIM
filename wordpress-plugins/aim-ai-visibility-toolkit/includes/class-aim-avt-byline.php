<?php
/**
 * Author attribution — Task 2.3.
 *
 * blog_author_present measures false: the single news post carries no byline
 * and no author schema. This adds a visible byline and points the BlogPosting
 * author at the Person node built in Task 2.2, so the same named engineer is
 * the accountable human in both the human and the machine layer.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Byline {

	/**
	 * Hook in.
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'prepend_byline' ), 8 );
		add_action( 'wp_head', array( __CLASS__, 'blog_posting_schema' ), 6 );
	}

	/**
	 * Should this request get a byline?
	 *
	 * @return bool
	 */
	protected static function applies() {
		if ( ! AIM_AVT_Settings::output_active() || ! AIM_AVT_Settings::get( 'byline.enabled' ) ) {
			return false;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		$types = (array) AIM_AVT_Settings::get( 'byline.post_types', array() );
		return in_array( get_post_type(), $types, true );
	}

	/**
	 * The person credited on this post.
	 *
	 * A per-post override wins; otherwise the default author from the schema
	 * settings; otherwise the WordPress author's display name.
	 *
	 * @param int $post_id Post ID.
	 * @return array{name:string,id:string,job_title:string}
	 */
	public static function author_for( $post_id ) {
		$override = get_post_meta( $post_id, '_aim_avt_person_key', true );
		$people   = (array) AIM_AVT_Settings::get( 'schema.people', array() );

		$chosen = null;
		foreach ( $people as $person ) {
			if ( empty( $person['name'] ) ) {
				continue;
			}
			if ( $override && ! empty( $person['key'] ) && $person['key'] === $override ) {
				$chosen = $person;
				break;
			}
			if ( ! $chosen && ! empty( $person['is_default_author'] ) ) {
				$chosen = $person;
			}
		}
		if ( ! $chosen ) {
			foreach ( $people as $person ) {
				if ( ! empty( $person['name'] ) ) {
					$chosen = $person;
					break;
				}
			}
		}

		if ( $chosen ) {
			$key = ! empty( $chosen['key'] ) ? sanitize_key( $chosen['key'] ) : sanitize_title( $chosen['name'] );
			return array(
				'name'      => $chosen['name'],
				'id'        => AIM_AVT_Schema::id_base() . '#person-' . $key,
				'job_title' => isset( $chosen['job_title'] ) ? $chosen['job_title'] : '',
			);
		}

		$post = get_post( $post_id );
		return array(
			'name'      => $post ? get_the_author_meta( 'display_name', $post->post_author ) : '',
			'id'        => '',
			'job_title' => '',
		);
	}

	/**
	 * Put a visible byline above the post content.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function prepend_byline( $content ) {
		if ( ! self::applies() || ! AIM_AVT_Settings::get( 'byline.show_visible' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		$author  = self::author_for( $post_id );
		if ( '' === trim( $author['name'] ) ) {
			return $content;
		}

		// Do not double up if the theme already prints a byline.
		if ( false !== stripos( $content, 'aim-avt-byline' ) ) {
			return $content;
		}

		$label = (string) AIM_AVT_Settings::get( 'byline.label', 'By' );
		$who   = $author['name'];
		if ( $author['job_title'] ) {
			$who .= ', ' . $author['job_title'];
		}

		$byline  = '<p class="aim-avt-byline">';
		$byline .= '<span class="aim-avt-byline__label">' . esc_html( $label ) . '</span> ';
		$byline .= '<span class="aim-avt-byline__name">' . esc_html( $who ) . '</span>';
		$byline .= ' <span class="aim-avt-byline__date">· ' . esc_html( get_the_date( '', $post_id ) ) . '</span>';
		$byline .= '</p>';

		wp_enqueue_style( 'aim-avt' );

		return $byline . $content;
	}

	/**
	 * BlogPosting schema with an author reference.
	 */
	public static function blog_posting_schema() {
		if ( ! AIM_AVT_Settings::output_active() || ! AIM_AVT_Settings::get( 'byline.enabled' ) || ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return;
		}
		if ( ! is_singular( 'post' ) ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		$author = self::author_for( $post->ID );
		if ( '' === trim( $author['name'] ) ) {
			return;
		}

		$node = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'BlogPosting',
			'@id'           => get_permalink( $post ) . '#aim-blogposting',
			'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
			'datePublished' => get_the_date( 'c', $post ),
			'dateModified'  => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
			'publisher'     => array( '@id' => AIM_AVT_Schema::org_reference() ),
		);

		if ( $author['id'] ) {
			$node['author'] = array( '@id' => $author['id'] );
		} else {
			$node['author'] = array(
				'@type' => 'Person',
				'name'  => $author['name'],
			);
		}

		if ( has_post_thumbnail( $post ) ) {
			$node['image'] = get_the_post_thumbnail_url( $post, 'full' );
		}

		$encoded = AIM_AVT_Schema::encode( $node );
		if ( false === $encoded ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" data-aim-avt="blogposting">' . $encoded . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapingOutput
	}
}
