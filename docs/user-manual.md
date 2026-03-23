# User Manual — ProcessFlow Manager

Welcome to ProcessFlow Manager — a standalone order management and workflow tracking platform for print shops and production businesses, converted from the ProcessFlow Manager WordPress plugin.

---

## Overview

ProcessFlow Manager helps you track customer print jobs and production orders through configurable workflow stages. Key capabilities:

- **Workflow Stages** — Customisable production stages with color-coding and WhatsApp notification templates
- **QR Code Tracking** — Each order has a unique QR code that staff scan to update stages on the spot
- **WhatsApp Notifications** — One-tap links that open WhatsApp with a pre-filled status update message
- **Public Customer Portal** — Customers self-serve order status using their order number + WhatsApp digits
- **Custom Fields** — Add extra fields to all order forms (artwork reference, finish type, quantity, etc.)
- **Two-Factor Authentication** — Every login requires an email verification code

---

## User Roles

| Role          | Permissions                                                          |
|---------------|----------------------------------------------------------------------|
| `user`        | Create and manage orders; view stage history                         |
| `admin`       | All of the above, plus manage stages, settings, custom fields, all users |
| `super_admin` | All admin powers, plus modify admin and super admin accounts         |

---

## Logging In

### Step 1 — Enter Credentials
Navigate to the login page and enter your **email address** and **password**.

### Step 2 — Verification Code (2FA)
After submitting valid credentials, a **6-digit code** is sent to your registered email. Enter it on the verification page.

- The code expires in **10 minutes**.
- Click **Resend Code** if it doesn't arrive within a minute (check spam folder first).

### Step 3 — Dashboard
Once verified, you are redirected to the main dashboard showing stage-based order statistics.

---

## Registering a New Account

1. Click **Create one** on the login page.
2. Fill in your first name, last name, email address, and a strong password.
   - **Password requirements:** At least 8 characters, 1 uppercase letter, 1 lowercase letter, 1 number.
3. After submitting, a verification code is sent to your email.
4. Enter the code on the verification page to activate your account.

---

## Forgot Password

1. Click **Forgot password?** on the login page.
2. Enter your registered email address.
3. You will receive a **password reset link** (expires in 1 hour).
4. Click the link and enter a new strong password.

---

## Dashboard

The dashboard displays:

- **Stage Stats cards** — Order count per workflow stage plus an Archived count.
- **Recent Orders** table — The 10 most recent active orders with stage badges.
- **New Order** button — Shortcut to the order creation form.

---

## Creating an Order

1. Click **New Order** in the sidebar or on the dashboard.
2. Fill in the required fields:
   - **Customer Name** *(required)* — The customer's full name.
   - **Business Name** — Company or trading name.
   - **WhatsApp Number** — Customer's WhatsApp contact (used for notifications). Include country code, e.g. `27821234567`.
   - **Invoice Number** — Reference number from your invoicing system.
   - **Job Details** *(required)* — Description of the print job.
   - **Product Lines** — Line-by-line list of items (one per line).
   - **Current Stage** — Select the starting stage (defaults to the first active stage).
   - **Custom Fields** — Any additional fields configured by your admin.
3. Click **Create Order**. You are redirected to the order detail page.

---

## Workflow Stages

Orders move through a series of configurable stages (e.g. In Design → In Print Queue → In Finishing → Ready for Collection).

Stages are shown as **coloured badges** on orders. The colour and name come from the Stage configured by your admin.

### Updating an Order's Stage

**Option 1 — Edit Page:** Go to the order, click **Edit**, change the **Current Stage** dropdown and save.

