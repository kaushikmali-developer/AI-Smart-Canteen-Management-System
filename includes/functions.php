<?php
declare(strict_types=1);

/**
 * SmartCanteen AI - Shared application helpers
 */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$key] ?? ''));

    if (mb_strlen($value) > $maxLength) {
        return mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirect_back(string $fallback): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    if ($referer !== '' && str_starts_with($referer, '/')) {
        redirect($referer);
    }

    redirect($fallback);
}
