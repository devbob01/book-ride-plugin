# Handsome Pete Booking System - Complete User Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [Configuration](#configuration)
3. [Using the Booking Form](#using-the-booking-form)
4. [Managing Bookings](#managing-bookings)
5. [Availability Management](#availability-management)
6. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Installation

1. **Upload Plugin**
   - Upload the `handsome-pete-booking` folder to `/wp-content/plugins/`
   - Or install via WordPress admin → Plugins → Add New → Upload Plugin

2. **Activate Plugin**
   - Go to Plugins → Installed Plugins
   - Find "Handsome Pete Booking System"
   - Click "Activate"

3. **Initial Setup**
   - After activation, you'll see a "Bookings" menu in WordPress admin
   - Start with the "Quick Start" guide for step-by-step setup

### Quick Setup Checklist

- [ ] Get Google Maps API key
- [ ] Get Stripe API keys
- [ ] Get Twilio credentials
- [ ] Enter all keys in Bookings → Settings
- [ ] Set up Stripe webhook
- [ ] Add booking form to a page using `[hp_booking_form]`
- [ ] Test booking flow

---

## Configuration

### Accessing Settings

Navigate to **Bookings → Settings** in WordPress admin.

### Pricing Settings

**Price per Kilometer**
- Default: $1.75
- Formula: Distance (km) × Price per km = Total Price
- Example: 25 km × $1.75 = $43.75

**Buffer Time**
- Default: 30 minutes
- Time between rides to prevent overlaps
- Includes cleanup, turnaround, refuel time

**Minimum Notice**
- Default: 2 hours
- How far in advance bookings must be made
- Prevents last-minute bookings

### Booking Settings

**Time Slot Interval**
- Default: 15 minutes
- Interval between available time slots
- Example: 15 = slots at :00, :15, :30, :45

**Working Hours**
- Default: 8:00 AM - 8:00 PM
- Times when bookings can be made
- Outside these hours, no slots appear

### API Configuration

#### Google Maps API

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create/select a project
3. Enable APIs:
   - Places API
   - Distance Matrix API
   - Geocoding API
   - Maps JavaScript API
4. Create API key
5. Enable billing (required, but $200 free credit monthly)
6. Paste key in Settings → Google Maps Settings

**Cost:** ~$50-150/month depending on booking volume

#### Stripe Setup

1. Create [Stripe account](https://stripe.com)
2. Get API keys from Dashboard → Developers → API Keys
3. Enter keys in Settings → Stripe Settings
4. **Set up Webhook:**
   - Go to Stripe Dashboard → Developers → Webhooks
   - Add endpoint: `https://yoursite.com/wp-json/hp-booking/v1/stripe-webhook`
   - Select events:
     - `payment_intent.succeeded`
     - `terminal.payment_succeeded`
     - `payment_intent.payment_failed`
   - Copy webhook signing secret
   - Paste in Settings → Stripe Settings → Webhook Secret

**Cost:** 2.9% + $0.30 per transaction

#### Twilio Setup

1. Create [Twilio account](https://www.twilio.com)
2. Get Account SID and Auth Token from dashboard
3. Purchase a phone number (or use trial for testing)
4. Enter all three in Settings → Twilio Settings

**Cost:** ~$0.01-0.05 per SMS message

### Driver Settings

Enter driver contact information:
- **Phone Number:** For SMS notifications (include country code, e.g., +1)
- **Email:** For email notifications (optional)

---

## Using the Booking Form

### Adding the Form to Your Site

Add this shortcode to any page or post:
```
[hp_booking_form]
```

**Example:**
1. Create a page called "Book a Ride"
2. Add the shortcode: `[hp_booking_form]`
3. Publish the page

### Customer Booking Process

#### Step 1: Select Date
- Calendar shows available dates
- Dates with no available slots are hidden
- Minimum notice period applies

#### Step 2: Select Time
- Available time slots appear
- Slots automatically filtered to prevent overlaps
- Respects working hours and buffer times

#### Step 3: Enter Addresses
- **Pickup Address:** Where driver picks up customer
- **Destination Address:** Where customer wants to go
- Both must be in service area (Grafton, Cobourg, Port Hope, Brighton)
- Address autocomplete helps with accuracy

#### Step 4: Review Price
- System calculates distance using Google Maps
- Price = Distance (km) × Price per km
- Estimated duration shown

#### Step 5: Choose Payment
- **Pay Now:** Immediate payment via credit/debit card
- **Pay on Pickup:** Pay when driver arrives (Tap to Pay)

#### Step 6: Enter Information
- Name (required)
- Email (required)
- Phone (required)

#### Confirmation
- Booking reference displayed
- Confirmation email sent
- SMS confirmation sent (if Twilio configured)
- Driver receives SMS notification

---

## Managing Bookings

### Viewing Bookings

Go to **Bookings → All Bookings** to see all bookings.

**Filtering:**
- **By Date:** Select specific date
- **By Status:** Pending, Confirmed, Completed, Cancelled
- Click "Filter" to apply

**Information Displayed:**
- Booking Reference
- Customer name and phone
- Pickup and destination
- Date and time
- Distance and price
- Status and payment status

### Booking Actions

#### Complete Booking
1. Find booking in list
2. Click "Complete" button
3. Status changes to "Completed"

#### Cancel Booking
1. Find booking in list
2. Click "Cancel" button
3. Confirm cancellation
4. Status changes to "Cancelled"
5. Handle refunds manually in Stripe if needed

### Dashboard Overview

**Bookings → Dashboard** shows:
- Today's bookings count
- Pending bookings
- Total revenue
- This month's bookings
- Upcoming bookings list

---

## Availability Management

### Blocking Time

Go to **Bookings → Availability** to block unavailable times.

**How to Block:**
1. Select block type:
   - **Unavailable:** General unavailability
   - **Maintenance:** Vehicle maintenance
   - **Personal:** Personal time off
2. Enter start date and time
3. Enter end date and time
4. Add reason (optional)
5. Click "Block Time"

**What Happens:**
- Blocked slots don't appear in booking form
- Customers cannot book during blocked periods
- Existing bookings unaffected

### Removing Blocks

Delete availability blocks to make time available again.

### Best Practices

- Block time in advance for days off
- Use maintenance blocks for vehicle service
- Review blocked times regularly
- Block entire days if needed (8:00 AM to 8:00 PM)

---

## Troubleshooting

### Booking Form Not Appearing

- Verify shortcode: `[hp_booking_form]`
- Check Google Maps API key in Settings
- Check browser console for errors
- Verify Google Maps API enabled

### Address Autocomplete Not Working

- Verify Google Maps API key correct
- Check Places API enabled
- Check browser console for errors
- Verify API key restrictions allow your domain

### Price Not Calculating

- Verify both addresses entered
- Check Distance Matrix API enabled
- Verify addresses in service area
- Check Google Cloud Console for quota issues

### Bookings Not Creating

- Check all required fields filled
- Verify addresses in service area
- Check time slot still available
- Review WordPress error logs
- Check database connection

### SMS Not Sending

- Verify Twilio credentials correct
- Check Twilio account balance
- Verify phone number format
- Check Twilio logs in dashboard

### Payments Not Processing

- Verify Stripe API keys correct
- Check Stripe webhook configured
- Verify webhook signing secret matches
- Check Stripe dashboard for attempts
- Review WordPress error logs

### Time Slots Not Showing

- Check working hours set correctly
- Verify minimum notice period
- Check for availability blocks
- Verify existing bookings aren't blocking all slots

### Service Area Validation Failing

- Verify service area coordinates in database
- Check addresses actually in service cities
- Review Google Geocoding API responses
- Update service area bounds if needed

### Getting Help

- Check WordPress error logs: `wp-content/debug.log`
- Enable WordPress debug mode in `wp-config.php`
- Check browser console for JavaScript errors
- Review API dashboards (Google, Stripe, Twilio) for errors
- Visit **Bookings → Help** for more troubleshooting tips

---

## Support

For additional help:
- Check **Bookings → Help** in WordPress admin
- Review this user guide
- Check API provider documentation (Google, Stripe, Twilio)

---

## Quick Reference

### Shortcode
```
[hp_booking_form]
```

### Service Area
- Grafton, Ontario
- Cobourg, Ontario
- Port Hope, Ontario
- Brighton, Ontario

### Default Settings
- Price: $1.75/km
- Buffer: 30 minutes
- Minimum Notice: 2 hours
- Time Slots: 15-minute intervals
- Working Hours: 8:00 AM - 8:00 PM

### Webhook URL
```
https://yoursite.com/wp-json/hp-booking/v1/stripe-webhook
```
