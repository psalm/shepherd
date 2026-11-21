<?php

/**
 * Opens the document up to <body>.
 *
 * Usage: (require __DIR__ . '/partials/head.php')('Page title', ['/assets/x.css'], ['/assets/x.js']);
 * Extra scripts are loaded with defer; only theme.js (which sets data-theme before first paint) blocks.
 *
 * @return Closure(string, list<string>, list<string>): void
 */
return static function (string $title, array $styles = [], array $scripts = []) : void {
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&amp;family=IBM+Plex+Sans:wght@400;500;600&amp;display=swap">
<link rel="stylesheet" href="<?= $asset('/assets/styles/main.css') ?>">
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
