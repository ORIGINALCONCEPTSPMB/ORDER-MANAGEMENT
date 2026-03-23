<?php
session_start();

// If already installed, show message
$installed = false;
if (file_exists(__DIR__ . '/config.php')) {
    $installed = true;
}

$step  = (int) ($_GET['step'] ?? 1);
$error = '';
$info  = '';

// Steps: 1=requirements, 2=database, 3=admin, 4=email, 5=install

// --- POST handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = (int) ($_POST['step'] ?? 1);

    if ($step === 2) {
        // Save DB config to session
        $_SESSION['install']['db_host']    = trim($_POST['db_host']    ?? 'localhost');
        $_SESSION['install']['db_name']    = trim($_POST['db_name']    ?? '');
        $_SESSION['install']['db_user']    = trim($_POST['db_user']    ?? '');
        $_SESSION['install']['db_pass']    = $_POST['db_pass']         ?? '';
        // Test connection
        try {
            $testPdo = new PDO(
                'mysql:host=' . $_SESSION['install']['db_host'] . ';charset=utf8mb4',
                $_SESSION['install']['db_user'],
                $_SESSION['install']['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // Create database if it doesn't exist
            $testPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $_SESSION['install']['db_name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $step = 3;
        } catch (Exception $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
            $step  = 2;
        }
    } elseif ($step === 3) {
        $_SESSION['install']['admin_first'] = trim($_POST['admin_first'] ?? 'Super');
        $_SESSION['install']['admin_last']  = trim($_POST['admin_last']  ?? 'Admin');
        $_SESSION['install']['admin_email'] = trim($_POST['admin_email'] ?? 'admin@example.com');
        $_SESSION['install']['admin_pass']  = $_POST['admin_pass']       ?? 'Admin@123!';
        $step = 4;
    } elseif ($step === 4) {
        $_SESSION['install']['app_url']      = rtrim(trim($_POST['app_url']      ?? ''), '/');
        $_SESSION['install']['app_name']     = trim($_POST['app_name']     ?? 'Order Management System');
        $_SESSION['install']['mail_host']    = trim($_POST['mail_host']    ?? '');
        $_SESSION['install']['mail_port']    = (int) ($_POST['mail_port']  ?? 587);
        $_SESSION['install']['mail_user']    = trim($_POST['mail_user']    ?? '');
        $_SESSION['install']['mail_pass']    = $_POST['mail_pass']         ?? '';
        $_SESSION['install']['mail_from']    = trim($_POST['mail_from']    ?? '');
        $_SESSION['install']['mail_name']    = trim($_POST['mail_name']    ?? '');
        $step = 5;
    } elseif ($step === 5) {
        // Perform installation
        try {
            $cfg = $_SESSION['install'];
            $pdo = new PDO(
                'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4',
                $cfg['db_user'],
                $cfg['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Run schema
            $schema = file_get_contents(__DIR__ . '/schema.sql');
            // Split by semicolons and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $stmt) {
                if ($stmt) {
                    $pdo->exec($stmt);
                }
            }

            // Update super_admin with configured credentials; clear plain-text password from session immediately
            $adminHash = password_hash($cfg['admin_pass'], PASSWORD_BCRYPT, ['cost'=>12]);
            unset($_SESSION['install']['admin_pass']);
            $pdo->prepare(
                "UPDATE users SET email=?, password_hash=?, first_name=?, last_name=? WHERE role='super_admin' LIMIT 1"
            )->execute([$cfg['admin_email'], $adminHash, $cfg['admin_first'], $cfg['admin_last']]);

            // Generate app secret
            $appSecret = bin2hex(random_bytes(16));

            // Write config.php
            $configContent = '<?php' . "\n";
            $configContent .= '// Database' . "\n";
            $configContent .= "define('DB_HOST', " . var_export($cfg['db_host'], true) . ");\n";
            $configContent .= "define('DB_NAME', " . var_export($cfg['db_name'], true) . ");\n";
            $configContent .= "define('DB_USER', " . var_export($cfg['db_user'], true) . ");\n";
            $configContent .= "define('DB_PASS', " . var_export($cfg['db_pass'], true) . ");\n";
            $configContent .= "define('DB_CHARSET', 'utf8mb4');\n\n";
            $configContent .= '// Application' . "\n";
            $configContent .= "define('APP_NAME', " . var_export($cfg['app_name'], true) . ");\n";
            $configContent .= "define('APP_URL', " . var_export($cfg['app_url'], true) . ");\n";
            $configContent .= "define('APP_SECRET', " . var_export($appSecret, true) . ");\n\n";
            $configContent .= '// Email (SMTP)' . "\n";
            $configContent .= "define('MAIL_HOST', " . var_export($cfg['mail_host'], true) . ");\n";
            $configContent .= "define('MAIL_PORT', " . (int)$cfg['mail_port'] . ");\n";
            $configContent .= "define('MAIL_USERNAME', " . var_export($cfg['mail_user'], true) . ");\n";
            $configContent .= "define('MAIL_PASSWORD', " . var_export($cfg['mail_pass'], true) . ");\n";
            $configContent .= "define('MAIL_FROM_NAME', " . var_export($cfg['mail_name'] ?: $cfg['app_name'], true) . ");\n";
            $configContent .= "define('MAIL_FROM_EMAIL', " . var_export($cfg['mail_from'] ?: $cfg['mail_user'], true) . ");\n";
            $configContent .= "define('MAIL_ENCRYPTION', 'tls');\n\n";
            $configContent .= '// Security' . "\n";
            $configContent .= "define('SESSION_LIFETIME', 7200);\n";
            $configContent .= "define('MAX_LOGIN_ATTEMPTS', 5);\n";
            $configContent .= "define('LOCKOUT_DURATION', 900);\n";
            $configContent .= "define('CODE_EXPIRY', 600);\n";
            $configContent .= "define('RESET_TOKEN_EXPIRY', 3600);\n";

            file_put_contents(__DIR__ . '/config.php', $configContent);
            chmod(__DIR__ . '/config.php', 0600);

            $step = 6; // Success step
        } catch (Exception $e) {
            $error = 'Installation failed: ' . $e->getMessage();
            $step  = 5;
        }
    }
}

// Requirements check
$requirements = [
    'PHP >= 7.4'        => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO'               => extension_loaded('pdo'),
    'PDO MySQL'         => extension_loaded('pdo_mysql'),
    'OpenSSL'           => extension_loaded('openssl'),
    'mbstring'          => extension_loaded('mbstring'),
    'Session support'   => function_exists('session_start'),
    'schema.sql exists' => file_exists(__DIR__ . '/schema.sql'),
    'Root writable'     => is_writable(__DIR__),
];
$allPass = !in_array(false, $requirements, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Installation Wizard &mdash; Order Management System</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
body { background:#f0f4f8; }
.install-wrap { max-width:680px; margin:40px auto; padding:0 16px; }
.install-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.08); overflow:hidden; }
.install-header { background:#1e293b; color:#fff; padding:24px 32px; }
.install-header h1 { font-size:1.3rem; margin:0; }
.install-header p  { font-size:.875rem; color:rgba(255,255,255,.65); margin:4px 0 0; }
.install-body { padding:32px; }
</style>
</head>
<body>
<div class="install-wrap">
    <div class="install-card">
        <div class="install-header">
            <h1>Order Management System</h1>
            <p>Installation Wizard</p>
        </div>
        <div class="install-body">

        <?php if ($installed && $step < 6): ?>
            <div class="alert alert-warning">
                <strong>Already installed.</strong> config.php exists. Delete it to re-run the installer.
            </div>
            <a href="index.php" class="btn btn-primary">Go to Application</a>
        <?php elseif ($step === 6): ?>
            <div class="alert alert-success">
                <strong>Installation successful!</strong> Your Order Management System is ready.
            </div>
            <div class="card" style="margin-top:20px;">
                <div class="card-body">
                    <h3>Default Admin Credentials</h3>
                    <p class="mt-8"><strong>Email:</strong> <?= htmlspecialchars($_SESSION['install']['admin_email'] ?? 'admin@example.com') ?></p>
                    <p class="mt-4"><strong>Password:</strong> <em>(the password you set during installation)</em></p>
                    <div class="alert alert-warning mt-16">
                        <strong>Security:</strong> Delete or rename <code>install.php</code> and set <code>config.php</code> permissions to 600.
                    </div>
                </div>
            </div>
            <div class="mt-16">
                <a href="auth/login.php" class="btn btn-primary btn-lg">Go to Login</a>
            </div>
        <?php else: ?>

            <!-- Step indicators -->
            <div class="install-steps" style="margin-bottom:28px;">
                <?php $stepLabels = ['1'=>'Requirements','2'=>'Database','3'=>'Admin','4'=>'Email','5'=>'Install']; ?>
                <?php foreach ($stepLabels as $n => $label): ?>
                <div class="install-step <?= $step == $n ? 'active' : ($step > $n ? 'complete' : '') ?>">
                    <?= $n ?>. <?= $label ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error mb-20"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
            <!-- STEP 1: Requirements -->
            <h2>System Requirements</h2>
            <p class="text-muted text-sm mb-16">Verifying your server meets the minimum requirements.</p>
            <?php foreach ($requirements as $label => $ok): ?>
            <div class="req-item">
                <?php if ($ok): ?>
                <span class="req-ok">&#10003;</span>
                <?php else: ?>
                <span class="req-fail">&#10007;</span>
                <?php endif; ?>
                <span><?= htmlspecialchars($label) ?></span>
            </div>
            <?php endforeach; ?>
            <div class="mt-24">
                <?php if ($allPass): ?>
                <a href="install.php?step=2" class="btn btn-primary btn-lg">Continue &rarr;</a>
                <?php else: ?>
                <div class="alert alert-error mt-16">Please fix the requirements above before continuing.</div>
                <a href="install.php?step=1" class="btn btn-secondary">Recheck</a>
                <?php endif; ?>
            </div>

            <?php elseif ($step === 2): ?>
            <!-- STEP 2: Database -->
            <h2>Database Configuration</h2>
            <p class="text-muted text-sm mb-16">Enter your MySQL database credentials.</p>
            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                <div class="form-group">
                    <label class="form-label">Database Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['db_host'] ?? 'localhost') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Database Name <span class="required">*</span></label>
                    <input type="text" name="db_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['install']['db_name'] ?? '') ?>" placeholder="order_management">
                </div>
                <div class="form-group">
                    <label class="form-label">Database Username <span class="required">*</span></label>
                    <input type="text" name="db_user" class="form-control" required value="<?= htmlspecialchars($_SESSION['install']['db_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Database Password</label>
                    <input type="password" name="db_pass" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['db_pass'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-lg">Test &amp; Continue &rarr;</button>
            </form>

            <?php elseif ($step === 3): ?>
            <!-- STEP 3: Admin setup -->
            <h2>Administrator Account</h2>
            <p class="text-muted text-sm mb-16">Configure the super admin account credentials.</p>
            <form method="POST" action="">
                <input type="hidden" name="step" value="3">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="admin_first" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['admin_first'] ?? 'Super') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="admin_last" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['admin_last'] ?? 'Admin') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Email <span class="required">*</span></label>
                    <input type="email" name="admin_email" class="form-control" required value="<?= htmlspecialchars($_SESSION['install']['admin_email'] ?? 'admin@example.com') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Admin Password <span class="required">*</span></label>
                    <input type="password" name="admin_pass" class="form-control" required value="<?= htmlspecialchars($_SESSION['install']['admin_pass'] ?? 'Admin@123!') ?>">
                    <div class="form-hint">Min 8 chars with uppercase, lowercase, and number.</div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">Continue &rarr;</button>
            </form>

            <?php elseif ($step === 4): ?>
            <!-- STEP 4: App & Email config -->
            <h2>Application &amp; Email Settings</h2>
            <p class="text-muted text-sm mb-16">Configure your application URL and optional email settings.</p>
            <form method="POST" action="">
                <input type="hidden" name="step" value="4">
                <div class="form-group">
                    <label class="form-label">Application Name</label>
                    <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['app_name'] ?? 'Order Management System') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Application URL <span class="required">*</span></label>
                    <input type="url" name="app_url" class="form-control" required
                           value="<?= htmlspecialchars($_SESSION['install']['app_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com'))) ?>"
                           placeholder="https://yourdomain.com">
                </div>
                <h3 style="margin:20px 0 12px;font-size:1rem;">SMTP Email (optional)</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="mail_host" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['mail_host'] ?? '') ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="mail_port" class="form-control" value="<?= htmlspecialchars((string)($_SESSION['install']['mail_port'] ?? 587)) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="mail_user" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['mail_user'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">SMTP Password</label>
                        <input type="password" name="mail_pass" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['mail_pass'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Email</label>
                        <input type="email" name="mail_from" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['mail_from'] ?? '') ?>" placeholder="noreply@yourdomain.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">From Name</label>
                        <input type="text" name="mail_name" class="form-control" value="<?= htmlspecialchars($_SESSION['install']['mail_name'] ?? '') ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">Continue &rarr;</button>
            </form>

            <?php elseif ($step === 5): ?>
            <!-- STEP 5: Confirm install -->
            <h2>Ready to Install</h2>
            <p class="text-muted text-sm mb-16">Review your settings and click Install to proceed.</p>
            <?php $cfg = $_SESSION['install'] ?? []; ?>
            <div class="card mb-20">
                <div class="card-body">
                    <p><strong>Database:</strong> <?= htmlspecialchars($cfg['db_name'] ?? '') ?> on <?= htmlspecialchars($cfg['db_host'] ?? '') ?></p>
                    <p class="mt-8"><strong>Admin Email:</strong> <?= htmlspecialchars($cfg['admin_email'] ?? '') ?></p>
                    <p class="mt-8"><strong>Application URL:</strong> <?= htmlspecialchars($cfg['app_url'] ?? '') ?></p>
                    <p class="mt-8"><strong>SMTP:</strong> <?= $cfg['mail_host'] ? htmlspecialchars($cfg['mail_host']) : 'Not configured (PHP mail() fallback)' ?></p>
                </div>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="step" value="5">
                <button type="submit" class="btn btn-primary btn-lg">Install Now</button>
                <a href="install.php?step=4" class="btn btn-secondary" style="margin-left:8px;">Back</a>
            </form>
            <?php endif; ?>

        <?php endif; ?>

        </div><!-- /.install-body -->
    </div><!-- /.install-card -->
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
