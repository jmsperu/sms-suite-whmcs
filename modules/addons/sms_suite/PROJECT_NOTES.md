# SMS Suite WHMCS Addon - Project Notes

> This file contains project context, requirements, and implementation details for AI assistants to understand the project when resuming work.

## Project Overview

**Purpose:** A comprehensive SMS Suite addon module for WHMCS that allows hosting companies to offer bulk SMS services to their clients.

**Client:** Kenya-based hosting company using WHMCS
**Primary Gateway:** Airtouch Kenya (local SMS provider)
**Country Focus:** Kenya (254 country code)

---

## Core Requirements Gathered

### 1. SMS Gateway Integration
- **Airtouch Kenya** as primary gateway (API integrated)
- Support for multiple gateways (Twilio, Vonage, Plivo, Infobip, Generic HTTP)
- Gateway abstraction pattern for easy addition of new providers
- Per-gateway, per-destination rate configuration

### 2. Billing & Credits System
- **Credit-based billing** - Clients purchase SMS credits
- **WHMCS Product Integration** - SMS packages sold as WHMCS products
- **Per-client rates** - Different rates for different clients
- **Per-network rates** - Different rates for Safaricom, Airtel, Telkom, etc.
- **Credit-to-Sender ID linking** - Track spending per sender ID and network
- Wallet system with transaction history

### 3. Sender ID Management
- **Manual approval workflow** - Admin manually sends letters to Kenya telcos for approval
- **Document uploads required for registration:**
  - Certificate of Incorporation
  - VAT Certificate
  - KYC Documents
  - Letter of Authorization
- **Per-network Sender IDs** - Same sender ID may need separate approval per network
- **Telco status tracking** - pending_telco, approved, rejected states
- **Shared vs dedicated** Sender IDs from pool

### 4. Kenya Network Detection
- **Network prefix database** for carrier detection
- Kenya mobile prefixes:
  - Safaricom: 0700-0729, 0740-0749, 0790-0799, 0110-0119, etc.
  - Airtel: 0730-0739, 0750-0756, 0780-0789, 0100-0109, etc.
  - Telkom: 0770-0779, 0760-0769
  - Faiba 4G: 0747
  - Equitel: 0763-0765
- Fallback to hardcoded detection if database empty

### 5. Client Area Features
- **Dashboard** - Stats, recent messages, quick actions
- **Send SMS** - Single/bulk message sending
- **Inbox/Chat** - Two-way conversation interface (NEW)
  - View conversations grouped by phone number
  - Chat-style message display
  - Reply to incoming messages
  - Mark messages as read
- **Campaigns** - Bulk SMS campaigns with scheduling
- **Contacts** - Contact management with import/export
- **Contact Groups** - Organize contacts into groups
- **Sender IDs** - Request and manage sender IDs
- **Message Logs** - View sent message history
- **Reports** - Usage analytics
- **Billing** - View balance, purchase credits
- **API Keys** - Generate API keys for integration
- **Preferences** - Notification settings

### 6. Admin Features
- Gateway management
- Client rate configuration
- Sender ID approval workflow
- Network prefix management
- Document viewing/download
- System diagnostics

### 7. Two-Way SMS / Inbound Messages
- Webhook endpoint for receiving delivery receipts and inbound messages
- URL: `/modules/addons/sms_suite/webhook.php?gateway=airtouch`
- Inbound messages stored with `direction=inbound`
- Opt-out keyword handling (STOP, UNSUBSCRIBE, etc.)
- Messages grouped by customer phone for conversation view

### 8. Client Notifications
- SMS notifications for WHMCS events (invoice, support ticket, etc.)
- 2FA via SMS OTP
- Notification preferences per client

---

## Database Tables (Key Ones)

