<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('MANAGER');

$page_title = 'Manager Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<section class="premium-hero"><div class="section-kicker" style="color:#8ce8c8">Management intelligence</div><h1 class="h2 mb-2">See the business behind every <span class="accent">order.</span></h1><p class="mb-0" style="max-width:620px;color:#d5e8e2">Track performance, trends and operational signals in a focused management workspace.</p></section>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 fw-bold mb-1">Manager Dashboard</h1>
        <p class="text-secondary mb-0">Monitor sales intelligence, analytics and management reports.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Signed in as</div>
                <div class="fs-5 fw-semibold mt-1"><?= e(current_user()['full_name']) ?></div>
                <div class="badge text-bg-primary mt-2"><?= e(current_role()) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card kpi-card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Authentication</div>
                <div class="fs-5 fw-semibold mt-1">Protected</div>
                <div class="text-success small mt-2"><i class="bi bi-shield-check"></i> Session active</div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info mt-4">
    Phase 3 authentication is active. The functional module for this role will be added in the next development phase.
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
