# SMS Suite API Documentation

## Overview

The SMS Suite API provides a RESTful interface for sending SMS and WhatsApp messages, managing contacts, campaigns, and accessing reports programmatically.

**Base URL:** `https://your-whmcs.com/modules/addons/sms_suite/api.php`

## Authentication

All API requests require authentication using API keys. API keys can be created from the client area.

### Methods

#### 1. HTTP Headers (Recommended)
```http
X-API-KEY: sms_xxxxxxxxxxxxx
X-API-SECRET: your_secret_key
```

#### 2. Basic Authentication
```http
Authorization: Basic base64(key_id:secret)
```

**Security Note:** Query parameter authentication is NOT supported to prevent credential exposure in server logs and browser history.

## Rate Limiting

Each API key has a configurable rate limit (default: 60 requests per minute). When exceeded, the API returns HTTP 429.

Response headers include:
- `X-RateLimit-Limit`: Maximum requests per minute
- `X-RateLimit-Remaining`: Remaining requests in current window

## Response Format

All responses are JSON formatted:

### Success Response
```json
{
  "success": true,
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "error": {
    "code": 400,
    "message": "Error description"
  }
}
```

## API Scopes

When creating an API key, you can assign specific scopes:

| Scope | Description |
|-------|-------------|
| `send_sms` | Send SMS messages |
| `send_whatsapp` | Send WhatsApp messages |
| `campaigns` | Manage campaigns |
| `contacts` | Manage contacts and groups |
| `balance` | View balance and transactions |
| `logs` | View message logs |
| `reports` | Access reports and usage statistics |
| `templates` | Manage message templates |
| `sender_ids` | Manage sender IDs |

---

# Endpoints

## Messaging

### Send Single SMS

Send a single SMS message.

**Endpoint:** `POST /send`

**Scope Required:** `send_sms`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient phone number (E.164 format) |
| `message` | string | Yes | Message content |
| `sender_id` | string | No | Sender ID to use |
| `gateway_id` | integer | No | Specific gateway to use |
| `channel` | string | No | Channel: `sms` (default) or `whatsapp` |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=send" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+1234567890",
    "message": "Hello from SMS Suite!",
    "sender_id": "MyCompany"
  }'
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "message_id": 12345,
    "segments": 1,
    "encoding": "gsm7",
    "status": "sent"
  }
}
```

---

### Send Bulk SMS

Send messages to multiple recipients.

**Endpoint:** `POST /send/bulk`

**Scope Required:** `send_sms`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `recipients` | array | Yes | Array of phone numbers (max 1000) |
| `message` | string | Yes | Message content |
| `sender_id` | string | No | Sender ID to use |
| `gateway_id` | integer | No | Specific gateway to use |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=send/bulk" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "recipients": ["+1234567890", "+0987654321", "+1122334455"],
    "message": "Bulk message to all!",
    "sender_id": "MyCompany"
  }'
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "total": 3,
    "sent": 3,
    "failed": 0,
    "results": [
      {"to": "+1234567890", "success": true, "message_id": 12345},
      {"to": "+0987654321", "success": true, "message_id": 12346},
      {"to": "+1122334455", "success": true, "message_id": 12347}
    ]
  }
}
```

---

### Schedule Message

Schedule a message for future delivery.

**Endpoint:** `POST /send/schedule`

**Scope Required:** `send_sms`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient phone number |
| `message` | string | Yes | Message content |
| `scheduled_at` | string | Yes | ISO 8601 datetime (e.g., `2024-12-25T10:00:00`) |
| `timezone` | string | No | Timezone (default: UTC) |
| `channel` | string | No | Channel: `sms` or `whatsapp` |
| `sender_id` | string | No | Sender ID to use |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=send/schedule" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+1234567890",
    "message": "Happy New Year!",
    "scheduled_at": "2024-12-31T23:59:00",
    "timezone": "America/New_York"
  }'
