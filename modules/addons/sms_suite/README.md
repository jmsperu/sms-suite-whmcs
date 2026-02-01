# SMS Suite - WHMCS Addon Module

A comprehensive SMS and WhatsApp messaging addon for WHMCS, converted from a Laravel bulk messaging application.

## Overview

SMS Suite provides enterprise-grade messaging capabilities directly integrated into WHMCS, including:

- **Multi-Channel Messaging**: SMS and WhatsApp Business API support
- **50+ Gateway Drivers**: Twilio, Plivo, Vonage, MessageBird, AWS SNS, and more
- **Campaign Management**: Bulk messaging, A/B testing, drip campaigns, recurring
- **Contact Management**: Groups, segments, custom fields, CSV import/export
- **Billing Integration**: Per-message, per-segment, wallet, and plan-based billing
- **REST API**: Full-featured API with 25+ endpoints, scopes, and rate limiting
- **Automation**: WHMCS hook-triggered messaging with personalized templates
- **Link Tracking**: Short URLs with click analytics and device detection
- **Comprehensive Reporting**: Usage reports, charts, and CSV exports

## API Documentation

Full REST API documentation is available at `docs/API.md`.

**Quick Start:**
```bash
# Send an SMS
curl -X POST "https://your-whmcs.com/modules/addons/sms_suite/api.php?endpoint=send" \
  -H "X-API-KEY: sms_xxxxxxxxxxxxx" \
  -H "X-API-SECRET: your_secret" \
  -H "Content-Type: application/json" \
  -d '{"to": "+1234567890", "message": "Hello from SMS Suite!"}'
```

See `docs/API.md` for complete endpoint documentation and examples in PHP, Python, and JavaScript.

## Requirements

- WHMCS 8.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## Installation

1. Upload the `sms_suite` folder to `modules/addons/`
2. Navigate to Setup > Addon Modules in WHMCS Admin
3. Find "SMS Suite" and click Activate
4. Configure module settings
5. Set up cron job: `php -q /path/to/whmcs/modules/addons/sms_suite/cron.php`

## Directory Structure

```
modules/addons/sms_suite/
├── sms_suite.php          # Main module file
├── hooks.php              # WHMCS hooks
├── cron.php               # Cron worker for campaigns
├── README.md              # This file
├── CHANGELOG.md           # Version history
├── admin/                 # Admin area controllers
├── client/                # Client area controllers
├── hooks/                 # Hook handler files
├── lang/                  # Language files
│   └── english.php
├── lib/                   # Core libraries
│   ├── Api/               # API endpoint handlers
│   ├── Automation/        # Automation triggers
│   ├── Billing/           # Billing engine
│   ├── Campaigns/         # Campaign management
│   ├── Contacts/          # Contact management
│   ├── Core/              # Core services
│   ├── Gateways/          # Gateway drivers
│   └── Reports/           # Reporting engine
├── templates/             # Smarty templates
│   ├── admin/
│   └── client/
└── assets/                # Static assets
    ├── css/
    ├── js/
    └── images/
```

---

# Phase 1: Design Document

## 1. Feature Inventory (from Laravel Source Analysis)

### 1.1 Core Features

| Feature | Description | Laravel Location |
|---------|-------------|------------------|
| **Campaigns** | Bulk SMS/WhatsApp with scheduling, recurring, batching | `Campaigns.php`, `SendCampaignSMS.php` |
| **Contacts** | Contact management with groups, custom fields, segments | `Contacts.php`, `ContactGroups.php` |
| **Sender IDs** | Request, purchase, approve, bind to gateways | `Senderid.php`, `SenderidPlans.php` |
| **Gateways** | 200+ SMS/WhatsApp providers, custom HTTP gateway | `SendingServer.php`, `CustomSendingServer.php` |
| **Billing** | Plans, subscriptions, credit-based, per-message | `Plan.php`, `Subscription.php` |
| **API** | REST API with token auth, rate limiting | API controllers, `api_token` |
| **WhatsApp** | WhatsApp Business API integration | Multiple WhatsApp gateway types |
| **Templates** | Message templates with variable substitution | `Templates.php`, `TemplateTags.php` |
| **Reports** | Message logs, campaign stats, exports | `Reports.php`, `TrackingLog.php` |
| **Automation** | Triggered messaging based on events | `Automation.php`, `AutomationJob.php` |
| **Two-Way SMS** | Inbound message handling, chat | `ChatBox.php`, `ChatBoxMessage.php` |
| **Blacklists** | Opt-out and blocked numbers | `Blacklists.php` |
| **Keywords** | Keyword-based auto-subscribe/unsubscribe | `Keywords.php` |

