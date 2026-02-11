# Xcobean SMS & WhatsApp API - Integration Guide

## Base URL

```
https://xcobean.com/modules/addons/sms_suite/webhook.php?route={endpoint}
```

## Authentication

Include these headers on **every** request:

```
X-API-Key: YOUR_API_KEY
X-API-Secret: YOUR_API_SECRET
Content-Type: application/json
```

**Alternative:** HTTP Basic Auth with `Authorization: Basic base64(key:secret)`

---

## Quick Start

### Send SMS

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "message": "Hello via SMS!"
  }'
```

### Send WhatsApp

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=whatsapp/send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "message": "Hello via WhatsApp!"
  }'
```

---

## Endpoints

### 1. Send SMS

**POST** `?route=send`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Phone number in international format, no `+` (e.g. `254702324532`) |
| `message` | string | Yes | Message text (max 1600 chars for SMS) |
| `sender_id` | string | No | Sender ID / alphanumeric name |
| `gateway_id` | integer | No | Gateway to use (get from `/gateways`). Default: auto-select |

**Response (201):**
```json
{
  "success": true,
  "data": {
    "message_id": 38,
    "segments": 1,
    "encoding": "gsm7",
    "status": "sent"
  }
}
```

---

### 2. Send WhatsApp Message

**POST** `?route=whatsapp/send`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Phone number, no `+` prefix |
| `message` | string | Yes | Message text (max 4096 chars) |
| `gateway_id` | integer | No | WhatsApp gateway ID. Default: auto-select |

**Response (201):**
```json
{
  "success": true,
  "data": {
    "message_id": 36,
    "status": "sent"
  }
}
```

> **Note:** WhatsApp text messages only work if the recipient has messaged your business number in the last 24 hours. For first contact, use a template message (see below).

---

### 3. Send WhatsApp Template Message

For first outbound contact (outside 24-hour window), you must use a Meta-approved template.

**POST** `?route=whatsapp/template`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Phone number |
| `template_name` | string | Yes | Meta-approved template name (e.g. `hello_world`) |
| `language` | string | No | Language code (default: `en`) |
| `template_params` | array | No | Variable values, e.g. `["John", "Order #123"]` |
| `gateway_id` | integer | No | WhatsApp gateway ID |

**Response (201):**
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

### 4. Unified Send (SMS or WhatsApp)

Single endpoint that routes by `channel` parameter.

**POST** `?route=send`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `to` | string | Yes | Phone number |
| `message` | string | Yes | Message text |
| `channel` | string | No | `sms` (default) or `whatsapp` |
| `sender_id` | string | No | Sender ID |
| `gateway_id` | integer | No | Gateway ID |

```bash
# Send as WhatsApp via the unified endpoint
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=send" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "to": "254702324532",
    "message": "Hello!",
    "channel": "whatsapp"
  }'
```

---

### 5. Send Bulk Messages

Send the same message to multiple recipients (up to 1000).

**POST** `?route=send/bulk`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `recipients` | array | Yes | Array of phone numbers (max 1000) |
| `message` | string | Yes | Message text |
| `channel` | string | No | `sms` or `whatsapp` |

```bash
curl -X POST "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=send/bulk" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d '{
    "recipients": ["254702324532", "254712345678"],
    "message": "Bulk notification",
    "channel": "whatsapp"
  }'
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "total": 2,
    "sent": 2,
    "failed": 0,
    "results": [
      {"to": "254702324532", "success": true, "message_id": 31, "error": null},
      {"to": "254712345678", "success": true, "message_id": 32, "error": null}
    ]
  }
}
```

---

### 6. Check Message Status

**GET** `?route=status&message_id={id}`

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=status&message_id=36" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message_id": 36,
    "to": "254702324532",
    "status": "delivered",
    "segments": 1,
    "cost": 0,
    "created_at": "2026-02-11 20:55:00",
    "delivered_at": "2026-02-11 20:55:03",
    "error": null
  }
}
```

**Possible statuses:** `queued` > `sending` > `sent` > `delivered` | `failed`

---

### 7. List Available Gateways

Discover which gateways (SMS and WhatsApp) are available. Use the `id` as `gateway_id` when sending.

**GET** `?route=gateways`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `channel` | string | No | Filter: `sms` or `whatsapp` |

```bash
# All gateways
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=gateways" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"

# WhatsApp gateways only
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=gateways&channel=whatsapp" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "gateways": [
      {"id": 1, "name": "XCBN", "type": "airtouch", "channel": "sms", "owned": false},
      {"id": 2, "name": "Xcobean WhatsApp", "type": "meta_whatsapp", "channel": "whatsapp", "owned": true}
    ]
  }
}
```

- `id` = use as `gateway_id` in send requests
- `owned: true` = your own gateway (free, no balance deduction)
- `owned: false` = shared gateway (deducts from balance)

---

### 8. Check Balance

**GET** `?route=balance`

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=balance" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "balance": 23.85,
    "currency": "USD",
    "billing_mode": "per_message"
  }
}
```

---

### 9. Message History

