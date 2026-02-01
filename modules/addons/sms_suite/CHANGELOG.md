# Changelog

All notable changes to SMS Suite will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024

### Phase 0 - Bootstrap
- [x] Initial project structure created
- [x] Laravel source extracted for analysis
- [x] WHMCS module directory structure established
- [x] README.md and CHANGELOG.md created

### Phase 1 - Analysis & Design
- [x] Feature inventory from Laravel source (200+ gateways, campaigns, contacts, billing, API)
- [x] Entity/model relationship mapping (63 models analyzed)
- [x] Message lifecycle documentation (queued -> sending -> sent -> delivered)
- [x] Segment counting analysis (GSM-7, GSM-7 Extended, UCS-2)
- [x] Gateway integration analysis (Twilio, Plivo, Vonage, Infobip)
- [x] Webhook/DLR pattern documentation
- [x] Billing logic analysis (per-message, per-segment, wallet, plans)
- [x] Complete WHMCS module design in README.md

### Phase 2 - Implementation (COMPLETED)

#### Slice 1: Module Skeleton + Schema
- [x] `sms_suite.php` - Main module with config, activate, deactivate, upgrade
- [x] Database schema creation (26+ tables)
- [x] Admin controller with dashboard and page stubs
- [x] Client controller with dashboard and page stubs
- [x] Client area templates (dashboard, send, logs, error)
- [x] WHMCS hooks (ClientAdd, ClientDelete, InvoicePaid, DailyCronJob)
- [x] Cron worker framework
- [x] Language file (english.php) with 200+ strings
- [x] Encryption/decryption helpers using WHMCS API

#### Slice 2: Gateway Framework
- [x] `lib/Gateways/GatewayInterface.php` - Interface + DTOs (MessageDTO, SendResult, ValidationResult, DLRResult, InboundResult)
- [x] `lib/Gateways/AbstractGateway.php` - Base class with HTTP helpers
- [x] `lib/Gateways/GatewayRegistry.php` - Driver registration and factory
- [x] `lib/Gateways/GenericHttpGateway.php` - Configurable HTTP gateway
- [x] `lib/Gateways/TwilioGateway.php` - Twilio integration
- [x] `lib/Gateways/PlivoGateway.php` - Plivo integration
- [x] `lib/Gateways/VonageGateway.php` - Vonage/Nexmo integration
- [x] `lib/Gateways/InfobipGateway.php` - Infobip integration
- [x] Admin gateway CRUD with encrypted credential storage
- [x] Gateway country pricing management
- [x] Gateway testing functionality

#### Slice 3: Message Core
- [x] `lib/Core/SegmentCounter.php` - GSM-7, GSM-7 Extended, UCS-2 detection and counting
- [x] `lib/Core/MessageService.php` - Send, process, status update, blacklist check
- [x] Real-time segment counter in client UI (JavaScript)
- [x] Admin broadcast functionality
- [x] Client send single message functionality

#### Slice 4: API Keys
- [x] `lib/Api/ApiKeyService.php` - Key generation, validation, rate limiting
- [x] `lib/Api/ApiController.php` - REST API controller
- [x] `api.php` - API entry point
- [x] Client API key management UI
- [x] Endpoints: send, send/bulk, balance, status, messages, segments, contacts
- [x] Scope-based permissions
- [x] Rate limiting per key

#### Slice 5: Sender IDs
- [x] `lib/Core/SenderIdService.php` - Request, approve, reject, bind
- [x] Client sender ID request form
- [x] Admin approval workflow
- [x] WHMCS invoice integration for sender ID purchases
- [x] Gateway binding support

#### Slice 6: Billing Engine
- [x] `lib/Billing/BillingService.php` - All billing modes
- [x] Wallet balance management
- [x] Plan/bundle credits support
- [x] Balance check before sending
- [x] Automatic deduction after successful send
- [x] Refund on failed delivery
- [x] Wallet top-up with WHMCS invoice integration
- [x] Transaction history

#### Slice 7: Contacts
- [x] `lib/Contacts/ContactService.php` - CRUD, groups, import/export
- [x] Contact management UI with search and filters
- [x] Contact groups with contact counts
- [x] CSV import functionality
- [x] CSV export functionality
- [x] Pagination support

#### Slice 8: Campaigns
- [x] `lib/Campaigns/CampaignService.php` - Create, schedule, process
- [x] Campaign creation with recipient selection (manual, group)
- [x] Scheduled sending
- [x] Batch processing with configurable batch size and delay
- [x] Pause/resume/cancel functionality
- [x] Progress tracking (sent, delivered, failed counts)
- [x] Cron worker integration

