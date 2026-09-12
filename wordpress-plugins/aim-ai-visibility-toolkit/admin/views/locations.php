<?php
/**
 * Facilities — Tasks 1.3, 1.4, 2.4.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_locations = (array) AIM_AVT_Settings::get( 'schema.locations', array() );

// Always render one spare blank row so a fourth plant can be added.
$aim_rows = $aim_locations;
$aim_rows[] = array(
	'key'           => '',
	'name'          => '',
	'head_office'   => false,
	'street'        => '',
	'locality'      => '',
	'region'        => '',
	'postal_code'   => '',
	'country'       => '',
	'phone'         => '',
	'email'         => '',
	'opening_hours' => '',
	'area_served'   => '',
	'latitude'      => '',
	'longitude'     => '',
	'gbp_url'       => '',
	'place_id'      => '',
);
?>

<div class="aim-avt-card aim-avt-card--highlight">
	<h2><?php esc_html_e( 'Task 1.3 — Name, address and phone in the machine-readable layer', 'aim-avt' ); ?> <span class="aim-avt-points">+43</span></h2>
	<p>
		<strong><?php esc_html_e( 'This is the single largest recoverable block in the checklist, and it is a data-entry task.', 'aim-avt' ); ?></strong>
		<?php esc_html_e( 'nap_in_schema measured false and nap_consistency_ratio measured 0.0 — not because the addresses disagree, but because there are no addresses in the markup to compare. Name, address and phone for all three facilities are published on the contact page in human-readable form, and in none of them can a machine read them.', 'aim-avt' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'For a business asking AI engines to recommend it for work in Texas, Alberta and Ontario, this is the gap that matters most. An engine that cannot resolve where a company physically is will not recommend it for location-bound work, and centrifuge repair is location-bound work.', 'aim-avt' ); ?>
	</p>
	<div class="aim-avt-alert aim-avt-alert--info">
		<p><strong><?php esc_html_e( 'Before you save:', 'aim-avt' ); ?></strong> <?php esc_html_e( 'consistency is what is being measured, so a single mismatched abbreviation costs the rule. Check each address below against your current records, and then check the rendered contact page says exactly the same thing — including suite and unit designations and the NW in the Edmonton address.', 'aim-avt' ); ?></p>
		<p><?php esc_html_e( 'The values pre-filled here are the ones recorded in the assessment. Verify them; do not assume.', 'aim-avt' ); ?></p>
	</div>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.4 — A LocalBusiness node for each plant', 'aim-avt' ); ?> <span class="aim-avt-points">+15</span></h2>
	<p>
		<?php esc_html_e( 'localbusiness_present measured false: no LocalBusiness or ProfessionalService node anywhere on the site, despite three staffed industrial facilities. The homepage embeds three Google Maps frames, but they are plain address-query embeds which carry no place entity — they draw a pin for a human and tell a machine nothing.', 'aim-avt' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'A complete row below becomes a LocalBusiness node linked to the parent Organization by @id, so the graph stays connected. A row missing its street or town is skipped rather than published half-formed, because a malformed node scores nothing and pollutes the graph.', 'aim-avt' ); ?>
	</p>
</div>

<?php AIM_AVT_Admin::form_open( 'save_locations' ); ?>

<?php foreach ( $aim_rows as $aim_i => $aim_loc ) : ?>
	<?php
	$aim_is_new = ( $aim_i >= count( $aim_locations ) );
	$aim_key    = isset( $aim_loc['key'] ) ? $aim_loc['key'] : '';
	?>
	<div class="aim-avt-card aim-avt-location-card<?php echo $aim_is_new ? ' aim-avt-location-card--new' : ''; ?>">
		<h3>
			<?php
			if ( $aim_is_new ) {
				esc_html_e( 'Add another facility', 'aim-avt' );
			} else {
				echo esc_html( $aim_loc['name'] ? $aim_loc['name'] : $aim_loc['locality'] );
			}
			?>
		</h3>

		<input type="hidden" name="loc[<?php echo esc_attr( $aim_i ); ?>][key]" value="<?php echo esc_attr( $aim_key ); ?>" />

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Facility name', 'aim-avt' ); ?></th>
					<td><input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][name]" value="<?php echo esc_attr( $aim_loc['name'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Street address', 'aim-avt' ); ?></th>
					<td><input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][street]" value="<?php echo esc_attr( $aim_loc['street'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Town or city', 'aim-avt' ); ?></th>
					<td><input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][locality]" value="<?php echo esc_attr( $aim_loc['locality'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'State or province', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="small-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][region]" value="<?php echo esc_attr( $aim_loc['region'] ); ?>" />
						<label><?php esc_html_e( 'Postal or ZIP code', 'aim-avt' ); ?> <input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][postal_code]" value="<?php echo esc_attr( $aim_loc['postal_code'] ); ?>" /></label>
						<label><?php esc_html_e( 'Country code', 'aim-avt' ); ?> <input type="text" class="small-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][country]" value="<?php echo esc_attr( $aim_loc['country'] ); ?>" placeholder="CA" /></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Telephone', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][phone]" value="<?php echo esc_attr( $aim_loc['phone'] ); ?>" />
						<label><?php esc_html_e( 'Email', 'aim-avt' ); ?> <input type="email" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][email]" value="<?php echo esc_attr( $aim_loc['email'] ); ?>" /></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Opening hours', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][opening_hours]" value="<?php echo esc_attr( $aim_loc['opening_hours'] ); ?>" placeholder="Mo-Fr 08:00-17:00" />
						<p class="description"><?php esc_html_e( 'Format: Mo-Fr 08:00-17:00. Separate several blocks with a comma, for example: Mo-Fr 07:00-17:00, Sa 08:00-12:00. Verify these against the real shop hours — a wrong opening time is worse than none.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Area served', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="large-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][area_served]" value="<?php echo esc_attr( $aim_loc['area_served'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated regions. This is what lets one company be the correct answer to three differently-located questions.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Head office', 'aim-avt' ); ?></th>
					<td>
						<label>
							<input type="radio" name="head_office" value="<?php echo esc_attr( $aim_key ); ?>" <?php checked( ! empty( $aim_loc['head_office'] ) ); ?> <?php disabled( '' === $aim_key ); ?> />
							<?php esc_html_e( 'This address goes on the Organization node', 'aim-avt' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Google Business Profile', 'aim-avt' ); ?><br /><span class="aim-avt-tag"><?php esc_html_e( 'Task 2.4', 'aim-avt' ); ?></span></th>
					<td>
						<input type="url" class="large-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][gbp_url]" value="<?php echo esc_attr( $aim_loc['gbp_url'] ); ?>" placeholder="https://g.page/r/..." />
						<p class="description"><?php esc_html_e( 'Paste the profile URL once the listing is claimed. It is added to sameAs automatically and used for the map embed.', 'aim-avt' ); ?></p>
						<input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][place_id]" value="<?php echo esc_attr( $aim_loc['place_id'] ); ?>" placeholder="<?php esc_attr_e( 'Google Place ID (optional)', 'aim-avt' ); ?>" />
						<p class="description"><?php esc_html_e( 'A Place ID enables a true place-linked map embed via the [aim_map] shortcode, replacing the address-query embeds that pass no signal.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coordinates', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][latitude]" value="<?php echo esc_attr( $aim_loc['latitude'] ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'aim-avt' ); ?>" />
						<input type="text" class="regular-text" name="loc[<?php echo esc_attr( $aim_i ); ?>][longitude]" value="<?php echo esc_attr( $aim_loc['longitude'] ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'aim-avt' ); ?>" />
						<p class="description"><?php esc_html_e( 'Optional. Right-click the plant in Google Maps and copy the numbers it shows.', 'aim-avt' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php if ( ! $aim_is_new ) : ?>
			<p class="description"><?php esc_html_e( 'To remove a facility, clear its name and street address, then save.', 'aim-avt' ); ?></p>
		<?php endif; ?>
	</div>
<?php endforeach; ?>

<?php AIM_AVT_Admin::form_close( __( 'Save all facilities', 'aim-avt' ) ); ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Keeping the visible page and the markup in step', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'The rule measures consistency between what a human reads and what a machine reads. The most reliable way to keep the two identical is to print both from the same source — paste this shortcode into your contact page in place of the typed-out addresses:', 'aim-avt' ); ?>
	</p>
	<p><code>[aim_locations]</code></p>
	<p class="description"><?php esc_html_e( 'Or for a single plant:', 'aim-avt' ); ?> <code>[aim_locations location="edmonton"]</code></p>
	<p><?php esc_html_e( 'And to replace an address-query map embed with a place-linked one, once a Place ID is filled in above:', 'aim-avt' ); ?></p>
	<p><code>[aim_map location="edmonton" key="YOUR_GOOGLE_MAPS_EMBED_API_KEY"]</code></p>
	<p class="description"><?php esc_html_e( 'Without an API key, the shortcode renders a link to the claimed Business Profile instead — still a real place entity, unlike an address query.', 'aim-avt' ); ?></p>
</div>