### 1.2 Messaging Channels

- **SMS** (Plain/GSM-7, Unicode/UCS-2)
- **WhatsApp** (via Business API, 360Dialog, Twilio, etc.)
- **MMS** (Media messages)
- **Voice** (Text-to-speech calls)
- **Viber**
- **OTP** (One-time passwords)

### 1.3 Gateway Integrations (200+)

**Major Providers:**
- Twilio, Plivo, Vonage/Nexmo, Infobip, MessageBird
- Bandwidth, SignalWire, Telnyx, ClickSend
- BulkSMS, TextLocal, Africa's Talking, Safaricom
- AWS SNS, SMPP protocol

**WhatsApp Providers:**
- WhatsApp Business API, 360Dialog, Twilio WhatsApp
- UltraMsg, WaAPI, Interakt, Whatsender

---

## 2. Entity Relationships

```
WHMCS Client (tblclients)
    │
    ├── mod_sms_settings (1:1) - Client SMS settings & billing mode
    │
    ├── mod_sms_wallet (1:1) - Credit balance
    │
    ├── mod_sms_api_keys (1:Many) - API keys
    │
    ├── mod_sms_sender_ids (1:Many) - Sender IDs
    │
    ├── mod_sms_contacts (1:Many)
    │       └── mod_sms_contact_custom_fields (1:Many)
    │
    ├── mod_sms_contact_groups (1:Many)
    │       ├── mod_sms_contacts (1:Many)
    │       └── mod_sms_contact_group_fields (1:Many)
    │
    ├── mod_sms_campaigns (1:Many)
    │       ├── mod_sms_campaign_recipients (1:Many)
    │       └── mod_sms_campaign_lists (Many:Many → contact_groups)
    │
    ├── mod_sms_messages (1:Many) - All sent messages
    │
    ├── mod_sms_templates (1:Many)
    │
    └── mod_sms_blacklist (1:Many)

mod_sms_gateways (Admin managed)
    ├── mod_sms_gateway_countries (1:Many) - Country pricing
    └── mod_sms_messages (1:Many)

mod_sms_automation_triggers (Admin managed)
    └── Links WHMCS hooks to templates/gateways
```

---

## 3. Message Lifecycle States

```
┌─────────────┐
│   QUEUED    │ ← Message created, pending send
└──────┬──────┘
       │
       ▼
┌─────────────┐
│  SENDING    │ ← Picked up by worker
└──────┬──────┘
       │
       ├────────────────┬────────────────┐
       ▼                ▼                ▼
┌─────────────┐  ┌─────────────┐  ┌─────────────┐
│    SENT     │  │   FAILED    │  │  REJECTED   │
└──────┬──────┘  └─────────────┘  └─────────────┘
       │
       │ (Webhook/DLR)
       ▼
┌─────────────┐
│  DELIVERED  │ ← Final successful state
└─────────────┘
       or
┌─────────────┐
│ UNDELIVERED │ ← Final failed state
└─────────────┘
```

**Status Values:**
- `queued` - Created, waiting to send
- `sending` - Being processed
- `sent` - Sent to gateway (awaiting DLR)
- `delivered` - Confirmed delivered
- `failed` - Send failed
- `rejected` - Rejected by gateway
- `undelivered` - DLR confirmed not delivered
- `expired` - Message expired before delivery

---

## 4. Segment Counting Implementation

Based on `SMSCounter.php` from Laravel source:

### Character Limits

| Encoding | Single SMS | Multipart (per segment) |
|----------|------------|-------------------------|
| GSM-7 | 160 chars | 153 chars |
| GSM-7 Extended | 160 chars | 153 chars |
| UTF-16/Unicode | 70 chars | 67 chars |
| WhatsApp | 1000 chars | N/A |

