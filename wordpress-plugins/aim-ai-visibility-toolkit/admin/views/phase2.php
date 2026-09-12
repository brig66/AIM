<?php
/**
 * Phase 2 — trust signals.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_byline        = (array) AIM_AVT_Settings::get( 'byline', array() );
$aim_testimonials  = AIM_AVT_Content::testimonials();
$aim_page_id       = (int) get_option( 'aim_avt_testimonials_page', 0 );
$aim_rating        = AIM_AVT_Schema::aggregate_rating();
$aim_min           = (int) AIM_AVT_Settings::get( 'schema.aggregate_rating.min_reviews', 3 );
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Why this phase matters', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'Trust & E-E-A-T scores 27 out of 100 with seven of nine rules at zero. The assessment showed exactly what that costs: asked which North American centrifuge repair firm has the best reviews, Perplexity could find no customer review data for Sentrimax at all, surfaced an Indeed employee rating of 3.0 out of 5 across eight reviews instead, and then warned the reader not to use it to choose a repair provider.', 'aim-avt' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'That is the most authoritative quality signal about the company currently available on the open web. Phase 2 replaces it.', 'aim-avt' ); ?></strong></p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 2.1 — Testimonials with rating markup', 'aim-avt' ); ?> <span class="aim-avt-points">+37</span></h2>
	<p>
		<?php esc_html_e( 'aggregate_rating_present is false and testimonials_page_present is false. Across 119 pages there is nowhere a customer says, in their own words, that Sentrimax did good work — and no rating markup of any kind. This is the largest single rule in the Trust category.', 'aim-avt' ); ?>
	</p>

	<h3><?php esc_html_e( 'Step 1 — Collect the quotes', 'aim-avt' ); ?></h3>
	<p><?php esc_html_e( 'The checklist asks for at least six attributed customer statements. For each one, capture:', 'aim-avt' ); ?></p>
	<ul class="aim-avt-bullets">
		<li><?php esc_html_e( 'The company — or a characterization like "a Gulf Coast rendering plant" where a name cannot be used.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'The person\'s role.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'The machine make and model.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'What was done.', 'aim-avt' ); ?></li>
	</ul>
	<p class="description"><?php esc_html_e( 'In parallel, ask the four or five customers most likely to agree to leave a Google review. That creates the public rating signal the engines are currently looking for and failing to find — and no plugin can create it for you.', 'aim-avt' ); ?></p>

	<h3><?php esc_html_e( 'Step 2 — Enter them', 'aim-avt' ); ?></h3>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . AIM_AVT_Content::TESTIMONIAL ) ); ?>"><?php esc_html_e( 'Add a testimonial', 'aim-avt' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . AIM_AVT_Content::TESTIMONIAL ) ); ?>"><?php esc_html_e( 'Manage testimonials', 'aim-avt' ); ?></a>
	</p>

	<div class="aim-avt-progress">
		<p>
			<?php
			printf(
				/* translators: 1: current count, 2: target count. */
				esc_html__( '%1$d of %2$d testimonials published.', 'aim-avt' ),
				count( $aim_testimonials ),
				6
			);
			?>
			<?php if ( $aim_rating ) : ?>
				<span class="aim-avt-badge aim-avt-badge--done">
					<?php
					printf(
						/* translators: 1: rating value, 2: review count. */
						esc_html__( 'Aggregate rating %1$s from %2$s reviews is being published.', 'aim-avt' ),
						esc_html( $aim_rating['ratingValue'] ),
						esc_html( $aim_rating['reviewCount'] )
					);
					?>
				</span>
			<?php else : ?>
				<span class="aim-avt-badge aim-avt-badge--todo">
					<?php
					printf(
						/* translators: %d: minimum reviews. */
						esc_html__( 'No aggregate rating is being published — it needs at least %d rated, attributed testimonials, and the setting switched on under Organization.', 'aim-avt' ),
						$aim_min
					);
					?>
				</span>
			<?php endif; ?>
		</p>
	</div>

	<h3><?php esc_html_e( 'Step 3 — Publish the page', 'aim-avt' ); ?></h3>
	<?php if ( $aim_page_id && get_post( $aim_page_id ) ) : ?>
		<p>
			<?php esc_html_e( 'Your testimonials page:', 'aim-avt' ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $aim_page_id ) ); ?>"><?php echo esc_html( get_the_title( $aim_page_id ) ); ?></a>
			<span class="aim-avt-badge aim-avt-badge--<?php echo 'publish' === get_post_status( $aim_page_id ) ? 'done' : 'partial'; ?>">
				<?php echo esc_html( 'publish' === get_post_status( $aim_page_id ) ? __( 'Published', 'aim-avt' ) : __( 'Still a draft', 'aim-avt' ) ); ?>
			</span>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'No testimonials page yet. This creates one as a draft containing the shortcode, so you can review and style it before publishing.', 'aim-avt' ); ?></p>
		<?php
		echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
			'create_page',
			__( 'Create the testimonials page', 'aim-avt' ),
			'button button-primary',
			'',
			array( 'page_key' => 'testimonials' )
		);
		?>
	<?php endif; ?>
	<p class="description">
		<?php esc_html_e( 'To put the testimonials on a page you have already built, paste this shortcode into it instead:', 'aim-avt' ); ?>
		<code>[aim_testimonials]</code>
	</p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 2.3 — Author attribution on news content', 'aim-avt' ); ?> <span class="aim-avt-points">+5</span></h2>
	<p>
		<?php esc_html_e( 'blog_author_present measures false. The news section carries a single post with no byline and no author schema. Unattributed technical writing reads to an engine as corporate copy; the same words under a named engineer with a knowsAbout profile read as expertise.', 'aim-avt' ); ?>
	</p>
	<?php if ( ! AIM_AVT_Schema::default_author_id() ) : ?>
		<div class="aim-avt-alert aim-avt-alert--warn">
			<p>
				<?php esc_html_e( 'No leadership person has been named yet, so there is nobody to attribute posts to.', 'aim-avt' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aim-avt-people' ) ); ?>"><?php esc_html_e( 'Add one first (Task 2.2)', 'aim-avt' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php AIM_AVT_Admin::form_open( 'save_byline' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Attribution', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="byline_enabled" value="1" <?php checked( ! empty( $aim_byline['enabled'] ) ); ?> />
						<?php esc_html_e( 'Add an author reference to the schema on news posts and case studies', 'aim-avt' ); ?>
					</label><br />
					<label>
						<input type="checkbox" name="byline_visible" value="1" <?php checked( ! empty( $aim_byline['show_visible'] ) ); ?> />
						<?php esc_html_e( 'Also show a visible byline above the post text', 'aim-avt' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'If your theme already prints a byline, leave the visible one off to avoid showing it twice.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Apply to', 'aim-avt' ); ?></th>
				<td>
					<?php
					$aim_types = array(
						'post'                     => __( 'News posts', 'aim-avt' ),
						AIM_AVT_Content::CASE_STUDY => __( 'Case studies', 'aim-avt' ),
					);
					foreach ( $aim_types as $aim_type => $aim_label ) :
						?>
						<label style="display:inline-block;min-width:11em;">
							<input type="checkbox" name="byline_types[]" value="<?php echo esc_attr( $aim_type ); ?>" <?php checked( in_array( $aim_type, (array) $aim_byline['post_types'], true ) ); ?> />
							<?php echo esc_html( $aim_label ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="byline_label"><?php esc_html_e( 'Byline wording', 'aim-avt' ); ?></label></th>
				<td><input type="text" id="byline_label" name="byline_label" class="small-text" value="<?php echo esc_attr( isset( $aim_byline['label'] ) ? $aim_byline['label'] : 'By' ); ?>" /></td>
			</tr>
		</tbody>
	</table>
	<?php AIM_AVT_Admin::form_close(); ?>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 2.4 — Google Business Profiles', 'aim-avt' ); ?> <span class="aim-avt-points aim-avt-points--special"><?php esc_html_e( 'no projected points — live input', 'aim-avt' ); ?></span></h2>
	<p>
		<?php esc_html_e( 'directory_placements measured 4, one short of the rubric top band at 6. Four were confirmed: Yelp (Edmonton), YellowPages.ca, the Ethanol Producer Magazine industry directory and COSSD. No Google Business Profile could be evidenced for any of the three plants — yet a Sentrimax entity does appear in ChatGPT\'s map panel, which means a maps entity exists somewhere that the website is not claiming or connecting to.', 'aim-avt' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'This one is off-site work. The plugin cannot claim a listing for you, but it uses the result the moment you paste the URL in.', 'aim-avt' ); ?></strong></p>
	<ol class="aim-avt-bullets">
		<li><?php esc_html_e( 'Confirm whether Sentrimax already owns verified profiles for Edmonton, Mansfield and Kitchener, and claim any that are unclaimed.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Complete each one fully: category, NAP matching the Locations screen exactly, hours, service list, photographs of the shop floor.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Paste each profile URL into the Locations screen. It flows into sameAs and into the map embeds automatically.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Add a Thomasnet supplier profile — Thomasnet category pages already surface in this vertical and Sentrimax is absent from them.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Pursue Water Environment Federation and relevant ethanol and rendering association directories. Perplexity cited the Ethanol Producer directory when describing Sentrimax, which shows these sources are actively used.', 'aim-avt' ); ?></li>
	</ol>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aim-avt-locations' ) ); ?>"><?php esc_html_e( 'Go to Locations to paste the profile URLs', 'aim-avt' ); ?></a>
	</p>

	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Plant', 'aim-avt' ); ?></th><th><?php esc_html_e( 'Business Profile URL recorded', 'aim-avt' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( (array) AIM_AVT_Settings::get( 'schema.locations', array() ) as $aim_loc ) : ?>
			<tr>
				<td><?php echo esc_html( $aim_loc['name'] ? $aim_loc['name'] : $aim_loc['locality'] ); ?></td>
				<td>
					<?php if ( ! empty( $aim_loc['gbp_url'] ) ) : ?>
						<span class="aim-avt-badge aim-avt-badge--done"><?php esc_html_e( 'Yes', 'aim-avt' ); ?></span>
						<code><?php echo esc_html( $aim_loc['gbp_url'] ); ?></code>
					<?php else : ?>
						<span class="aim-avt-badge aim-avt-badge--todo"><?php esc_html_e( 'Not yet', 'aim-avt' ); ?></span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
