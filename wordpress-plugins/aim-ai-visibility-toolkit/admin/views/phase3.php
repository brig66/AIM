<?php
/**
 * Phase 3 — authority.
 *
 * @package AIM_AVT
 */

defined( 'ABSPATH' ) || exit;

$aim_p     = (array) AIM_AVT_Settings::get( 'pricing', array() );
$aim_bands = (array) $aim_p['bands'];
for ( $aim_i = count( $aim_bands ); $aim_i < count( (array) $aim_p['bands'] ) + 2; $aim_i++ ) {
	$aim_bands[] = array( 'name' => '', 'min' => '', 'max' => '', 'turnaround' => '', 'assumptions' => '' );
}

$aim_press_page = (int) get_option( 'aim_avt_press_page', 0 );
$aim_about      = AIM_AVT_Checklist::check_about();
?>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Recall versus citation', 'aim-avt' ); ?></h2>
	<p>
		<?php esc_html_e( 'The assessment found that ChatGPT names Sentrimax in four of five answers but attaches no source citation to three of them, while competitors\' mentions carry links. The brand is being recalled from model memory rather than cited from its own site.', 'aim-avt' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'Recall decays with every model refresh and cannot be improved by anything Sentrimax publishes. Citation compounds and is entirely within the company\'s control.', 'aim-avt' ); ?></strong>
		<?php esc_html_e( 'Everything in Phase 3 converts the first into the second.', 'aim-avt' ); ?>
	</p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 3.1 — Publish three case studies', 'aim-avt' ); ?> <span class="aim-avt-points">+28</span></h2>
	<p>
		<?php esc_html_e( 'case_study_score measures 0. Across 119 pages there is not one structured account of a specific job — no customer, no machine, no failure mode, no before-and-after, no measured outcome. For a company whose entire proposition is "your machine was down and we got it running", this is the largest content gap in the assessment, and it is scored twice.', 'aim-avt' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'The plugin gives you the fields, the Article schema and the named author. The jobs, the measurements and the written customer permission have to come from the shop. The single most valuable thing Sentrimax can do in week one is nominate one engineer as the source for this material — everything in Phase 3 depends on that decision, and it takes a day.', 'aim-avt' ); ?>
	</p>
	<p><?php esc_html_e( 'The checklist suggests three jobs spanning different industries and machine brands, for example a municipal wastewater Alfa Laval overhaul, an oilfield solids-control rebuild, and a rendering-plant gearbox and hardfacing job.', 'aim-avt' ); ?></p>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . AIM_AVT_Content::CASE_STUDY ) ); ?>"><?php esc_html_e( 'Add a case study', 'aim-avt' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . AIM_AVT_Content::CASE_STUDY ) ); ?>"><?php esc_html_e( 'Manage case studies', 'aim-avt' ); ?></a>
	</p>
	<?php $aim_cs = AIM_AVT_Checklist::check_case_studies(); ?>
	<p><span class="aim-avt-badge aim-avt-badge--<?php echo esc_attr( $aim_cs['status'] ); ?>"><?php echo esc_html( $aim_cs['note'] ); ?></span></p>
	<p class="description"><?php esc_html_e( 'List them anywhere with the shortcode', 'aim-avt' ); ?> <code>[aim_case_studies]</code></p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 3.3 — Put real numbers on the repair-cost page', 'aim-avt' ); ?> <span class="aim-avt-points">+13</span></h2>
	<p>
		<?php esc_html_e( 'pricing_content_present measures false. The pipeline searched all 119 pages for pricing schema and for rendered price tokens and found nothing. There is a 1,899-word page at /decanter-centrifuge-repair-cost/ that discusses cost at length without stating a single figure.', 'aim-avt' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'Cost is among the most common things buyers ask AI assistants, and a page about cost that contains no numbers is the one page guaranteed not to be cited for it.', 'aim-avt' ); ?></strong>
		<?php esc_html_e( 'Banded ranges with stated assumptions are enough — precision is not required, presence is.', 'aim-avt' ); ?>
	</p>

	<?php AIM_AVT_Admin::form_open( 'save_pricing' ); ?>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Pricing output', 'aim-avt' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="pricing_enabled" value="1" <?php checked( ! empty( $aim_p['enabled'] ) ); ?> />
						<?php esc_html_e( 'Render the pricing table and its Offer markup where the shortcode appears', 'aim-avt' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'A band with no figure in it renders nothing at all — an empty table would be the same defect the assessment recorded.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Currency', 'aim-avt' ); ?></th>
				<td>
					<select name="currency">
						<option value="USD" <?php selected( 'USD', $aim_p['currency'] ); ?>><?php esc_html_e( 'US dollars', 'aim-avt' ); ?></option>
						<option value="CAD" <?php selected( 'CAD', $aim_p['currency'] ); ?>><?php esc_html_e( 'Canadian dollars', 'aim-avt' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="as_of"><?php esc_html_e( 'Pricing as of', 'aim-avt' ); ?></label></th>
				<td>
					<input type="date" id="as_of" name="as_of" value="<?php echo esc_attr( $aim_p['as_of'] ); ?>" />
					<p class="description"><?php esc_html_e( 'Printed on the page as a dated line. The checklist asks that you review it quarterly.', 'aim-avt' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="pricing_note"><?php esc_html_e( 'Note printed under the table', 'aim-avt' ); ?></label></th>
				<td><textarea id="pricing_note" name="pricing_note" rows="3" class="large-text"><?php echo esc_textarea( $aim_p['note'] ); ?></textarea></td>
			</tr>
		</tbody>
	</table>

	<table class="widefat striped aim-avt-rules">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Work', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'From', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'To', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'Typical turnaround', 'aim-avt' ); ?></th>
				<th><?php esc_html_e( 'What moves the number', 'aim-avt' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $aim_bands as $aim_i => $aim_band ) : ?>
			<tr>
				<td><input type="text" class="widefat" name="band[<?php echo esc_attr( $aim_i ); ?>][name]" value="<?php echo esc_attr( $aim_band['name'] ); ?>" /></td>
				<td><input type="text" class="small-text" name="band[<?php echo esc_attr( $aim_i ); ?>][min]" value="<?php echo esc_attr( $aim_band['min'] ); ?>" placeholder="8000" /></td>
				<td><input type="text" class="small-text" name="band[<?php echo esc_attr( $aim_i ); ?>][max]" value="<?php echo esc_attr( $aim_band['max'] ); ?>" placeholder="15000" /></td>
				<td><input type="text" class="regular-text" name="band[<?php echo esc_attr( $aim_i ); ?>][turnaround]" value="<?php echo esc_attr( $aim_band['turnaround'] ); ?>" placeholder="<?php esc_attr_e( '10-14 days', 'aim-avt' ); ?>" /></td>
				<td><input type="text" class="widefat" name="band[<?php echo esc_attr( $aim_i ); ?>][assumptions]" value="<?php echo esc_attr( $aim_band['assumptions'] ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">
		<?php esc_html_e( 'State turnaround alongside price — downtime cost is usually the larger number for the buyer. Clear a row\'s name and save to delete it.', 'aim-avt' ); ?>
	</p>
	<?php AIM_AVT_Admin::form_close( __( 'Save pricing', 'aim-avt' ) ); ?>

	<p><?php esc_html_e( 'Then paste this into /decanter-centrifuge-repair-cost/:', 'aim-avt' ); ?> <code>[aim_pricing]</code></p>
	<p class="description">
		<?php esc_html_e( 'The checklist also notes a quick partial win: the equipment items already carry prices in their titles, for example the Bird SA-70 gearbox listing. Bringing those into Offer markup satisfies the same rule.', 'aim-avt' ); ?>
	</p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 3.4 — Rebuild genuine earned media', 'aim-avt' ); ?> <span class="aim-avt-points">+7</span></h2>
	<p>
		<?php esc_html_e( 'Task 1.1 removes 48 counted "placements" that were link spam, taking this rule to zero. This task replaces them with real ones. The rule counts distinct verified third-party placements linked from a press or recognition page, so the deliverable is two things: genuine coverage, and a page that collects it.', 'aim-avt' ); ?>
	</p>
	<div class="aim-avt-alert aim-avt-alert--warn">
		<p><strong><?php esc_html_e( 'Engage no vendor offering paid link placements.', 'aim-avt' ); ?></strong> <?php esc_html_e( 'That is the pattern currently on the site and it is what Task 1.1 exists to remove.', 'aim-avt' ); ?></p>
	</div>
	<ul class="aim-avt-bullets">
		<li><?php esc_html_e( 'Pitch technical bylines to trade publications, starting with Ethanol Producer Magazine — it already carries a Sentrimax directory listing and Perplexity already cites it when describing the company.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Approach Water Environment Federation publications and rendering-industry press with the case studies from Task 3.1.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'List OEM and distributor partner pages, association memberships and the ISO 9001:2015 certification with its registrar link.', 'aim-avt' ); ?></li>
	</ul>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . AIM_AVT_Content::PRESS_ITEM ) ); ?>"><?php esc_html_e( 'Add a press item', 'aim-avt' ); ?></a>
		<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . AIM_AVT_Content::PRESS_ITEM ) ); ?>"><?php esc_html_e( 'Manage press items', 'aim-avt' ); ?></a>
		<?php if ( ! $aim_press_page || ! get_post( $aim_press_page ) ) : ?>
			<?php
			echo AIM_AVT_Admin::button_form( // phpcs:ignore WordPress.Security.EscapingOutput
				'create_page',
				__( 'Create the press page', 'aim-avt' ),
				'button',
				'',
				array( 'page_key' => 'press' )
			);
			?>
		<?php else : ?>
			<a class="button" href="<?php echo esc_url( get_edit_post_link( $aim_press_page ) ); ?>"><?php esc_html_e( 'Edit the press page', 'aim-avt' ); ?></a>
		<?php endif; ?>
	</p>
	<p class="description"><?php esc_html_e( 'Shortcode:', 'aim-avt' ); ?> <code>[aim_press]</code></p>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 3.2 — Restore the news section', 'aim-avt' ); ?> <span class="aim-avt-points">+15</span></h2>
	<p>
		<?php esc_html_e( 'blog_substantial_ratio measures 0.0 — the section holds one post of 372 words, against an early-2026 archived capture showing four or more articles. A news section is the one part of a site that shows an engine the company is currently active and currently expert. A dormant one signals the opposite.', 'aim-avt' ); ?>
	</p>
	<?php $aim_blog = AIM_AVT_Checklist::check_blog(); ?>
	<p><span class="aim-avt-badge aim-avt-badge--<?php echo esc_attr( $aim_blog['status'] ); ?>"><?php echo esc_html( $aim_blog['note'] ); ?></span></p>
	<ul class="aim-avt-bullets">
		<li><?php esc_html_e( 'Two substantive technical articles per month, each 1,200+ words answering one question completely.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Draw topics from real service-desk questions rather than keyword lists — field observations, failure post-mortems and process notes.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Byline every post to a named engineer with Person schema (Tasks 2.2 and 2.3).', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Consider restoring the four earlier articles if the underlying content is still accurate.', 'aim-avt' ); ?></li>
	</ul>
</div>

<div class="aim-avt-card">
	<h2><?php esc_html_e( 'Task 3.5 — Extend the About page', 'aim-avt' ); ?> <span class="aim-avt-points">+4</span></h2>
	<p><span class="aim-avt-badge aim-avt-badge--<?php echo esc_attr( $aim_about['status'] ); ?>"><?php echo esc_html( $aim_about['note'] ); ?></span></p>
	<p>
		<?php esc_html_e( 'The current page establishes the 2002 founding and the ISO 9001:2015 certification and stops short of the story. The About page is where an AI engine forms its model of what kind of company this is, and it is the page most often quoted when an engine is asked to describe a business rather than recommend one.', 'aim-avt' ); ?>
	</p>
	<ul class="aim-avt-bullets">
		<li><?php esc_html_e( 'Extend past 900 words with the founding story, the engineering lineage, and the reasoning behind the three-plant footprint.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Name the leadership team and link to their profiles (Task 2.2).', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Describe the ISO 9001:2015 quality system in operational terms — what actually gets documented on a repair.', 'aim-avt' ); ?></li>
		<li><?php esc_html_e( 'Avoid filler. The rule measures words but an engine measures substance, and padding is visible to both.', 'aim-avt' ); ?></li>
	</ul>
</div>
