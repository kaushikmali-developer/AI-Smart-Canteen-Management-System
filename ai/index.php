<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('ADMIN', 'MANAGER');

$pageTitle = 'AI Command Center';

$features = [
    ['title'=>'AI Forecast','description'=>'Historical sales trends and baseline forecasts.','url'=>'forecast.php','icon'=>'bi-graph-up-arrow'],
    ['title'=>'AI Recommendations','description'=>'Demand, inventory and recipe-based recommendations.','url'=>'recommendations.php','icon'=>'bi-lightbulb'],
    ['title'=>'AI Ask','description'=>'Ask questions about sales, popular items and inventory.','url'=>'ask.php','icon'=>'bi-chat-dots'],
    ['title'=>'Analytics Assistant','description'=>'Database-grounded sales and inventory insights.','url'=>'analytics_assistant.php','icon'=>'bi-stars'],
];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<section class="premium-hero"><div class="section-kicker" style="color:#8ce8c8">Intelligence layer</div><h1 class="h2 mb-2">AI Command <span class="accent">Center.</span></h1><p class="mb-0" style="max-width:680px;color:#d5e8e2">Turn canteen data into forecasts, recommendations and clear operational answers.</p></section>

<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-cpu me-2"></i>AI Command Center</h1>
            <p class="text-muted mb-0">All SmartCanteen AI features in one secure page.</p>
        </div>
        <a href="../<?= e(strtolower((string) current_role())) ?>/dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    </div>

    <div class="alert alert-info border-0 shadow-sm">
        <i class="bi bi-shield-lock me-2"></i>
        <strong>Secure area:</strong> Admin and Manager access is required.
    </div>

    <div class="row g-4">
        <?php foreach ($features as $feature): ?>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="feature-card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="feature-icon">
                        <i class="bi <?= e($feature['icon']) ?> fs-1 text-primary"></i>
                    </div>
                    <h2 class="h5"><?= e($feature['title']) ?></h2>
                    <p class="text-muted small flex-grow-1"><?= e($feature['description']) ?></p>
                    <button type="button"
                            class="btn btn-primary w-100"
                            onclick="openAiFeature(<?= htmlspecialchars(json_encode($feature['url']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($feature['title']), ENT_QUOTES, 'UTF-8') ?>)">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Feature
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="aiFeatureModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width:95%;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiFeatureModalTitle">AI Feature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-0" style="height:78vh;">
                <!-- Do not hide the iframe behind a permanent loading screen.
                     Some PHP pages can take a few seconds because they query analytics data. -->
                <iframe id="aiFeatureFrame"
                        title="AI Feature"
                        src="about:blank"
                        style="display:block;width:100%;height:100%;border:0;background:#fff;"
                        loading="eager"></iframe>
            </div>

            <div class="modal-footer">
                <span class="small text-muted me-auto">
                    <i class="bi bi-shield-check me-1"></i>Protected by Admin/Manager authorization
                </span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a id="aiDirectLink" class="btn btn-outline-primary" href="#" target="_blank" rel="noopener">
                    Open separately
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function openAiFeature(url, title) {
    const modalEl = document.getElementById('aiFeatureModal');
    const frame = document.getElementById('aiFeatureFrame');
    const titleEl = document.getElementById('aiFeatureModalTitle');
    const directLink = document.getElementById('aiDirectLink');

    titleEl.textContent = title;
    directLink.href = url;

    // Reset first, then load the selected same-origin PHP page.
    frame.src = 'about:blank';

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();

    // Start loading after the modal is visible. This avoids the browser
    // deprioritizing a hidden iframe and removes the indefinite spinner.
    window.setTimeout(function () {
        frame.src = url;
    }, 50);
}

document.getElementById('aiFeatureModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('aiFeatureFrame').src = 'about:blank';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
