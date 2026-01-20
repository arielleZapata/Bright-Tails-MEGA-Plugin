# Bright Tails MEGA Plugin

A comprehensive WordPress plugin for managing pet services, booking packages, and payment tracking. Built with React, PHP, and integrated with Stripe and Cal.com APIs.

## 🎯 Overview

This plugin provides a complete solution for a pet services business, featuring:
- **Pet Profile Management**: Create and display pet profiles with images
- **Booking Package Tracker**: Track customer credits and remaining lessons
- **Stripe Integration**: Payment processing, webhook handling, and invoice management
- **Cal.com Integration**: Booking synchronization and tracking
- **Credit Management**: Manual credit adjustment system for administrators

## 🏗️ Architecture

### Technology Stack
- **Backend**: PHP 7.4+, WordPress REST API
- **Frontend**: React 18+ with WordPress Scripts
- **Styling**: Tailwind CSS
- **Payment Processing**: Stripe PHP SDK
- **Booking System**: Cal.com API v2
- **Database**: MySQL (WordPress database)

### Plugin Structure
```
bright-tails-MEGA-plugin/
├── src/
│   ├── admin/              # Admin interface
│   │   ├── settings.php    # Plugin settings page
│   │   └── credit-manager.php  # Manual credit management
│   ├── features/
│   │   ├── pet-profile/    # Pet profile feature
│   │   └── booking-package-tracker/  # Booking tracker feature
│   └── includes/
│       ├── stripe-webhook.php      # Stripe webhook handler
│       ├── stripe-last-payment.php # Stripe payment lookup
│       └── routes-webhooks.php    # REST API routes
├── build/                  # Compiled assets
├── vendor/                 # Composer dependencies (Stripe SDK)
└── index.php              # Main plugin file
```

## 🚀 Features

### 1. Booking Package Tracker
- **Credit System**: Tracks customer credits (lessons) with a ledger system
- **Stripe Integration**: Automatically adds credits when payments are received
- **Payment Lookup**: Queries Stripe API to find customer's last payment
- **Package Detection**: Automatically detects package type (8-pack, 4-pack, single) from payment amount
- **Cal.com Sync**: Displays bookings from Cal.com API filtered by purchase date
- **Real-time Display**: React-based UI showing remaining credits and booking history

### 2. Stripe Payment Integration
- **Webhook Handler**: Processes `checkout.session.completed` events
- **Payment Fallback**: Direct Stripe API queries when webhooks haven't fired
- **Guest Checkout Support**: Handles both registered customers and guest checkouts
- **Invoice Display**: Shows all customer invoices from Stripe
- **Package Mapping**: Maps payment amounts to credit packages:
  - $280 = 8 credits (8-Pack)
  - $150 = 4 credits (4-Pack)
  - $45 = 1 credit (Single)

### 3. Credit Management System
- **Ledger-Based**: All credit changes tracked in `bt_credits_ledger` table
- **Multiple Sources**: Credits can come from Stripe payments, manual adjustments, or booking deductions
- **Admin Interface**: Manual credit adjustment tool for administrators
- **Transaction History**: View all credit transactions with timestamps

### 4. Cal.com Integration
- **Booking Sync**: Fetches bookings from Cal.com API
- **Email Matching**: Supports separate emails for Stripe and Cal.com
- **Date Filtering**: Shows only bookings after purchase date
- **Status Tracking**: Displays booking status and details

## 📦 Installation

### Prerequisites
- WordPress 5.0+
- PHP 7.4+
- Composer (for Stripe SDK)
- Node.js & npm (for frontend builds)

### Setup Steps

1. **Install Dependencies**
   ```bash
   # Install PHP dependencies (Stripe SDK)
   composer install
   
   # Install Node dependencies
   npm install
   ```

2. **Configure Settings**
   - Go to WordPress Admin → Settings → Bright Tails
   - Enter Stripe Secret Key (or define `BT_STRIPE_SECRET_KEY` in wp-config.php)
   - Enter Stripe Webhook Secret
   - Enter Cal.com API Key (optional)
   - Enter Cal.com Booking URL (optional)