```

---

### Get Message Status

Get the delivery status of a message.

**Endpoint:** `GET /status`

**Scope Required:** `logs`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message_id` | integer | Yes | The message ID |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "message_id": 12345,
    "to": "+1234567890",
    "status": "delivered",
    "segments": 1,
    "cost": 0.05,
    "created_at": "2024-01-15T10:30:00",
    "delivered_at": "2024-01-15T10:30:05",
    "error": null
  }
}
```

**Status Values:**
- `queued` - Message is in queue
- `sending` - Message is being sent
- `sent` - Message sent to gateway
- `delivered` - Message delivered to recipient
- `failed` - Message delivery failed
- `rejected` - Message rejected by gateway

---

### Get Message History

Get a list of sent messages.

**Endpoint:** `GET /messages`

**Scope Required:** `logs`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Results per page (max 100, default 50) |
| `offset` | integer | No | Pagination offset |
| `status` | string | No | Filter by status |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "messages": [
      {
        "id": 12345,
        "to": "+1234567890",
        "from": "MyCompany",
        "message": "Hello!",
        "channel": "sms",
        "status": "delivered",
        "segments": 1,
        "cost": 0.05,
        "created_at": "2024-01-15T10:30:00",
        "delivered_at": "2024-01-15T10:30:05"
      }
    ],
    "pagination": {
      "total": 150,
      "limit": 50,
      "offset": 0
    }
  }
}
```

---

### Count Segments

Preview segment count for a message without sending.

**Endpoint:** `GET /segments`

**Scope Required:** None

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | Message content to analyze |
| `channel` | string | No | Channel: `sms` or `whatsapp` |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "segments": 1,
    "encoding": "gsm7",
    "length": 95,
    "max_length": 160,
    "remaining": 65
  }
}
```

---

## WhatsApp

### Send WhatsApp Message

Send a text message via WhatsApp.

**Endpoint:** `POST /whatsapp/send`

**Scope Required:** `send_whatsapp`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient WhatsApp number |
| `message` | string | Yes | Message content |
| `sender_id` | string | No | WhatsApp business number |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=whatsapp/send" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+1234567890",
    "message": "Hello via WhatsApp!"
  }'
```

---

### Send WhatsApp Template

Send a pre-approved WhatsApp template message.

**Endpoint:** `POST /whatsapp/template`

**Scope Required:** `send_whatsapp`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient WhatsApp number |
| `template_name` | string | Yes | Template name |
| `template_params` | array | No | Template variable values |
| `language` | string | No | Template language (default: `en`) |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=whatsapp/template" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+1234567890",
    "template_name": "order_confirmation",
    "template_params": {
      "1": "John",
      "2": "ORD-12345",
      "3": "$99.99"
    }
  }'
```

---

### Send WhatsApp Media

Send media (image, video, document) via WhatsApp.

**Endpoint:** `POST /whatsapp/media`

**Scope Required:** `send_whatsapp`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `to` | string | Yes | Recipient WhatsApp number |
| `media_url` | string | Yes | Public URL of the media file |
| `media_type` | string | No | Type: `image`, `video`, `audio`, `document` |
| `caption` | string | No | Media caption |
| `filename` | string | No | Filename for documents |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=whatsapp/media" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "to": "+1234567890",
    "media_url": "https://example.com/image.jpg",
    "media_type": "image",
    "caption": "Check out this photo!"
  }'
```

---

## Contacts

### Get Contacts

Retrieve contact list.

**Endpoint:** `GET /contacts`

**Scope Required:** `contacts`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Results per page (max 100) |
| `offset` | integer | No | Pagination offset |
| `group_id` | integer | No | Filter by group |

---

### Create Contact

Add a new contact.

**Endpoint:** `POST /contacts`

**Scope Required:** `contacts`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `phone` | string | Yes | Phone number |
| `first_name` | string | No | First name |
| `last_name` | string | No | Last name |
| `email` | string | No | Email address |
| `group_id` | integer | No | Contact group ID |
| `custom_fields` | object | No | Custom field values |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=contacts" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+1234567890",
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "group_id": 5,
    "custom_fields": {
      "company": "Acme Inc",
      "city": "New York"
    }
  }'
```

---

### Get Contact Groups

Retrieve contact groups.

**Endpoint:** `GET /contacts/groups`

**Scope Required:** `contacts`

---

### Import Contacts

Bulk import contacts.

**Endpoint:** `POST /contacts/import`

**Scope Required:** `contacts`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `contacts` | array | Yes | Array of contact objects (max 10,000) |
| `group_id` | integer | No | Group to add contacts to |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=contacts/import" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "group_id": 5,
    "contacts": [
      {"phone": "+1234567890", "first_name": "John", "last_name": "Doe"},
      {"phone": "+0987654321", "first_name": "Jane", "last_name": "Smith"}
    ]
  }'
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "imported": 2,
    "skipped": 0,
    "total": 2
  }
}
```

