# Shortcode Reference

## Booking Package Tracker Shortcode

### Basic Usage

```
[brighttails-booking-tracker email="customer@example.com"]
```

### Full Usage with All Parameters

```
[brighttails-booking-tracker email="customer@example.com" credits="5" cal_email="cal@example.com"]
```

## Parameters

| Parameter | Required | Type | Default | Description |
|-----------|----------|------|---------|-------------|
| `email` | Yes | string | - | Customer email address for Stripe payment lookup |
| `credits` | No | number | Auto-calculated | Manual override for remaining credits/lessons |
| `cal_email` | No | string | Same as `email` | Cal.com email address (if different from Stripe email) |

## Examples

### Example 1: Basic Usage
```
[brighttails-booking-tracker email="john@example.com"]
```
- Uses email for both Stripe and Cal.com
- Credits calculated automatically from ledger

### Example 2: Manual Credits Override
```
[brighttails-booking-tracker email="john@example.com" credits="8"]
```
- Manually sets credits to 8
- Overrides ledger calculation
- Useful for testing or manual management

### Example 3: Separate Emails
```
[brighttails-booking-tracker email="stripe@example.com" cal_email="cal@example.com"]
```
- Different emails for Stripe and Cal.com
- Credits calculated from Stripe email
- Bookings fetched from Cal.com email

### Example 4: Full Configuration
```
[brighttails-booking-tracker email="customer@example.com" credits="5" cal_email="booking@example.com"]
```
- All parameters specified
- Manual credits override
- Separate Cal.com email

## What It Displays

The shortcode displays:

1. **Remaining Credits**: Current credit balance
2. **Status**: Active/Inactive based on credits
3. **Last Payment**: Payment amount, date, and status from Stripe
4. **Purchased Package**: Package type and initial credits
5. **Completed Bookings**: Count of bookings since purchase
6. **Stripe Invoices**: List of all customer invoices
7. **Cal.com Bookings**: Bookings from Cal.com API (if configured)

## Credit Calculation

### Automatic (Default)
If `credits` parameter is not provided, credits are calculated from the ledger:
```sql
SELECT SUM(delta) FROM wp_bt_credits_ledger WHERE email = 'customer@example.com'
```

### Manual Override
If `credits` parameter is provided, that value is used instead of ledger calculation.

**Use Cases for Manual Override:**
- Testing different credit values
- Temporary adjustments
- Displaying credits from external system
- Overriding ledger for specific customers

## Notes

- The shortcode automatically enqueues required CSS and JavaScript
- Works in Gutenberg blocks, Classic Editor, and page builders (Elementor, etc.)
- React component loads asynchronously
- Falls back to noscript HTML if JavaScript is disabled
- All emails are sanitized and validated

## Troubleshooting

### Credits Not Showing
- Check if `credits` parameter is a valid number
- Verify email is correct
- Check ledger table exists and has data

### Payment Not Found
- Verify Stripe secret key is configured
- Check customer email matches Stripe customer
- Review WordPress debug log for API errors

### Cal.com Bookings Not Showing
- Verify Cal.com API key is configured
- Check `cal_email` matches Cal.com attendee email
- Ensure bookings exist and are after purchase date
