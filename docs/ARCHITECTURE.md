# Plugin Architecture Documentation

## System Overview

The Bright Tails MEGA Plugin is a WordPress plugin that integrates Stripe payments and Cal.com bookings to manage a pet services business. It uses a credit-based system where customers purchase packages that grant them a certain number of lessons/credits.

## Data Flow

### Payment Processing Flow

```
1. Customer makes payment via Stripe Checkout
   ↓
2. Stripe sends webhook to /wp-json/brighttails/v1/webhooks/stripe
   ↓
3. Plugin verifies webhook signature
   ↓
4. Extracts package info from metadata or payment amount
   ↓
5. Adds credits to bt_credits_ledger table
   ↓
6. Customer sees updated credits in frontend
```

### Credit Lookup Flow

```
1. Frontend requests: GET /wp-json/brighttails/v1/me/last-payment?email=...
   ↓
2. Backend queries Stripe API for customer/payment
   ↓
3. Calculates remaining credits from ledger
   ↓
4. Queries Cal.com API for bookings (if configured)
   ↓
5. Filters bookings by purchase date
   ↓
6. Returns JSON response with all data
   ↓
7. React component displays information
```

## Database Design

### Credits Ledger Table
The ledger uses a double-entry accounting approach where all changes are recorded as deltas:

- **Positive delta**: Credits added (from Stripe payment, manual adjustment)
- **Negative delta**: Credits used (from booking, refund)

This allows for:
- Complete audit trail
- Easy balance calculation: `SUM(delta)`
- Multiple sources of credits
- Transaction history

### Example Ledger Entries
```
Email: customer@example.com
| Delta | Source    | External ID          | Created At          |
|-------|-----------|----------------------|---------------------|
| +8    | stripe    | cs_abc123            | 2026-01-15 14:12:07|
| -1    | booking   | cal_xyz789           | 2026-01-16 10:00:00|
| -1    | booking   | cal_def456           | 2026-01-17 11:00:00|
| +2    | manual    | admin_1234567890_1   | 2026-01-18 09:00:00|
```
Balance: 8 - 1 - 1 + 2 = 8 credits

## API Integration Patterns

### Stripe Integration

**Webhook Handler** (`stripe-webhook.php`):
- Listens for `checkout.session.completed` events
- Extracts package from metadata or payment amount
- Adds credits to ledger with idempotency check

**Payment Lookup** (`stripe-last-payment.php`):
- Multi-strategy search for payments
- Handles both registered customers and guest checkouts
- Extracts package information
- Fetches invoices

### Cal.com Integration

**Booking Query**:
- Uses Cal.com API v2 `/bookings` endpoint
- Filters by `attendeeEmail` parameter
- Supports separate email for Cal.com vs Stripe
- Filters results by purchase date client-side

## Frontend Architecture

### React Component Structure

```
BookingTrackerComponent
├── Props: email, calEmail, credits, hasPackage
├── State: lastPayment, paymentLoading, paymentError
├── useEffect: Fetches payment data on mount
└── Render:
    ├── Credit Display
    ├── Last Payment Info
    ├── Package Details
    ├── Completed Bookings
    └── Cal.com Bookings
```

### Data Hydration

The component receives initial data from PHP via a hidden `<pre>` element:
```html
<div class="bt-booking-tracker-update-me">
  <pre>{"email":"...","credits":8,"hasPackage":true}</pre>
</div>
```

React then:
1. Finds all `.bt-booking-tracker-update-me` elements
2. Parses JSON from `<pre>` element
3. Renders component with initial data
4. Fetches additional data from REST API

## Security Considerations

### Input Validation
- All emails sanitized with `sanitize_email()`
- All text inputs sanitized with `sanitize_text_field()`
- SQL queries use prepared statements
- Nonce verification for admin actions

### API Security
- Stripe webhook signature verification
- Capability checks for admin functions
- REST API permission callbacks
- Error handling prevents information leakage

## Error Handling

### Graceful Degradation
- If Stripe SDK not loaded: Returns error, doesn't crash
- If Cal.com API fails: Shows error message, continues
- If no payment found: Returns `found: false`, not error
- If table missing: Returns 0 credits, logs warning

### Logging
All operations log to WordPress error log:
- Customer searches
- Payment lookups
- API calls
- Filtering operations
- Errors and exceptions

## Performance Considerations

### Database Queries
- Indexed on `email`, `source`, `external_id`
- Uses `SUM()` for balance calculation (single query)
- Limits results where appropriate

### API Calls
- Stripe API calls are server-side only
- Cal.com API calls cached in response (not persisted)
- Timeout set to 10 seconds for external APIs

### Frontend
- React components only render when needed
- API calls made on component mount
- Loading states prevent multiple requests

## Extension Points

### Adding New Credit Sources
1. Add entry to ledger with unique `source` value
2. Update credit calculation (already handles all sources)
3. Add to admin transaction view (if needed)

### Adding New Package Types
1. Update package mapping in `stripe-webhook.php`
2. Update package mapping in `stripe-last-payment.php`
3. Update amount detection logic

### Customizing Frontend
- Modify `src/features/booking-package-tracker/frontend.js`
- Update styles in component JSX
- Add new API endpoints as needed

## Testing Considerations

### Unit Testing
- Test credit calculation logic
- Test package detection from amounts
- Test date filtering logic

### Integration Testing
- Test Stripe webhook processing
- Test Cal.com API integration
- Test REST API endpoints

### Manual Testing
- Test guest checkout flow
- Test registered customer flow
- Test manual credit adjustment
- Test invoice display
