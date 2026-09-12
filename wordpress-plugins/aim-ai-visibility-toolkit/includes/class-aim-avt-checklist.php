<?php
/**
 * The checklist, as data.
 *
 * Every task from the AIM AI Visibility Checklist for sentrimax.com, with the
 * points it recovers, the rubric rule it traces to, whether this plugin can
 * perform it, and a live status check so the dashboard reflects the site as it
 * is now rather than as it was on 2026-09-12.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

class AIM_AVT_Checklist {

	/** Status constants. */
	const DONE     = 'done';
	const PARTIAL  = 'partial';
	const TODO     = 'todo';
	const MANUAL   = 'manual';

	/**
	 * The full task list.
	 *
	 * @return array
	 */
	public static function tasks() {
		return array(

			// ---------------- Phase 1 ----------------
			'1.1' => array(
				'phase'    => 1,
				'title'    => __( 'Remove the foreign outbound links from /news/', 'aim-avt' ),
				'points'   => -15,
				'points_label' => __( 'costs 15 pts, correctly', 'aim-avt' ),
				'rules'    => 'trust_eeat.earned_media',
				'effort'   => 1,
				'automated'=> true,
				'urgent'   => true,
				'screen'   => 'aim-avt-phase1',
				'summary'  => __( '51 outbound links to 48 unrelated third-party domains sit on the news page. They are not in the post body and not in the February 2026 archive capture, which points at injection rather than authoring. This deliberately costs 15 points and is still the first thing to do.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_linkguard' ),
			),
			'1.2' => array(
				'phase'    => 1,
				'title'    => __( 'Complete the Organization schema node', 'aim-avt' ),
				'points'   => 20,
				'rules'    => 'structured_data.org_core',
				'effort'   => 1,
				'automated'=> true,
				'screen'   => 'aim-avt-organization',
				'summary'  => __( 'Organization markup is on all 119 pages but org_has_core_fields is false. Telephone, email, address, description and url are all published in prose already.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_org_core' ),
			),
			'1.3' => array(
				'phase'    => 1,
				'title'    => __( 'Add PostalAddress and NAP to the machine-readable layer', 'aim-avt' ),
				'points'   => 43,
				'rules'    => 'entity_recognition.nap_in_schema, nap_consistent, trust_eeat.real_address',
				'effort'   => 1,
				'automated'=> true,
				'depends'  => '1.2',
				'screen'   => 'aim-avt-locations',
				'summary'  => __( 'The single largest recoverable block on the list, and it is data entry. Name, address and phone for all three facilities are published for humans and in none of them can a machine read them.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_nap' ),
			),
			'1.4' => array(
				'phase'    => 1,
				'title'    => __( 'Add a LocalBusiness node for each plant', 'aim-avt' ),
				'points'   => 15,
				'rules'    => 'structured_data.localbusiness',
				'effort'   => 2,
				'automated'=> true,
				'depends'  => '1.3',
				'screen'   => 'aim-avt-locations',
				'summary'  => __( 'No LocalBusiness or ProfessionalService node anywhere, despite three staffed industrial facilities. This is what lets one company be the right answer to three differently-located questions.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_localbusiness' ),
			),
			'1.5' => array(
				'phase'    => 1,
				'title'    => __( 'Declare sameAs links to the existing social profiles', 'aim-avt' ),
				'points'   => 40,
				'rules'    => 'entity_recognition.sameas_present, sameas_valid, structured_data.sameas_valid',
				'effort'   => 1,
				'automated'=> true,
				'screen'   => 'aim-avt-organization',
				'summary'  => __( 'Three live company profiles are linked from the footer in ordinary HTML and not one is declared in schema. The best value in the document.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_sameas' ),
			),
			'1.6' => array(
				'phase'    => 1,
				'title'    => __( 'Add foundingDate to the Organization node', 'aim-avt' ),
				'points'   => 10,
				'rules'    => 'entity_recognition.founding_date',
				'effort'   => 1,
				'automated'=> true,
				'depends'  => '1.2',
				'screen'   => 'aim-avt-organization',
				'summary'  => __( '2002 is stated on the About page and in llms.txt, and no engine can read it as a fact.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_founding' ),
			),
			'1.7' => array(
				'phase'    => 1,
				'title'    => __( 'Fix the 31 pages rendering without an H1', 'aim-avt' ),
				'points'   => 10,
				'rules'    => 'ai_readability.single_h1',
				'effort'   => 2,
				'automated'=> true,
				'screen'   => 'aim-avt-phase1',
				'summary'  => __( 'single_h1_ratio is 0.739. The homepage is one of the 31. The pattern points at one page-builder template setting rather than 31 authoring mistakes.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_h1' ),
			),
			'1.8' => array(
				'phase'    => 1,
				'title'    => __( 'Regenerate llms.txt and resolve the dead language mirrors', 'aim-avt' ),
				'points'   => 10,
				'rules'    => 'technical_ai_readiness.llms_wellformed',
				'effort'   => 1,
				'automated'=> true,
				'screen'   => 'aim-avt-llms',
				'summary'  => __( 'The file is well structured but encoding-corrupted, and it points at /fr/ and /es/, neither of which is in the sitemap. The one file whose whole purpose is orienting AI systems is directing them at withdrawn content.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_llms' ),
			),
			'1.9' => array(
				'phase'    => 1,
				'title'    => __( 'Resolve the duplicate equipment URL', 'aim-avt' ),
				'points'   => 4,
				'rules'    => 'technical_ai_readiness.clean_inventory',
				'effort'   => 1,
				'automated'=> true,
				'screen'   => 'aim-avt-redirects',
				'summary'  => __( '/equipment/alfa-laval-g2-100-2/ is a -2 suffixed copy of /equipment/alfa-laval-g2-100/. One duplicate is not a crisis, but the -2 suffix is how duplicate drift starts.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_redirects' ),
			),

			// ---------------- Phase 2 ----------------
			'2.1' => array(
				'phase'    => 2,
				'title'    => __( 'Build a testimonials page with aggregateRating markup', 'aim-avt' ),
				'points'   => 37,
				'rules'    => 'trust_eeat.rating_schema, testimonials_page, structured_data.rating_markup',
				'effort'   => 2,
				'automated'=> true,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase2',
				'summary'  => __( 'Across 119 pages there is nowhere a customer says, in their own words, that Sentrimax did good work. The plugin builds the page and the markup; the quotes have to come from customers.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_testimonials' ),
			),
			'2.2' => array(
				'phase'    => 2,
				'title'    => __( 'Deepen the leadership Person node', 'aim-avt' ),
				'points'   => 17,
				'rules'    => 'trust_eeat.person_depth, structured_data.person_depth',
				'effort'   => 1,
				'automated'=> true,
				'screen'   => 'aim-avt-people',
				'summary'  => __( 'One Person node exists and carries a name and nothing else. E-E-A-T begins with a named, verifiable, accountable human, and a bare name is the weakest version of that.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_people' ),
			),
			'2.3' => array(
				'phase'    => 2,
				'title'    => __( 'Add author attribution to news content', 'aim-avt' ),
				'points'   => 5,
				'rules'    => 'trust_eeat.author_info',
				'effort'   => 1,
				'automated'=> true,
				'depends'  => '2.2',
				'screen'   => 'aim-avt-phase2',
				'summary'  => __( 'The single news post has no byline and no author schema. The same words under a named engineer with a knowsAbout profile read as expertise.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_byline' ),
			),
			'2.4' => array(
				'phase'    => 2,
				'title'    => __( 'Claim Google Business Profiles for all three plants', 'aim-avt' ),
				'points'   => 0,
				'points_label' => __( 'no projected points — live input', 'aim-avt' ),
				'rules'    => 'ai_search_visibility.directory_presence',
				'effort'   => 2,
				'automated'=> false,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase2',
				'summary'  => __( 'Four directory placements were confirmed against a top band of six, and no Google Business Profile could be evidenced for any plant. Claim them off-site, then paste each profile URL into the Locations screen so it flows into sameAs and the map embeds.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_gbp' ),
			),
			'2.5' => array(
				'phase'    => 2,
				'title'    => __( 'Correct heading hierarchy on pages that skip a level', 'aim-avt' ),
				'points'   => 5,
				'rules'    => 'ai_readability.heading_hierarchy',
				'effort'   => 2,
				'automated'=> true,
				'depends'  => '1.7',
				'screen'   => 'aim-avt-phase1',
				'summary'  => __( 'clean_hierarchy_ratio is 0.723. Do this after the H1 fix, because correcting the H1 changes the hierarchy on the 31 affected pages.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_hierarchy' ),
			),

			// ---------------- Phase 3 ----------------
			'3.1' => array(
				'phase'    => 3,
				'title'    => __( 'Publish three case studies', 'aim-avt' ),
				'points'   => 28,
				'rules'    => 'content_authority.case_studies, trust_eeat.case_study_proof',
				'effort'   => 3,
				'automated'=> false,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase3',
				'summary'  => __( 'case_study_score is 0. The largest content gap in the assessment, scored twice because proof of delivered work is both a content signal and the core E-E-A-T signal. The plugin supplies the structure and the Article markup; the shop supplies the jobs.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_case_studies' ),
			),
			'3.2' => array(
				'phase'    => 3,
				'title'    => __( 'Restore the news section to substantive, original publishing', 'aim-avt' ),
				'points'   => 15,
				'rules'    => 'content_authority.blog_originality',
				'effort'   => 3,
				'automated'=> false,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase3',
				'summary'  => __( 'blog_substantial_ratio is 0.0 — one post of 372 words, against an early-2026 capture showing four or more articles. Target: two technical articles a month, 1,200+ words each, bylined.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_blog' ),
			),
			'3.3' => array(
				'phase'    => 3,
				'title'    => __( 'Put real numbers on the repair-cost page', 'aim-avt' ),
				'points'   => 13,
				'rules'    => 'content_authority.pricing_transparency, trust_eeat.pricing_transparency',
				'effort'   => 2,
				'automated'=> true,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase3',
				'summary'  => __( 'A 1,899-word page at /decanter-centrifuge-repair-cost/ discusses cost at length without stating a figure. Banded ranges with stated assumptions are enough — precision is not required, presence is.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_pricing' ),
			),
			'3.4' => array(
				'phase'    => 3,
				'title'    => __( 'Rebuild genuine earned media', 'aim-avt' ),
				'points'   => 7,
				'rules'    => 'trust_eeat.earned_media',
				'effort'   => 3,
				'automated'=> false,
				'needs_client' => true,
				'depends'  => '1.1, 3.1',
				'screen'   => 'aim-avt-phase3',
				'summary'  => __( 'Task 1.1 takes this rule to zero by removing 48 counted placements that were link spam. This replaces them with real ones and a page that collects them.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_press' ),
			),
			'3.5' => array(
				'phase'    => 3,
				'title'    => __( 'Extend the About page', 'aim-avt' ),
				'points'   => 4,
				'rules'    => 'content_authority.about_depth',
				'effort'   => 1,
				'automated'=> false,
				'needs_client' => true,
				'screen'   => 'aim-avt-phase3',
				'summary'  => __( 'about_words is 668, earning 6 of 10 points; the next band is 900. The About page is the one most often quoted when an engine is asked to describe a business.', 'aim-avt' ),
				'check'    => array( __CLASS__, 'check_about' ),
			),
		);
	}

	/**
	 * Resolve a task's live status.
	 *
	 * @param string $id Task ID.
	 * @return array{status:string,note:string}
	 */
	public static function status( $id ) {
		$tasks = self::tasks();
		if ( ! isset( $tasks[ $id ] ) ) {
			return array( 'status' => self::TODO, 'note' => '' );
		}

		$task = $tasks[ $id ];

		// A manually-ticked task always wins — the operator knows things the
		// plugin cannot see, such as whether a Google profile was claimed.
		$manual = (array) AIM_AVT_Settings::get( 'manual_progress', array() );
		if ( ! empty( $manual[ $id ] ) ) {
			return array(
				'status' => self::DONE,
				'note'   => __( 'Marked complete by hand.', 'aim-avt' ),
			);
		}

		if ( is_callable( $task['check'] ) ) {
			return call_user_func( $task['check'] );
		}

		return array( 'status' => self::TODO, 'note' => '' );
	}

	// -------------------------------------------------------------------
	// Status checks.
	// -------------------------------------------------------------------

	/** @return array */
	public static function check_linkguard() {
		$report = AIM_AVT_Linkguard::last_report();
		$live   = (bool) AIM_AVT_Settings::get( 'linkguard.live_enabled' );

		if ( ! $report ) {
			return array(
				'status' => self::TODO,
				'note'   => __( 'Not scanned yet. Run the scan on the Phase 1 screen — this is the urgent one.', 'aim-avt' ),
			);
		}

		$db_hits  = count( $report['posts'] ) + count( $report['comments'] );
		$rendered = ! empty( $report['rendered_only'] );

		if ( 0 === $db_hits && ! $rendered ) {
			return array( 'status' => self::DONE, 'note' => __( 'Last scan found no foreign outbound links.', 'aim-avt' ) );
		}
		if ( $rendered && $live ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => __( 'Links are being stripped from the rendered page, but they are still coming from somewhere outside the post content. Treat that as a security incident.', 'aim-avt' ),
			);
		}
		if ( $rendered ) {
			return array(
				'status' => self::TODO,
				'note'   => __( 'Links render on the page but are not in the post content or comments. That is the injection signature — switch on the live filter now and audit plugins, themes and admin accounts.', 'aim-avt' ),
			);
		}
		return array(
			'status' => self::TODO,
			'note'   => sprintf(
				/* translators: %d: number of items. */
				__( '%d item(s) in the database still carry foreign links.', 'aim-avt' ),
				$db_hits
			),
		);
	}

	/** @return array */
	public static function check_org_core() {
		if ( ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Schema output is switched off.', 'aim-avt' ) );
		}
		$fields  = AIM_AVT_Schema::organization_fields();
		$missing = array();
		foreach ( array( 'telephone' => __( 'telephone', 'aim-avt' ), 'email' => __( 'email', 'aim-avt' ), 'address' => __( 'postal address', 'aim-avt' ), 'description' => __( 'description', 'aim-avt' ), 'url' => __( 'url', 'aim-avt' ) ) as $key => $label ) {
			if ( empty( $fields[ $key ] ) ) {
				$missing[] = $label;
			}
		}
		if ( ! $missing ) {
			return array( 'status' => self::DONE, 'note' => __( 'All core fields are set and being published.', 'aim-avt' ) );
		}
		return array(
			'status' => self::PARTIAL,
			'note'   => sprintf(
				/* translators: %s: comma-separated field names. */
				__( 'Still missing: %s.', 'aim-avt' ),
				implode( ', ', $missing )
			),
		);
	}

	/** @return array */
	public static function check_nap() {
		if ( ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Schema output is switched off.', 'aim-avt' ) );
		}
		$locations = (array) AIM_AVT_Settings::get( 'schema.locations', array() );
		$complete  = 0;
		foreach ( $locations as $location ) {
			if ( AIM_AVT_Schema::postal_address( $location ) && ! empty( $location['phone'] ) ) {
				$complete++;
			}
		}
		if ( $complete && $complete === count( $locations ) ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of facilities. */
					__( 'All %d facilities carry a complete, machine-readable address and phone.', 'aim-avt' ),
					$complete
				),
			);
		}
		if ( $complete ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: 1: complete count, 2: total. */
					__( '%1$d of %2$d facilities are complete.', 'aim-avt' ),
					$complete,
					count( $locations )
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'No facility has a complete address yet.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_localbusiness() {
		if ( ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Schema output is switched off.', 'aim-avt' ) );
		}
		$nodes = AIM_AVT_Schema::local_business_nodes( 'test' );
		$count = count( $nodes );
		if ( $count >= 3 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of nodes. */
					__( '%d LocalBusiness nodes are being published.', 'aim-avt' ),
					$count
				),
			);
		}
		if ( $count ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: %d: number of nodes. */
					__( 'Only %d LocalBusiness node(s) can be built — the rest are missing address fields.', 'aim-avt' ),
					$count
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'No LocalBusiness node can be built yet.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_sameas() {
		if ( ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Schema output is switched off.', 'aim-avt' ) );
		}
		$count = count( AIM_AVT_Schema::same_as_urls() );
		if ( $count >= 3 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of URLs. */
					__( '%d profile URLs are declared.', 'aim-avt' ),
					$count
				),
			);
		}
		if ( $count ) {
			return array( 'status' => self::PARTIAL, 'note' => __( 'Fewer than three profiles are declared.', 'aim-avt' ) );
		}
		return array( 'status' => self::TODO, 'note' => __( 'No sameAs URLs are declared.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_founding() {
		if ( ! AIM_AVT_Settings::get( 'schema.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Schema output is switched off.', 'aim-avt' ) );
		}
		$year = AIM_AVT_Settings::get( 'schema.founding_date' );
		if ( $year ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %s: year. */
					__( 'foundingDate is published as %s.', 'aim-avt' ),
					$year
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'foundingDate is not set.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_h1() {
		if ( AIM_AVT_Settings::get( 'headings.h1_enabled' ) ) {
			return array( 'status' => self::DONE, 'note' => __( 'The H1 repair is running on every page. Run the health check to confirm the ratio.', 'aim-avt' ) );
		}
		return array( 'status' => self::TODO, 'note' => __( 'The H1 repair is switched off.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_hierarchy() {
		$enabled = AIM_AVT_Settings::get( 'headings.hierarchy_enabled' );
		$mode    = AIM_AVT_Settings::get( 'headings.hierarchy_mode' );
		if ( $enabled && 'fix' === $mode ) {
			return array( 'status' => self::DONE, 'note' => __( 'Heading levels are being corrected on output.', 'aim-avt' ) );
		}
		if ( $enabled ) {
			return array( 'status' => self::PARTIAL, 'note' => __( 'Running in report-only mode — nothing is being changed yet.', 'aim-avt' ) );
		}
		return array( 'status' => self::TODO, 'note' => __( 'Heading hierarchy repair is switched off.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_llms() {
		$status = AIM_AVT_LLMS::status();
		if ( $status['exists'] && ! $status['mojibake'] && empty( $status['dead_links'] ) ) {
			return array( 'status' => self::DONE, 'note' => __( 'The published file is clean UTF-8 and every link resolves.', 'aim-avt' ) );
		}
		if ( ! $status['exists'] && AIM_AVT_Settings::get( 'llms.enabled' ) ) {
			return array( 'status' => self::DONE, 'note' => __( 'No physical file — the plugin is serving a generated one.', 'aim-avt' ) );
		}
		$problems = array();
		if ( $status['mojibake'] ) {
			$problems[] = __( 'character-encoding corruption', 'aim-avt' );
		}
		if ( ! empty( $status['dead_links'] ) ) {
			$problems[] = sprintf(
				/* translators: %d: number of links. */
				__( '%d link(s) pointing at pages that do not resolve', 'aim-avt' ),
				count( $status['dead_links'] )
			);
		}
		if ( ! $problems ) {
			return array( 'status' => self::TODO, 'note' => __( 'No llms.txt is being served.', 'aim-avt' ) );
		}
		return array(
			'status' => self::TODO,
			'note'   => sprintf(
				/* translators: %s: list of problems. */
				__( 'The published file has %s.', 'aim-avt' ),
				implode( ' and ', $problems )
			),
		);
	}

	/** @return array */
	public static function check_redirects() {
		if ( ! AIM_AVT_Settings::get( 'redirects.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Redirects are switched off.', 'aim-avt' ) );
		}
		$count = count( AIM_AVT_Redirects::rules() );
		return array(
			'status' => $count ? self::DONE : self::TODO,
			'note'   => sprintf(
				/* translators: %d: number of rules. */
				__( '%d redirect rule(s) active.', 'aim-avt' ),
				$count
			),
		);
	}

	/** @return array */
	public static function check_testimonials() {
		$count = count( AIM_AVT_Content::testimonials() );
		$page  = (int) get_option( 'aim_avt_testimonials_page', 0 );
		$live  = $page && 'publish' === get_post_status( $page );
		$rating= AIM_AVT_Schema::aggregate_rating();

		if ( $count >= 6 && $live && $rating ) {
			return array( 'status' => self::DONE, 'note' => __( 'Six or more attributed testimonials are published with a substantiated aggregate rating.', 'aim-avt' ) );
		}
		if ( $count ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: 1: count of testimonials, 2: whether the page is published. */
					__( '%1$d testimonial(s) entered. Page published: %2$s. The checklist asks for at least six.', 'aim-avt' ),
					$count,
					$live ? __( 'yes', 'aim-avt' ) : __( 'no', 'aim-avt' )
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'No testimonials yet. Collect at least six attributed customer statements.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_people() {
		$people = (array) AIM_AVT_Settings::get( 'schema.people', array() );
		$deep   = 0;
		$named  = 0;
		foreach ( $people as $person ) {
			if ( empty( $person['name'] ) ) {
				continue;
			}
			$named++;
			$has_depth = ! empty( $person['job_title'] ) && ! empty( $person['description'] ) && ! empty( $person['linkedin'] ) && ! empty( $person['knows_about'] );
			if ( $has_depth ) {
				$deep++;
			}
		}
		if ( ! $named ) {
			return array( 'status' => self::TODO, 'note' => __( 'No leadership person has been named yet.', 'aim-avt' ) );
		}
		if ( $deep === $named ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of people. */
					__( '%d Person node(s) carry job title, description, sameAs and knowsAbout.', 'aim-avt' ),
					$deep
				),
			);
		}
		return array(
			'status' => self::PARTIAL,
			'note'   => sprintf(
				/* translators: 1: deep count, 2: named count. */
				__( '%1$d of %2$d named people have the full field set. A bare name is the weakest version of this signal.', 'aim-avt' ),
				$deep,
				$named
			),
		);
	}

	/** @return array */
	public static function check_byline() {
		if ( ! AIM_AVT_Settings::get( 'byline.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Author attribution is switched off.', 'aim-avt' ) );
		}
		$author = AIM_AVT_Schema::default_author_id();
		if ( ! $author ) {
			return array( 'status' => self::PARTIAL, 'note' => __( 'Attribution is on but no Person has been named to attribute to.', 'aim-avt' ) );
		}
		return array( 'status' => self::DONE, 'note' => __( 'Posts carry a visible byline and an author reference in schema.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_gbp() {
		$locations = (array) AIM_AVT_Settings::get( 'schema.locations', array() );
		$claimed   = 0;
		foreach ( $locations as $location ) {
			if ( ! empty( $location['gbp_url'] ) ) {
				$claimed++;
			}
		}
		if ( $claimed >= count( $locations ) && $claimed > 0 ) {
			return array( 'status' => self::DONE, 'note' => __( 'A Business Profile URL is recorded for every plant.', 'aim-avt' ) );
		}
		if ( $claimed ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: 1: claimed, 2: total. */
					__( '%1$d of %2$d plants have a Business Profile URL recorded.', 'aim-avt' ),
					$claimed,
					count( $locations )
				),
			);
		}
		return array( 'status' => self::MANUAL, 'note' => __( 'Claim the profiles at business.google.com, then paste each URL into the Locations screen.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_case_studies() {
		$count = count( AIM_AVT_Content::case_studies() );
		if ( $count >= 3 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of case studies. */
					__( '%d case studies published — the rubric top band is three.', 'aim-avt' ),
					$count
				),
			);
		}
		if ( $count ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: %d: number of case studies. */
					__( '%d of 3 published.', 'aim-avt' ),
					$count
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'None yet. Nominate one engineer as the source for case-study material — that decision unblocks all of Phase 3.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_blog() {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		$substantial = 0;
		foreach ( $posts as $post ) {
			if ( str_word_count( wp_strip_all_tags( $post->post_content ) ) >= 1200 ) {
				$substantial++;
			}
		}
		$total = count( $posts );
		if ( $total && $substantial / $total >= 0.5 && $substantial >= 4 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: 1: substantial count, 2: total. */
					__( '%1$d of %2$d posts are 1,200+ words.', 'aim-avt' ),
					$substantial,
					$total
				),
			);
		}
		return array(
			'status' => $substantial ? self::PARTIAL : self::TODO,
			'note'   => sprintf(
				/* translators: 1: substantial count, 2: total. */
				__( '%1$d of %2$d published posts reach 1,200 words. Target is two substantive articles a month.', 'aim-avt' ),
				$substantial,
				$total
			),
		);
	}

	/** @return array */
	public static function check_pricing() {
		if ( ! AIM_AVT_Settings::get( 'pricing.enabled' ) ) {
			return array( 'status' => self::TODO, 'note' => __( 'Pricing output is switched off.', 'aim-avt' ) );
		}
		$filled = 0;
		foreach ( (array) AIM_AVT_Settings::get( 'pricing.bands', array() ) as $band ) {
			if ( '' !== trim( (string) $band['min'] ) || '' !== trim( (string) $band['max'] ) ) {
				$filled++;
			}
		}
		if ( $filled >= 3 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: number of bands. */
					__( '%d price bands carry real figures and Offer markup.', 'aim-avt' ),
					$filled
				),
			);
		}
		if ( $filled ) {
			return array( 'status' => self::PARTIAL, 'note' => __( 'Some bands are still empty. An empty band publishes nothing.', 'aim-avt' ) );
		}
		return array( 'status' => self::TODO, 'note' => __( 'No figures entered. A page about cost with no numbers is the one page guaranteed not to be cited for it.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_press() {
		$items = AIM_AVT_Content::press_items();
		$page  = (int) get_option( 'aim_avt_press_page', 0 );
		$live  = $page && 'publish' === get_post_status( $page );

		if ( count( $items ) >= 6 && $live ) {
			return array( 'status' => self::DONE, 'note' => __( 'A published press page lists six or more genuine placements.', 'aim-avt' ) );
		}
		if ( $items ) {
			return array(
				'status' => self::PARTIAL,
				'note'   => sprintf(
					/* translators: %d: number of items. */
					__( '%d placement(s) recorded.', 'aim-avt' ),
					count( $items )
				),
			);
		}
		return array( 'status' => self::TODO, 'note' => __( 'Nothing recorded yet. Engage no vendor offering paid link placements — that is the pattern Task 1.1 exists to remove.', 'aim-avt' ) );
	}

	/** @return array */
	public static function check_about() {
		$page = get_page_by_path( 'about' );
		if ( ! $page ) {
			$page = get_page_by_path( 'about-us' );
		}
		if ( ! $page ) {
			return array( 'status' => self::MANUAL, 'note' => __( 'Could not find an About page automatically. Target is 900+ words.', 'aim-avt' ) );
		}
		$words = str_word_count( wp_strip_all_tags( $page->post_content ) );
		if ( $words >= 900 ) {
			return array(
				'status' => self::DONE,
				'note'   => sprintf(
					/* translators: %d: word count. */
					__( 'The About page is %d words — past the 900 band.', 'aim-avt' ),
					$words
				),
			);
		}
		return array(
			'status' => self::TODO,
			'note'   => sprintf(
				/* translators: 1: current words, 2: words still needed. */
				__( 'The About page is %1$d words. Another %2$d words clears the next band.', 'aim-avt' ),
				$words,
				900 - $words
			),
		);
	}

	/**
	 * Totals for the dashboard header.
	 *
	 * @return array
	 */
	public static function totals() {
		$counts = array(
			self::DONE    => 0,
			self::PARTIAL => 0,
			self::TODO    => 0,
			self::MANUAL  => 0,
		);
		$by_phase = array( 1 => $counts, 2 => $counts, 3 => $counts );

		foreach ( self::tasks() as $id => $task ) {
			$status = self::status( $id );
			$counts[ $status['status'] ]++;
			$by_phase[ $task['phase'] ][ $status['status'] ]++;
		}

		return array(
			'counts'   => $counts,
			'by_phase' => $by_phase,
			'total'    => count( self::tasks() ),
		);
	}
}
