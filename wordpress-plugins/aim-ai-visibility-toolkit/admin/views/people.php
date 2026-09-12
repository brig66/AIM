<?php
/**
 * Leadership Person nodes — Task 2.2.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_people = (array) AIM_AVT_Settings::get( 'schema.people', array() );

$aim_rows = $aim_people;
for ( $aim_pad = count( $aim_rows ); $aim_pad < max( 3, count( $aim_people ) + 1 ); $aim_pad++ ) {
	$aim_rows[] = array(
		'key'               => '',
		'name'              => '',
		'job_title'         => '',
		'description'       => '',
		'image'             => '',
		'linkedin'          => '',
		'knows_about'       => '',
		'is_default_author' => false,
	);
}
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 2.2 — Deepen the leadership Person node', 'aim-avt' ); ?> <span class="aim-avt-points">+17</span></h2>
	<p>
		<?php esc_html_e( 'person_schema_present is true and already scores its 12 points — exactly one Person node exists across the 119 crawled pages. person_schema_deep is false: the node carries a name and nothing else. No sameAs to a public profile, no knowsAbout, no image, no description.', 'aim-avt' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'E-E-A-T begins with a named, verifiable, accountable human, and a bare name is the weakest possible version of that.', 'aim-avt' ); ?></strong>
		<?php esc_html_e( 'The checklist also suggests adding nodes for the senior engineering and service leads: depth of named expertise is what the rule is proxying for.', 'aim-avt' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'These fields are blank because the assessment did not evidence who holds these roles. Nothing is published for a person until you enter a name.', 'aim-avt' ); ?>
	</p>
</div>

<?php AIM_AVT_Admin::form_open( 'save_people' ); ?>

<?php foreach ( $aim_rows as $aim_i => $aim_person ) : ?>
	<?php $aim_key = ! empty( $aim_person['key'] ) ? $aim_person['key'] : 'person-' . $aim_i; ?>
	<div class="aim-avt-card">
		<h3>
			<?php
			if ( ! empty( $aim_person['name'] ) ) {
				echo esc_html( $aim_person['name'] );
			} else {
				printf(
					/* translators: %d: row number. */
					esc_html__( 'Person %d', 'aim-avt' ),
					(int) $aim_i + 1
				);
			}
			?>
		</h3>

		<input type="hidden" name="person[<?php echo esc_attr( $aim_i ); ?>][key]" value="<?php echo esc_attr( $aim_key ); ?>" />

		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Full name', 'aim-avt' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="person[<?php echo esc_attr( $aim_i ); ?>][name]" value="<?php echo esc_attr( $aim_person['name'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Leave blank to skip this row entirely.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Job title', 'aim-avt' ); ?></th>
					<td><input type="text" class="regular-text" name="person[<?php echo esc_attr( $aim_i ); ?>][job_title]" value="<?php echo esc_attr( $aim_person['job_title'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Short biography', 'aim-avt' ); ?></th>
					<td>
						<textarea class="large-text" rows="3" name="person[<?php echo esc_attr( $aim_i ); ?>][description]"><?php echo esc_textarea( $aim_person['description'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Two or three sentences of real engineering background. This is what turns a name into evidence.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Public LinkedIn profile', 'aim-avt' ); ?></th>
					<td>
						<input type="url" class="large-text" name="person[<?php echo esc_attr( $aim_i ); ?>][linkedin]" value="<?php echo esc_attr( $aim_person['linkedin'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Published as sameAs. Check it loads while you are signed out of LinkedIn.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Photograph URL', 'aim-avt' ); ?></th>
					<td><input type="url" class="large-text" name="person[<?php echo esc_attr( $aim_i ); ?>][image]" value="<?php echo esc_attr( $aim_person['image'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Areas of expertise', 'aim-avt' ); ?></th>
					<td>
						<textarea class="large-text" rows="6" name="person[<?php echo esc_attr( $aim_i ); ?>][knows_about]"><?php echo esc_textarea( $aim_person['knows_about'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One per line. Published as knowsAbout. The checklist names: decanter and tricanter centrifuge repair, dynamic balancing to ISO G 0.5, gearbox and backdrive overhaul, and hardfacing.', 'aim-avt' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default author', 'aim-avt' ); ?></th>
					<td>
						<label>
							<input type="radio" name="default_author" value="<?php echo esc_attr( $aim_key ); ?>" <?php checked( ! empty( $aim_person['is_default_author'] ) ); ?> />
							<?php esc_html_e( 'Credit news posts and case studies to this person unless a post says otherwise', 'aim-avt' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Used by Task 2.3, which puts a visible byline on the news content and points the schema author at this node.', 'aim-avt' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</div>
<?php endforeach; ?>

<?php AIM_AVT_Admin::form_close( __( 'Save leadership profiles', 'aim-avt' ) ); ?>
