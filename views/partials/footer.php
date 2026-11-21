<?php

use Psalm\Shepherd\Links;

/**
 * Site footer with the Psalm ecosystem links and the links to Shepherd's own source and issue tracker.
 *
 * @return Closure(): void
 */
return static function () : void {
    $links = [
        'Psalm docs' => Links::PSALM_DOCS,
        'Playground' => Links::PLAYGROUND,
        'Plugins' => Links::PLUGINS,
        'GitHub' => Links::PSALM_SOURCE,
        'Support' => Links::SUPPORT,
        'Shepherd source' => Links::SOURCE,
        'Report a problem' => Links::ISSUES,
    ];
    ?>
    <footer class="site-footer">
        <ul class="container">
    <?php foreach ($links as $label => $url) : ?>
            <li><a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($label) ?></a></li>
    <?php endforeach; ?>
        </ul>
    </footer>
    <?php
};
