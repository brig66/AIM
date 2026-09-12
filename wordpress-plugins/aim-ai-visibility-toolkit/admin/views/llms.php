<?php
/**
 * llms.txt — Task 1.8.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_c      = (array) AIM_AVT_Settings::get( 'llms', array() );
$aim_status = AIM_AVT_LLMS::status();
$aim_body   = AIM_AVT_LLMS::generate();

$aim_sections = array(
	'services'   => __( 'Services', 'aim-avt' ),
	'locations'  => __( 'Service areas', 'aim-avt' ),
	'equipment'  => __( 'Equipment', 'aim-avt' ),
	'industries' => __( 'Industries', 'aim-avt' ),
	'parts'      => __( 'Parts', 'aim-avt' ),
	'content'    => __( 'Key pages', 'aim-avt' ),
);
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.8 — Regenerate llms.txt and resolve the dead language mirrors', 'aim-avt' ); ?> <span class="aim-avt-points">+10</span></h2>
	<p>
		<?php esc_html_e( 'llms_present is true and scores. llms_wellformed measures false, on two counts. First, character encoding: the final entries read "FranÃ§ais" and "EspaÃ±ol" — UTF-8 bytes served as Latin-1. Second, and more seriously, those entries link to /fr/ and /es/, and neither path appears anywhere in the current 119-URL sitemap inventory.', 'aim-avt' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'The file whose only purpose is to orient AI systems is currently directing them to content that has been withdrawn.', 'aim-avt' ); ?></strong></p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'What is published right now', 'aim-avt' ); ?></h2>
	<?php if ( ! $aim_status['exists'] ) : ?>
		<p><?php esc_html_e( 'There is no physical llms.txt file in the WordPress root folder.', 'aim-avt' ); ?></p>
		<?php if ( AIM_AVT_Settings::get( 'llms.enabled' ) ) : ?>
			<p class="aim-avt-alert aim-avt-alert--ok"><?php esc_html_e( 'The plugin is serving a generated one at /llms.txt instead. Nothing further is needed.', 'aim-avt' ); ?></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Switch generation on below and the plugin will serve one. Note that the assessment found a file being served, which may mean it lives somewhere the plugin cannot see — check with your host if the URL still returns the old content.', 'aim-avt' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<table class="widefat striped aim-avt-kv">
			<tbody>
				<tr><th><?php esc_html_e( 'File', 'aim-avt' ); ?></th><td><code><?php echo esc_html( $aim_status['path'] ); ?></code></td></tr>
				<tr><th><?php esc_html_e( 'Size', 'aim-avt' ); ?></th><td><?php echo esc_html( sprintf( '%s bytes, %s lines', number_format_i18n( $aim_status['bytes'] ), number_format_i18n( $aim_status['lines'] ) ) ); ?></td></tr>
				<tr>
					<th><?php esc_html_e( 'Character encoding', 'aim-avt' ); ?></th>
					<td>
						<?php if ( $aim_status['mojibake'] ) : ?>
							<span class="aim-avt-badge aim-avt-badge--todo"><?php esc_html_e( 'Corrupted', 'aim-avt' ); ?></span>
							<?php esc_html_e( 'Accented characters are mangled — this is the llms_wellformed failure.', 'aim-avt' ); ?>
						<?php else : ?>
							<span class="aim-avt-badge aim-avt-badge--done"><?php esc_html_e( 'Clean', 'aim-avt' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Links that do not resolve', 'aim-avt' ); ?></th>
					<td>
						<?php if ( $aim_status['dead_links'] ) : ?>
							<span class="aim-avt-badge aim-avt-badge--todo"><?php echo esc_html( count( $aim_status['dead_links'] ) ); ?></span>
							<ul class="aim-avt-deadlinks">
								<?php foreach ( $aim_status['dead_links'] as $aim_dead ) : ?>
									<li><code><?php echo esc_html( $aim_dead ); ?></code></li>
								<?php endforeach; ?>
							</ul>
							<p class="description"><?php esc_html_e( 'Add a redirect for each of these on the Redirects screen so they 301 to their English equivalent rather than returning a 404 or soft-404.', 'aim-avt' ); ?></p>
						<?php else : ?>
							<span class="aim-avt-badge aim-avt-badge--done"><?php esc_html_e( 'None', 'aim-avt' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Writable', 'aim-avt' ); ?></th>
					<td><?php echo $aim_status['writable'] ? esc_html__( 'Yes', 'aim-avt' ) : esc_html__( 'No — your host has the web root locked down', 'aim-avt' ); ?></td>
				</tr>
			</tbody>
		</table>

		<details class="aim-avt-details">
			<summary><?php esc_html_e( 'Show the current file', 'aim-avt' ); ?></summary>
			<pre class="aim-avt-pre"><?php echo esc_html( $aim_status['contents'] ); ?></pre>
		</details>
	<?php endif; ?>
</div>

<?php AIM_AVT_Admin::form_open( 'save_llms' ); ?>
<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Generate a replacement', 'aim-avt' ); ?></h2>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Serve llms.txt', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="llms_enabled" value="1" <?php checked( ! empty( $aim_c['enabled'] ) ); ?> />
						<?php esc_html_e( 'Let the plugin serve /llms.txt when no physical file is in the way', 'aim-avt' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'A physical file at the web root is served by the web server before WordPress ever sees the request, which is why the "write to disk" button below exists.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Content', 'aim-avt' ); ?></th>
				<td>
					<label><input type="radio" name="llms_mode" value="generate" <?php checked( 'custom' !== ( isset( $aim_c['mode'] ) ? $aim_c['mode'] : 'generate' ) ); ?> /> <?php esc_html_e( 'Generate from the site (recommended — links stay in step with what is published)', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="llms_mode" value="custom" <?php checked( 'custom', isset( $aim_c['mode'] ) ? $aim_c['mode'] : '' ); ?> /> <?php esc_html_e( 'Use the text I write below', 'aim-avt' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="llms_summary"><?php esc_html_e( 'Business summary', 'aim-avt' ); ?></label></th>
				<td><textarea id="llms_summary" name="llms_summary" rows="5" class="large-text"><?php echo esc_textarea( isset( $aim_c['summary'] ) ? $aim_c['summary'] : '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Link sections to include', 'aim-avt' ); ?></th>
				<td>
					<?php foreach ( $aim_sections as $aim_key => $aim_label ) : ?>
						<label style="display:inline-block;min-width:11em;">
							<input type="checkbox" name="llms_sections[]" value="<?php echo esc_attr( $aim_key ); ?>" <?php checked( in_array( $aim_key, (array) $aim_c['include_sections'], true ) ); ?> />
							<?php echo esc_html( $aim_label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Only published, indexable content is listed, so no entry can point at a URL the sitemap does not carry.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="max_links"><?php esc_html_e( 'Maximum links per section', 'aim-avt' ); ?></label></th>
				<td><input type="number" min="1" max="200" id="max_links" name="max_links" class="small-text" value="<?php echo esc_attr( $aim_c['max_links_per_section'] ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'French and Spanish mirrors', 'aim-avt' ); ?></th>
				<td>
					<label><input type="radio" name="lang_mirrors" value="removed" <?php checked( 'kept' !== ( isset( $aim_c['lang_mirrors'] ) ? $aim_c['lang_mirrors'] : 'removed' ) ); ?> /> <?php esc_html_e( 'Withdrawn permanently — leave them out and redirect the paths', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="lang_mirrors" value="kept" <?php checked( 'kept', isset( $aim_c['lang_mirrors'] ) ? $aim_c['lang_mirrors'] : '' ); ?> /> <?php esc_html_e( 'Coming back — list them, but only once each path actually resolves', 'aim-avt' ); ?></label>
					<p class="description"><?php esc_html_e( 'Archived captures confirm both mirrors existed as recently as spring 2026, with roughly 40 pages between them. Decide their fate before publishing: if they are gone, make sure both paths 301 rather than 404.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="llms_custom_body"><?php esc_html_e( 'Custom file contents', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="llms_custom_body" name="llms_custom_body" rows="12" class="large-text code"><?php echo esc_textarea( isset( $aim_c['custom_body'] ) ? $aim_c['custom_body'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Only used when "Use the text I write below" is selected. It is still forced to clean UTF-8 on the way out.', 'aim-avt' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<?php AIM_AVT_Admin::form_close(); ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Preview of what would be published', 'aim-avt' ); ?></h2>
	<pre class="aim-avt-pre"><?php echo esc_html( $aim_body ); ?></pre>
	<p>
		<?php
		echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
			'write_llms',
			__( 'Write this file to disk, replacing the current one', 'aim-avt' ),
			'button button-primary',
			__( 'Overwrite llms.txt with the text above? The current file is saved as a restore point first, so this can be undone.', 'aim-avt' )
		);
		?>
	</p>
	<p class="description"><?php esc_html_e( 'If your host does not allow the write, copy the text above and paste it over llms.txt by FTP or your host file manager. Save it as UTF-8.', 'aim-avt' ); ?></p>
</div>
