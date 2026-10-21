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
        $name = post_string('full_name', 100);
        $email = strtolower(post_string('email', 190));
        $phone = post_string('phone', 20);
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($name === '' || mb_strlen($name) < 2) {
            $errors[] = 'Please enter your full name.';
        }

        if (!valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must contain at least 8 characters.';
        }

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors === []) {
            $check = db()->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);

            if ($check->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        }

        if ($errors === []) {
            $role = db()->query(
                "SELECT role_id FROM roles WHERE role_name = 'STUDENT' LIMIT 1"
            )->fetchColumn();

            if ($role === false) {
                $errors[] = 'Student role is not configured.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO users
                    (role_id, full_name, email, phone, password_hash, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)'
                );

                $stmt->execute([
                    (int) $role,
                    $name,
                    $email,
                    $phone !== '' ? $phone : null,
                    password_hash($password, PASSWORD_DEFAULT),
                ]);

                set_flash('success', 'Registration successful. You can now sign in.');
                redirect('/SmartCanteenAI/auth/login.php');
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Account | SmartCanteen AI</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="/SmartCanteenAI/assets/css/swiggy-inspired.css" rel="stylesheet">
<style>
body{background:#fff}.auth-register{min-height:100vh;display:grid;grid-template-columns:1fr 520px;background:#fff}.auth-photo{background:linear-gradient(90deg,rgba(24,25,34,.94),rgba(24,25,34,.45)),url('https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=1500&q=85') center/cover;display:flex;align-items:flex-end;padding:60px;color:#fff}.auth-photo h1{font-size:clamp(42px,5vw,70px);line-height:.98;letter-spacing:-.05em;font-weight:850;margin:0 0 18px}.auth-photo p{max-width:520px;color:#eee;font-size:17px}.auth-panel{display:flex;align-items:center;padding:40px 44px}.auth-box{width:100%}.brand{font-weight:850;font-size:21px;letter-spacing:-.04em;margin-bottom:45px}.brand i{display:inline-grid;place-items:center;width:40px;height:40px;border-radius:9px;background:#ff5200;color:#fff;margin-right:8px}.auth-box h2{font-size:34px}.auth-box .form-control{height:50px}.auth-box .btn-primary{height:50px}@media(max-width:900px){.auth-register{grid-template-columns:1fr}.auth-photo{display:none}.auth-panel{min-height:100vh;padding:30px 22px}}
</style>
</head>
<body>
<div class="auth-register">
<section class="auth-photo"><div><div class="brand"><i class="bi bi-cup-hot-fill"></i>SmartCanteen AI</div><h1>Your canteen,<br>made smarter.</h1><p>Quick ordering, live order tracking and a better everyday food experience for students.</p></div></section>
<main class="auth-panel"><div class="auth-box">
<div class="mb-4"><div class="section-kicker mb-2">Join SmartCanteen AI</div><h2 class="mb-1">Create your account</h2><p class="text-secondary">Register as a student and start ordering.</p></div>
<?php if ($errors !== []): ?><div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" novalidate>
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
<div class="mb-3"><label class="form-label" for="full_name">Full Name</label><input class="form-control" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label" for="phone">Phone <span class="text-secondary">(optional)</span></label><input class="form-control" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>"></div>
<div class="mb-3"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" minlength="8" required></div>
<div class="mb-4"><label class="form-label" for="confirm_password">Confirm Password</label><input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required></div>
<button class="btn btn-primary w-100" type="submit">Create Account</button>
</form>
<p class="text-center mt-4 mb-0 text-secondary">Already have an account? <a href="/SmartCanteenAI/auth/login.php" style="color:#ff5200;font-weight:800;text-decoration:none">Sign in</a></p>
</div></main>
</div></body></html>