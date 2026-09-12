<?php
/**
 * Undo & Restore.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_snapshots = AIM_AVT_Snapshots::listing();
$aim_log       = AIM_AVT_Log::all( 60 );
?>

<div class="aim-avt-card aim-avt-card--undo">
	<h2><?php esc_html_e( 'If something looks wrong', 'aim-avt' ); ?></h2>
	<p><?php esc_html_e( 'There are three levels of undo, from the fastest to the most thorough. Start at the top.', 'aim-avt' ); ?></p>
	<ol class="aim-avt-undo-ladder">
		<li>
			<strong><?php esc_html_e( 'Turn everything off.', 'aim-avt' ); ?></strong>
			<?php esc_html_e( 'One button. Every front-end change stops immediately and the site renders exactly as it did before the plugin. Your settings and content are kept, so you can turn it back on later.', 'aim-avt' ); ?>
			<?php
			if ( AIM_AVT_Settings::get( 'master_enabled' ) ) {
				echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
					'master_off',
					__( 'Turn everything off now', 'aim-avt' ),
					'button button-primary aim-avt-panic'
				);
			} else {
				echo '<em>' . esc_html__( 'Already off.', 'aim-avt' ) . '</em>';
			}
			?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Restore a specific point in time.', 'aim-avt' ); ?></strong>
			<?php esc_html_e( 'Every save and every database change below saved a restore point first. Restoring one puts back the settings, and any page content, comments or files that change touched.', 'aim-avt' ); ?>
		</li>
		<li>
			<strong><?php esc_html_e( 'Deactivate or delete the plugin.', 'aim-avt' ); ?></strong>
			<?php esc_html_e( 'Deactivating stops all output and keeps your data. Deleting removes the plugin; you are asked on the way out whether to keep or remove its stored data.', 'aim-avt' ); ?>
		</li>
	</ol>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Restore points', 'aim-avt' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Restoring is itself undoable — a backup of the current state is taken automatically before any restore runs.', 'aim-avt' ); ?>
	</p>

	<?php if ( ! $aim_snapshots ) : ?>
		<p><?php esc_html_e( 'No restore points yet. One is created the first time you save a screen.', 'aim-avt' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'What it captures', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Includes', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'By', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Action', 'aim-avt' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $aim_snapshots as $aim_row ) : ?>
				<?php
				$aim_body   = AIM_AVT_Snapshots::body( $aim_row['id'] );
				$aim_extras = array();
				if ( $aim_body ) {
					if ( ! empty( $aim_body['posts'] ) ) {
						$aim_extras[] = sprintf(
							/* translators: %d: number of pages. */
							_n( '%d page or post', '%d pages or posts', count( $aim_body['posts'] ), 'aim-avt' ),
							count( $aim_body['posts'] )
						);
					}
					if ( ! empty( $aim_body['extra']['comments'] ) ) {
						$aim_extras[] = sprintf(
							/* translators: %d: number of comments. */
							_n( '%d comment', '%d comments', count( $aim_body['extra']['comments'] ), 'aim-avt' ),
							count( $aim_body['extra']['comments'] )
						);
					}
					if ( ! empty( $aim_body['extra']['files'] ) ) {
						$aim_extras[] = sprintf(
							/* translators: %d: number of files. */
							_n( '%d file', '%d files', count( $aim_body['extra']['files'] ), 'aim-avt' ),
							count( $aim_body['extra']['files'] )
						);
					}
				}
				?>
				<tr>
					<td>
						<?php
						echo esc_html(
							wp_date(
								get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
								$aim_row['created']
							)
						);
						?>
					</td>
					<td><?php echo esc_html( $aim_row['label'] ); ?></td>
					<td>
						<?php esc_html_e( 'All settings', 'aim-avt' ); ?>
						<?php if ( $aim_extras ) : ?>
							<br /><strong><?php echo esc_html( implode( ', ', $aim_extras ) ); ?></strong>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $aim_row['user'] ); ?></td>
					<td class="aim-avt-row-actions">
						<?php
						echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
							'restore',
							__( 'Restore', 'aim-avt' ),
							'button button-primary button-small',
							__( 'Restore the site to this point? A backup of the current state is taken first, so this is reversible.', 'aim-avt' ),
							array( 'snapshot_id' => $aim_row['id'] )
						);
						echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
							'delete_snapshot',
							__( 'Delete', 'aim-avt' ),
							'button button-small',
							__( 'Delete this restore point? It cannot be recovered.', 'aim-avt' ),
							array( 'snapshot_id' => $aim_row['id'] )
						);
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Start over', 'aim-avt' ); ?></h2>
	<p><?php esc_html_e( 'Puts every setting back to the values the plugin shipped with and switches output off. Your testimonials, case studies and press items are not touched. A restore point is saved first.', 'aim-avt' ); ?></p>
	<?php
	echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
		'reset_all',
		__( 'Reset every setting to defaults', 'aim-avt' ),
		'button',
		__( 'Reset every setting to the plugin defaults and switch output off?', 'aim-avt' )
	);
	?>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Activity log', 'aim-avt' ); ?></h2>
	<?php if ( ! $aim_log ) : ?>
		<p><?php esc_html_e( 'Nothing logged yet.', 'aim-avt' ); ?></p>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'Who', 'aim-avt' ); ?></th>
					<th><?php esc_html_e( 'What happened', 'aim-avt' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $aim_log as $aim_entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $aim_entry['time'] ) ); ?></td>
					<td><?php echo esc_html( $aim_entry['user'] ); ?></td>
					<td><?php echo esc_html( $aim_entry['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><?php echo AIM_AVT_Admin::button_form( 'clear_log', __( 'Clear the log', 'aim-avt' ), 'button button-small' ); // phpcs:ignore WordPress.Security.EscapingOutput ?></p>
	<?php endif; ?>
</div>