### GSM-7 Extended Characters
These count as 2 characters: `[ ] { } ~ ^ € | \`

### Detection Algorithm
1. Check for non-GSM characters → UTF-16
2. Check for GSM-7 extended chars → GSM-7_EX
3. Default → GSM-7

### Segment Calculation
```php
segments = ceil(length / per_message_limit)
```

---

## 5. Webhook/Delivery Receipt Patterns

### Inbound Webhook Flow
```
Gateway → POST /index.php?m=sms_suite&webhook=<gateway_type>
       → Authenticate (token/signature)
       → Store raw payload in mod_sms_webhooks_inbox
       → Parse via gateway driver
       → Update message status
       → Trigger any automation
```

### DLR Status Mapping
| Gateway Status | Normalized Status |
|----------------|-------------------|
| DELIVRD, DELIVERED, SENT | delivered |
| UNDELIVERABLE, UNDELIV | undelivered |
| EXPIRED, DELETED | expired |
| REJECTED, REJECTD | rejected |
| FAILED, ERROR | failed |

---

## 6. Billing Logic

### Billing Modes

1. **per_message** - Flat rate per message sent
2. **per_segment** - Rate per SMS segment (recommended)
3. **wallet** - Prepaid credit balance
4. **plan** - WHMCS product-based bundles

### Cost Calculation
```
cost = segments × rate_per_segment × country_multiplier
```

### Credit Flow
1. Client sends message
2. Calculate segments and cost
3. Check sufficient balance/credits
4. Deduct from wallet or plan credits
5. Log transaction
6. If DLR shows failed → refund

---

## 7. Database Schema (WHMCS Tables)

### Core Tables

```sql
-- Module settings
mod_sms_settings
  id, client_id (FK→tblclients), billing_mode, default_gateway_id,
  default_sender_id, webhook_url, api_enabled, created_at, updated_at

-- Gateways
mod_sms_gateways
  id, name, type, channel (sms|whatsapp|both), status,
  credentials (encrypted JSON), settings (JSON), quota_value, quota_unit,
  success_keyword, balance, created_at, updated_at

-- Gateway country pricing
mod_sms_gateway_countries
  id, gateway_id (FK), country_code, country_name,
  sms_rate, whatsapp_rate, status, created_at, updated_at

-- Sender IDs
mod_sms_sender_ids
  id, client_id (FK), sender_id, type (alphanumeric|numeric),
  status (pending|active|rejected|expired), price, currency_id,
  invoice_id (FK→tblinvoices), gateway_ids (JSON), validity_date,
  created_at, updated_at

-- Contacts
mod_sms_contacts
  id, client_id (FK), group_id (FK), phone, first_name, last_name,
  email, status (subscribed|unsubscribed), custom_data (JSON),
  created_at, updated_at
  INDEX: (client_id, phone), (group_id, status)

-- Contact Groups
mod_sms_contact_groups
  id, client_id (FK), name, description, default_sender_id,
  welcome_sms, unsubscribe_sms, status, contact_count (cached),
  created_at, updated_at

-- Contact Group Fields
mod_sms_contact_group_fields
  id, group_id (FK), label, tag, type, default_value,
  required, visible, sort_order, created_at, updated_at

-- Campaigns
mod_sms_campaigns
  id, client_id (FK), name, channel (sms|whatsapp),
  gateway_id (FK), sender_id, message, media_url,
  status (draft|scheduled|queued|sending|paused|completed|failed|cancelled),
  schedule_time, schedule_type (onetime|recurring),
  frequency_amount, frequency_unit, recurring_end,
  total_recipients, sent_count, delivered_count, failed_count,
  cost_total, batch_id, created_at, updated_at
  INDEX: (status, schedule_time), (client_id, status)

-- Campaign Lists (junction)
mod_sms_campaign_lists
  id, campaign_id (FK), group_id (FK)

-- Campaign Recipients
mod_sms_campaign_recipients
  id, campaign_id (FK), contact_id (FK), phone, status,
  message_id (FK→mod_sms_messages), created_at

-- Messages (main log)
mod_sms_messages
  id, client_id (FK), campaign_id (FK), automation_id,
  gateway_id (FK), channel (sms|whatsapp), direction (outbound|inbound),
  sender_id, to_number, message, media_url,
  encoding (gsm7|gsm7ex|ucs2), segments, units,
  cost, status, provider_message_id, error,
  api_key_id, delivered_at, created_at, updated_at
  INDEX: (client_id, created_at), (status), (provider_message_id)

-- Webhook inbox (raw payloads)
mod_sms_webhooks_inbox
  id, gateway_id (FK), gateway_type, payload (TEXT),
  processed, error, created_at