3. **Build Frontend Assets**
   ```bash
   npm run build
   ```

4. **Activate Plugin**
   - The plugin will automatically create required database tables on activation

## 🔧 Configuration

### Stripe Setup
1. Get your Stripe Secret Key from Stripe Dashboard → Developers → API keys
2. Create a webhook endpoint pointing to: `/wp-json/brighttails/v1/webhooks/stripe`
3. Add webhook secret to plugin settings
4. Ensure checkout sessions include `metadata.package` field (or payment amounts match: $280, $150, $45)

### Cal.com Setup
1. Get API key from Cal.com Settings → API Keys
2. Add to plugin settings
3. Configure booking URL if needed

## 📊 Database Schema

### `wp_bt_credits_ledger`
Tracks all credit additions and subtractions:
- `id`: Primary key
- `email`: Customer email
- `delta`: Credit change (+ for additions, - for deductions)
- `source`: Source of change ('stripe', 'manual', 'booking', etc.)
- `external_id`: External reference (Stripe session ID, etc.)
- `created_at`: Timestamp

### `wp_bt_bookings`
Stores Cal.com booking snapshots:
- `id`: Primary key
- `email`: Customer email
- `cal_booking_id`: Cal.com booking ID
- `status`: Booking status
- `start_time`: Booking start time
- `created_at`: Timestamp

## 🔌 REST API Endpoints

### `GET /wp-json/brighttails/v1/me/last-payment`
Returns customer's last payment and booking information.

**Query Parameters:**
- `email` (required): Stripe customer email
- `cal_email` (optional): Cal.com email (defaults to Stripe email)

**Response:**
```json
{
  "found": true,
  "email": "customer@example.com",
  "amount": 280.00,
  "currency": "USD",
  "status": "succeeded",
  "created": "2026-01-15T14:12:07+00:00",
  "purchased_package": {
    "package_id": "8_pack",
    "package_name": "8-Pack",
    "credits": 8
  },
  "remaining_credits": 5,
  "completed_bookings_since_purchase": 3,
  "invoices": [...],
  "cal_bookings": [...]
}
```

### `POST /wp-json/brighttails/v1/webhooks/stripe`
Handles Stripe webhook events (checkout.session.completed).

## 🎨 Frontend Components

### Booking Package Tracker
React component that displays:
- Remaining credits
- Last payment information
- Purchased package details
- Completed bookings count
- Cal.com bookings
- Stripe invoices

**Usage:**
```php
[brighttails-booking-tracker email="customer@example.com" cal_email="cal@example.com"]
```

## 🛠️ Development

### Building Assets
```bash
# Development mode with hot reload
npm start

# Production build
npm run build
```

### File Structure
- `src/features/*/index.js`: Editor components
- `src/features/*/frontend.js`: Frontend React components
- `src/features/*/render.php`: Server-side rendering
- `src/includes/*.php`: Backend logic and API handlers

## 📝 Code Highlights

### Stripe Payment Lookup
The plugin implements a multi-strategy approach to find payments:
1. Search by customer email (registered customers)
2. Search PaymentIntents by receipt_email (guest checkouts)
3. Search Charges by receipt_email (guest checkouts)
4. Search Checkout Sessions (guest checkouts)

### Credit Calculation
Credits are calculated by summing all `delta` values in the ledger:
```php
SELECT SUM(delta) FROM wp_bt_credits_ledger WHERE email = 'customer@example.com'
```

### Package Detection
Packages are detected from:
1. Stripe metadata (`package` field)
2. Payment description text
3. Payment amount ($280, $150, or $45)

## 🔒 Security

- All user inputs are sanitized
- Nonce verification for admin actions
- Capability checks for admin functions
- SQL prepared statements
- Email validation
- REST API permission callbacks

## 📄 License

This plugin is proprietary software for Bright Tails.

## 👤 Author

Bright Tails Development Team

## 🙏 Acknowledgments

- Stripe for payment processing
- Cal.com for booking management
- WordPress for the platform
- React team for the frontend framework
