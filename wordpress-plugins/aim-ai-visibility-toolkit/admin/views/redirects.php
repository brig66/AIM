<?php
/**
 * Redirects — Tasks 1.9 and 1.8.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_rules = (array) AIM_AVT_Settings::get( 'redirects.rules', array() );

$aim_rows = $aim_rules;
for ( $aim_i = count( $aim_rows ); $aim_i < count( $aim_rules ) + 3; $aim_i++ ) {
	$aim_rows[] = array( 'from' => '', 'to' => '', 'code' => 301, 'note' => '' );
}
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.9 — Resolve the duplicate equipment URL', 'aim-avt' ); ?> <span class="aim-avt-points">+4</span></h2>
	<p>
		<?php esc_html_e( 'duplicate_ratio measured 0.0084 — one duplicate across 119 URLs. /equipment/alfa-laval-g2-100-2/ is a -2 suffixed copy coexisting with /equipment/alfa-laval-g2-100/. One duplicate is not a crisis, but the -2 suffix is how duplicate drift starts on WordPress, and clearing it now costs minutes.', 'aim-avt' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'This plugin redirects rather than deletes. Nothing is lost, the canonical URL wins, and switching a rule off puts the original URL straight back. Once you are satisfied the redirect is right, you can delete the duplicate in WordPress and remove it from the sitemap — the redirect will keep working.', 'aim-avt' ); ?>
	</p>
</div>

<?php AIM_AVT_Admin::form_open( 'save_redirects' ); ?>
<div class="aim-avt-card">
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redirects', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="redirects_enabled" value="1" <?php checked( AIM_AVT_Settings::get( 'redirects.enabled' ) ); ?> />
						<?php esc_html_e( 'Send the redirects below', 'aim-avt' ); ?>
					</label>
				</td>
			</tr>
		</tbody>
	</table>

	<table class="widefat striped aim-avt-rules">
		<thead>
			<tr>
				<th><?php esc_html_e( 'From (path on this site)', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'To', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'Type', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'Note', 'aim-avt' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $aim_rows as $aim_i => $aim_rule ) : ?>
			<tr>
				<td><input type="text" class="widefat code" name="rule[<?php echo esc_attr( $aim_i ); ?>][from]" value="<?php echo esc_attr( $aim_rule['from'] ); ?>" placeholder="/old-page/" /></td>
				<td><input type="text" class="widefat code" name="rule[<?php echo esc_attr( $aim_i ); ?>][to]" value="<?php echo esc_attr( $aim_rule['to'] ); ?>" placeholder="/new-page/" /></td>
				<td>
					<select name="rule[<?php echo esc_attr( $aim_i ); ?>][code]">
						<option value="301" <?php selected( 301, (int) $aim_rule['code'] ); ?>><?php esc_html_e( '301 permanent', 'aim-avt' ); ?></option>
						<option value="302" <?php selected( 302, (int) $aim_rule['code'] ); ?>><?php esc_html_e( '302 temporary', 'aim-avt' ); ?></option>
					</select>
				</td>
				<td><input type="text" class="widefat" name="rule[<?php echo esc_attr( $aim_i ); ?>][note]" value="<?php echo esc_attr( $aim_rule['note'] ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Clear both the From and To boxes on a row and save to delete that rule.', 'aim-avt' ); ?></p>
</div>
<?php AIM_AVT_Admin::form_close( __( 'Save redirects', 'aim-avt' ) ); ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Check the rules against the live site', 'aim-avt' ); ?></h2>
	<?php $aim_test = AIM_AVT_Redirects::test(); ?>
	<?php if ( ! $aim_test ) : ?>
		<p><?php esc_html_e( 'No rules to check.', 'aim-avt' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'From', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Responds with', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'To', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Responds with', 'aim-avt' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $aim_test as $aim_row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $aim_row['from'] ); ?></code></td>
					<td>
						<?php
						echo esc_html( $aim_row['from_status'] );
						if ( 200 === $aim_row['from_status'] && AIM_AVT_Settings::get( 'redirects.enabled' ) ) {
							echo ' <span class="aim-avt-tag">' . esc_html__( 'still serving a page — check the path', 'aim-avt' ) . '</span>';
						}
						?>
					</td>
					<td><code><?php echo esc_html( $aim_row['to'] ); ?></code></td>
					<td>
						<?php
						echo esc_html( $aim_row['to_status'] );
						if ( 200 !== $aim_row['to_status'] ) {
							echo ' <span class="aim-avt-tag aim-avt-tag--urgent">' . esc_html__( 'target does not return 200', 'aim-avt' ) . '</span>';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'A target that does not return 200 means the redirect would send visitors and crawlers to a broken page. Fix that before switching redirects on.', 'aim-avt' ); ?></p>
	<?php endif; ?>
</div>
