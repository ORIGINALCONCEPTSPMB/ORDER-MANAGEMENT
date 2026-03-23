# Installation Guide

This guide covers installing the Order Management System on a cPanel shared hosting environment.

---

## Prerequisites

| Requirement      | Minimum Version |
|------------------|-----------------|
| PHP              | 7.4             |
| MySQL / MariaDB  | 5.7 / 10.3      |
| PHP Extensions   | PDO, PDO_MySQL, OpenSSL, mbstring |
| Web Server       | Apache (mod_rewrite enabled)      |
| cPanel Access    | For database and file management  |

---

## Step 1 — Download and Upload Files

1. Download the project as a ZIP archive.
2. Log in to cPanel → **File Manager**.
3. Navigate to `public_html` (or a subdirectory like `public_html/orders`).
4. Click **Upload** and upload the ZIP file.
5. Right-click the uploaded ZIP → **Extract**.
6. Make sure all files (including `.htaccess`) are in the correct directory.

> **Tip:** You can also use FTP/SFTP with FileZilla or similar clients.

---

## Step 2 — Create a MySQL Database and User

1. In cPanel, go to **MySQL Databases**.
2. Under **Create New Database**, enter a name (e.g., `cpanelusername_orders`) and click **Create Database**.
3. Under **MySQL Users**, create a new user with a strong password.
4. Under **Add User To Database**, add the new user to the database and grant **ALL PRIVILEGES**.
5. Note down:
   - Database host: `localhost`
   - Database name
   - Database username
   - Database password

---

## Step 3 — Run the Installer

1. Open your browser and navigate to:
   ```
   https://yourdomain.com/install.php
   ```
   (Replace `yourdomain.com` with your actual domain or subdomain.)

2. **Step 1 — Requirements Check:** The wizard verifies PHP extensions and permissions. All items must show a green checkmark.

3. **Step 2 — Database Configuration:** Enter the MySQL credentials from Step 2.

4. **Step 3 — Admin Account:** Set your super admin email and password. Use a strong password (min 8 chars, uppercase, lowercase, number).

5. **Step 4 — Application & Email:** Enter your application URL and optional SMTP settings for email delivery. You can skip SMTP — the system will fall back to PHP `mail()`.

6. **Step 5 — Install:** Review settings and click **Install Now**. The wizard writes `config.php` and runs the database schema.

---

## Step 4 — Post-Installation Security

After successful installation:

### Delete or protect install.php
```bash
# Via SSH
rm public_html/install.php
```
Or via cPanel File Manager — right-click → Delete.

### Set file permissions
```bash
# Directories
find public_html/orders -type d -exec chmod 755 {} \;

# Files
find public_html/orders -type f -exec chmod 644 {} \;

# Config file (read only by server)
chmod 600 public_html/orders/config.php
```

### Verify .htaccess is active
The `.htaccess` file blocks direct access to `config.php` and `*.sql` files. Make sure Apache's `AllowOverride All` is enabled for your directory (usually the default on cPanel hosts).

---

## Step 5 — Log In

Navigate to:
```
https://yourdomain.com/auth/login.php
```

Use the admin email and password you configured in Step 3. You will be sent a 2FA verification code to your email (requires working email/SMTP).

---

## SMTP Email Configuration

The system supports TLS/STARTTLS SMTP. Recommended providers:

| Provider      | Host                    | Port |
|---------------|-------------------------|------|
| Gmail         | `smtp.gmail.com`        | 587  |
| Mailgun       | `smtp.mailgun.org`      | 587  |
| SendGrid      | `smtp.sendgrid.net`     | 587  |
| cPanel default| `mail.yourdomain.com`   | 587  |

If SMTP is not configured or fails, the system falls back to PHP's built-in `mail()` function.

---

## Troubleshooting

### "Database connection failed"
- Double-check host, database name, username, and password.
- On cPanel, the host is almost always `localhost`.
- Ensure the database user has been granted privileges.

### "Root directory not writable"
- Set the web root directory to `755`: `chmod 755 public_html/orders`

### White page / 500 error
- Check the PHP error log in cPanel → **Error Logs**.
- Temporarily enable error display by adding `<?php ini_set('display_errors',1); error_reporting(E_ALL); ?>` at the top of `index.php` (remove after debugging).

### Emails not sending
- Verify SMTP credentials.
- Check that port 587 is not blocked by your host's firewall.
- Try switching to PHP `mail()` by leaving SMTP host blank.
- Check spam/junk folders.

### 2FA code not received
- Verify the email address used during login.
- Check spam folder.
- Use the **Resend Code** button on the verification page.

---

## Upgrading

1. Back up `config.php` and the database.
2. Upload new files (overwrite everything except `config.php`).
3. Review `schema.sql` for any new `ALTER TABLE` or index changes and run them manually if upgrading an existing database.
