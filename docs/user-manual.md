# User Manual

Welcome to the Order Management System. This manual explains how to use every feature of the platform.

---

## Overview

The Order Management System helps teams track customer orders from creation through completion. It provides:

- A **centralized dashboard** with order statistics
- **Full order lifecycle management** — create, assign, update status, view history
- **Role-based access** so admins can manage users and all orders
- **Two-factor authentication** for enhanced security
- **Email notifications** for verification codes and password resets

---

## User Roles

| Role          | Permissions                                                          |
|---------------|----------------------------------------------------------------------|
| `user`        | Create and manage their own orders; view orders assigned to them     |
| `admin`       | All of the above, plus manage all orders and users                   |
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
Once verified, you are redirected to the main dashboard.

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

- **Stats cards** — Total orders, Pending, Processing, Completed, Cancelled counts.
- **Recent Orders** table — The 10 most recently created orders with quick View/Edit links.
- **New Order** button — Shortcut to the order creation form.

---

## Creating an Order

1. Click **New Order** in the sidebar or on the dashboard.
2. Fill in the required fields:
   - **Customer Name** *(required)* — The customer's full name.
   - **Customer Email** — Optional; used for reference.
   - **Customer Phone** — Optional.
   - **Description** *(required)* — Details of the order.
   - **Priority** — Low / Medium / High.
   - **Status** — Starting status (usually Pending).
   - **Assign To** *(admin only)* — Assign the order to a team member.
   - **Internal Notes** — Private notes not visible to the customer.
3. Click **Create Order**. You are redirected to the order detail page.

---

## Order Statuses

| Status       | Meaning                                       |
|--------------|-----------------------------------------------|
| `Pending`    | Newly created; awaiting action                |
| `Processing` | Work has begun                                |
| `Completed`  | Order fulfilled                               |
| `Cancelled`  | Order was cancelled                           |

Admins can update status from the order detail page or via bulk actions in Admin → Manage Orders.

---

## Order Priorities

| Priority | Badge Color |
|----------|-------------|
| Low      | Gray        |
| Medium   | Orange      |
| High     | Red         |

---

## Viewing an Order

Click on an **Order Number** or the **View** button to open the order detail page.

The detail page shows:
- All customer and order details
- Current status and priority badges
- Assigned team member
- Full **Activity History** timeline showing every status change and update

Admins see an additional **Update Status** panel on the right side.

---

## Editing an Order

Click **Edit** on any order you created (or any order if you are an admin).

Changes are logged to the order history automatically.

---

## Filtering and Searching Orders

On the **All Orders** page:
- Use the **Search** box to filter by order number, customer name, or email.
- Use the **Status** and **Priority** dropdowns to filter.
- Click **Filter** to apply; **Clear** to reset.
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
Summary statistics for users and orders, plus quick action links.

### Manage Users
- **Add User** — Click the **+ Add User** button to open a modal form. All new users are created as verified and active.
- **Change Role** — Use the role dropdown in the table to promote or demote a user.
- **Activate / Deactivate** — Toggle a user's active status. Deactivated users cannot log in.
- **Reset Password** — Generates a temporary password and sends it to the user's email.
- **Super admin accounts** cannot be deactivated or modified by regular admins.

### Manage Orders (Admin)
- **All orders** are visible regardless of who created them.
- **Bulk Status Update** — Check multiple orders, select a new status from the dropdown, and click **Apply to Selected**.
- **Assign** — Change the assigned user for any order directly from the table.
- **Export CSV** — Downloads all visible orders as a CSV file for reporting.

---

## FAQ

**Q: I didn't receive my verification code.**
A: Check your spam/junk folder. Click **Resend Code** on the verification page. If SMTP is not configured, the system uses PHP `mail()` which may not work on all servers.

**Q: Can I use the system without email (no 2FA)?**
A: The system requires email verification for registration and 2FA for every login. For development, you can check the `verification_codes` table in the database directly to retrieve codes.

**Q: How do I promote a user to admin?**
A: Log in as an admin, go to **Admin → Manage Users**, and change the user's role using the dropdown in the table.

**Q: Can I export orders?**
A: Yes. Go to **Admin → Manage Orders** and click **Export CSV**.

**Q: How do I change the application name or URL after installation?**
A: Edit `config.php` in the root directory and update the `APP_NAME` and `APP_URL` constants.

**Q: Is my data secure?**
A: The system uses PDO prepared statements to prevent SQL injection, bcrypt for password hashing, CSRF tokens on all forms, and session-based authentication with database-backed token validation.
