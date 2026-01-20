# Features Documentation

## Core Features

### 1. Pet Profile Management

A Gutenberg block that allows users to create and display pet profiles with:
- Pet name, age, breed, weight
- Pet image upload
- Owner email association
- Responsive design with Tailwind CSS

**Block Name**: `brighttails/brighttailsmegaplugin`

**Usage**: Add the block in Gutenberg editor and configure attributes.

---

### 2. Booking Package Tracker

A comprehensive credit and booking management system.

#### Credit System
- **Ledger-Based Tracking**: All credit changes are recorded in a ledger table
- **Automatic Credit Addition**: Credits added when Stripe payments are received
- **Manual Adjustment**: Admin interface to manually adjust credits
- **Real-Time Balance**: Balance calculated as sum of all ledger entries

#### Payment Integration
- **Stripe Webhook**: Automatically processes payments and adds credits
- **Payment Lookup**: Queries Stripe API to find customer's last payment
- **Package Detection**: Automatically determines package from:
  - Stripe metadata
  - Payment description
  - Payment amount ($280, $150, $45)
- **Guest Checkout Support**: Handles both registered and guest customers

#### Booking Tracking
- **Cal.com Integration**: Fetches bookings from Cal.com API
- **Date Filtering**: Shows only bookings after purchase date
- **Separate Email Support**: Different emails for Stripe and Cal.com
- **Booking History**: Displays booking status, dates, and details

#### Display Features
- **Remaining Credits**: Shows current credit balance
- **Last Payment Info**: Displays payment amount, date, and status
- **Package Details**: Shows purchased package and initial credits
- **Completed Bookings**: Count of bookings since purchase
- **Invoice List**: All Stripe invoices for the customer
- **Cal.com Bookings**: List of bookings from Cal.com

**Shortcode**: `[brighttails-booking-tracker email="customer@example.com" cal_email="cal@example.com"]`

---

### 3. Admin Credit Manager

A WordPress admin interface for manually managing customer credits.

**Location**: WordPress Admin → Bright Tails → Credit Manager

#### Features
- **Credit Adjustment**: Add or remove credits manually
- **User Balances**: View all user credit balances
- **Transaction History**: See all credit transactions
- **Audit Trail**: Complete history of all credit changes

#### Usage
1. Enter customer email
2. Enter credit adjustment (positive to add, negative to remove)
3. Optional reason for adjustment
4. Submit to update credits

---

### 4. Stripe Integration

#### Webhook Handler
- Listens for `checkout.session.completed` events
- Verifies webhook signature
- Extracts package information
- Adds credits to ledger
- Idempotency check prevents duplicate credits

#### Payment Lookup
- Multi-strategy search for payments
- Supports guest checkouts
- Fetches customer invoices
- Extracts package from payment

#### Invoice Display
- Shows all customer invoices
- Displays invoice status, amount, date
- Links to hosted invoice URL
- Sorted by creation date

---

### 5. Cal.com Integration

#### Booking Sync
- Fetches bookings via Cal.com API v2
- Filters by attendee email
- Supports separate Cal.com email
- Filters bookings by purchase date

#### Booking Display
- Shows booking status
- Displays booking dates
- Shows booking titles
- Links to booking details

---

## Package Types

The plugin supports three package types:

| Package | Payment Amount | Credits | Description |
|---------|---------------|---------|-------------|
| 8-Pack | $280.00 | 8 | 8 training sessions |
| 4-Pack | $150.00 | 4 | 4 training sessions |
| Single | $45.00 | 1 | 1 training session |

Packages are automatically detected from:
1. Stripe checkout session metadata (`package` field)
2. Payment description text
3. Payment amount

---

## Credit Sources

Credits can come from multiple sources:

| Source | Description | Delta |
|--------|-------------|-------|
| `stripe` | Stripe payment webhook | Positive |
| `manual` | Admin manual adjustment | Positive/Negative |
| `booking` | Booking deduction | Negative |
| `refund` | Payment refund | Negative |

All sources are tracked in the ledger for complete audit trail.

---

## User Experience Flow

### Customer Journey

1. **Purchase**: Customer purchases package via Stripe Checkout
2. **Webhook**: Stripe sends webhook, credits added automatically
3. **Display**: Customer sees credits on booking tracker component
4. **Booking**: Customer books session via Cal.com
5. **Deduction**: Credits deducted when booking completed
6. **Tracking**: Customer can see remaining credits and booking history

### Admin Journey

1. **Settings**: Configure Stripe and Cal.com API keys
2. **Monitoring**: View user balances and transactions
3. **Adjustment**: Manually adjust credits if needed
4. **Troubleshooting**: Use debug logs to diagnose issues

---

## Technical Features

### Error Handling
- Graceful degradation when APIs unavailable
- Detailed error logging
- User-friendly error messages
- Fallback strategies for payment lookup

### Performance
- Indexed database queries
- Efficient credit calculation (single SUM query)
- Cached API responses (in memory)
- Optimized React rendering

### Security
- Input sanitization
- SQL prepared statements
- Nonce verification
- Capability checks
- Webhook signature verification

### Debugging
- Comprehensive logging to WordPress error log
- Debug information in API responses
- Console logging in frontend
- Transaction history in admin

---

## Future Enhancements

Potential features for future development:

- [ ] Email notifications for low credits
- [ ] Credit expiration dates
- [ ] Bulk credit adjustments
- [ ] Credit transfer between users
- [ ] Advanced reporting and analytics
- [ ] Export transaction history
- [ ] Credit purchase reminders
- [ ] Integration with more booking systems
