# Setup Guide

## Initial Setup

### 1. Install Plugin

1. Upload plugin to `/wp-content/plugins/bright-tails-MEGA-plugin/`
2. Activate plugin in WordPress admin
3. Database tables will be created automatically

### 2. Install Dependencies

```bash
# Navigate to plugin directory
cd wp-content/plugins/bright-tails-MEGA-plugin

# Install PHP dependencies (Stripe SDK)
composer install

# Install Node dependencies
npm install

# Build frontend assets
npm run build
```

### 3. Configure Stripe

#### Get Stripe API Keys

1. Go to [Stripe Dashboard](https://dashboard.stripe.com)
2. Navigate to **Developers → API keys**
3. Copy your **Secret key** (starts with `sk_live_` or `sk_test_`)

#### Set Up Webhook

1. Go to **Developers → Webhooks**
2. Click **Add endpoint**
3. Set endpoint URL to: `https://yoursite.com/wp-json/brighttails/v1/webhooks/stripe`
4. Select event: `checkout.session.completed`
5. Copy the **Signing secret** (starts with `whsec_`)

#### Configure Plugin

1. Go to WordPress Admin → **Settings → Bright Tails**
2. Enter **Stripe Secret Key**
3. Enter **Stripe Webhook Secret**
4. Save settings

**Alternative**: Define `BT_STRIPE_SECRET_KEY` constant in `wp-config.php`:
```php
define('BT_STRIPE_SECRET_KEY', 'sk_live_...');
```

#### Configure Checkout Sessions

Ensure your Stripe Checkout Sessions include package metadata:

```javascript
// In your Stripe Checkout creation
const session = await stripe.checkout.sessions.create({
  // ... other options
  metadata: {
    package: '8_pack' // or '4_pack' or 'single'
  }
});
```

Or ensure payment amounts match:
- $280.00 = 8-Pack
- $150.00 = 4-Pack
- $45.00 = Single

---

### 4. Configure Cal.com (Optional)

#### Get Cal.com API Key

1. Go to [Cal.com](https://cal.com)
2. Navigate to **Settings → API Keys**
3. Create a new API key
4. Copy the key (starts with `cal_live_` or `cal_test_`)

#### Configure Plugin

1. Go to WordPress Admin → **Settings → Bright Tails**
2. Enter **Cal.com API Key**
3. Enter **Cal.com Booking URL** (optional)
4. Save settings

---

## Usage

### Display Booking Tracker

#### Using Shortcode

Add to any page or post:
```
[brighttails-booking-tracker email="customer@example.com"]
```

With separate Cal.com email:
```
[brighttails-booking-tracker email="stripe@example.com" cal_email="cal@example.com"]
```

#### Using Gutenberg Block

1. Add block in Gutenberg editor
2. Configure email in block settings
3. Publish page

---

### Manual Credit Management

1. Go to WordPress Admin → **Bright Tails → Credit Manager**
2. Enter customer email
3. Enter credit adjustment:
   - Positive number to add credits
   - Negative number to remove credits
4. Optional: Add reason for adjustment
5. Click **Adjust Credits**

---

## Testing

### Test Stripe Webhook

1. Make a test payment in Stripe
2. Check WordPress debug log for webhook processing
3. Verify credits added in Credit Manager
4. Check booking tracker displays credits

### Test Payment Lookup

1. Visit page with booking tracker
2. Check browser console for API response
3. Verify payment information displays
4. Check invoices are shown

### Test Cal.com Integration

1. Ensure Cal.com API key is configured
2. Create test booking in Cal.com
3. Check booking tracker for Cal.com bookings
4. Verify bookings filtered by purchase date

---

## Troubleshooting

### Credits Not Adding

**Check:**
1. Stripe webhook secret is correct
2. Webhook endpoint is accessible
3. Webhook events are being received (check Stripe dashboard)
4. WordPress debug log for errors

**Debug:**
- Check `wp-content/debug.log` for webhook processing
- Verify webhook signature in Stripe dashboard
- Test webhook manually using Stripe CLI

### Payment Not Found

**Check:**
1. Stripe secret key is correct
2. Customer email matches Stripe customer email
3. Payment was successful
4. Check WordPress debug log for search strategies

**Debug:**
- Check browser console for API response
- Review debug object in API response
- Check WordPress debug log for search attempts

### Cal.com Bookings Not Showing

**Check:**
1. Cal.com API key is correct
2. Email matches Cal.com attendee email
3. Bookings exist in Cal.com
4. Bookings are after purchase date

**Debug:**
- Check `cal_api_error` in API response
- Review `cal_all_bookings_count` vs `cal_bookings_count`
- Check WordPress debug log for Cal.com API calls
- Verify booking dates in Cal.com dashboard

### Frontend Not Loading

**Check:**
1. Frontend assets are built (`npm run build`)
2. Assets are enqueued correctly
3. No JavaScript errors in console
4. React is loading

**Debug:**
- Check browser console for errors
- Verify `frontend.js` file exists in `build/` directory
- Check network tab for 404 errors
- Verify React dependencies are loaded

---

## Maintenance

### Regular Tasks

1. **Monitor Credits**: Check Credit Manager for unusual activity
2. **Review Transactions**: Review transaction history regularly
3. **Check Logs**: Review WordPress debug log for errors
4. **Update Dependencies**: Keep Stripe SDK and Node packages updated

### Backup

Important data to backup:
- `wp_bt_credits_ledger` table
- `wp_bt_bookings` table
- Plugin settings (stored in WordPress options)

### Updates

Before updating:
1. Backup database
2. Test in staging environment
3. Review changelog
4. Update dependencies: `composer update` and `npm update`

---

## Support

For issues or questions:
1. Check WordPress debug log
2. Review browser console
3. Check API responses
4. Review documentation

Common issues are documented in this guide. For additional help, review the code comments and error messages.
