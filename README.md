# Bright Tails MEGA Plugin

WordPress plugin for managing pet services, booking packages, and payments.

## What It Does

- **Pet Profiles**: Create and display pet profiles with images
- **Booking Package Tracker**: Shows how many lessons/credits a customer has left
- **Stripe Integration**: Connects to Stripe to track payments and add credits
- **Cal.com Integration**: Shows bookings from Cal.com calendar
- **Credit Management**: Admins can manually adjust customer credits

## Current Status

The tracker is **manually operated**. Credits are tracked but not automatically deducted when bookings are completed.

## Setup

1. Install dependencies:
   ```bash
   composer install
   npm install
   ```

2. Build assets:
   ```bash
   npm run build
   ```

3. Configure in WordPress Admin → Settings → Bright Tails:
   - Stripe Secret Key
   - Stripe Webhook Secret
   - Cal.com API Key (optional)
   - Cal.com Booking URL (optional)

4. Activate the plugin

## Stripe Webhook

Set up a webhook endpoint at:
```
/wp-json/brighttails/v1/webhooks/stripe
```

Listen for `checkout.session.completed` events.

## Package Types

- $280 = 8 credits (8-Pack)
- $150 = 4 credits (4-Pack)
- $45 = 1 credit (Single)

## Usage

Use the shortcode to display a customer's tracker:
```php
[brighttails-booking-tracker email="customer@example.com"]
```

If the customer uses a different email for Cal.com:
```php
[brighttails-booking-tracker email="customer@example.com" cal_email="cal@example.com"]
```

## Future Improvements

- **Automated Tracker**: Automatically deduct credits when Cal.com bookings are completed
- **Stripe + Cal.com Integration**: Connect Stripe payments directly with Cal.com bookings so credits are automatically added and deducted
- **Real-time Sync**: Auto-sync between Stripe payments and Cal.com bookings without manual intervention
