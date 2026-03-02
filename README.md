# Handsome Pete Booking System

A custom WordPress plugin for ride booking with distance-based pricing, Google Maps integration, and Stripe payments.

## Features

- **Distance-based pricing**: $1.75 per kilometer calculated using Google Distance Matrix API
- **Service area restrictions**: Limited to Grafton, Cobourg, Port Hope, and Brighton
- **Overlap prevention**: Strict single-driver booking system with no overlapping rides
- **Payment options**: 
  - Pay now via Stripe Checkout
  - Pay on pickup via Stripe Tap to Pay
- **SMS notifications**: Automatic notifications via Twilio to driver and customers
- **Admin dashboard**: Complete booking management interface
- **Availability management**: Block dates/times for driver unavailability

## Installation

1. Upload the `handsome-pete-booking` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > Bookings to configure:
   - Google Maps API key
   - Stripe API keys
   - Twilio credentials
   - Pricing settings

## Configuration

### Required API Keys

1. **Google Maps Platform**
   - Places API (for address autocomplete)
   - Distance Matrix API (for distance calculation)
   - Enable billing and restrict API keys to your domain

2. **Stripe**
   - Publishable key and Secret key
   - Webhook endpoint: `https://yoursite.com/wp-json/hp-booking/v1/stripe-webhook`
   - Subscribe to: `payment_intent.succeeded`, `terminal.payment_succeeded`, `payment_intent.payment_failed`

3. **Twilio**
   - Account SID
   - Auth Token
   - Phone number for sending SMS

### Service Area Setup

Service area bounds are pre-configured for:
- Grafton, Ontario
- Cobourg, Ontario
- Port Hope, Ontario
- Brighton, Ontario

To modify service areas, update the `wp_hp_service_areas` table directly.

## Usage

### For Customers

Use the shortcode `[hp_booking_form]` on any page to display the booking form.

### For Administrators

1. **View Bookings**: Navigate to Bookings > All Bookings
2. **Manage Availability**: Go to Bookings > Availability
3. **Configure Settings**: Visit Bookings > Settings

## Development

### File Structure

```
handsome-pete-booking/
├── handsome-pete-booking.php (main plugin file)
├── includes/
│   ├── class-database.php
│   ├── class-api-endpoints.php
│   ├── class-google-maps.php
│   ├── class-stripe-integration.php
│   ├── class-twilio-integration.php
│   ├── class-booking-validator.php
│   ├── class-availability-manager.php
│   └── class-admin-dashboard.php
├── admin/
│   ├── class-admin-menu.php
│   ├── admin-dashboard.php
│   ├── admin-bookings-list.php
│   ├── admin-availability.php
│   ├── admin-settings.php
│   └── assets/
├── public/
│   ├── shortcodes/
│   └── assets/
└── README.md
```

### Database Tables

- `wp_hp_bookings`: Stores all bookings
- `wp_hp_availability_blocks`: Driver availability blocks
- `wp_hp_service_areas`: Service area boundaries
- `wp_hp_settings`: Plugin configuration

## API Endpoints

### Public Endpoints

- `GET /wp-json/hp-booking/v1/availability?date=YYYY-MM-DD`
- `POST /wp-json/hp-booking/v1/calculate-price`
- `POST /wp-json/hp-booking/v1/validate-address`
- `POST /wp-json/hp-booking/v1/create-booking`
- `POST /wp-json/hp-booking/v1/stripe-webhook`

### Admin Endpoints (require authentication)

- `GET /wp-json/hp-booking/v1/admin/bookings`
- `GET /wp-json/hp-booking/v1/admin/booking/{id}`
- `POST /wp-json/hp-booking/v1/admin/booking/{id}/cancel`
- `POST /wp-json/hp-booking/v1/admin/booking/{id}/complete`
- `POST /wp-json/hp-booking/v1/admin/availability/block`
- `DELETE /wp-json/hp-booking/v1/admin/availability/block/{id}`
- `GET /wp-json/hp-booking/v1/admin/stats`

## Updating the live site

To push changes directly to https://handsomepete.ca/booking/, use the single reference: **[HOSTED-SITE-UPDATES.md](HOSTED-SITE-UPDATES.md)**. It contains FTP host, server paths (plugin is `booking-pete` on server), and upload commands for CSS/JS/PHP.

## Support

For issues or questions, contact the development team.

## License

GPL v2 or later