#### Slice 9: Webhooks
- [x] `webhook.php` - Universal webhook handler
- [x] Gateway-specific DLR parsing (Twilio, Plivo, Vonage, Infobip)
- [x] Generic webhook pattern matching
- [x] Inbound message handling
- [x] Automatic opt-out keyword detection (STOP, UNSUBSCRIBE, etc.)
- [x] Status update integration with MessageService
- [x] Campaign delivered count updates

#### Slice 10: Reports
- [x] `lib/Reports/ReportService.php` - Usage, daily, destinations, gateway stats
- [x] Usage summary (total messages, segments, cost, by status, by channel)
- [x] Daily activity breakdown
- [x] Top destinations report
- [x] Date range filtering
- [x] CSV export

#### Slice 11: Automation
- [x] `lib/Automation/AutomationService.php` - Hook-based triggers
- [x] 15+ WHMCS hook integrations (ClientAdd, InvoicePaid, TicketOpen, etc.)
- [x] Template variable support with dot notation
- [x] Condition-based filtering
- [x] Execution logging
- [x] Hooks registered in hooks.php

### Phase 2.5 - Laravel Feature Parity (COMPLETED)

#### Enhanced Gateway Support
- [x] Extended gateway registry with 50+ gateway drivers
- [x] MessageBird, Clickatell, Sinch, Bandwidth implementations
- [x] Africa's Talking, Termii (Africa region)
- [x] MSG91, Textlocal, Kaleyra (India region)
- [x] SMSGlobal, Telnyx, Telesign, Routee
- [x] BulkSMS, SMS.to, SignalWire, Zenvia
- [x] AWS SNS integration
- [x] GatewayTypes registry with 50+ provider definitions

#### Full WhatsApp Business API
- [x] `lib/WhatsApp/WhatsAppService.php` - Complete WhatsApp integration
- [x] Template message support with parameters
- [x] Media messages (image, video, audio, document)
- [x] Interactive messages (buttons, lists)
- [x] Location messages
- [x] Conversation/chatbox management
- [x] 24-hour messaging window handling
- [x] Auto-reply triggers
- [x] Multiple WhatsApp providers (Twilio, Meta, Gupshup, UltraMsg, etc.)

#### Template System & Personalization
- [x] `lib/Core/TemplateService.php` - Full merge tag system
- [x] Client variables ({first_name}, {last_name}, {company}, etc.)
- [x] Contact variables with custom field support
- [x] Invoice variables ({invoice.total}, {invoice.duedate})
- [x] Service variables ({service.domain}, {service.product})
- [x] Ticket variables ({ticket.subject}, {ticket.status})
- [x] Order and domain variables
- [x] System variables ({company_name}, {date}, {time})
- [x] Dot notation support (client.first_name)
- [x] Template preview functionality

#### Advanced Campaign Features
- [x] `lib/Campaigns/AdvancedCampaignService.php` - Full advanced features
- [x] A/B testing with up to 4 variants
- [x] Variant percentage distribution
- [x] A/B test results and winner detection
- [x] Drip campaigns / sequences
- [x] Multi-step drip with configurable delays
- [x] Drip subscriber management
- [x] Recurring campaigns (minute/hour/day/week/month)
- [x] Recurring campaign logging
- [x] Link tracking with short URLs
- [x] Click analytics (device, IP, country)
- [x] Contact segmentation with conditions
- [x] Segment operators (equals, contains, greater_than, etc.)
- [x] Scheduled individual messages
- [x] Timezone support for scheduling

#### Enhanced Reporting
- [x] Hourly distribution charts
- [x] Day of week distribution
- [x] Message trend over time
- [x] Status distribution for pie charts
- [x] Delivery metrics (rate, avg time)
- [x] Link click stats for campaigns
- [x] A/B test reports with significance
- [x] Real-time dashboard stats

#### Database Schema Updates
- [x] mod_sms_whatsapp_templates - WhatsApp template management
- [x] mod_sms_chatbox - Conversation management
- [x] mod_sms_chatbox_messages - Conversation messages
- [x] mod_sms_auto_replies - Keyword-based auto-replies
- [x] mod_sms_campaign_ab_tests - A/B testing variants
- [x] mod_sms_drip_campaigns - Drip campaign definitions
- [x] mod_sms_drip_steps - Drip campaign steps
- [x] mod_sms_drip_subscribers - Drip subscribers
- [x] mod_sms_tracking_links - Short URL tracking
- [x] mod_sms_link_clicks - Click analytics
- [x] mod_sms_segments - Contact segments
- [x] mod_sms_segment_conditions - Segment filters
- [x] mod_sms_recurring_log - Recurring campaign history
- [x] mod_sms_scheduled - Scheduled messages

