<?php
// SmartCanteen AI Command Center navigation.
// Include this file from the shared header/navigation area.
// It is intentionally protected by the server-side role check.
if (function_exists('is_logged_in') && is_logged_in() &&
    function_exists('current_role') &&
    in_array(current_role(), ['ADMIN', 'MANAGER'], true)):
?>
<li class="nav-item">
    <a class="nav-link" href="/SmartCanteenAI/ai/">
        <i class="bi bi-cpu me-1"></i>AI Command Center
    </a>
</li>
<?php endif; ?>