---

## Campaigns

### Get Campaigns

List all campaigns.

**Endpoint:** `GET /campaigns`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Results per page |
| `offset` | integer | No | Pagination offset |

---

### Create Campaign

Create a new campaign.

**Endpoint:** `POST /campaigns`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Campaign name |
| `message` | string | Yes | Message content |
| `channel` | string | No | `sms` or `whatsapp` |
| `sender_id` | string | No | Sender ID |
| `recipients` | array | No | Array of phone numbers |
| `group_id` | integer | No | Contact group ID |
| `scheduled_at` | string | No | Schedule time (ISO 8601) |
| `send_now` | boolean | No | Send immediately |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=campaigns" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "January Sale",
    "message": "50% off all products this week!",
    "group_id": 10,
    "scheduled_at": "2024-01-15T09:00:00",
    "sender_id": "MyStore"
  }'
```

---

### Get Campaign Status

Get detailed campaign status.

**Endpoint:** `GET /campaigns/status`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `campaign_id` | integer | Yes | Campaign ID |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "name": "January Sale",
    "status": "sending",
    "progress": 45.5,
    "total_recipients": 1000,
    "sent_count": 455,
    "delivered_count": 420,
    "failed_count": 10,
    "started_at": "2024-01-15T09:00:00",
    "completed_at": null
  }
}
```

---

### Pause Campaign

Pause a running campaign.

**Endpoint:** `POST /campaigns/pause`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `campaign_id` | integer | Yes | Campaign ID |

---

### Resume Campaign

Resume a paused campaign.

**Endpoint:** `POST /campaigns/resume`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `campaign_id` | integer | Yes | Campaign ID |

---

### Cancel Campaign

Cancel a campaign.

**Endpoint:** `POST /campaigns/cancel`

**Scope Required:** `campaigns`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `campaign_id` | integer | Yes | Campaign ID |

---

## Sender IDs

### Get Sender IDs

List all sender IDs.

**Endpoint:** `GET /senderids`

**Scope Required:** `sender_ids`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "sender_ids": [
      {
        "id": 1,
        "sender_id": "MyCompany",
        "type": "alphanumeric",
        "status": "approved",
        "validity_date": "2025-12-31"
      }
    ]
  }
}
```

---

### Request Sender ID

Request a new sender ID.

**Endpoint:** `POST /senderids/request`

**Scope Required:** `sender_ids`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sender_id` | string | Yes | Desired sender ID |
| `type` | string | No | Type: `alphanumeric`, `numeric`, `shortcode` |
| `notes` | string | No | Request notes |

---

## Account & Billing

### Get Balance

Get current wallet balance.

**Endpoint:** `GET /balance`

**Scope Required:** `balance`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "balance": 150.50,
    "currency": "USD",
    "billing_mode": "per_segment"
  }
}
```

---

### Get Transactions

Get transaction history.

**Endpoint:** `GET /transactions`

**Scope Required:** `balance`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | integer | No | Results per page |
| `offset` | integer | No | Pagination offset |

---

### Get Usage Statistics

Get usage statistics for a date range.

**Endpoint:** `GET /usage`

**Scope Required:** `reports`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `start_date` | string | No | Start date (YYYY-MM-DD) |
| `end_date` | string | No | End date (YYYY-MM-DD) |

**Example Response:**
```json
{
  "success": true,
  "data": {
    "total_messages": 5420,
    "total_segments": 6100,
    "total_cost": 305.00,
    "by_status": {
      "delivered": 5200,
      "failed": 150,
      "pending": 70
    },
    "by_channel": {
      "sms": 4500,
      "whatsapp": 920
    }
  }
}
```

---

## Templates

### Get Templates

List all message templates.

**Endpoint:** `GET /templates`

**Scope Required:** `templates`

---

### Create Template

Create a new message template.

**Endpoint:** `POST /templates`

**Scope Required:** `templates`

**Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `name` | string | Yes | Template name |
| `content` | string | Yes | Template content with merge tags |
| `category` | string | No | Template category |
| `channel` | string | No | `sms` or `whatsapp` |

**Example Request:**
```bash
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=templates" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Order Confirmation",
    "content": "Hi {first_name}, your order #{order.id} has been confirmed!",
    "category": "transactional"
  }'
