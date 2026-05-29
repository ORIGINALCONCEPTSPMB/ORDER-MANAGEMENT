# ProcessFlow Manager with QR Connect

A complete WordPress plugin for order management with QR code workflow tracking and WhatsApp notifications.

---

## Features

- **Dual-Front Architecture** – Full WordPress admin dashboard *plus* a public-facing user portal via shortcodes.
- **Dynamic Workflow Stages** – Create, reorder, and colour-code stages (default: In Design → In Print Queue → In Finishing → Ready for Collection).
- **QR Code Integration** – Every order gets a unique QR code (Google Charts API, no API key required). Scanning a code advances the stage and redirects to WhatsApp.
- **WhatsApp Notifications** – Deep-link (`wa.me`) messages with merge-tag templates per stage.
- **PDF Label Printing** – Browser-printable QR label sheets for any selection of orders.
- **Custom Fields** – Add extra order fields (text, textarea, number, date, checkbox) from the Settings screen.
- **Secure Front-End Admin** – Password-protected `[processflow_admin_dashboard]` shortcode with transient-based sessions (independent of WordPress users).

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+

---

## Installation

1. Copy/upload the `processflow-manager` folder to `wp-content/plugins/`.
2. In the WordPress admin go to **Plugins → Installed Plugins** and click **Activate** next to *ProcessFlow Manager*.
3. The plugin creates four database tables and seeds four default stages on activation.

---

## Quick Start

### Admin dashboard (WordPress back-end)

Navigate to **ProcessFlow** in the WordPress admin sidebar.

| Page | URL slug |
|------|----------|
| Dashboard | `admin.php?page=processflow-dashboard` |
| Orders | `admin.php?page=processflow-orders` |
| Stages | `admin.php?page=processflow-stages` |
| QR Codes | `admin.php?page=processflow-qr-codes` |
| Settings | `admin.php?page=processflow-settings` |

### Front-end shortcodes

#### Admin portal on any page
```
[processflow_admin_dashboard]
```
Visitors are shown a password-protected login form. Set (or change) the password in **Settings → Security**.

#### Customer order-tracking portal
```
[processflow_user_portal]
[processflow_user_portal title="Check Your Print Job"]
```
Customers enter their **Order Number / Invoice #** to view live status.

---

## Shortcode Attributes

### `[processflow_user_portal]`

| Attribute | Default | Description |
|-----------|---------|-------------|
| `title` | *Settings → Portal Title* | Page heading displayed above the login form |

---

## WhatsApp Template Merge Tags

Each stage can have its own WhatsApp message template. The following merge tags are replaced at send time:

| Tag | Replaced with |
|-----|---------------|
| `{customer_name}` | Customer full name |
| `{business_name}` | Business / company name |
| `{stage_name}` | Current stage name |
| `{order_id}` | Customer-facing order number (invoice number, fallback to numeric ID) |
| `{order_number}` | Customer-facing order number (invoice number, fallback to numeric ID) |
| `{invoice_number}` | Invoice number |
| `{date}` | Current date `d/m/Y` |
| `{time}` | Current time `H:i` |

---

## QR Code Flow

```
Print QR label
      │
      ▼
Customer/operator scans QR code
      │
      ▼
Plugin validates hash
      │
      ▼
Stage advances automatically
      │
      ▼
Browser redirects to wa.me deep-link
      │
      ▼
WhatsApp opens with pre-filled message
```

The QR scan URL format is:

```
https://yoursite.com/?processflow_scan=1&order_id={id}&hash={hash}
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `wp_processflow_orders` | All orders with customer info, stage, QR hash |
| `wp_processflow_stages` | Workflow stages with colour and WhatsApp templates |
| `wp_processflow_stage_history` | Audit log of every stage transition per order |
| `wp_processflow_custom_fields` | Configurable extra fields for orders |

Tables are **not dropped** on deactivation (data is preserved). A full uninstall script can be added if required.

---

## Settings

| Setting | Location | Notes |
|---------|----------|-------|
| Company name / phone / email | Settings → General | Used in outgoing messages |
| Portal title & intro text | Settings → General | Displayed on the customer portal |
| Orders per page | Settings → General | Admin list pagination |
| Admin portal password | Settings → Security | Independent of WP user passwords |
| Custom fields | Settings → Custom Fields | Extra order input fields |

---

## Security

- All user inputs are sanitized with `sanitize_text_field()`, `sanitize_textarea_field()`, `absint()`, etc.
- All database queries use `$wpdb->prepare()` (parameterised).
- All forms verify WordPress nonces (`wp_verify_nonce()` / `check_ajax_referer()`).
- Admin AJAX handlers require `manage_options` capability **or** a valid transient session token.
- All output is escaped with `esc_html()`, `esc_attr()`, `esc_url()`.
- QR hash comparison uses `hash_equals()` (timing-safe).
- Admin portal password is stored as a `wp_hash_password()` hash.
- Session tokens are 32-character cryptographically random strings stored in WordPress transients.

---

## Running Tests

Tests require a WordPress test environment (e.g. set up via `bin/install-wp-tests.sh`).

```bash
# Install WP test suite (once)
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run all tests
phpunit --configuration phpunit.xml
```

Individual suites:

```bash
phpunit tests/test-orders.php
phpunit tests/test-qr.php
```

---

## File Structure

```
processflow-manager/
├── processflow-manager.php          # Plugin entry point
├── includes/
│   ├── class-activator.php          # DB creation + default data
│   ├── class-deactivator.php        # Cleanup on deactivation
│   ├── class-database.php           # All DB operations (CRUD)
│   ├── class-order-manager.php      # Order business logic
│   ├── class-qr-engine.php          # QR generation + scan handler
│   ├── class-whatsapp.php           # WhatsApp link / template engine
│   ├── class-settings.php           # Plugin options wrapper
│   ├── class-admin.php              # WP admin UI + AJAX handlers
│   └── class-public.php             # Public portal shortcode + AJAX
├── admin/
│   ├── css/processflow-admin.css
│   ├── js/processflow-admin.js
│   └── partials/
│       ├── dashboard.php
│       ├── orders.php
│       ├── stages.php
│       ├── settings.php
│       └── qr-codes.php
├── public/
│   ├── css/processflow-public.css
│   ├── js/processflow-public.js
│   └── partials/
│       └── user-portal.php
├── templates/
│   ├── admin-dashboard.php          # Login form for shortcode
│   └── user-portal.php              # Wrapper (for theme overrides)
└── tests/
    ├── test-orders.php
    └── test-qr.php
```

---

## Changelog

### 1.0.0
- Initial release.
- Order CRUD with 4 default stages.
- QR code generation via Google Charts API.
- WhatsApp deep-link notifications with merge-tag templates.
- PDF label printing.
- Public customer portal shortcode.
- Front-end admin dashboard shortcode with password authentication.
- Custom fields builder.

---

## License

GPL-2.0+ – see [LICENSE](LICENSE.md).
