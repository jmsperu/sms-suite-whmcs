# SMS Suite - WhatsApp API Documentation

## Base URL

```
https://xcobean.com/modules/addons/sms_suite/webhook.php?route=
```

All endpoints are accessed by appending the endpoint path to the `route` query parameter.

---

## Authentication

Every request must include API credentials via HTTP headers:

| Header | Description |
|--------|-------------|
| `X-API-Key` | Your API key (starts with `sms_`) |
| `X-API-Secret` | Your API secret |

**Alternative:** Basic Authentication with `base64(key_id:secret)` in the `Authorization` header.

API keys are generated from the SMS Suite client area under **API Keys**. Ensure the key has the `send_whatsapp` scope enabled.

---

## Endpoints

### 1. Send WhatsApp Text Message

Send a text message via WhatsApp.

**POST** `webhook.php?route=whatsapp/send`

#### Request

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=whatsapp/send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "message": "Hello from the API!"
  }'
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Recipient phone number in international format (no + prefix) |
| `message` | string | Yes | Message text (max 4096 characters) |
| `sender_id` | string | No | Sender ID override |
| `gateway_id` | integer | No | Specific gateway ID to use |

#### Response (Success - HTTP 201)

```json
{
  "success": true,
  "data": {
    "message_id": 28,
    "status": "sent"
  }
}
```

#### Response (Error - HTTP 400)

```json
{
  "success": false,
  "error": {
    "code": 400,
    "message": "Missing required parameters: to, message"
  }
}
```

---

### 2. Send WhatsApp Template Message

Send a pre-approved WhatsApp template message (required for first outbound to a number outside the 24-hour window).

**POST** `webhook.php?route=whatsapp/template`

#### Request

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=whatsapp/template" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "template_name": "hello_world",
    "language": "en_US",
    "template_params": ["John"]
  }'
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Recipient phone number |
| `template_name` | string | Yes | Meta-approved template name |
| `language` | string | No | Template language code (default: `en`) |
| `template_params` | array | No | Template variable values |
| `gateway_id` | integer | No | Specific gateway ID |

#### Response (Success - HTTP 201)

```json
{
  "success": true,
  "data": {
    "message_id": 30,
    "provider_message_id": "wamid.HBgM..."
  }
}
```

---

### 3. Send SMS (also supports WhatsApp via channel parameter)

Send a message via any channel including WhatsApp.

**POST** `webhook.php?route=send`

#### Request

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "message": "Hello via unified send!",
    "channel": "whatsapp"
  }'
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Recipient phone number |
| `message` | string | Yes | Message text |
| `channel` | string | No | `sms` (default) or `whatsapp` |
| `sender_id` | string | No | Sender ID |
| `gateway_id` | integer | No | Specific gateway ID |

---

### 4. Send Bulk Messages

Send the same message to multiple recipients.

**POST** `webhook.php?route=send/bulk`

#### Request

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=send/bulk" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "recipients": ["254702324532", "254712345678", "254723456789"],
    "message": "Bulk notification message",
    "channel": "whatsapp"
  }'
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `recipients` | array | Yes | Array of phone numbers (max 1000) |
| `message` | string | Yes | Message text |
| `channel` | string | No | `sms` or `whatsapp` |

#### Response

```json
{
  "success": true,
  "data": {
    "total": 3,
    "sent": 3,
    "failed": 0,
    "results": [
      {"to": "254702324532", "success": true, "message_id": 31, "error": null},
      {"to": "254712345678", "success": true, "message_id": 32, "error": null},
      {"to": "254723456789", "success": true, "message_id": 33, "error": null}
    ]
  }
}
```

---

### 5. Get Message Status

Check delivery status of a sent message.

**GET** `webhook.php?route=status&message_id=28`

#### Request

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=status&message_id=28" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

#### Response

```json
{
  "success": true,
  "data": {
    "message_id": 28,
    "to": "254702324532",
    "status": "delivered",
    "segments": 1,
    "cost": 0,
    "created_at": "2026-02-11 17:30:00",
    "delivered_at": "2026-02-11 17:30:05",
    "error": null
  }
}
```

---

### 6. Get Balance

Check account balance.

**GET** `webhook.php?route=balance`

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=balance" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

---

### 7. Get Message History

Retrieve sent messages.

**GET** `webhook.php?route=messages&limit=50&offset=0&status=delivered`

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=messages&limit=10" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

---

### 8. List Available Gateways

List gateways available to your account. Use this to discover which `gateway_id` to pass when sending.

**GET** `webhook.php?route=gateways`

#### Request

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=gateways" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

#### Parameters

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `channel` | string | No | Filter by channel: `sms` or `whatsapp` |

#### Response

```json
{
  "success": true,
  "data": {
    "gateways": [
      {
        "id": 1,
        "name": "XCBN",
        "type": "airtouch",
        "channel": "sms",
        "owned": false
      },
      {
        "id": 2,
        "name": "Xcobean WhatsApp",
        "type": "meta_whatsapp",
        "channel": "whatsapp",
        "owned": true
      }
    ]
  }
}
```

**Fields:**
- `id` — Use this as `gateway_id` when sending messages
- `owned` — `true` if this is your own gateway (no balance deduction), `false` if shared/admin gateway
- `channel` — Which channel this gateway supports (`sms`, `whatsapp`, or `both`)

---

## Error Codes

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created (message sent) |
| 400 | Bad request (missing/invalid parameters) |
| 401 | Invalid API credentials |
| 403 | Insufficient permissions (scope not enabled) |
| 404 | Endpoint not found |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

---

## Rate Limits

Default: **100 requests per minute** per API key. Configurable per key.

---

## Notes

- Phone numbers should be in international format **without** the `+` prefix (e.g., `254702324532`)
- WhatsApp text messages require the recipient to have messaged your business number within the last 24 hours, OR use a template message first
- Messages sent via client-owned WhatsApp gateways do **not** deduct from SMS balance
- The `gateway_id` parameter is optional; if omitted, the system automatically selects the client's WhatsApp gateway
