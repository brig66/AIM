<?php
/**
 * Health check — fetch a few real pages and report what they now contain.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_health = get_option( 'aim_avt_health', false );
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Health check', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'This fetches a handful of your pages exactly as a crawler would, and reports what actually came back — how many H1 elements each page has, how many blocks of structured data it carries, whether they parse, and which schema types are present. It is the fastest way to confirm the plugin is doing what the screens say it is doing.', 'aim-avt' ); ?>
	</p>
	<?php echo AIM_AVT_Admin::button_form( 'health_check', __( 'Run the health check', 'aim-avt' ), 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>

	<?php if ( ! AIM_AVT_Settings::get( 'master_enabled' ) ) : ?>
		<p class="description"><?php esc_html_e( 'Output is currently switched off, so this will show the site as it is without the plugin. That is a useful "before" reading — run it again after switching output on.', 'aim-avt' ); ?></p>
	<?php elseif ( AIM_AVT_Settings::get( 'safe_mode' ) ) : ?>
		<div class="aim-avt-alert aim-avt-alert--warn">
			<p><?php esc_html_e( 'Preview mode is on. The health check fetches pages as a logged-out visitor, so it will show the site WITHOUT your changes. Turn Preview mode off to check what the public and AI crawlers actually receive.', 'aim-avt' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php if ( $aim_health && ! empty( $aim_health['results'] ) ) : ?>
	<div class="aim-avt-card">
		<h2>
			<?php
			printf(
				/* translators: %s: date and time. */
				esc_html__( 'Results from %s', 'aim-avt' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aim_health['ran_at'] ) )
			);
			?>
		</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Page', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'H1 count', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Structured data blocks', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Schema types found', 'aim-avt' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $aim_health['results'] as $aim_url => $aim_row ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( $aim_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $aim_url, PHP_URL_PATH ) ? wp_parse_url( $aim_url, PHP_URL_PATH ) : '/' ); ?></a></td>
					<?php if ( isset( $aim_row['error'] ) ) : ?>
						<td colspan="3"><span class="aim-avt-badge aim-avt-badge--todo"><?php echo esc_html( $aim_row['error'] ); ?></span></td>
					<?php else : ?>
						<td>
							<?php if ( 1 === (int) $aim_row['h1_count'] ) : ?>
								<span class="aim-avt-badge aim-avt-badge--done">1</span>
							<?php else : ?>
								<span class="aim-avt-badge aim-avt-badge--todo"><?php echo esc_html( $aim_row['h1_count'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							printf(
								/* translators: 1: valid blocks, 2: total blocks. */
								esc_html__( '%1$d of %2$d parse cleanly', 'aim-avt' ),
								(int) $aim_row['jsonld_valid'],
								(int) $aim_row['jsonld_blocks']
							);
							?>
						</td>
						<td class="aim-avt-types">
							<?php
							if ( empty( $aim_row['node_types'] ) ) {
								echo '<em>' . esc_html__( 'none', 'aim-avt' ) . '</em>';
							} else {
								$aim_out = array();
								foreach ( $aim_row['node_types'] as $aim_type => $aim_n ) {
									$aim_out[] = $aim_n > 1 ? $aim_type . ' ×' . $aim_n : $aim_type;
								}
								echo esc_html( implode( ', ', $aim_out ) );
							}
							?>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'What to look for', 'aim-avt' ); ?></h3>
		<ul class="aim-avt-bullets">
			<li><strong><?php esc_html_e( 'H1 count of exactly 1 on every row.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'A 0 means the H1 repair could not find a heading to promote on that page — set the text for it under Phase 1, Per-page H1 text.', 'aim-avt' ); ?></li>
			<li><strong><?php esc_html_e( 'Organization, LocalBusiness and Person in the types column.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'If Organization appears twice, the merge did not find your existing node and published a second one. Switch the Organization screen to Merge only and re-run.', 'aim-avt' ); ?></li>
			<li><strong><?php esc_html_e( 'Every block parsing cleanly.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'A block that does not parse is invisible to every engine, whoever produced it.', 'aim-avt' ); ?></li>
		</ul>
	</div>
<?php endif; ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Validate with the official testers', 'aim-avt' ); ?></h2>
	<p><?php esc_html_e( 'The checklist asks you to validate in Google\'s Rich Results Test before publishing. Open these against your live URLs once output is on and Preview mode is off:', 'aim-avt' ); ?></p>
	<ul class="aim-avt-bullets">
		<li><a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Google Rich Results Test', 'aim-avt' ); ?></a></li>
		<li><a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Schema.org validator', 'aim-avt' ); ?></a></li>
	</ul>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Diagnostic URLs', 'aim-avt' ); ?></h2>
	<p><?php esc_html_e( 'While you are logged in as an administrator, you can add these to the end of any page address on the site:', 'aim-avt' ); ?></p>
	<table class="widefat striped">
		<tbody>
			<tr>
				<td><code>?aim_avt=off</code></td>
				<td><?php esc_html_e( 'Renders the page as if the plugin were switched off, so you can compare side by side. Only works for administrators, so a crawler can never be served a different page.', 'aim-avt' ); ?></td>
			</tr>
			<tr>
				<td><code>?aim_avt=debug</code></td>
				<td><?php esc_html_e( 'Renders the page normally and appends an HTML comment at the very bottom saying what the plugin did — whether the schema merged or published standalone, and how many links were stripped. View the page source to read it.', 'aim-avt' ); ?></td>
			</tr>
		</tbody>
	</table>
	<p>
		<a class="button" href="<?php echo esc_url( home_url( '/?aim_avt=debug' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the homepage in debug mode', 'aim-avt' ); ?></a>
	</p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Shortcode reference', 'aim-avt' ); ?></h2>
	<table class="widefat striped">
		<tbody>
			<tr><td><code>[aim_locations]</code></td><td><?php esc_html_e( 'Prints all three facility addresses from the same source the schema uses, so the visible text and the markup cannot drift apart. Add location="edmonton" for one plant.', 'aim-avt' ); ?></td></tr>
			<tr><td><code>[aim_map location="edmonton"]</code></td><td><?php esc_html_e( 'A place-linked map embed. Add key="..." with a Google Maps Embed API key for a real map; without one it renders a link to the claimed Business Profile.', 'aim-avt' ); ?></td></tr>
			<tr><td><code>[aim_testimonials]</code></td><td><?php esc_html_e( 'The testimonials, with Review markup and the substantiated aggregate rating.', 'aim-avt' ); ?></td></tr>
			<tr><td><code>[aim_case_studies]</code></td><td><?php esc_html_e( 'An index of published case studies.', 'aim-avt' ); ?></td></tr>
			<tr><td><code>[aim_press]</code></td><td><?php esc_html_e( 'The press and recognition list, grouped by type.', 'aim-avt' ); ?></td></tr>
			<tr><td><code>[aim_pricing]</code></td><td><?php esc_html_e( 'The pricing bands, with Offer and PriceSpecification markup.', 'aim-avt' ); ?></td></tr>
		</tbody>
	</table>
</div>