**Option 2 — QR Scan:** See [QR Code Scanning](#qr-code-scanning) below.

**Option 3 — Bulk Action (admin):** Select multiple orders in Admin → Manage Orders → choose **Change Stage** from bulk actions.

---

## QR Code Scanning

Every order has a unique QR code visible on the order detail page. Print this QR code on the physical job ticket.

When a staff member scans the QR code with their phone:

1. The QR scan page opens — **no login required**.
2. The page shows the order details, current stage, and a progress bar.
3. Staff select the **new stage** from the dropdown and tap **Update Stage**.
4. A **WhatsApp button** appears with a pre-filled message to send to the customer.

> **Security note:** The QR hash uses 128-bit random entropy, making it practically impossible to guess.

---

## WhatsApp Notifications

After scanning a QR code or viewing an order, a **WhatsApp button** appears if the order has a WhatsApp number stored.

Tapping the button opens WhatsApp (web or app) with a pre-filled message based on the stage's template. The message is sent manually — you tap **Send** in WhatsApp.

### Template Variables

Stage templates can include:

| Variable          | Replaced with              |
|-------------------|---------------------------|
| `{customer_name}` | Customer's name            |
| `{order_id}`      | Order ID number            |
| `{business_name}` | Business/company name      |
| `{stage_name}`    | Current stage name         |

Templates are edited under **Admin → Stages**.

---

## Customer Tracking Portal

Customers can check their own order status without logging in.

**URL:** `https://yourdomain.com/orders/track.php`

Customers enter:
1. Their **Order ID** (shown on the job ticket or invoice)
2. The **last 4 digits** of their WhatsApp number

If the details match, they see:
- Current stage badge and color
- Progress bar across all stages
- Stage history timeline

> Rate-limited to 10 attempts per session per 15 minutes.

---

## Viewing an Order

Click on an **Order ID** or the **View** button to open the order detail page.

The detail page shows:
- All order and customer details (customer name, business, WhatsApp link, invoice number, job details, product lines, custom fields)
- Current stage badge with color
- QR code image for printing
- WhatsApp notification button
- Full **Stage History** timeline
- **Archive** / **Unarchive** button (admin)

---

## Editing an Order

Click **Edit** on any order to update its details. Changing the stage automatically logs an entry to the stage history.

---

## Archiving Orders

Archiving moves an order to the "Completed" archive — it is hidden from the main orders list but not deleted.

- **Archive:** Click the **Archive** button on the order view page, or use bulk archive in Admin → Manage Orders.
- **Unarchive:** In the Admin Orders page, switch to the **Archived** tab and click **Unarchive**.

---

## Filtering and Searching Orders

On the **All Orders** page:
- Use the **Search** box to filter by order ID, customer name, or business name.
- Use the **Stage** dropdown to filter by workflow stage.
- Click any column header to sort the table.

---

## Account Settings

### Profile Settings
Go to **Account → Profile Settings** to update your first name, last name, or email address.

### Change Password
Go to **Account → Change Password**. You must enter your current password before setting a new one.

---

## Admin Panel

> Accessible only to users with `admin` or `super_admin` role.

### Admin Dashboard
Summary statistics for users and orders by stage, plus quick action links.

### Manage Users
- **Add User** — Click the **+ Add User** button. All new users are created as verified and active.
- **Change Role** — Use the role dropdown in the table.
- **Activate / Deactivate** — Toggle a user's login access.
- **Reset Password** — Generates a temporary random password and sends it to the user's email.

### Manage Orders (Admin)
- All orders visible regardless of who created them.
- **Filter** by stage, archived status, or search.
- **Bulk Actions** — Archive, unarchive, change stage, or delete multiple orders.
- **Export CSV** — Downloads all visible orders.
- **Import CSV** — Upload a CSV file to bulk-create orders (columns: customer_name, business_name, whatsapp, invoice_number, job_details).
- **InvoiceNinja Import** — Browse invoices from your InvoiceNinja account and import selected ones as orders.

### Stages (`Admin → Stages`)
- **Add Stage** — Name, colour picker, WhatsApp message template, position, active/inactive toggle.
- **Edit Stage** — Update any stage in place.
- **Delete Stage** — Stages in use by active orders cannot be deleted.
- **Reorder** — Change `Order Position` to control the stage sequence.

### Settings (`Admin → Settings`)

**General Tab:**
- Company name, phone, email
- Customer portal title and intro text
- Orders per page

**WhatsApp Tab:**
- Default country code (e.g. `27` for South Africa)
- Enable/disable WhatsApp integration

**InvoiceNinja Tab:**
- InvoiceNinja base URL and API token
- Test connection and browse invoices for import

**Custom Fields Tab:**
- Add fields (label, type: text/textarea/number/date/checkbox, required)
- Delete existing custom fields

**Backup & Restore Tab:**
- **Export:** Download a JSON backup of all orders, stages, settings, and custom fields.
- **Import:** Upload a JSON backup to restore data (passwords not included; user accounts must be re-created).

---

## FAQ

**Q: I didn't receive my verification code.**
A: Check your spam/junk folder. Click **Resend Code** on the verification page. If SMTP is not configured, check your server's PHP mail() functionality.

**Q: How do I print the QR code for a job ticket?**
A: Open the order detail page and right-click the QR code image to save it, or use your browser's print function.

**Q: Can the customer see internal notes?**
A: The customer tracking portal only shows stage name and stage history — no internal fields.

**Q: How do I change the default country code for WhatsApp numbers?**
A: Go to **Admin → Settings → WhatsApp** and update the "Default Country Code" field.

**Q: Can I export orders?**
A: Yes. Go to **Admin → Manage Orders** and click **Export CSV**.

**Q: How do I change the application name or URL after installation?**
A: Edit `config.php` in the root directory and update the `APP_NAME` and `APP_URL` constants.

**Q: Is my data secure?**
A: The system uses PDO prepared statements throughout, bcrypt password hashing, CSRF tokens on all forms, session-based authentication with database-backed token validation, and 128-bit random QR hashes.

