<?php
/**
 * Kolekta — page-level helpers (used by PHP-rendered pages).
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

/** Redirect unauthenticated visitors to the sign-in page. */
function page_guest_only(): void
{
    $user = current_user(false);
    if ($user !== null) {
        header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'user.php'));
        exit;
    }
}

/** Require a signed-in user; optionally constrain the role. */
function page_guard(?string $role = null): array
{
    $user = current_user(false);
    if ($user === null) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: login.php?next=' . $next);
        exit;
    }
    if ($role !== null && $user['role'] !== $role) {
        header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'user.php'));
        exit;
    }
    return $user;
}

/** CSRF meta tag for pages. */
function csrf_meta(): string
{
    return '<meta name="csrf" content="' . csrf_token() . '">';
}

/** Kolekta wordmark (inline SVG mark + word). */
function brand_mark(): string
{
    return
        '<svg class="mark" viewBox="0 0 32 32" aria-hidden="true">' .
        '<rect width="32" height="32" rx="9" fill="currentColor"/>' .
        '<path d="M9.5 21.5C13 25 19 25 22.5 21.5c1.1-1.05 1.1-2.95 0-4" stroke="#F6F1E6" stroke-width="2.4" stroke-linecap="round" fill="none"/>' .
        '<path d="M8 16.5c2.6-2.4 6.2-2.9 9.5-1.9 1.5.44 2.7.3 3.5-.4" stroke="#F6F1E6" stroke-width="2.4" stroke-linecap="round" fill="none"/>' .
        '<path d="M9.5 11.5C13 15 19 15 22.5 11.5" stroke="#F6F1E6" stroke-width="2.4" stroke-linecap="round" fill="none" opacity=".9"/>' .
        '</svg>';
}

function brand_word(): string
{
    return '<span class="wordmark"><span class="wordmark--mark">' . brand_mark() . '</span><span class="wordmark--word">Kolekta</span></span>';
}

/** Gentle escape helper for server-rendered text. */
function esc(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Server-side thin-line icon set (mirror of the icons in assets/js/app.js). */
function icon(string $name): string
{
    static $icons = [
        'arrowRight' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        'bell'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
        'bioLeaf'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 21c0-3 1.85-5.4 5.1-6"/></svg>',
        'calendar'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'camera'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
        'clock'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'fileText'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
        'flag'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        'home'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'inbox'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
        'logout'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        'megaphone'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
        'nonbio'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l-1.5 18H7.5L6 3z"/><line x1="9.5" y1="9" x2="14.5" y2="9"/></svg>',
        'recycle'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4.8a1.8 1.8 0 0 1-1.6-2.7L6 11"/><path d="M17 19h2.2a1.8 1.8 0 0 0 1.6-2.7L18 11"/><path d="m9 21 2.5-3.5h5L19 21"/><path d="M12 10.5 9.5 7"/><path d="m14.5 7 4 7"/><path d="M9.6 13H8a2 2 0 0 1-1.7-3l1-1.7"/></svg>',
        'send'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
        'truck'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h-5"/><path d="M15 8h4l3 4v5a1 1 0 0 1-1 1h-1"/><circle cx="7" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/></svg>',
        'x'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    ];
    return $icons[$name] ?? '';
}

/** Dismiss-only cookie notice for pages that don't require a login. */
function cookie_notice(bool $inLegal = false): string
{
    $privacyHref = $inLegal ? 'privacy.php' : 'legal/privacy.php';
    $cookiesHref = $inLegal ? 'cookies.php' : 'legal/cookies.php';
    return
        '<div class="cookie-notice" id="cookieNotice" role="region" aria-label="Cookie notice" hidden>' .
        '<p class="cookie-notice--text"><strong>Cookies on Kolekta.</strong> We use one essential session cookie to keep you signed in, and no tracking cookies. ' .
        '<a href="' . esc($cookiesHref) . '">Cookie Policy</a> &middot; <a href="' . esc($privacyHref) . '">Privacy Policy</a></p>' .
        '<button type="button" class="btn btn--secondary cookie-notice--btn" onclick="dismissCookieNotice()">Acknowledge</button>' .
        '</div>';
}

/** Legal page layout — shared header/nav/footer for the Terms & policies. */
function legal_page(string $title): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="description" content="Kolekta ' . esc($title) . ' for residents of the barangay.">';
    echo '<title>' . esc($title) . ' — Kolekta</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..700;1,9..144,300..700&family=Inter:wght@400..700&family=IBM+Plex+Mono:wght@400;500&display=swap">';
    echo '<link rel="stylesheet" href="../assets/css/kolekta.css">';
    echo '</head><body class="legal-body">';
    echo '<header class="site-header"><div class="wrap site-header--inner">';
    echo '<a class="brand" href="../index.php" aria-label="Kolekta home">' . brand_word() . '</a>';
    echo '<nav class="site-nav" aria-label="Legal pages">';
    echo '<a href="../index.php">Back to site</a>';
    echo '<a class="is-here" href="privacy.php">Privacy</a>';
    echo '<a href="cookies.php">Cookies</a>';
    echo '<a href="terms.php">Terms</a>';
    echo '</nav></div></header>';
    echo '<main class="wrap"><article class="legal">';
    echo '<h1 class="legal--title">' . esc($title) . '</h1>';
    echo '<p class="legal--updated">Last updated: September 2026</p>';
}

/** Close a legal page body. */
function legal_page_close(): void
{
    echo '</article></main>';
    echo '<footer class="site-footer"><div class="wrap site-footer--inner">';
    echo '<span>Kolekta — barangay waste dispatch alerts · RA 9003 · RA 10173</span>';
    echo '<nav class="site-nav" aria-label="Footer"><a href="privacy.php">Privacy Policy</a><a href="cookies.php">Cookie Policy</a><a href="terms.php">Terms of Service</a></nav>';
    echo '</div></footer>';
    echo cookie_notice(true);
    echo '<script src="../assets/js/app.js" defer></script>';
    echo '<script src="../assets/js/legal.js" defer></script>';
    echo '</body></html>';
}