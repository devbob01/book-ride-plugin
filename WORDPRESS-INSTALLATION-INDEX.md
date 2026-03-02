# WordPress Installation Index
## Pete Booking Site

**Installation Path:** `/Users/devbob/Local Sites/pete-booking/app/public`

**Date Indexed:** January 22, 2026

---

## WordPress Configuration

### Database Settings
- **Database Name:** `local`
- **Database User:** `root`
- **Database Host:** `localhost`
- **Charset:** `utf8`
- **Environment:** Local Development (Local by Flywheel)

### WordPress Version
- Standard WordPress installation
- Located at: `/wp-content/`

---

## Installed Plugins

### 1. Handsome Pete Booking System
**Location:** `/wp-content/plugins/Archive-1/`

**Status:** ✅ Installed (Note: Plugin folder is named "Archive-1" - consider renaming to "handsome-pete-booking" for clarity)

**Version:** 1.0.0

**Plugin File:** `handsome-pete-booking.php`

**Structure:**
```
Archive-1/
├── admin/
│   ├── admin-availability.php
│   ├── admin-bookings-list.php
│   ├── admin-dashboard.php
│   ├── admin-help.php
│   ├── admin-quick-start.php
│   ├── admin-settings.php
│   ├── assets/
│   │   ├── admin.css
│   │   └── admin.js
│   └── class-admin-menu.php
├── includes/
│   ├── class-admin-dashboard.php
│   ├── class-api-endpoints.php
│   ├── class-availability-manager.php
│   ├── class-booking-validator.php
│   ├── class-database.php
│   ├── class-google-maps.php
│   ├── class-stripe-integration.php
│   └── class-twilio-integration.php
├── public/
│   ├── assets/
│   │   ├── booking-app.js
│   │   ├── react-app/
│   │   │   ├── main.js
│   │   │   └── main.jsx
│   │   └── styles.css
│   └── shortcodes/
│       └── booking-form.php
├── API-KEY-SETUP.md
├── composer.json
├── DEPLOYMENT.md
├── handsome-pete-booking.php (Main Plugin File)
├── IMPLEMENTATION-STATUS.md
├── PRE-SETUP-CHECKLIST.md
├── README.md
├── uninstall.php
└── USER-GUIDE.md
```

**Key Features:**
- Distance-based pricing system
- Google Maps integration (Places API, Distance Matrix API, Geocoding API)
- Stripe payment processing
- Twilio SMS notifications
- Service area validation (Grafton, Cobourg, Port Hope, Brighton)
- Admin dashboard for managing bookings
- Availability management system
- REST API endpoints

**Shortcode:** `[hp_booking_form]`

**Admin Menu:** Bookings (with submenus: Dashboard, Bookings, Availability, Settings, Help)

**API Endpoints:**
- `/wp-json/hp-booking/v1/availability`
- `/wp-json/hp-booking/v1/calculate-price`
- `/wp-json/hp-booking/v1/validate-address`
- `/wp-json/hp-booking/v1/create-booking`
- `/wp-json/hp-booking/v1/stripe-webhook`
- Admin endpoints (require authentication)

---

## Active Themes

### Default Themes Installed:
1. **Twenty Twenty-Five** (`twentytwentyfive/`)
2. **Twenty Twenty-Four** (`twentytwentyfour/`)
3. **Twenty Twenty-Three** (`twentytwentythree/`)

**Location:** `/wp-content/themes/`

---

## Plugin Status & Notes

### ✅ Installed Version Observations:
- Plugin is properly installed and structured
- Main plugin file is correctly named and located
- All core classes are present
- Public assets (JS, CSS) are in place
- Shortcode handler is implemented

### ⚠️ Recommendations:

1. **Plugin Folder Naming:**
   - Current: `Archive-1/`
   - Recommended: `handsome-pete-booking/`
   - The current name suggests it may have been archived or is a backup

2. **Version Differences Detected:**
   - **Source code location:** `/Users/devbob/Documents/handsomepete/dev/handsome-pete-booking/`
   - **Installed location:** `/Users/devbob/Local Sites/pete-booking/app/public/wp-content/plugins/Archive-1/`
   
   **Key Differences Found:**
   - **Installed version** uses `hpBooking` global variable (defined via `wp_localize_script`)
   - **Source version** uses `window.hpBooking` with complex initialization logic
   - **Installed version** has simpler `booking-form.php` without `wp_head` hooks
   - **Source version** has enhanced error handling and retry logic for `hpBooking` initialization
   - **Source version** has debug code removed (cleaner production code)
   
   **Action Required:** 
   - Decide which version to use (installed vs source)
   - If using source version, update installed plugin files
   - If keeping installed version, ensure it's working correctly

3. **Elementor Compatibility:**
   - Elementor was not found in the plugins directory
   - If using Elementor, ensure the booking form shortcode works within Elementor widgets
   - The plugin's CSS (liquid glass effects) should work with Elementor pages
   - Note: Source version has Elementor-ready CSS in `background.html` file

---

## Database Tables

The plugin creates the following database tables (with `wp_` prefix):
- `wp_hp_bookings` - Stores all booking records
- `wp_hp_availability_blocks` - Manages blocked/unavailable time slots
- `wp_hp_service_areas` - Defines service area boundaries
- `wp_hp_settings` - Stores plugin configuration

---

## Configuration Requirements

### Required API Keys:
1. **Google Maps API Key**
   - Required APIs: Places API, Distance Matrix API, Geocoding API, Maps JavaScript API
   - Configure in: Bookings → Settings → Google Maps Settings

2. **Stripe Keys** (Optional - for online payments)
   - Publishable Key (`pk_...`)
   - Secret Key (`sk_...`)
   - Webhook Secret
   - Configure in: Bookings → Settings → Stripe Settings

3. **Twilio Credentials** (Optional - for SMS notifications)
   - Account SID
   - Auth Token
   - Phone Number
   - Configure in: Bookings → Settings → Twilio Settings

### Default Settings:
- Price per km: $1.75
- Buffer time: 30 minutes
- Minimum notice: 2 hours
- Time slot interval: 15 minutes
- Working hours: 08:00 - 20:00

---

## File Paths Reference

### WordPress Root:
```
/Users/devbob/Local Sites/pete-booking/app/public/
```

### Plugin Location:
```
/wp-content/plugins/Archive-1/
```

### Plugin Assets:
- JavaScript: `/wp-content/plugins/Archive-1/public/assets/booking-app.js`
- CSS: `/wp-content/plugins/Archive-1/public/assets/styles.css`
- Admin CSS: `/wp-content/plugins/Archive-1/admin/assets/admin.css`
- Admin JS: `/wp-content/plugins/Archive-1/admin/assets/admin.js`

### REST API Base:
```
/wp-json/hp-booking/v1/
```

---

## Next Steps

1. ✅ Verify plugin is activated in WordPress admin
2. ⏭️ Configure API keys in plugin settings
3. ⏭️ Test booking form shortcode on a page
4. ⏭️ Verify database tables were created
5. ⏭️ Test booking flow end-to-end
6. ⏭️ Consider renaming plugin folder from "Archive-1" to "handsome-pete-booking"

---

## Support Files

Documentation files in plugin directory:
- `API-KEY-SETUP.md` - Instructions for setting up API keys
- `DEPLOYMENT.md` - Deployment instructions
- `IMPLEMENTATION-STATUS.md` - Implementation status
- `PRE-SETUP-CHECKLIST.md` - Pre-setup checklist
- `README.md` - Plugin overview
- `USER-GUIDE.md` - User guide

---

**Last Updated:** January 22, 2026
