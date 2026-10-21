<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(role_dashboard(current_role() ?? ''));
}

redirect('/SmartCanteenAI/auth/login.php');
