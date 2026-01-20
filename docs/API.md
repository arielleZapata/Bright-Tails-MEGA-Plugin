# API Documentation

## REST API Endpoints

### GET /wp-json/brighttails/v1/me/last-payment

Retrieves the last payment information for a customer, along with credit balance, bookings, and invoices.

#### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `email` | string | Yes | Stripe customer email address |
| `cal_email` | string | No | Cal.com email (defaults to `email` if not provided) |

#### Response Format

**Success Response (200 OK)**
```json
{
  "found": true,
  "email": "customer@example.com",
  "cal_email": "cal@example.com",
  "customer_id": "cus_abc123",
  "customer_name": "John Doe",
  "is_guest": false,
  "amount": 280.00,
  "currency": "USD",
  "status": "succeeded",
  "created": "2026-01-15T14:12:07+00:00",
  "id": "pi_abc123",
  "source": "payment_intent",
  "invoices": [
    {
      "id": "in_abc123",
      "number": "INV-001",
      "amount_due": 0.00,
      "amount_paid": 280.00,
      "currency": "USD",
      "status": "paid",
      "created": "2026-01-15T14:12:07+00:00",
      "paid": true,
      "hosted_invoice_url": "https://invoice.stripe.com/..."
    }
  ],
  "completed_bookings_since_purchase": 3,
  "purchase_date": "2026-01-15T14:12:07+00:00",
  "purchased_package": {
    "package_id": "8_pack",
    "package_name": "8-Pack",
    "credits": 8
  },
  "remaining_credits": 5,
  "cal_bookings": [
    {
      "id": 123,
      "uid": "abc123",
      "title": "Dog Training Session",
      "status": "accepted",
      "start": "2026-01-16T10:00:00Z",
      "end": "2026-01-16T11:00:00Z"
    }
  ],
  "cal_bookings_count": 1,
  "cal_all_bookings_count": 5,
  "cal_api_error": null,
  "cal_api_response_code": 200,
  "debug": {
    "stripe_email": "customer@example.com",
    "cal_email": "cal@example.com",
    "customer_found": true,
    "customer_id": "cus_abc123",
    "invoices_count": 1,
    "payment_source": "payment_intent",
    "completed_bookings_count": 3,
    "purchased_package": "8_pack",
    "package_credits": 8,
    "remaining_credits": 5,
    "cal_api_queried": true,
    "cal_api_key_configured": true
  }
}
```

**No Payment Found (200 OK)**
```json
{
  "found": false,
  "email": "customer@example.com",
  "reason": "no_payment_found",
  "customer_id": null,
  "invoices": [],
  "debug": {
    "searched_email": "customer@example.com",
    "customer_found": false
  }
}
```

**Error Response (400/500)**
```json
{
  "error": "Missing email parameter",
  "reason": "email_required"
}
```

#### Error Codes

| Code | Reason | Description |
|------|--------|-------------|
| 400 | `email_required` | Email parameter missing |
| 400 | `invalid_email` | Invalid email format |
| 500 | `stripe_key_missing` | Stripe secret key not configured |
| 500 | `stripe_sdk_missing` | Stripe PHP SDK not installed |
| 500 | `stripe_api_error` | Stripe API returned an error |
| 500 | `php_error` | PHP fatal error occurred |

---

### POST /wp-json/brighttails/v1/webhooks/stripe

Handles Stripe webhook events, specifically `checkout.session.completed`.

#### Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Stripe-Signature` | Yes | Stripe webhook signature for verification |

#### Request Body

Raw JSON from Stripe webhook event.

#### Response Format

**Success (200 OK)**
```json
{
  "received": true,
  "email": "customer@example.com",
  "credits_added": 8,
  "session_id": "cs_abc123"
}
```

**Duplicate (200 OK)**
```json
{
  "received": true,
  "duplicate": true
}
```

**Error (400/500)**
```json
{
  "error": "Invalid signature",
  "detail": "No signatures found matching the expected signature"
}
```

---

## Admin API

### Credit Adjustment

Accessed via WordPress admin interface at `Bright Tails → Credit Manager`.

**Form Fields:**
- `email`: Customer email (required)
- `delta`: Credit adjustment, positive or negative (required)
- `reason`: Optional reason for adjustment

**Process:**
1. Validates email and delta
2. Inserts record into `bt_credits_ledger` table
3. Returns new balance
4. Logs transaction

---

## Stripe API Integration

### Payment Lookup Strategies

The plugin uses multiple strategies to find payments, in order:

1. **Customer Search**: `Customer::all(['email' => $email])`
   - For registered customers
   - Fastest and most reliable

2. **PaymentIntent Search**: `PaymentIntent::all(['limit' => 100])`
   - Filters by `receipt_email`
   - For guest checkouts

3. **Charge Search**: `Charge::all(['limit' => 100])`
   - Filters by `receipt_email` or `billing_details.email`
   - Fallback for older payment methods

4. **Checkout Session Search**: `Checkout\Session::all(['limit' => 100])`
   - Filters by customer email
   - Retrieves associated PaymentIntent

### Package Detection

Packages are detected in this order:

1. **Metadata**: `session.metadata.package` or `payment_intent.metadata.package`
2. **Description**: Text matching in payment description
3. **Amount**: Payment amount matching:
   - $280.00 = 8-Pack (8 credits)
   - $150.00 = 4-Pack (4 credits)
   - $45.00 = Single (1 credit)

---

## Cal.com API Integration

### Endpoint

`GET https://api.cal.com/v2/bookings?attendeeEmail={email}`

### Authentication

Bearer token in `Authorization` header:
```
Authorization: Bearer {cal_api_key}
```

### Response Format

```json
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "uid": "abc123",
      "title": "Dog Training Session",
      "status": "accepted",
      "start": "2026-01-16T10:00:00Z",
      "end": "2026-01-16T11:00:00Z",
      "attendees": [
        {
          "email": "customer@example.com",
          "name": "John Doe"
        }
      ]
    }
  ],
  "pagination": {
    "totalItems": 10,
    "returnedItems": 10,
    "itemsPerPage": 10,
    "currentPage": 1
  }
}
```

### Filtering

The plugin filters bookings to only show those after the purchase date:
- Compares `booking.start` timestamp with purchase timestamp
- Falls back to `booking.createdAt` if `start` not available
- Only includes bookings where `booking_date >= purchase_date`

---

## Database Queries

### Get User Credits

```sql
SELECT COALESCE(SUM(delta), 0) 
FROM wp_bt_credits_ledger 
WHERE email = %s
```

### Get Completed Bookings Since Purchase

```sql
SELECT COUNT(*) 
FROM wp_bt_credits_ledger 
WHERE email = %s 
  AND delta < 0 
  AND source != 'stripe'
  AND created_at >= %s
```

### Get Recent Transactions

```sql
SELECT email, delta, source, external_id, created_at 
FROM wp_bt_credits_ledger 
ORDER BY created_at DESC 
LIMIT 50
```

---

## Error Handling

All endpoints return proper HTTP status codes:
- `200`: Success (even if no data found)
- `400`: Client error (bad request, invalid parameters)
- `500`: Server error (API errors, missing configuration)

Error responses include:
- `error`: Human-readable error message
- `reason`: Machine-readable error code
- `detail`: Additional error details (if available)
