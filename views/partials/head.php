<?php

/**
 * Opens the document up to <body>.
 *
 * Usage: (require __DIR__ . '/partials/head.php')('Page title', ['/assets/x.css'], ['/assets/x.js'], ['ibm-plex-sans-400']);
 * Extra scripts are loaded with defer; only theme.js (which sets data-theme before first paint) blocks.
 * $preload_fonts names files in assets/fonts (without .woff2) that the first screen needs; preload only those,
 * every preloaded font competes with the stylesheet for bandwidth.
 *
 * @param list<string> $preload_fonts
 * @return Closure(string, list<string>=, list<string>=, list<string>=): void
 */
return static function (
    string $title,
    array $styles = [],
    array $scripts = [],
    array $preload_fonts = ['ibm-plex-sans-400']
) : void {
    // Appends ?v=<mtime> so a changed asset is never served from a stale browser or CDN cache.
    $asset = static function (string $path) : string {
        $mtime = @filemtime(__DIR__ . '/../..' . $path);

        return htmlspecialchars($path . ($mtime !== false ? '?v=' . $mtime : ''));
    };
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?></title>
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