#### Cron Enhancements
- [x] Scheduled message processing
- [x] Drip campaign processing
- [x] Recurring campaign processing
- [x] Link tracking endpoint (track.php)

#### REST API Enhancements
- [x] 25+ API endpoints covering all features
- [x] WhatsApp endpoints (send, template, media)
- [x] Campaign management endpoints (create, status, pause, resume, cancel)
- [x] Sender ID endpoints (list, request)
- [x] Template endpoints (list, create)
- [x] Transaction and usage statistics endpoints
- [x] Contact import endpoint (bulk up to 10,000)
- [x] Scheduled message endpoint
- [x] Extended API scopes (templates, sender_ids)

#### Documentation
- [x] Comprehensive API documentation (docs/API.md)
- [x] PHP, Python, JavaScript code examples
- [x] Webhook documentation
- [x] Merge tag reference
- [x] Error code reference

### Phase 3 - Commercial Hardening (IN PROGRESS)

#### Security Hardening
- [x] CSRF protection on all POST forms (client and admin areas)
- [x] SecurityHelper class with comprehensive utilities
- [x] Removed insecure API credential passing via GET parameters
- [x] File upload validation with MIME type and CSV injection checks
- [x] XSS fixes in admin output (proper escaping, json_encode for JS)
- [x] Rate limiting on API key generation
- [x] Security event logging

#### Performance Optimization
- [x] Database transactions for billing operations (prevents data inconsistency)
- [x] Row-level locking for wallet operations
- [x] Fixed N+1 query pattern in ContactService::getGroups()
- [x] Added performance indexes for common queries
- [x] Index helper for upgrade migrations

#### Code Quality
- [x] Consistent error handling patterns
- [x] Better input validation throughout
- [x] Secure JSON encoding for JavaScript contexts

#### Gateway Enhancements
- [x] Fixed missing `getRequiredFields()` in all gateway classes
- [x] Added `httpPost()` and `httpGet()` helpers to AbstractGateway
- [x] SMPP Gateway with full protocol support (SMPP 3.4)
- [x] Added TextlocalIndia, TextlocalUK, Kaleyra, Fast2SMS gateways
- [x] All 50+ gateway drivers now have proper required fields defined

#### Internal WHMCS Notifications
- [x] `lib/Core/NotificationService.php` - SMS counterparts to WHMCS emails
- [x] 30+ notification types mapped to WHMCS email templates
- [x] Categories: client, order, invoice, quote, domain, service, ticket
- [x] Admin SMS notifications for key events (new orders, tickets, logins)
- [x] Client SMS opt-in/opt-out preferences
- [x] EmailPreSend hook for automatic SMS alongside emails
- [x] Configurable templates per notification type

#### SMS Verification System
- [x] `lib/Core/VerificationService.php` - Token-based verification
- [x] Client account verification via SMS
- [x] Order verification (before/after checkout)
- [x] Two-factor authentication support
- [x] Configurable token length (numeric, alpha, alphanumeric)
- [x] Token expiry and max attempts limits
- [x] Rate limiting to prevent abuse
- [x] Verification audit logging

#### Database Schema Updates
- [x] `mod_sms_notification_templates` - SMS templates for email counterparts
- [x] `mod_sms_admin_notifications` - Admin notification preferences
- [x] `mod_sms_verification_tokens` - Hashed token storage
- [x] `mod_sms_client_verification` - Client verification status
- [x] `mod_sms_order_verification` - Order verification status
- [x] `mod_sms_verification_templates` - Custom verification messages
- [x] `mod_sms_verification_logs` - Verification audit log
- [x] Added `accept_sms`, `accept_marketing_sms`, `enabled_notifications` to settings

#### Remaining
- [ ] Compatibility testing (WHMCS 8.x versions)
- [ ] Final packaging

---

## Module Structure

