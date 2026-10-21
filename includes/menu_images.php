<?php
declare(strict_types=1);

function smartcanteen_menu_slug(string $name): string {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? '';
    return trim($name, '-');
}

function smartcanteen_web_base(): string {
    // Local XAMPP project lives under /SmartCanteenAI; InfinityFree serves from domain root.
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    return (stripos($script, '/SmartCanteenAI/') !== false) ? '/SmartCanteenAI' : '';
}

function smartcanteen_resolve_menu_image(?string $storedImage, string $itemName, string $defaultImage): string {
    $storedImage = trim((string)$storedImage);

    // 1. Admin-uploaded image always wins.
    if ($storedImage !== '') {
        if (preg_match('#^https?://#i', $storedImage)) return $storedImage;
        if (str_starts_with($storedImage, '/')) return $storedImage;
        return smartcanteen_web_base() . '/assets/uploads/menu/' . rawurlencode($storedImage);
    }

    // 2. Locally downloaded real food photo.
    $slug = smartcanteen_menu_slug($itemName);
    if ($slug !== '') {
        $relative = '/assets/uploads/menu/builtin/' . rawurlencode($slug) . '.jpg';
        $local = dirname(__DIR__) . '/assets/uploads/menu/builtin/' . $slug . '.jpg';
        if (is_file($local) && filesize($local) > 1000) {
            return smartcanteen_web_base() . $relative;
        }
    }

    // 3. Generic fallback.
    if (str_starts_with($defaultImage, '/')) return $defaultImage;
    return smartcanteen_web_base() . '/' . ltrim($defaultImage, '/');
}