```

---

## Template Variables (Merge Tags)

The following merge tags can be used in message templates:

### Client Variables
| Tag | Description |
|-----|-------------|
| `{first_name}` | Client first name |
| `{last_name}` | Client last name |
| `{full_name}` | Client full name |
| `{email}` | Client email |
| `{phone}` | Client phone |
| `{company}` | Company name |
| `{address1}` | Address line 1 |
| `{city}` | City |
| `{state}` | State/Region |
| `{country}` | Country |
| `{postcode}` | Postal code |

### Invoice Variables
| Tag | Description |
|-----|-------------|
| `{invoice.id}` | Invoice ID |
| `{invoice.num}` | Invoice number |
| `{invoice.total}` | Invoice total |
| `{invoice.subtotal}` | Invoice subtotal |
| `{invoice.duedate}` | Due date |
| `{invoice.status}` | Invoice status |

### Service Variables
| Tag | Description |
|-----|-------------|
| `{service.id}` | Service ID |
| `{service.domain}` | Domain name |
| `{service.product}` | Product name |
| `{service.status}` | Service status |
| `{service.nextduedate}` | Next due date |

### System Variables
| Tag | Description |
|-----|-------------|
| `{company_name}` | Your company name |
| `{date}` | Current date |
| `{time}` | Current time |

---

## Error Codes

| Code | Description |
|------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid API credentials |
| 403 | Forbidden - Insufficient permissions |
| 404 | Not Found - Resource not found |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error |

---

## Code Examples

### PHP
```php
<?php
$apiKey = 'sms_xxxxxxxxxxxxx';
$apiSecret = 'your_secret_key';
$baseUrl = 'https://your-whmcs.com/modules/addons/sms_suite/api.php';

// Send SMS
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '?endpoint=send',
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-KEY: ' . $apiKey,
        'X-API-SECRET: ' . $apiSecret,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'to' => '+1234567890',
        'message' => 'Hello from PHP!',
    ]),
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Message sent! ID: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['error']['message'];
}
```

### Python
```python
import requests

api_key = 'sms_xxxxxxxxxxxxx'
api_secret = 'your_secret_key'
base_url = 'https://your-whmcs.com/modules/addons/sms_suite/api.php'

headers = {
    'X-API-KEY': api_key,
    'X-API-SECRET': api_secret,
    'Content-Type': 'application/json'
}

# Send SMS
response = requests.post(
    f'{base_url}?endpoint=send',
    headers=headers,
    json={
        'to': '+1234567890',
        'message': 'Hello from Python!'
    }
)

result = response.json()
if result['success']:
    print(f"Message sent! ID: {result['data']['message_id']}")
else:
    print(f"Error: {result['error']['message']}")
```

### JavaScript (Node.js)
```javascript
const axios = require('axios');

const apiKey = 'sms_xxxxxxxxxxxxx';
const apiSecret = 'your_secret_key';
const baseUrl = 'https://your-whmcs.com/modules/addons/sms_suite/api.php';

async function sendSMS(to, message) {
    try {
        const response = await axios.post(`${baseUrl}?endpoint=send`, {
            to,
            message
        }, {
            headers: {
                'X-API-KEY': apiKey,
                'X-API-SECRET': apiSecret,
                'Content-Type': 'application/json'
            }
        });

        if (response.data.success) {
            console.log(`Message sent! ID: ${response.data.data.message_id}`);
        } else {
            console.error(`Error: ${response.data.error.message}`);
        }
    } catch (error) {
        console.error('Request failed:', error.message);
    }
}

sendSMS('+1234567890', 'Hello from Node.js!');
```

---

## Webhooks

SMS Suite can send webhook notifications for message status updates. Configure webhooks in the admin area.

### Delivery Report Webhook

```json
{
  "event": "message.delivered",
  "timestamp": "2024-01-15T10:30:05Z",
  "data": {
    "message_id": 12345,
    "to": "+1234567890",
    "status": "delivered",
    "provider_message_id": "abc123",
    "delivered_at": "2024-01-15T10:30:05Z"
  }
}
```

### Inbound Message Webhook

```json
{
  "event": "message.inbound",
  "timestamp": "2024-01-15T10:30:05Z",
  "data": {
    "from": "+1234567890",
    "to": "+0987654321",
    "message": "Reply message",
    "channel": "sms",
    "received_at": "2024-01-15T10:30:05Z"
  }
}
```

---

## Support

For API support, please contact your WHMCS administrator or refer to the SMS Suite documentation.
