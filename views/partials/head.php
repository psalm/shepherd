<?php

/**
 * Opens the document up to <body>.
 *
 * Usage: (require __DIR__ . '/partials/head.php')('Page title', ['/assets/x.css'], ['/assets/x.js'], ['ibm-plex-sans-400'], 'Description');
 * Extra scripts are loaded with defer; only theme.js (which sets data-theme before first paint) blocks.
 * $preload_fonts names files in assets/fonts (without .woff2) that the first screen needs; preload only those,
 * every preloaded font competes with the stylesheet for bandwidth.
 * The title feeds <title> and og:title. A non-empty $description adds the description and og:description tags.
 * The Open Graph tags carry the canonical URL (request path without the query string) and a static card image:
 * $share_card picks 'home' (the site card) or 'project' (the card for project pages).
 * X falls back to the og:* tags, so only twitter:card is set.
 *
 * @param list<string> $preload_fonts
 * @param 'home'|'project' $share_card
 * @return Closure(string, list<string>=, list<string>=, list<string>=, string=, 'home'|'project'=): void
 */
return static function (
    string $title,
    array $styles = [],
    array $scripts = [],
    array $preload_fonts = ['ibm-plex-sans-400'],
    string $description = '',
    string $share_card = 'home'
) : void {
    $cards = [
        'home' => ['/assets/img/og.png', 'Shepherd: Watch your PHP get stricter. Type coverage for PHP, by Psalm.'],
        'project' => ['/assets/img/og-project.png', 'Shepherd: type coverage history of a PHP project, by Psalm.'],
    ];
    [$card_image, $card_alt] = $cards[$share_card];
    // Appends ?v=<mtime> so a changed asset is never served from a stale browser or CDN cache.
    $asset = static function (string $path) : string {
        $mtime = @filemtime(__DIR__ . '/../..' . $path);

        return htmlspecialchars($path . ($mtime !== false ? '?v=' . $mtime : ''));
    };
    $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $page_url = 'https://shepherd.dev' . (is_string($request_path) && $request_path !== '' ? $request_path : '/');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?></title>
    <?php if ($description !== '') : ?>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
    <?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Shepherd">
<meta property="og:title" content="<?= htmlspecialchars($title) ?>">
    <?php if ($description !== '') : ?>
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <?php endif; ?>
<meta property="og:url" content="<?= htmlspecialchars($page_url) ?>">
<meta property="og:image" content="https://shepherd.dev<?= $asset($card_image) ?>">
<meta property="og:image:alt" content="<?= htmlspecialchars($card_alt) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#cccccc" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1b1d1f" media="(prefers-color-scheme: dark)">
<link rel="icon" type="image/png" href="<?= $asset('/assets/img/favicon.png') ?>">
    <?php foreach ($preload_fonts as $font) : ?>
<link rel="preload" href="/assets/fonts/<?= htmlspecialchars($font) ?>.woff2" as="font" type="font/woff2" crossorigin>
    <?php endforeach; ?>
<link rel="stylesheet" href="<?= $asset('/assets/styles/main.css') ?>">
<noscript><style>.needs-js { display: none !important; }</style></noscript>
<link rel="expect" href="#main" blocking="render">
    <?php foreach ($styles as $style) : ?>
<link rel="stylesheet" href="<?= $asset($style) ?>">
    <?php endforeach; ?>
<script src="<?= $asset('/assets/js/theme.js') ?>"></script>
    <?php foreach ($scripts as $script) : ?>
<script src="<?= $asset($script) ?>" defer></script>
    <?php endforeach; ?>
</head>
<body>
    <?php
};
