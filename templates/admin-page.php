<?php
if (!defined('ABSPATH')) exit;
?>

<div class="aim-ai-container">
    <div class="aim-ai-header">
        <h1>AIM AI Visibility Technical Suite</h1>
        <p class="subtitle">Retail Fixture Solutions - AI Visibility Implementation</p>
    </div>

    <div class="aim-ai-status">
        <div class="status-card">
            <h3>Plugin Status</h3>
            <p>Total Changes: <strong><?php echo esc_html($status['total_changes']); ?></strong></p>
            <p>Last Updated: <strong><?php echo esc_html($status['last_change'] ? date_i18n('F j, Y g:i a', strtotime($status['last_change'])) : 'Never'); ?></strong></p>
            <p>Status: <strong><?php echo $status['settings_active'] ? '<span class="status-active">Active</span>' : '<span class="status-inactive">Inactive</span>'; ?></strong></p>
        </div>
    </div>

    <div class="aim-ai-tabs">
        <div class="tabs-nav">
            <button class="tab-button active" data-tab="organization">Organization</button>
            <button class="tab-button" data-tab="metadata">Metadata</button>
            <button class="tab-button" data-tab="services">Services</button>
            <button class="tab-button" data-tab="advanced">Advanced</button>
        </div>

        <form method="post" id="aim-ai-settings-form" class="aim-ai-form">
            <?php wp_nonce_field('aim_ai_nonce'); ?>

            <!-- Organization Tab -->
            <div class="tab-content active" id="organization-tab">
                <h2>Organization Information</h2>
                <p class="description">Configure your organization's schema and entity information</p>

                <div class="form-group">
                    <label for="org_name">Organization Name</label>
                    <input type="text" name="settings[aim_ai_org_name]" id="org_name" value="<?php echo esc_attr($settings['org_name']); ?>" required>
                    <small>Legal business name</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="org_phone">Phone Number</label>
                        <input type="tel" name="settings[aim_ai_org_phone]" id="org_phone" value="<?php echo esc_attr($settings['org_phone']); ?>" placeholder="+1-XXX-XXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label for="org_url">Website URL</label>
                        <input type="url" name="settings[aim_ai_org_url]" id="org_url" value="<?php echo esc_attr($settings['org_url']); ?>" required>
                    </div>
                </div>

                <h3>Address Information</h3>
                <div class="form-group">
                    <label for="org_address">Street Address</label>
                    <input type="text" name="settings[aim_ai_org_address]" id="org_address" value="<?php echo esc_attr($settings['org_address']); ?>" placeholder="123 Main Street">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="org_city">City</label>
                        <input type="text" name="settings[aim_ai_org_city]" id="org_city" value="<?php echo esc_attr($settings['org_city']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="org_state">State/Province</label>
                        <input type="text" name="settings[aim_ai_org_state]" id="org_state" value="<?php echo esc_attr($settings['org_state']); ?>" maxlength="2">
                    </div>
                    <div class="form-group">
                        <label for="org_zip">ZIP/Postal Code</label>
                        <input type="text" name="settings[aim_ai_org_zip]" id="org_zip" value="<?php echo esc_attr($settings['org_zip']); ?>">
                    </div>
                </div>

                <h3>Additional Organization Data</h3>
                <div class="form-group">
                    <label for="org_founding_date">Founding Date (YYYY-MM-DD)</label>
                    <input type="date" name="settings[aim_ai_org_founding_date]" id="org_founding_date" value="<?php echo esc_attr($settings['org_founding_date']); ?>">
                </div>

                <div class="form-group">
                    <label for="org_logo_url">Logo URL</label>
                    <input type="url" name="settings[aim_ai_org_logo_url]" id="org_logo_url" value="<?php echo esc_attr($settings['org_logo_url']); ?>" placeholder="https://example.com/logo.png">
                    <small>Direct URL to logo image (PNG, JPG recommended)</small>
                </div>

                <div class="form-group">
                    <label for="org_description">Organization Description</label>
                    <textarea name="settings[aim_ai_org_description]" id="org_description" rows="4" placeholder="Brief description of your organization..."><?php echo esc_textarea($settings['org_description']); ?></textarea>
                    <small>Natural language summary for schema</small>
                </div>

                <h3>Verified Profiles (sameAs Links)</h3>
                <div class="form-group">
                    <label for="linkedin_url">LinkedIn Company Page</label>
                    <input type="url" name="settings[aim_ai_linkedin_url]" id="linkedin_url" value="<?php echo esc_attr($settings['linkedin_url']); ?>" placeholder="https://www.linkedin.com/company/your-company">
                </div>

                <div class="form-group">
                    <label for="gcp_url">Google Business Profile URL</label>
                    <input type="url" name="settings[aim_ai_gcp_url]" id="gcp_url" value="<?php echo esc_attr($settings['gcp_url']); ?>" placeholder="https://www.google.com/business/">
                </div>

                <h3>Leadership / Person Schema</h3>
                <div class="form-group">
                    <label for="linkedin_ceo_name">Executive Name</label>
                    <input type="text" name="settings[aim_ai_linkedin_ceo_name]" id="linkedin_ceo_name" value="<?php echo esc_attr($settings['linkedin_ceo_name']); ?>" placeholder="First and Last Name">
                </div>

                <div class="form-group">
                    <label for="linkedin_ceo_url">Executive LinkedIn Profile</label>
                    <input type="url" name="settings[aim_ai_linkedin_ceo_url]" id="linkedin_ceo_url" value="<?php echo esc_attr($settings['linkedin_ceo_url']); ?>" placeholder="https://www.linkedin.com/in/your-profile">
                </div>
            </div>

            <!-- Metadata Tab -->
            <div class="tab-content" id="metadata-tab">
                <h2>Metadata Configuration</h2>
                <p class="description">Configure meta descriptions and default page metadata</p>

                <div class="form-group">
                    <label for="default_meta_description">Default Meta Description</label>
                    <textarea name="settings[aim_ai_default_meta_description]" id="default_meta_description" rows="3" maxlength="160" placeholder="Used when page-specific description not available..."><?php echo esc_textarea($settings['default_meta_description']); ?></textarea>
                    <small>160 characters recommended. This is shown in search results.</small>
                </div>

                <div class="info-box">
                    <h4>Meta Description Best Practices</h4>
                    <ul>
                        <li>Keep between 150-160 characters</li>
                        <li>Include primary target keywords naturally</li>
                        <li>Write compelling copy (call-to-action recommended)</li>
                        <li>Make it unique per page for best results</li>
                    </ul>
                </div>
            </div>

            <!-- Services Tab -->
            <div class="tab-content" id="services-tab">
                <h2>Service Categories</h2>
                <p class="description">Define your core product/service lines for Service schema</p>

                <div id="services-list">
                    <?php
                    $services = $settings['service_categories'];
                    if (is_array($services) && !empty($services)) {
                        foreach ($services as $index => $service) {
                            ?>
                            <div class="service-item" data-index="<?php echo $index; ?>">
                                <input type="hidden" name="settings[aim_ai_service_categories][<?php echo $index; ?>][index]" value="<?php echo $index; ?>">
                                <div class="form-group">
                                    <label>Service Name</label>
                                    <input type="text" name="settings[aim_ai_service_categories][<?php echo $index; ?>][name]" value="<?php echo esc_attr($service['name'] ?? ''); ?>" placeholder="e.g., Gondola Shelving Systems">
                                </div>
                                <div class="form-group">
                                    <label>Service Description</label>
                                    <textarea name="settings[aim_ai_service_categories][<?php echo $index; ?>][description]" placeholder="Description of this service category..."><?php echo esc_textarea($service['description'] ?? ''); ?></textarea>
                                </div>
                                <button type="button" class="button button-secondary remove-service">Remove Service</button>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>

                <button type="button" id="add-service" class="button button-primary">+ Add Service Category</button>

                <div class="info-box">
                    <h4>Service Categories to Add</h4>
                    <ul>
                        <li>Gondola Shelving / Retail Shelving</li>
                        <li>Stockroom Racking Systems</li>
                        <li>Loading Dock Equipment</li>
                        <li>Custom Fabrication Services</li>
                        <li>Turnkey Rollout / Project Management</li>
                    </ul>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="tab-content" id="advanced-tab">
                <h2>Advanced Settings</h2>
                <p class="description">Toggle features and configure advanced options</p>

                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="settings[aim_ai_enable_schema]" value="1" <?php checked($settings['enable_schema'], 1); ?>>
                        <strong>Enable Schema.org Markup</strong>
                    </label>
                    <small>Injects structured data (JSON-LD) for Organization, Service, and Person schema</small>
                </div>

                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="settings[aim_ai_enable_metadata]" value="1" <?php checked($settings['enable_metadata'], 1); ?>>
                        <strong>Enable Meta Tags</strong>
                    </label>
                    <small>Adds meta descriptions and other metadata to page head</small>
                </div>

                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="settings[aim_ai_enable_heading_fix]" value="1" <?php checked($settings['enable_heading_fix'], 1); ?>>
                        <strong>Enable Heading Hierarchy Fix</strong>
                    </label>
                    <small>Ensures proper H1-H6 heading structure (requires Divi template audit)</small>
                </div>

                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="settings[aim_ai_tracking_enabled]" value="1" <?php checked($settings['tracking_enabled'], 1); ?>>
                        <strong>Enable Change Tracking</strong>
                    </label>
                    <small>Maintains version history for undo functionality</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="button button-primary button-large">Save Settings</button>
                <button type="button" id="undo-button" class="button button-secondary button-large">↶ Undo Last Changes</button>
            </div>
        </form>
    </div>

    <div class="aim-ai-footer">
        <p>AIM AI Visibility Technical Suite v<?php echo AIM_AI_PLUGIN_VERSION; ?> | <a href="https://aim-tex.com" target="_blank">Advanced Integrated Marketing Inc.</a></p>
        <p>Assessment Date: 2026-09-13 | Domain: retailfixturesolutions.com | Score: 36/100</p>
    </div>
</div>
