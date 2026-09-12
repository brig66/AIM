<?php
/**
 * Organization schema — Tasks 1.2, 1.5, 1.6.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_s = (array) AIM_AVT_Settings::get( 'schema', array() );
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'How the markup is added', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'The site already publishes an Organization node on all 119 pages, and its schema graph scored a perfect 1.000 for coherence — no broken @id references, no orphan nodes. That is genuinely rare and must not be broken.', 'aim-avt' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'So the default behaviour is to merge the missing fields into the Organization node you already have, rather than publish a second one. Two Organization nodes would mean two entities where there is one company, and would undo the one structured-data rule the site currently passes outright.', 'aim-avt' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Fields your site already publishes are never overwritten. This plugin fills gaps; it does not overrule your SEO plugin or your editor.', 'aim-avt' ); ?>
	</p>
</div>

<?php AIM_AVT_Admin::form_open( 'save_schema' ); ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Schema output', 'aim-avt' ); ?></h2>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Publish the markup', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="schema_enabled" value="1" <?php checked( ! empty( $aim_s['enabled'] ) ); ?> />
						<?php esc_html_e( 'Add the Organization fields, LocalBusiness nodes, Person nodes and Review markup to the site', 'aim-avt' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Method', 'aim-avt' ); ?></th>
				<td>
					<label><input type="radio" name="schema_mode" value="auto" <?php checked( 'auto', isset( $aim_s['mode'] ) ? $aim_s['mode'] : 'auto' ); ?> /> <strong><?php esc_html_e( 'Automatic (recommended)', 'aim-avt' ); ?></strong> — <?php esc_html_e( 'merge into the existing Organization node; publish a standalone one only if none is found', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="schema_mode" value="merge" <?php checked( 'merge', isset( $aim_s['mode'] ) ? $aim_s['mode'] : '' ); ?> /> <?php esc_html_e( 'Merge only — do nothing if no Organization node is found', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="schema_mode" value="standalone" <?php checked( 'standalone', isset( $aim_s['mode'] ) ? $aim_s['mode'] : '' ); ?> /> <?php esc_html_e( 'Standalone — always publish a separate graph', 'aim-avt' ); ?></label>
					<p class="description"><?php esc_html_e( 'Only choose Standalone if the Health Check shows the merge is not finding your Organization node.', 'aim-avt' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.2 — Organization core fields', 'aim-avt' ); ?> <span class="aim-avt-points">+20</span></h2>
	<p class="description"><?php esc_html_e( 'org_has_core_fields measured false. An AI engine reading the site can confirm a company called Sentrimax Centrifuges exists and can see its logo, and cannot confirm where it is, how to reach it, or how long it has been trading. Every one of those facts is already published in prose.', 'aim-avt' ); ?></p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="org_name"><?php esc_html_e( 'Company name', 'aim-avt' ); ?></label></th>
				<td><input type="text" id="org_name" name="org_name" class="regular-text" value="<?php echo esc_attr( $aim_s['org_name'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="org_legal_name"><?php esc_html_e( 'Registered legal name', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="org_legal_name" name="org_legal_name" class="regular-text" value="<?php echo esc_attr( $aim_s['org_legal_name'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Only if it differs from the trading name. Leave blank otherwise.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="org_url"><?php esc_html_e( 'Website address', 'aim-avt' ); ?></label></th>
				<td><input type="url" id="org_url" name="org_url" class="regular-text" value="<?php echo esc_attr( $aim_s['org_url'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="org_phone"><?php esc_html_e( 'Main telephone', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="org_phone" name="org_phone" class="regular-text" value="<?php echo esc_attr( $aim_s['org_phone'] ); ?>" />
					<p class="description"><?php esc_html_e( 'International format is safest: +1-780-434-1781', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr class="<?php echo empty( $aim_s['org_email'] ) ? 'aim-avt-needs-input' : ''; ?>">
				<th scope="row"><label for="org_email"><?php esc_html_e( 'Main email address', 'aim-avt' ); ?></label></th>
				<td>
					<input type="email" id="org_email" name="org_email" class="regular-text" value="<?php echo esc_attr( $aim_s['org_email'] ); ?>" />
					<p class="description">
						<?php esc_html_e( 'The assessment could not evidence this, so it is deliberately blank. Fill in the address you want AI assistants to hand out — this is one of the core fields the rule measures.', 'aim-avt' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="org_logo"><?php esc_html_e( 'Logo image URL', 'aim-avt' ); ?></label></th>
				<td>
					<input type="url" id="org_logo" name="org_logo" class="large-text" value="<?php echo esc_attr( $aim_s['org_logo'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Leave blank to keep whatever logo your site already declares — logo_in_schema already passes.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="org_description"><?php esc_html_e( 'Description', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="org_description" name="org_description" rows="4" class="large-text"><?php echo esc_textarea( $aim_s['org_description'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'A natural-language sentence describing the business. Do not keyword-stuff it — an engine reads this as prose and padding is visible.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="founding_date"><?php esc_html_e( 'Year founded', 'aim-avt' ); ?></label> <span class="aim-avt-points">+10</span></th>
				<td>
					<input type="text" id="founding_date" name="founding_date" class="small-text" value="<?php echo esc_attr( $aim_s['founding_date'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Task 1.6. Twenty-four years of continuous trading is a meaningful trust signal in an industry where buyers are wary of shops that appear and vanish. Make sure the About page, llms.txt and this field all state the same year.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="iso_cert"><?php esc_html_e( 'Quality certification', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="iso_cert" name="iso_cert" class="regular-text" value="<?php echo esc_attr( $aim_s['iso_cert'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Published as a Certification node on the Organization.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="iso_registrar"><?php esc_html_e( 'Certification registrar', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="iso_registrar" name="iso_registrar" class="regular-text" value="<?php echo esc_attr( $aim_s['iso_registrar'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. BSI, SAI Global', 'aim-avt' ); ?>" />
					<input type="url" name="iso_registrar_url" class="regular-text" value="<?php echo esc_attr( $aim_s['iso_registrar_url'] ); ?>" placeholder="<?php esc_attr_e( 'Registrar URL', 'aim-avt' ); ?>" />
					<p class="description"><?php esc_html_e( 'Task 3.4 asks for the registrar link on the press and recognition page too.', 'aim-avt' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.5 — sameAs profile links', 'aim-avt' ); ?> <span class="aim-avt-points">+40</span></h2>
	<p>
		<?php esc_html_e( 'sameas_count measured 0. Three live, healthy company profiles are linked from the site footer in ordinary HTML and not one of them is declared in schema. sameAs is how a company tells an engine "these other accounts are also me" — it is the mechanism by which a LinkedIn follower count, a Yelp listing and a directory entry get treated as evidence about one entity instead of four unrelated strings.', 'aim-avt' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'The checklist calls this the best value in the document: a five-line change worth 40 category points across two categories.', 'aim-avt' ); ?></strong></p>

	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="same_as"><?php esc_html_e( 'Profile URLs', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="same_as" name="same_as" rows="7" class="large-text code"><?php echo esc_textarea( implode( "\n", (array) $aim_s['same_as'] ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'One URL per line. Use public URLs only — never an admin or dashboard path, which resolves to nothing for a logged-out crawler. Check each one loads while you are signed out.', 'aim-avt' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Google Business Profile URLs entered on the Locations screen are added to this list automatically, so you do not need to repeat them here.', 'aim-avt' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Still to add once verified: the YellowPages.ca listing URL. Both Yelp and YellowPages.ca were confirmed live in the assessment.', 'aim-avt' ); ?>
					</p>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 2.1 — Aggregate rating', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'Rating markup has to be substantiated by reviews a visitor can actually read on the page. Inventing an aggregate rating without them is both a policy violation and pointless, because engines cross-check it.', 'aim-avt' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'With this on, the rating is calculated from your published testimonials and is only emitted on the page that displays them. If you have not entered enough rated, attributed testimonials, nothing is published at all — the setting simply waits.', 'aim-avt' ); ?>
	</p>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Publish aggregateRating', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="rating_enabled" value="1" <?php checked( ! empty( $aim_s['aggregate_rating']['enabled'] ) ); ?> />
						<?php esc_html_e( 'Calculate it from the published testimonials', 'aim-avt' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rating_min"><?php esc_html_e( 'Minimum rated reviews required', 'aim-avt' ); ?></label></th>
				<td>
					<input type="number" min="1" max="50" id="rating_min" name="rating_min" class="small-text" value="<?php echo esc_attr( $aim_s['aggregate_rating']['min_reviews'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Below this count, no rating markup is published. The checklist asks for at least six attributed customer statements on the page.', 'aim-avt' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<?php AIM_AVT_Admin::form_close(); ?>