```
modules/addons/sms_suite/
├── sms_suite.php           # Main module file (40+ tables)
├── hooks.php               # WHMCS hooks with automation triggers
├── cron.php                # Cron worker for campaigns/messages/drip
├── api.php                 # REST API entry point (25+ endpoints)
├── webhook.php             # Webhook handler for DLRs/inbound
├── track.php               # Link tracking endpoint
├── README.md               # Main documentation
├── CHANGELOG.md            # This file
├── docs/
│   └── API.md              # Comprehensive REST API documentation
├── admin/
│   └── controller.php      # Admin area routing and pages
├── client/
│   └── controller.php      # Client area routing and pages
├── lang/
│   └── english.php         # Language strings (200+)
├── lib/
│   ├── Api/
│   │   ├── ApiKeyService.php
│   │   └── ApiController.php
│   ├── Automation/
│   │   └── AutomationService.php
│   ├── Billing/
│   │   └── BillingService.php
│   ├── Campaigns/
│   │   ├── CampaignService.php
│   │   └── AdvancedCampaignService.php  # A/B, drip, recurring, tracking
│   ├── Contacts/
│   │   └── ContactService.php
│   ├── Core/
│   │   ├── MessageService.php
│   │   ├── SegmentCounter.php
│   │   ├── SenderIdService.php
│   │   └── TemplateService.php          # Merge tags & personalization
│   ├── Gateways/
│   │   ├── GatewayInterface.php
│   │   ├── AbstractGateway.php
│   │   ├── GatewayRegistry.php
│   │   ├── GatewayDrivers.php           # 50+ gateway implementations
│   │   ├── GenericHttpGateway.php
│   │   ├── TwilioGateway.php
│   │   ├── PlivoGateway.php
│   │   ├── VonageGateway.php
│   │   └── InfobipGateway.php
│   ├── Reports/
│   │   └── ReportService.php            # Enhanced with chart data
│   └── WhatsApp/
│       └── WhatsAppService.php          # Full WhatsApp Business API
└── templates/
    └── client/
        ├── dashboard.tpl
        ├── send.tpl
        ├── logs.tpl
        ├── contacts.tpl
        ├── campaigns.tpl
        ├── sender_ids.tpl
        ├── api_keys.tpl
        ├── billing.tpl
        ├── reports.tpl
        └── error.tpl
```

## Database Tables (40+)

| Table | Purpose |
|-------|---------|
| **Core Tables** | |
| mod_sms_settings | Client settings |
| mod_sms_gateways | Gateway configurations |
| mod_sms_gateway_countries | Country pricing |
| mod_sms_messages | Message logs |
| mod_sms_templates | Message templates |
| mod_sms_countries | Country reference |
| mod_sms_cron_status | Cron tracking |
| **Sender ID** | |
| mod_sms_sender_ids | Sender ID management |
| mod_sms_sender_id_plans | Sender ID pricing |
| **Contacts** | |
| mod_sms_contact_groups | Contact groups |
| mod_sms_contact_group_fields | Custom fields |
| mod_sms_contacts | Contacts |
| mod_sms_blacklist | Blocked numbers |
| mod_sms_optouts | Opt-out list |
| **Campaigns** | |
| mod_sms_campaigns | Campaigns |
| mod_sms_campaign_lists | Campaign-group junction |
| mod_sms_campaign_recipients | Campaign recipients |
| mod_sms_campaign_ab_tests | A/B test variants |
| mod_sms_recurring_log | Recurring campaign history |
| mod_sms_scheduled | Scheduled messages |
| **Drip Campaigns** | |
| mod_sms_drip_campaigns | Drip campaign definitions |
| mod_sms_drip_steps | Drip campaign steps |
| mod_sms_drip_subscribers | Drip subscribers |
| **Segmentation** | |
| mod_sms_segments | Contact segments |
| mod_sms_segment_conditions | Segment filters |
| **Link Tracking** | |
| mod_sms_tracking_links | Short URL tracking |
| mod_sms_link_clicks | Click analytics |
| **WhatsApp** | |
| mod_sms_whatsapp_templates | WhatsApp templates |
| mod_sms_chatbox | Conversations |
| mod_sms_chatbox_messages | Conversation messages |
| mod_sms_auto_replies | Keyword auto-replies |
| **Webhooks** | |
| mod_sms_webhooks_inbox | Webhook storage |
| **API** | |
| mod_sms_api_keys | API keys |
| mod_sms_api_rate_limits | Rate limiting |
| mod_sms_api_audit | API audit log |
| mod_sms_rate_limits | Rate limit counters |
| **Billing** | |
| mod_sms_wallet | Credit wallet |
| mod_sms_wallet_transactions | Wallet transactions |
| mod_sms_plan_credits | Plan-based credits |
| mod_sms_pending_topups | Pending wallet top-ups |
| **Automation** | |
| mod_sms_automation_triggers | Legacy automation config |
| mod_sms_automations | Automation triggers |
| mod_sms_automation_logs | Automation execution logs |
