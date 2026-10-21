<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (is_logged_in()) {
    redirect(role_dashboard(current_role() ?? ''));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf((string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session security token is invalid. Please try again.';
    } else {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($password === '') {
            $errors[] = 'Please enter your password.';
        }

        if ($errors === []) {
            $stmt = db()->prepare(
                'SELECT u.user_id, u.full_name, u.email, u.password_hash,
                        u.is_active, r.role_name
                 FROM users u
                 INNER JOIN roles r ON r.role_id = u.role_id
                 WHERE u.email = ?
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errors[] = 'Invalid email or password.';
            } elseif (!(int) $user['is_active']) {
                $errors[] = 'This account is currently inactive. Please contact an administrator.';
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $update = db()->prepare(
                        'UPDATE users SET password_hash = ? WHERE user_id = ?'
                    );
                    $update->execute([$newHash, $user['user_id']]);
                }

                login_user($user);
                redirect(role_dashboard($user['role_name']));
            }
        }
    }
}

$flashError = get_flash('error');
if ($flashError !== null) {
    $errors[] = $flashError;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | SmartCanteen AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --orange: #ff5200;
            --orange2: #e94b00;
            --ink: #172019;
            --muted: #737b76;
            --cream: #fffaf5
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
            color: var(--ink);
            background: #fff
        }

        .login-shell {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.08fr .92fr
        }

        .food-side {
            position: relative;
            min-height: 100vh;
            background: linear-gradient(120deg, rgba(18, 24, 20, .78), rgba(255, 82, 0, .18)), url("https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1800&q=85") center/cover
        }

        .food-side:after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, .08), rgba(0, 0, 0, .62))
        }

        .food-content {
            position: absolute;
            z-index: 2;
            left: 8%;
            right: 8%;
            bottom: 9%;
            color: #fff
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 22px;
            margin-bottom: 30px
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: #fff;
            color: var(--orange);
            font-size: 22px
        }

        .food-content h1 {
            font-size: clamp(42px, 5vw, 76px);
            line-height: .98;
            letter-spacing: -3px;
            margin: 0 0 20px;
            font-weight: 800
        }

        .food-content p {
            font-size: 18px;
            max-width: 570px;
            color: rgba(255, 255, 255, .84);
            margin: 0
        }

        .login-side {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: var(--cream)
        }

        .login-card {
            width: min(460px, 100%)
        }

        .badge-soft {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff0e9;
            color: var(--orange);
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 18px
        }

        .welcome {
            margin-bottom: 30px
        }

        h2 {
            font-size: 38px;
            letter-spacing: -1.5px;
            margin: 0 0 8px;
            font-weight: 800
        }

        .subtitle {
            color: var(--muted);
            margin: 0
        }

        .form-label {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 8px
        }

        .input-wrap {
            position: relative;
            margin-bottom: 18px
        }

        .input-wrap>i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #909993
        }

        .form-control {
            height: 54px;
            border: 1px solid #e5e6e3;
            border-radius: 14px;
            padding-left: 47px;
            padding-right: 46px;
            background: #fff;
            box-shadow: none
        }

        .form-control:focus {
            border-color: #ff9b70;
            box-shadow: 0 0 0 4px rgba(255, 82, 0, .10)
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: none;
            color: #84918b
        }

        .btn-login {
            width: 100%;
            height: 54px;
            border: 0;
            border-radius: 14px;
            background: var(--orange);
            color: #fff;
            font-weight: 800;
            font-size: 15px;
            box-shadow: 0 10px 24px rgba(255, 82, 0, .20);
            transition: .2s
        }

        .btn-login:hover {
            background: var(--orange2);
            transform: translateY(-1px)
        }

        .alert {
            border: 0;
            border-radius: 14px;
            font-size: 14px
        }

        .secure {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            color: #7b8781;
            font-size: 12px;
            margin-top: 20px
        }

        .register {
            margin-top: 22px;
            text-align: center;
            color: var(--muted);
            font-size: 14px
        }

        .register a {
            color: var(--orange);
            font-weight: 800;
            text-decoration: none
        }

        @media(max-width:900px) {
            .login-shell {
                grid-template-columns: 1fr
            }

            .food-side {
                display: none
            }

            .login-side {
                min-height: 100vh;
                padding: 28px
            }

            .login-card {
                max-width: 440px
            }
        }
    </style>
</head>

<body>
    <div class="login-shell">
        <section class="food-side">
            <div class="food-content">
                <div class="logo"><span class="logo-icon"><i class="bi bi-cup-hot-fill"></i></span>SmartCanteen AI</div>
                <h1>Your Cravings.<br>Served Fresh.</h1>
                <p>Smart ordering, intelligent inventory and data-driven canteen management — all in one place.</p>
            </div>
        </section>
        <main class="login-side">
            <div class="login-card">
                <div class="welcome"><span class="badge-soft"><i class="bi bi-stars"></i> Smart Canteen Platform</span>
                    <h2>Welcome back</h2>
                    <p class="subtitle">Sign in to continue to your dashboard.</p>
                </div>
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger d-flex gap-2 align-items-center mb-4"><i
                            class="bi bi-exclamation-circle"></i>
                        <ul class="mb-0 ps-0" style="list-style:none"><?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li><?php endforeach; ?>
                        </ul>
                    </div><?php endif; ?>
                <form method="post" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-wrap"><i class="bi bi-envelope"></i><input class="form-control" type="email"
                            id="email" name="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"
                            placeholder="you@example.com" required autofocus></div>
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap"><i class="bi bi-lock"></i><input class="form-control" id="password"
                            type="password" name="password" autocomplete="current-password"
                            placeholder="Enter your password" required><button class="password-toggle" type="button"
                            onclick="togglePassword()" aria-label="Show password"><i id="eye"
                                class="bi bi-eye"></i></button></div>
                    <button class="btn-login" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Sign
                        in</button>
                </form>
                <div class="secure"><i class="bi bi-shield-check"></i> Secure sign-in with protected sessions</div>
                <div class="register">Don't have an account? <a href="register.php">Create one</a></div>
            </div>
        </main>
    </div>
    <script>function togglePassword() { const p = document.getElementById('password'), e = document.getElementById('eye'); if (p.type === 'password') { p.type = 'text'; e.className = 'bi bi-eye-slash' } else { p.type = 'password'; e.className = 'bi bi-eye' } }</script>
</body>

</html>