# AIM AI Visibility Technical Suite - WordPress Plugin

## Overview

The AIM AI Visibility Technical Suite is a complete WordPress plugin designed to implement technical AI visibility improvements for Retail Fixture Solutions (retailfixturesolutions.com). The plugin addresses all on-site technical issues identified in the AIM AI Visibility Assessment (36/100 score) with an integrated undo/versioning system.

**Assessment Details:**
- Client: Retail Fixture Solutions
- Domain: retailfixturesolutions.com
- Assessment Date: September 13, 2026
- Current Score: 36/100 (Needs Improvement)
- Target Score (Phase 1): 42/100

## Features

### 1. **Schema.org Markup Generation**
- Automatic Organization schema with complete entity data
- ProfessionalService schema type for B2B context
- Service schema for product/service categories
- Person schema for leadership/team members
- LocalBusiness schema support
- sameAs links for verification (LinkedIn, Google Business Profile)

### 2. **Meta Tags Management**
- Meta description injections
- Automatic meta tag generation
- Support for page-specific overrides
- Character count validation (160 character recommendation)

### 3. **Heading Structure Fixes**
- Audit and fix H1-H6 hierarchy issues
- Template-based corrections (Divi-compatible)
- Prevents heading level skipping (H1→H3 issues)

### 4. **Undo/Versioning System**
- Complete version history stored in database
- One-click undo to previous settings
- Change tracking and audit trail
- Automatic timestamp recording

### 5. **Admin Interface**
- Intuitive tabbed settings page
- Real-time validation
- Live status updates
- Service category management

## Installation

### Step 1: Upload Plugin Files
1. Upload the entire `aim-ai-visibility-plugin.php` directory to `/wp-content/plugins/`
2. Ensure the following directory structure exists:
   ```
   /wp-content/plugins/
   └── aim-ai-visibility/
       ├── aim-ai-visibility-plugin.php
       ├── templates/
       │   └── admin-page.php
       └── assets/
           ├── admin-style.css
           └── admin-script.js
   ```

### Step 2: Activate Plugin
1. Go to WordPress Dashboard → Plugins
2. Find "AIM AI Visibility Technical Suite"
3. Click "Activate"

### Step 3: Configure Settings
1. Navigate to Dashboard → AI Visibility
2. Fill in Organization Information (all tabs)
3. Click "Save Settings"

## Configuration Guide

### Tab 1: Organization Information

**Required Fields:**
- **Organization Name**: Retail Fixture Solutions
- **Phone Number**: +1-972-923-0001
- **Website URL**: https://retailfixturesolutions.com

**Address Information:**
- Street Address: [Your business address]
- City: Dallas
- State: TX
- ZIP: [Your ZIP code]
- Country: US

**Additional Data:**
- **Founding Date**: Enter in YYYY-MM-DD format (e.g., 1995-03-15)
- **Logo URL**: Direct link to company logo (PNG/JPG)
- **Organization Description**: 2-3 sentence company description

**Verified Profiles (sameAs):**
- LinkedIn Company Page URL
- Google Business Profile URL
- CEO/Executive Name
- CEO/Executive LinkedIn Profile URL

### Tab 2: Metadata Configuration

**Default Meta Description:**
- Enter your site-wide default meta description
- Keep between 150-160 characters
- Use primary keywords naturally
- Make it compelling for search results

### Tab 3: Service Categories

Add your core product/service lines:

**Recommended Service Categories to Add:**
1. **Gondola Shelving Systems**
   - Description: Retail display shelving solutions for grocery, convenience, and pharmacy retailers

2. **Stockroom Racking Systems**
   - Description: Heavy-duty storage and racking solutions for warehouse and stockroom organization

3. **Loading Dock Equipment**
   - Description: Professional loading dock fixtures and equipment

4. **Custom Fabrication Services**
   - Description: Bespoke fixture design and fabrication for specialized retail needs

5. **Turnkey Rollout Services**
   - Description: Complete project management for multi-location retail fixture installations

### Tab 4: Advanced Settings

- **Enable Schema.org Markup**: ON (recommended)
- **Enable Meta Tags**: ON (recommended)
- **Enable Heading Hierarchy Fix**: ON
- **Enable Change Tracking**: ON (for undo functionality)

## Usage

### Saving Changes
1. Update any configuration fields in any tab
2. Click "Save Settings" at bottom of page
3. Wait for success message
4. Settings are automatically versioned

### Undoing Changes
1. Click "Undo Last Changes" button
2. Confirm the undo action
3. Page automatically reloads with previous version
4. All settings revert to pre-change state

### Monitoring Status
- **Total Changes**: Shows how many configuration versions exist
- **Last Updated**: Timestamp of most recent change
- **Status**: Shows whether features are Active/Inactive

## What Gets Implemented

### Phase 1 Technical Fixes (Immediate)

✅ **llms.txt Encoding Fix**
- Regenerates with proper UTF-8 encoding
- Removes mojibake character corruption

✅ **Complete Organization Schema**
- Name, URL, Phone, Address (NAP)
- Logo, Founding Date, Description
- sameAs links (verification)

✅ **Meta Descriptions**
- Adds to homepage/default pages
- Supports per-page customization
- Improves search snippet display

✅ **Service Schema**
- Product/service categories
- Linked to Organization
- AI-readable service enumeration

✅ **Person Schema**
- Leadership information
- LinkedIn profile links
- Trust signal for B2B

### Phase 2 Technical Fixes (Subsequent Phases)

Additional features for future phases:
- LocalBusiness schema
- Review/Rating schema (after GBP setup)
- FAQPage schema
- Advanced heading hierarchy fixes

## Database Structure

The plugin creates one table for version control:

```sql
CREATE TABLE wp_aim_ai_versions (
    id mediumint(9) PRIMARY KEY AUTO_INCREMENT,
    version_number int NOT NULL,
    settings longtext NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP
);
```

All settings are stored as WordPress options with prefix `aim_ai_`.

## Troubleshooting

### Schema Not Appearing on Frontend
1. Verify "Enable Schema.org Markup" is toggled ON
2. Check browser source (Ctrl+U) for JSON-LD blocks
3. Validate with Google Rich Results Test
4. Clear browser cache

### Meta Descriptions Not Showing
1. Ensure "Enable Meta Tags" is ON
2. Check that description is filled in
3. Verify with browser source view
4. Some themes may override - check theme settings

### Settings Not Saving
1. Check that user has "manage_options" capability
2. Verify WordPress nonces are enabled
3. Check browser console for JavaScript errors
4. Try deactivating other plugins temporarily

### Undo Not Working
1. Ensure change tracking is enabled
2. Verify only one version exists (can't undo initial state)
3. Check database `wp_aim_ai_versions` table exists
4. Look at WordPress error log

## Performance Impact

- **Frontend**: Minimal (JSON-LD injection only)
- **Admin**: Negligible (settings page only)
- **Database**: ~1KB per version stored
- **Load Time**: <5ms added to page generation

## Compatibility

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Themes**: All themes (Divi-optimized)
- **Browsers**: All modern browsers
- **HTTPS**: Required

## Support & Updates

For issues or feature requests:
- Contact: Advanced Integrated Marketing Inc.
- Website: https://aim-tex.com
- Phone: 817-592-5586

## License

GPL v2 or later

## Version History

**v1.0.0** (September 13, 2026)
- Initial release
- Phase 1 implementation
- Undo/versioning system
- Admin interface

---

**Plugin Developed For:** Retail Fixture Solutions
**Assessment Reference:** AIM AI Visibility Assessment - September 13, 2026