-- Templates
mod_sms_templates
  id, client_id (FK), name, channel, message, status,
  created_at, updated_at

-- API Keys
mod_sms_api_keys
  id, client_id (FK), name, key_prefix, key_hash,
  scopes (JSON), rate_limit, rate_window,
  last_used_at, expires_at, status, created_at, updated_at

-- API Rate Limit Tracking
mod_sms_api_rate_limits
  id, api_key_id (FK), window_start, request_count

-- API Audit Log
mod_sms_api_audit
  id, api_key_id (FK), client_id (FK), endpoint, method,
  request_data (TEXT, redacted), response_code, ip_address,
  created_at

-- Wallet
mod_sms_wallet
  id, client_id (FK), balance, currency_id, updated_at

-- Wallet Transactions
mod_sms_wallet_transactions
  id, client_id (FK), type (topup|deduction|refund|adjustment),
  amount, balance_before, balance_after, reference_type,
  reference_id, description, created_at

-- Plan Credits
mod_sms_plan_credits
  id, client_id (FK), product_id (FK→tblproducts),
  service_id (FK→tblhosting), credits_total, credits_used,
  reset_date, created_at, updated_at

-- Blacklist
mod_sms_blacklist
  id, client_id (FK), phone, reason, created_at

-- Automation Triggers
mod_sms_automation_triggers
  id, name, hook_name, event_type, template_id (FK),
  gateway_id (FK), sender_id, conditions (JSON),
  status, created_at, updated_at

-- Opt-outs (global)
mod_sms_optouts
  id, phone, channel, reason, created_at