**GET** `?route=messages`

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `limit` | integer | No | Results per page (default 50, max 100) |
| `offset` | integer | No | Pagination offset |
| `status` | string | No | Filter by status: `sent`, `delivered`, `failed` |

```bash
curl "https://xcobean.com/modules/addons/sms_suite/webhook.php?route=messages&limit=10" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

---

## Error Handling

All errors return this format:

```json
{
  "success": false,
  "error": {
    "code": 400,
    "message": "Missing required parameters: to, message"
  }
}
```

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created (message sent) |
| 400 | Bad request - missing or invalid parameters |
| 401 | Invalid API credentials |
| 403 | Insufficient permissions |
| 404 | Endpoint not found |
| 429 | Rate limit exceeded (100 req/min) |
| 500 | Server error |

---

## Code Examples

### PHP

```php
<?php
function sendMessage($to, $message, $channel = 'sms') {
    $url = 'https://xcobean.com/modules/addons/sms_suite/webhook.php?route='
         . ($channel === 'whatsapp' ? 'whatsapp/send' : 'send');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: YOUR_API_KEY',
            'X-API-Secret: YOUR_API_SECRET',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'to'      => $to,
            'message' => $message,
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return json_decode($response, true);
}

// Send SMS
$result = sendMessage('254702324532', 'Hello via SMS!');

// Send WhatsApp
$result = sendMessage('254702324532', 'Hello via WhatsApp!', 'whatsapp');

if ($result['success']) {
    echo "Sent! Message ID: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['error']['message'];
}
```

### Python

```python
import requests

API_KEY = "YOUR_API_KEY"
API_SECRET = "YOUR_API_SECRET"
BASE_URL = "https://xcobean.com/modules/addons/sms_suite/webhook.php"

headers = {
    "Content-Type": "application/json",
    "X-API-Key": API_KEY,
    "X-API-Secret": API_SECRET,
}

def send_sms(to, message):
    r = requests.post(f"{BASE_URL}?route=send", json={"to": to, "message": message}, headers=headers)
    return r.json()

def send_whatsapp(to, message):
    r = requests.post(f"{BASE_URL}?route=whatsapp/send", json={"to": to, "message": message}, headers=headers)
    return r.json()

def send_whatsapp_template(to, template_name, params=None, language="en"):
    payload = {"to": to, "template_name": template_name, "language": language}
    if params:
        payload["template_params"] = params
    r = requests.post(f"{BASE_URL}?route=whatsapp/template", json=payload, headers=headers)
    return r.json()

def get_status(message_id):
    r = requests.get(f"{BASE_URL}?route=status&message_id={message_id}", headers=headers)
    return r.json()

def get_gateways(channel=None):
    url = f"{BASE_URL}?route=gateways"
    if channel:
        url += f"&channel={channel}"
    r = requests.get(url, headers=headers)
    return r.json()

# Examples
result = send_sms("254702324532", "Hello via SMS!")
result = send_whatsapp("254702324532", "Hello via WhatsApp!")
result = send_whatsapp_template("254702324532", "hello_world", ["John"])
gateways = get_gateways("whatsapp")
```

### JavaScript / Node.js

```javascript
const API_KEY = "YOUR_API_KEY";
const API_SECRET = "YOUR_API_SECRET";
const BASE_URL = "https://xcobean.com/modules/addons/sms_suite/webhook.php";

const headers = {
  "Content-Type": "application/json",
  "X-API-Key": API_KEY,
  "X-API-Secret": API_SECRET,
};

async function sendSMS(to, message) {
  const res = await fetch(`${BASE_URL}?route=send`, {
    method: "POST",
    headers,
    body: JSON.stringify({ to, message }),
  });
  return res.json();
}

async function sendWhatsApp(to, message) {
  const res = await fetch(`${BASE_URL}?route=whatsapp/send`, {
    method: "POST",
    headers,
    body: JSON.stringify({ to, message }),
  });
  return res.json();
}

async function getGateways(channel) {
  const url = channel
    ? `${BASE_URL}?route=gateways&channel=${channel}`
    : `${BASE_URL}?route=gateways`;
  const res = await fetch(url, { headers });
  return res.json();
}

// Usage
const smsResult = await sendSMS("254702324532", "Hello via SMS!");
const waResult  = await sendWhatsApp("254702324532", "Hello via WhatsApp!");
const gateways  = await getGateways("whatsapp");
```

---

## Important Notes

1. **Phone numbers** must be in international format **without** the `+` prefix (e.g. `254702324532` not `+254702324532`)
2. **WhatsApp 24-hour rule**: Text messages only work if the recipient messaged your business number in the last 24 hours. Use template messages for first contact.
3. **Gateway selection**: If you omit `gateway_id`, the system auto-selects the best gateway for the channel. Call `?route=gateways` to see available options.
4. **Rate limit**: 100 requests per minute per API key.
5. **WhatsApp messages via your own gateway** (`owned: true`) do not deduct from SMS balance.
6. **SMS messages** use the shared gateway and deduct from balance.
