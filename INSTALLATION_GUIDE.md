# Quick Installation & Setup Guide

## 5-Minute Setup

### 1. Copy Plugin Files (2 minutes)

Upload these files to your WordPress installation:

```
/wp-content/plugins/aim-ai-visibility/
├── aim-ai-visibility-plugin.php      (main plugin file)
├── templates/
│   └── admin-page.php                (admin interface template)
└── assets/
    ├── admin-style.css               (styling)
    └── admin-script.js               (functionality)
```

### 2. Activate Plugin (1 minute)

1. Log in to WordPress Dashboard
2. Navigate to **Plugins** menu
3. Find **AIM AI Visibility Technical Suite**
4. Click **Activate**

### 3. Initial Configuration (2 minutes)

1. Go to **Dashboard → AI Visibility**
2. Fill in required fields:
   - Organization Name: `Retail Fixture Solutions`
   - Phone: `+1-972-923-0001`
   - URL: `https://retailfixturesolutions.com`
   - Address: Your business address
   - Founding Date: Your founding year (YYYY-MM-DD)

3. Click **Save Settings**

## Complete Configuration Checklist

### ✅ Organization Tab
- [ ] Organization Name
- [ ] Phone Number
- [ ] Website URL
- [ ] Street Address
- [ ] City (Dallas)
- [ ] State (TX)
- [ ] ZIP Code
- [ ] Founding Date (format: 1995-03-15)
- [ ] Logo URL (https://example.com/logo.png)
- [ ] Organization Description
- [ ] LinkedIn Company Page URL
- [ ] Google Business Profile URL
- [ ] CEO/Executive Name
- [ ] CEO/Executive LinkedIn Profile

### ✅ Metadata Tab
- [ ] Default Meta Description (150-160 characters)

### ✅ Services Tab
- [ ] Add 5 service categories:
  1. Gondola Shelving Systems
  2. Stockroom Racking Systems
  3. Loading Dock Equipment
  4. Custom Fabrication Services
  5. Turnkey Rollout Services

### ✅ Advanced Tab
- [ ] Enable Schema.org Markup (toggle ON)
- [ ] Enable Meta Tags (toggle ON)
- [ ] Enable Heading Hierarchy Fix (toggle ON)
- [ ] Enable Change Tracking (toggle ON)

## What Happens Next

Once configured, the plugin automatically:

1. **Injects schema.org JSON-LD markup** into your site's `<head>` tag
2. **Adds meta descriptions** to pages
3. **Generates Service schemas** for each category
4. **Creates Person schema** for leadership
5. **Stores sameAs links** for entity verification
6. **Tracks all changes** for undo capability

### Frontend Changes (Invisible to Visitors)

- JSON-LD structured data added to page source
- Meta tags in page header
- No visual changes to website appearance
- No performance impact

### Admin Changes (Dashboard Only)

- New "AI Visibility" menu item
- Settings page with 4 tabs
- Status dashboard
- Undo button

## Verification

### Check Schema Is Working

1. Go to any page on your site
2. Right-click → "View Page Source"
3. Search for `"@context": "https://schema.org"`
4. You should see JSON-LD blocks

### Validate with Google

1. Visit [Google Rich Results Test](https://search.google.com/test/rich-results)
2. Enter your website URL
3. Look for "Organization" and "Service" schema detected

### Check Meta Descriptions

1. View page source
2. Search for `<meta name="description"`
3. Should see your configured description

## Troubleshooting

### Plugin Doesn't Appear in Dashboard

**Solution:** 
- Go to Plugins → All Plugins
- Search for "AIM AI Visibility"
- Click Activate if showing inactive

### Settings Page Blank

**Solution:**
- Clear browser cache (Ctrl+Shift+Del)
- Disable browser extensions temporarily
- Try different browser

### Can't Save Settings

**Solution:**
- Ensure logged in as Admin
- Check server permissions (755+ for files)
- Disable other SEO plugins temporarily
- Check WordPress error log

### Schema Not Showing on Site

**Solution:**
- Verify "Enable Schema" toggle is ON
- Check page source (not visual inspection)
- Validate with Google Rich Results Test
- Clear browser cache

## Getting Help

### Common Issues & Fixes

**Q: Nothing shows when I save**
A: Check browser console (F12) for errors. Ensure your theme has `wp_head()` hook.

**Q: Can I edit settings later?**
A: Yes, go to AI Visibility dashboard anytime to update.

**Q: What if I make a mistake?**
A: Click "Undo Last Changes" to revert to previous version.

**Q: Does this affect my site speed?**
A: No, impact is <1ms. Schema injection is lightweight.

**Q: Can I deactivate later?**
A: Yes. Deactivate from Plugins menu. Schema stops being injected.

## Next Steps

After initial setup:

1. **Verify schema** works using Google Rich Results Test
2. **Monitor status** - Check "Total Changes" counter
3. **Plan Phase 2** - Review assessment for content updates
4. **Request reviews** - Start collecting customer testimonials
5. **Update GBP** - Set up/claim Google Business Profile

## Contact

For technical support:
- **Email**: support@aim-tex.com
- **Phone**: 817-592-5586
- **Website**: https://aim-tex.com

---

**Version:** 1.0.0  
**Last Updated:** September 13, 2026  
**For:** Retail Fixture Solutions (retailfixturesolutions.com)
