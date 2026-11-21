<?php

use Psalm\Shepherd\Links;

/**
 * Site footer with the documentation, source and issue links.
 *
 * @return Closure(): void
 */
return static function () : void {
    ?>
    <footer class="site-footer">
        <ul class="container">
            <li><a href="<?= htmlspecialchars(Links::SHEPHERD_DOCS) ?>">Docs: running Psalm with <code>--shepherd</code></a></li>
            <li><a href="<?= htmlspecialchars(Links::PSALM) ?>">Psalm</a></li>
            <li><a href="<?= htmlspecialchars(Links::SOURCE) ?>">Source on GitHub</a></li>
            <li><a href="<?= htmlspecialchars(Links::ISSUES) ?>">Report a problem</a></li>
        </ul>
    </footer>
    <?php
};
