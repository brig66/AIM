<?php
/**
 * Phase 1 — the link farm (Task 1.1) and heading structure (Tasks 1.7, 2.5).
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_lg     = (array) AIM_AVT_Settings::get( 'linkguard', array() );
$aim_head   = (array) AIM_AVT_Settings::get( 'headings', array() );
$aim_report = AIM_AVT_Linkguard::last_report();
?>

<div class="aim-avt-card aim-avt-card--urgent">
	<h2>
		<?php esc_html_e( 'Task 1.1 — Remove the foreign outbound links from /news/', 'aim-avt' ); ?>
		<span class="aim-avt-tag aim-avt-tag--urgent"><?php esc_html_e( 'Do this first', 'aim-avt' ); ?></span>
	</h2>
	<p>
		<?php esc_html_e( 'The assessment found 51 outbound links on the news page pointing to 48 live third-party domains — UK and Netherlands sites, several of them expired-domain shells, none with any relationship to centrifuges. Critically, they do not appear in the WordPress post body, and they do not appear in the Internet Archive capture of the same page from February 2026.', 'aim-avt' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Removing them deliberately costs 15 points, because the rubric had counted those 48 domains as earned media. That is the right outcome: outbound links to a link farm are a live liability with both search engines and AI crawlers, and they sit on the page the pipeline treats as the company press page.', 'aim-avt' ); ?>
	</p>

	<h3><?php esc_html_e( 'Step 1 — Find out where the links actually live', 'aim-avt' ); ?></h3>
	<p class="description"><?php esc_html_e( 'This reads your posts, pages and comments, then fetches the live page and compares. It changes nothing.', 'aim-avt' ); ?></p>
	<?php echo AIM_AVT_Admin::button_form( 'scan_links', __( 'Scan the site now', 'aim-avt' ), 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapingOutput ?>

	<?php if ( $aim_report ) : ?>
		<div class="aim-avt-scan-result">
			<h4>
				<?php
				printf(
					/* translators: %s: date and time. */
					esc_html__( 'Scan of %s', 'aim-avt' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aim_report['scanned_at'] ) )
				);
				?>
			</h4>

			<?php if ( $aim_report['injected'] ) : ?>
				<div class="aim-avt-alert aim-avt-alert--danger">
					<h4><?php esc_html_e( 'Treat this as a security incident', 'aim-avt' ); ?></h4>
					<p>
						<?php
						printf(
							/* translators: %d: number of domains. */
							esc_html__( '%d domain(s) appear on the rendered page but are nowhere in your post content or comments. That is the signature of injected content — a plugin, the theme, or a modified file is adding them at render time.', 'aim-avt' ),
							count( $aim_report['rendered_only'] )
						);
						?>
					</p>
					<p><strong><?php esc_html_e( 'Do all of these:', 'aim-avt' ); ?></strong></p>
					<ul>
						<li><?php esc_html_e( 'Switch on the live filter below right now. It strips the links from every page the moment it is on, which buys you time.', 'aim-avt' ); ?></li>
						<li><?php esc_html_e( 'Audit installed plugins and themes for anything you did not install or do not recognize.', 'aim-avt' ); ?></li>
						<li><?php esc_html_e( 'Review every administrator account and remove any you do not recognize.', 'aim-avt' ); ?></li>
						<li><?php esc_html_e( 'Check the theme directory for unexpected files.', 'aim-avt' ); ?></li>
						<li><?php esc_html_e( 'Force a password reset for all administrators.', 'aim-avt' ); ?></li>
						<li><?php esc_html_e( 'Ask your host to run a malware scan of the filesystem.', 'aim-avt' ); ?></li>
					</ul>
					<p class="description"><?php esc_html_e( 'The live filter hides the symptom. It does not fix a compromise, and the plugin cannot tell you it has fixed one.', 'aim-avt' ); ?></p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Domain rendering on the page', 'aim-avt' ); ?></th><th><?php esc_html_e( 'Links', 'aim-avt' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $aim_report['rendered_only'] as $aim_host => $aim_count ) : ?>
							<tr><td><code><?php echo esc_html( $aim_host ); ?></code></td><td><?php echo esc_html( $aim_count ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( $aim_report['posts'] ) : ?>
				<h4><?php esc_html_e( 'Found in page or post content', 'aim-avt' ); ?></h4>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Page', 'aim-avt' ); ?></th><th><?php esc_html_e( 'Foreign links', 'aim-avt' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $aim_report['posts'] as $aim_pid => $aim_row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $aim_pid ) ); ?>"><?php echo esc_html( $aim_row['title'] ); ?></a>
							</td>
							<td>
								<?php echo esc_html( count( $aim_row['links'] ) ); ?>
								<div class="aim-avt-domains">
									<?php
									$aim_hosts = array();
									foreach ( $aim_row['links'] as $aim_link ) {
										$aim_hosts[ $aim_link['host'] ] = true;
									}
									echo esc_html( implode( ', ', array_slice( array_keys( $aim_hosts ), 0, 12 ) ) );
									if ( count( $aim_hosts ) > 12 ) {
										echo esc_html( sprintf( ' … +%d more', count( $aim_hosts ) - 12 ) );
									}
									?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $aim_report['comments'] ) : ?>
				<h4>
					<?php
					printf(
						/* translators: %d: number of comments. */
						esc_html__( 'Found in %d comment(s)', 'aim-avt' ),
						count( $aim_report['comments'] )
					);
					?>
				</h4>
				<p class="description"><?php esc_html_e( 'The checklist says: if they are in comments, delete them, disable comments on all news content, and purge the spam queue.', 'aim-avt' ); ?></p>
			<?php endif; ?>

			<?php if ( ! $aim_report['posts'] && ! $aim_report['comments'] && ! $aim_report['injected'] ) : ?>
				<div class="aim-avt-alert aim-avt-alert--ok">
					<p><?php esc_html_e( 'No foreign outbound links found, in the database or on the rendered page. Nothing to remove.', 'aim-avt' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Step 2 — Strip them from the rendered page', 'aim-avt' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'This is the safe fix: it changes nothing in your database, only what visitors and crawlers receive. Switch it off and the page is back to exactly what it was.', 'aim-avt' ); ?>
	</p>

	<?php AIM_AVT_Admin::form_open( 'save_linkguard' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Live filter', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="live_enabled" value="1" <?php checked( ! empty( $aim_lg['live_enabled'] ) ); ?> />
						<?php esc_html_e( 'Strip foreign outbound links from pages as they are served', 'aim-avt' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="scope"><?php esc_html_e( 'Apply to these paths', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="scope" name="scope" rows="3" class="large-text code"><?php echo esc_textarea( isset( $aim_lg['scope'] ) ? $aim_lg['scope'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One path per line, for example /news/. A path also covers everything beneath it. Leave empty to apply to the whole site.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="allowlist"><?php esc_html_e( 'Never strip these domains', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="allowlist" name="allowlist" rows="10" class="large-text code"><?php echo esc_textarea( isset( $aim_lg['allowlist'] ) ? $aim_lg['allowlist'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One domain per line. Subdomains are covered automatically. Your own site is always allowed. Add any OEM, supplier, association or directory you legitimately link to before switching the filter on.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'How to strip them', 'aim-avt' ); ?></th>
				<td>
					<label><input type="radio" name="strip_mode" value="unlink" <?php checked( 'remove' !== ( isset( $aim_lg['strip_mode'] ) ? $aim_lg['strip_mode'] : 'unlink' ) ); ?> /> <?php esc_html_e( 'Keep the words, remove the link (recommended — the page reads the same)', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="strip_mode" value="remove" <?php checked( 'remove', isset( $aim_lg['strip_mode'] ) ? $aim_lg['strip_mode'] : '' ); ?> /> <?php esc_html_e( 'Remove the link and its text entirely', 'aim-avt' ); ?></label>
				</td>
			</tr>
		</tbody>
	</table>
	<?php AIM_AVT_Admin::form_close(); ?>

	<h3><?php esc_html_e( 'Step 3 — Clean the database (optional, and undoable)', 'aim-avt' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Only do this once the scan above shows the links really are in your content or comments. A restore point capturing the exact previous content of every page touched, and every comment deleted, is saved before anything changes.', 'aim-avt' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aim-avt-form">
		<input type="hidden" name="action" value="aim_avt_action" />
		<input type="hidden" name="aim_action" value="clean_links" />
		<input type="hidden" name="aim_return" value="aim-avt-phase1" />
		<?php wp_nonce_field( 'aim_avt_clean_links', '_aim_nonce' ); ?>
		<p><label><input type="checkbox" name="clean_posts" value="1" checked /> <?php esc_html_e( 'Remove the links from page and post content', 'aim-avt' ); ?></label></p>
		<p><label><input type="checkbox" name="delete_spam" value="1" checked /> <?php esc_html_e( 'Delete comments that carry foreign links', 'aim-avt' ); ?></label></p>
		<p><label><input type="checkbox" name="lock_comments" value="1" /> <?php esc_html_e( 'Close comments and pingbacks on all posts', 'aim-avt' ); ?></label></p>
		<p>
			<button type="submit" class="button button-primary" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Change the database now? A restore point is saved first, so this can be undone from the Undo & Restore screen.', 'aim-avt' ) ) ); ?>);">
				<?php esc_html_e( 'Clean the database', 'aim-avt' ); ?>
			</button>
		</p>
	</form>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 1.7 — Fix the pages rendering without an H1', 'aim-avt' ); ?> <span class="aim-avt-points">+10</span></h2>
	<p>
		<?php esc_html_e( 'single_h1_ratio measured 0.739: 31 of 119 pages carry no H1 element at all, and the homepage is one of them. The affected pages cluster on builder-rendered templates, which points at one template setting rather than 31 authoring mistakes.', 'aim-avt' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'Rather than edit a theme template, this repairs the page as it is served. The preferred behaviour promotes the heading that already carries the page title from H2 to H1, so nothing moves and nothing is added — the page looks identical and a machine reader can finally see the subject.', 'aim-avt' ); ?>
	</p>

	<?php AIM_AVT_Admin::form_open( 'save_headings' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'H1 repair', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="h1_enabled" value="1" <?php checked( ! empty( $aim_head['h1_enabled'] ) ); ?> />
						<?php esc_html_e( 'Guarantee exactly one H1 on every page', 'aim-avt' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Pages that already have exactly one H1 are left alone. Pages with more than one keep the first and the rest become H2.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'When a page has no H1', 'aim-avt' ); ?></th>
				<td>
					<label><input type="radio" name="h1_mode" value="promote" <?php checked( 'insert' !== ( isset( $aim_head['h1_mode'] ) ? $aim_head['h1_mode'] : 'promote' ) ); ?> /> <?php esc_html_e( 'Promote the existing title heading (recommended — nothing visible changes)', 'aim-avt' ); ?></label><br />
					<label><input type="radio" name="h1_mode" value="insert" <?php checked( 'insert', isset( $aim_head['h1_mode'] ) ? $aim_head['h1_mode'] : '' ); ?> /> <?php esc_html_e( 'Insert a new H1 at the top of the content', 'aim-avt' ); ?></label>
					<p class="description"><?php esc_html_e( 'Promote falls back to inserting when there is no heading to promote.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="h1_home_text"><?php esc_html_e( 'Homepage H1 text', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="h1_home_text" name="h1_home_text" class="large-text" value="<?php echo esc_attr( isset( $aim_head['h1_home_text'] ) ? $aim_head['h1_home_text'] : '' ); ?>" />
					<p class="description"><?php esc_html_e( 'The homepage is the most important document on the site for entity resolution, and its WordPress title is usually just "Home". Say what the business does.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="h1_overrides"><?php esc_html_e( 'Per-page H1 text', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="h1_overrides" name="h1_overrides" rows="4" class="large-text code" placeholder="/equipment/|Industrial Centrifuges for Sale"><?php echo esc_textarea( isset( $aim_head['h1_overrides'] ) ? $aim_head['h1_overrides'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One per line, in the form: /path/|Heading text', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="h1_exclusions"><?php esc_html_e( 'Leave these pages alone', 'aim-avt' ); ?></label></th>
				<td>
					<textarea id="h1_exclusions" name="h1_exclusions" rows="3" class="large-text code"><?php echo esc_textarea( isset( $aim_head['h1_exclusions'] ) ? $aim_head['h1_exclusions'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One path per line. Add a trailing * to match everything beneath a path, for example /landing/*', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="h1_class"><?php esc_html_e( 'CSS class for an inserted H1', 'aim-avt' ); ?></label></th>
				<td>
					<input type="text" id="h1_class" name="h1_class" class="regular-text" value="<?php echo esc_attr( isset( $aim_head['h1_class'] ) ? $aim_head['h1_class'] : 'aim-avt-h1' ); ?>" />
					<p class="description"><?php esc_html_e( 'Only used when a new H1 is inserted. Set it to a heading class your theme already styles if the default does not match.', 'aim-avt' ); ?></p>
				</td>
			</tr>

			<tr><th colspan="2"><h3 style="margin:0;"><?php esc_html_e( 'Task 2.5 — Heading hierarchy', 'aim-avt' ); ?> <span class="aim-avt-points">+5</span></h3></th></tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Close heading-level gaps', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="hierarchy_enabled" value="1" <?php checked( ! empty( $aim_head['hierarchy_enabled'] ) ); ?> />
						<?php esc_html_e( 'Correct pages that skip a heading level', 'aim-avt' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'clean_hierarchy_ratio measured 0.723 — roughly 33 of 119 pages jump from an H2 to an H4, or use an H3 with no H2 above it. Turn this on after the H1 repair, because fixing the H1 changes the hierarchy on the affected pages.', 'aim-avt' ); ?></p>
					<p>
						<label><input type="radio" name="hierarchy_mode" value="report" <?php checked( 'fix' !== ( isset( $aim_head['hierarchy_mode'] ) ? $aim_head['hierarchy_mode'] : 'report' ) ); ?> /> <?php esc_html_e( 'Report only — measure, change nothing', 'aim-avt' ); ?></label><br />
						<label><input type="radio" name="hierarchy_mode" value="fix" <?php checked( 'fix', isset( $aim_head['hierarchy_mode'] ) ? $aim_head['hierarchy_mode'] : '' ); ?> /> <?php esc_html_e( 'Fix — demote headings that jump a level', 'aim-avt' ); ?></label>
					</p>
					<p class="description"><?php esc_html_e( 'Levels are only ever lowered, never raised, so the outline can only get better formed. Heading text and styling are untouched; only the tag changes, so your theme may render a corrected heading at a different size. Check a few pages in Preview mode before going live.', 'aim-avt' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<?php AIM_AVT_Admin::form_close(); ?>
</div>