```

---

## 8. Admin Pages

| Route | Page | Description |
|-------|------|-------------|
| `action=dashboard` | Dashboard | Overview, stats, quick actions |
| `action=gateways` | Gateways | CRUD gateways, test, balance |
| `action=gateway_edit&id=X` | Gateway Edit | Configure gateway |
| `action=gateway_countries&id=X` | Gateway Pricing | Country rates |
| `action=sender_ids` | Sender IDs | View all, approve/reject |
| `action=sender_id_plans` | Sender ID Plans | Pricing plans |
| `action=campaigns` | Campaigns | View all campaigns |
| `action=messages` | Message Logs | All messages, filters |
| `action=templates` | Templates | System templates |
| `action=automation` | Automation | Hook triggers config |
| `action=reports` | Reports | Usage reports |
| `action=settings` | Settings | Module configuration |
| `action=clients` | Clients | Per-client settings |
| `action=client_edit&id=X` | Client Edit | Client billing/credits |
| `action=webhooks` | Webhook Inbox | Raw payloads, reprocess |
| `action=diagnostics` | Diagnostics | Health check, cron status |

---

## 9. Client Area Pages

| Route | Page | Description |
|-------|------|-------------|
| `action=dashboard` | Dashboard | Balance, usage, quick send |
| `action=send` | Send Message | Single SMS/WhatsApp |
| `action=campaigns` | Campaigns | Create/manage campaigns |
| `action=campaign_create` | New Campaign | Campaign wizard |
| `action=contacts` | Contacts | Manage contacts |
| `action=contact_groups` | Groups | Manage groups |
| `action=import` | Import | CSV import |
| `action=sender_ids` | Sender IDs | Request/view sender IDs |
| `action=templates` | Templates | Personal templates |
| `action=logs` | Message Logs | Sent messages |
| `action=api_keys` | API Keys | Create/manage keys |
| `action=api_docs` | API Docs | API documentation |
| `action=billing` | Billing | Wallet, transactions |
| `action=reports` | Reports | Usage reports |

---

## 10. Cron Tasks

| Task | Cadence | Description |
|------|---------|-------------|
| Campaign Processor | Every minute | Process scheduled campaigns |
| Message Queue | Every minute | Send queued messages |
| DLR Processor | Every 5 minutes | Process pending webhooks |
| Rate Limit Reset | Every hour | Clean rate limit counters |
| Sender ID Expiry | Daily | Check/expire sender IDs |
| Report Aggregation | Daily | Generate daily stats |
| Log Cleanup | Weekly | Prune old logs (configurable) |

---

## 11. Webhook Endpoints

| Endpoint | Auth | Description |
|----------|------|-------------|
| `?m=sms_suite&webhook=dlr&gateway=X` | Token/Signature | Delivery receipts |
| `?m=sms_suite&webhook=inbound&gateway=X` | Token/Signature | Inbound messages |
| `?m=sms_suite&webhook=status&gateway=X` | Token/Signature | Status callbacks |

**Authentication Methods:**
- **Token**: `?token=XXXXX` query param or `X-Webhook-Token` header
- **Signature**: HMAC signature verification (gateway-specific)
- **IP Whitelist**: Optional per-gateway IP filtering

---

## 12. Gateway Driver Architecture

### Interface
```php
interface GatewayInterface {
    public function send(MessageDTO $message): SendResult;
    public function getBalance(): ?float;
    public function parseDeliveryReceipt(array $payload): ?DLRResult;
    public function parseInboundMessage(array $payload): ?InboundResult;
    public function validateConfig(array $config): ValidationResult;
    public function getRequiredFields(): array;
    public function supportsChannel(string $channel): bool;
}
```

### Gateway Registry
```php
class GatewayRegistry {
    public function register(string $type, string $class): void;
    public function get(string $type): GatewayInterface;
    public function all(): array;
}
```

### Built-in Drivers
1. **GenericHttpGateway** - Configurable HTTP gateway
2. **TwilioGateway** - Twilio SMS/WhatsApp
3. **PlivoGateway** - Plivo SMS
4. **VonageGateway** - Vonage/Nexmo SMS
5. **InfobipGateway** - Infobip SMS
6. **SMPPGateway** - SMPP protocol

### GenericHttpGateway Config
```json
{
  "endpoint": "https://api.provider.com/send",
  "method": "POST",
  "auth_type": "bearer|basic|query|header",
  "auth_config": {},
  "headers": {},
  "body_template": {},
  "response_mapping": {
    "message_id": "$.data.id",
    "status": "$.status"
  },
  "success_codes": [200, 201],
  "success_keyword": "success"
}
```

---

## 13. API Key Design

### Key Format
```
sms_live_XXXXXXXXXXXXXXXXXXXXXXXX (32 chars)
sms_test_XXXXXXXXXXXXXXXXXXXXXXXX (for sandbox)
```

### Storage
- Display full key only once on creation
- Store: `key_prefix` (first 8 chars) + `key_hash` (bcrypt)

### Scopes
| Scope | Permissions |
|-------|-------------|
| `send_sms` | Send SMS messages |
| `send_whatsapp` | Send WhatsApp messages |
| `campaigns` | Create/manage campaigns |
| `contacts` | Manage contacts |
| `balance` | View balance/credits |
| `logs` | View message logs |
| `reports` | Access reports |

### Rate Limits
- Per-key limit (default: 100/minute)
- Per-client aggregate limit
- Burst allowance option

### Audit Fields
- Request timestamp
- Endpoint called
- IP address
- Request data (redacted)
- Response code

---

## 14. Billing Engine Design

### BillingService
```php
class BillingService {
    public function calculateCost(Message $msg): CostResult;
    public function canSend(int $clientId, CostResult $cost): bool;
    public function deduct(int $clientId, Message $msg, CostResult $cost): Transaction;
    public function refund(int $clientId, Message $msg): Transaction;
    public function getBalance(int $clientId): BalanceInfo;
    public function topUp(int $clientId, float $amount, string $ref): Transaction;
}
```

### Billing Modes

**per_message:**
- Fixed rate per message regardless of segments
- Simple, easy to understand

**per_segment:**
- Rate × number of segments
- More accurate cost allocation

**wallet:**
- Prepaid credit system
- Deduct on send, refund on failure
- Top-up via WHMCS invoice

**plan:**
- Credits tied to WHMCS products
- Reset on billing cycle
- Overage options

---

## 15. Security Requirements

1. **Credential Encryption**: AES-256 using WHMCS cc_encryption_hash
2. **API Secret Hashing**: bcrypt with cost 12
3. **CSRF Protection**: Token validation on all forms
4. **Input Validation**: Sanitize all user inputs
5. **Permission Checks**: Admin roles + client ownership
6. **Rate Limiting**: API and send operations
7. **Audit Logging**: All sensitive operations
8. **Webhook Auth**: Token/signature verification

---

## License

Proprietary - All rights reserved.

## Support

For support, please contact the module vendor.