```
mod_sms_gateways          - Gateway configurations
mod_sms_messages          - Message log (inbound/outbound)
mod_sms_campaigns         - Bulk campaigns
mod_sms_contacts          - Client contacts
mod_sms_contact_groups    - Contact groups
mod_sms_sender_ids        - Sender ID requests
mod_sms_sender_id_pool    - Admin sender ID pool
mod_sms_client_sender_ids - Assigned sender IDs
mod_sms_wallet            - Client credit balance
mod_sms_wallet_transactions - Credit history
mod_sms_network_prefixes  - Phone prefix to network mapping
mod_sms_webhooks_inbox    - Incoming webhook payloads
mod_sms_optouts           - Opt-out phone numbers
mod_sms_client_rates      - Per-client pricing
mod_sms_destination_rates - Per-destination pricing
```

---

## File Structure

```
modules/addons/sms_suite/
├── sms_suite.php           # Main module file, activation, DB schema
├── hooks.php               # WHMCS hooks for notifications
├── webhook.php             # Inbound webhook handler
├── cron.php                # Scheduled tasks
├── admin/
│   └── controller.php      # Admin area routing
├── client/
│   └── controller.php      # Client area routing
├── lib/
│   ├── Core/
│   │   ├── MessageService.php
│   │   ├── SenderIdService.php
│   │   ├── SecurityHelper.php
│   │   └── SegmentCounter.php
│   ├── Gateways/
│   │   ├── GatewayInterface.php
│   │   ├── AbstractGateway.php
│   │   ├── AirtouchGateway.php    # Kenya gateway
│   │   ├── TwilioGateway.php
│   │   └── ...
│   ├── Billing/
│   │   └── BillingService.php
│   ├── Campaigns/
│   │   └── CampaignService.php
│   └── Contacts/
│       └── ContactService.php
├── templates/
│   ├── admin/              # Admin templates
│   └── client/             # Client templates
│       ├── dashboard.tpl
│       ├── send.tpl
│       ├── inbox.tpl       # Conversation list
│       ├── conversation.tpl # Chat view
│       ├── campaigns.tpl
│       ├── contacts.tpl
│       ├── contact_groups.tpl
│       ├── sender_ids.tpl
│       └── ...
├── uploads/
│   └── sender_ids/         # Document uploads (protected)
└── lang/
    └── english.php
```

---

## Important Implementation Details

### Conversation Tracking
- `to_number` field ALWAYS stores the customer/remote phone number
- For outbound: to_number = customer phone, sender_id = our sender ID
- For inbound: to_number = customer phone (from), sender_id = our number (to)
- This allows grouping by `to_number` for conversation view

### CSRF Protection
- All forms use `SecurityHelper::getCsrfToken()` and `SecurityHelper::verifyCsrfPost()`
- Token stored in session with 1-hour expiry

### File Uploads
- Stored in `uploads/sender_ids/{client_id}/`
- Protected by .htaccess (Deny from all)
- Allowed types: PDF, JPEG, PNG
- Max size: 5MB

### Database Migrations
- Run via "Repair Database Tables" button in admin
- Uses `$columnExists()` helper to add missing columns
- Safe to run multiple times (idempotent)

---

## Known Issues / Pending Items

1. **WHMCS Product Redirect Bug**
   - Product ID 328 redirects to "linux-unlimited" instead of showing order form
   - Appears to be WHMCS configuration issue, not module-related
   - Investigated: product group, module assignment checked
   - Status: Unresolved, needs further WHMCS debugging

2. **Server Module** (Not Started)
   - `modules/servers/sms_suite/` exists but not implemented
   - Would allow product provisioning automation

3. **Inbound Webhook Testing**
   - Webhook handler implemented but needs live testing with Airtouch
   - Configure callback URL in Airtouch dashboard

---

## Recent Commits

```
d23612a - Add client inbox/chat, contact groups, Sender ID documents, and network prefixes
4fb9c85 - Add currency options and billing rates configuration
67d2f38 - Add SMS credit purchasing and Sender ID billing with WHMCS integration
b5dbaa2 - Add client profile SMS widget and notification system
```

---

## How to Test After Changes

1. Go to Admin > Addons > SMS Suite
2. Click "Repair Database Tables" to run migrations
3. Check "Run Diagnostics" for any issues
4. Test in client area: index.php?m=sms_suite

---

## Contact/Context

- Working directory: `C:\sms`
- Git repo: Yes (master branch)
- Platform: Windows (MSYS/Git Bash)

---

*Last Updated: 2026-02-01*
