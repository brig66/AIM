<?php
/**
 * Dashboard — the whole checklist at a glance.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_totals = AIM_AVT_Checklist::totals();
$aim_tasks  = AIM_AVT_Checklist::tasks();
$aim_on     = (bool) AIM_AVT_Settings::get( 'master_enabled' );
$aim_manual = (array) AIM_AVT_Settings::get( 'manual_progress', array() );

$aim_labels = array(
	AIM_AVT_Checklist::DONE    => __( 'Done', 'aim-avt' ),
	AIM_AVT_Checklist::PARTIAL => __( 'In progress', 'aim-avt' ),
	AIM_AVT_Checklist::TODO    => __( 'To do', 'aim-avt' ),
	AIM_AVT_Checklist::MANUAL  => __( 'Off-site work', 'aim-avt' ),
);
?>

<?php if ( ! $aim_on ) : ?>
	<div class="aim-avt-card aim-avt-card--start">
		<h2><?php esc_html_e( 'Start here', 'aim-avt' ); ?></h2>
		<p>
			<?php esc_html_e( 'Nothing on your website has changed yet. The plugin is installed and configured with the addresses, phone numbers and profile links confirmed in the assessment, but every front-end change is switched off until you turn it on.', 'aim-avt' ); ?>
		</p>
		<p><strong><?php esc_html_e( 'The safe order to work in:', 'aim-avt' ); ?></strong></p>
		<ol>
			<li><?php esc_html_e( 'Go through the Organization, Locations and People screens and check the details are right. Nothing publishes while output is off.', 'aim-avt' ); ?></li>
			<li><?php esc_html_e( 'Turn on Preview mode, then turn output on. Only logged-in administrators will see the changes.', 'aim-avt' ); ?></li>
			<li><?php esc_html_e( 'Walk a few pages — the homepage, the contact page, a product page — and check they look right.', 'aim-avt' ); ?></li>
			<li><?php esc_html_e( 'Turn Preview mode off so the changes go live for everyone.', 'aim-avt' ); ?></li>
		</ol>
		<p class="aim-avt-actions">
			<?php
			echo AIM_AVT_Admin::button_form( 'safe_mode', __( 'Turn on Preview mode', 'aim-avt' ), 'button' ); // phpcs:ignore WordPress.Security.EscapingOutput
			echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
				'master_on',
				__( 'Turn output on', 'aim-avt' ),
				'button button-primary',
				__( 'Switch the plugin output on? You can switch it off again at any time with one button, and a restore point is saved first.', 'aim-avt' )
			);
			?>
		</p>
	</div>
<?php else : ?>
	<div class="aim-avt-card aim-avt-card--live">
		<h2><?php esc_html_e( 'Output is on', 'aim-avt' ); ?></h2>
		<p>
			<?php
			if ( AIM_AVT_Settings::get( 'safe_mode' ) ) {
				esc_html_e( 'Preview mode is on, so only logged-in administrators see the changes. Turn it off when you are happy for the public site to carry them.', 'aim-avt' );
			} else {
				esc_html_e( 'The changes below are live for everyone, including AI crawlers. If anything looks wrong, use "Turn everything off now" at the top of this page — the site reverts instantly and nothing is lost.', 'aim-avt' );
			}
			?>
		</p>
		<p class="aim-avt-actions">
			<?php
			echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
				'safe_mode',
				AIM_AVT_Settings::get( 'safe_mode' ) ? __( 'Turn Preview mode off (go live)', 'aim-avt' ) : __( 'Turn Preview mode on', 'aim-avt' ),
				'button'
			);
			?>
			<a class="button" href="<?php echo esc_url( home_url( '/?aim_avt=debug' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the homepage with a diagnostic comment', 'aim-avt' ); ?></a>
			<a class="button" href="<?php echo esc_url( home_url( '/?aim_avt=off' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the homepage as if the plugin were off', 'aim-avt' ); ?></a>
		</p>
	</div>
<?php endif; ?>

<div class="aim-avt-stats">
	<?php foreach ( $aim_labels as $aim_key => $aim_label ) : ?>
		<div class="aim-avt-stat aim-avt-stat--<?php echo esc_attr( $aim_key ); ?>">
			<span class="aim-avt-stat__number"><?php echo esc_html( $aim_totals['counts'][ $aim_key ] ); ?></span>
			<span class="aim-avt-stat__label"><?php echo esc_html( $aim_label ); ?></span>
		</div>
	<?php endforeach; ?>
	<div class="aim-avt-stat aim-avt-stat--total">
		<span class="aim-avt-stat__number"><?php echo esc_html( $aim_totals['total'] ); ?></span>
		<span class="aim-avt-stat__label"><?php esc_html_e( 'Tasks in the checklist', 'aim-avt' ); ?></span>
	</div>
</div>

<?php
$aim_phase_meta = array(
	1 => array(
		'title'  => __( 'Phase 1 — First 30 days', 'aim-avt' ),
		'target' => __( 'Projected 70/100', 'aim-avt' ),
		'blurb'  => __( 'Configuration and markup only. No new content is written in this phase — the address is on the contact page, the founding year is in the About copy, and the social profiles are in the footer. None of it is in the machine-readable layer.', 'aim-avt' ),
	),
	2 => array(
		'title'  => __( 'Phase 2 — Days 30 to 90', 'aim-avt' ),
		'target' => __( 'Projected 77/100', 'aim-avt' ),
		'blurb'  => __( 'Phase 1 told the engines who Sentrimax is. Phase 2 gives them a reason to prefer it. Every task here is a trust signal, and this phase stalls without input from the client.', 'aim-avt' ),
	),
	3 => array(
		'title'  => __( 'Phase 3 — Months 3 to 9', 'aim-avt' ),
		'target' => __( 'Projected 86/100', 'aim-avt' ),
		'blurb'  => __( 'Phase 3 builds the asset that earns citations rather than mentions. It stalls without engineering: case studies and technical articles need job records, measurements and photographs that only the shop has.', 'aim-avt' ),
	),
);

foreach ( $aim_phase_meta as $aim_phase => $aim_meta ) :
	?>
	<div class="aim-avt-phase">
		<div class="aim-avt-phase__header">
			<h2><?php echo esc_html( $aim_meta['title'] ); ?></h2>
			<span class="aim-avt-phase__target"><?php echo esc_html( $aim_meta['target'] ); ?></span>
		</div>
		<p class="aim-avt-phase__blurb"><?php echo esc_html( $aim_meta['blurb'] ); ?></p>

		<table class="widefat striped aim-avt-tasks">
			<thead>
				<tr>
					<th class="aim-avt-col-id"><?php esc_html_e( 'Task', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'What it is', 'aim-avt' ); ?></th>
					<th class="aim-avt-col-points"><?php esc_html_e( 'Points', 'aim-avt' ); ?></th>
					<th class="aim-avt-col-status"><?php esc_html_e( 'Status', 'aim-avt' ); ?></th>
					<th class="aim-avt-col-action"><?php esc_html_e( 'Action', 'aim-avt' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $aim_tasks as $aim_id => $aim_task ) :
				if ( $aim_task['phase'] !== $aim_phase ) {
					continue;
				}
				$aim_status = AIM_AVT_Checklist::status( $aim_id );
				?>
				<tr class="aim-avt-task aim-avt-task--<?php echo esc_attr( $aim_status['status'] ); ?><?php echo ! empty( $aim_task['urgent'] ) ? ' aim-avt-task--urgent' : ''; ?>">
					<td class="aim-avt-col-id">
						<strong><?php echo esc_html( $aim_id ); ?></strong>
						<?php if ( ! empty( $aim_task['urgent'] ) ) : ?>
							<span class="aim-avt-tag aim-avt-tag--urgent"><?php esc_html_e( 'This week', 'aim-avt' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<strong><?php echo esc_html( $aim_task['title'] ); ?></strong>
						<p class="aim-avt-task__summary"><?php echo esc_html( $aim_task['summary'] ); ?></p>
						<p class="aim-avt-task__meta">
							<span><?php echo esc_html( $aim_task['rules'] ); ?></span>
							<span class="aim-avt-effort" title="<?php esc_attr_e( 'Effort', 'aim-avt' ); ?>"><?php echo esc_html( str_repeat( '●', (int) $aim_task['effort'] ) ); ?></span>
							<?php if ( ! empty( $aim_task['depends'] ) ) : ?>
								<span class="aim-avt-depends">
									<?php
									printf(
										/* translators: %s: task numbers. */
										esc_html__( 'depends on %s', 'aim-avt' ),
										esc_html( $aim_task['depends'] )
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( ! empty( $aim_task['needs_client'] ) ) : ?>
								<span class="aim-avt-tag"><?php esc_html_e( 'needs Sentrimax input', 'aim-avt' ); ?></span>
							<?php endif; ?>
							<?php if ( empty( $aim_task['automated'] ) ) : ?>
								<span class="aim-avt-tag"><?php esc_html_e( 'not automatable', 'aim-avt' ); ?></span>
							<?php endif; ?>
						</p>
						<?php if ( ! empty( $aim_status['note'] ) ) : ?>
							<p class="aim-avt-task__note"><?php echo esc_html( $aim_status['note'] ); ?></p>
						<?php endif; ?>
					</td>
					<td class="aim-avt-col-points">
						<?php
						if ( isset( $aim_task['points_label'] ) ) {
							echo '<span class="aim-avt-points aim-avt-points--special">' . esc_html( $aim_task['points_label'] ) . '</span>';
						} else {
							echo '<span class="aim-avt-points">' . esc_html( sprintf( '+%d', $aim_task['points'] ) ) . '</span>';
						}
						?>
					</td>
					<td class="aim-avt-col-status">
						<span class="aim-avt-badge aim-avt-badge--<?php echo esc_attr( $aim_status['status'] ); ?>">
							<?php echo esc_html( $aim_labels[ $aim_status['status'] ] ); ?>
						</span>
					</td>
					<td class="aim-avt-col-action">
						<?php if ( ! empty( $aim_task['screen'] ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $aim_task['screen'] ) ); ?>"><?php esc_html_e( 'Open', 'aim-avt' ); ?></a>
						<?php endif; ?>
						<?php
						echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
							'toggle_task',
							! empty( $aim_manual[ $aim_id ] ) ? __( 'Un-tick', 'aim-avt' ) : __( 'Tick off', 'aim-avt' ),
							'button button-small aim-avt-tick',
							'',
							array( 'task_id' => $aim_id )
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endforeach; ?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'What this plugin deliberately will not do', 'aim-avt' ); ?></h2>
	<ul class="aim-avt-limits">
		<li><strong><?php esc_html_e( 'Invent a rating or a review.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'aggregateRating is only published once enough real, attributed, visible reviews exist. An unsubstantiated rating is a policy violation and engines cross-check it.', 'aim-avt' ); ?></li>
		<li><strong><?php esc_html_e( 'Publish a price it made up.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'A pricing band with no figure renders nothing at all.', 'aim-avt' ); ?></li>
		<li><strong><?php esc_html_e( 'Rewrite your 24 city URLs.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'The assessment is explicit that the current slugs are correct for buyers and for search, and that rewriting them to satisfy a detector would trade real equity for a reporting artifact.', 'aim-avt' ); ?></li>
		<li><strong><?php esc_html_e( 'Delete a page.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'The duplicate equipment URL is redirected, not deleted, so the change can be undone.', 'aim-avt' ); ?></li>
		<li><strong><?php esc_html_e( 'Write your case studies or articles.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'It supplies the structure, the fields and the markup. The measurements and the customer permission have to come from the shop.', 'aim-avt' ); ?></li>
	</ul>
</div>
