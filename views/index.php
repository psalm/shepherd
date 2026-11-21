<?php

use Psalm\Shepherd\Api;

require __DIR__ . '/../vendor/autoload.php';

(require __DIR__ . '/partials/head.php')('Shepherd');
(require __DIR__ . '/partials/nav.php')();
?>
    <main id="main" class="container page" tabindex="-1">
        <div class="intro">
            <p>Shepherd is a currently-experimental service to handle CI output from <a href="https://psalm.dev">Psalm</a>.</p>
            <p>It's being actively developed at <a href="https://github.com/psalm/shepherd">github.com/psalm/shepherd</a>.</p>
        </div>
        <div class="coverage_list">
            <h1>Recent type coverage</h1>

            <ul>
            <?php foreach (Api::getRecentGithubRepositories() as $github_repository) : ?>
                <li>
                    <a href="/github/<?= htmlspecialchars($github_repository) ?>"><?= htmlspecialchars($github_repository) ?></a><br>
                    <img src="/github/<?= htmlspecialchars($github_repository) ?>/coverage.svg" alt="Type coverage badge" height="20">
                    <img src="/github/<?= htmlspecialchars($github_repository) ?>/level.svg" alt="Psalm level badge" height="20">
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </main>
</body>
</html>
