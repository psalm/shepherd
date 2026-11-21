<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;
use Psalm\Shepherd\DatabaseUnavailableException;
use Psalm\Shepherd\Format;
use Psalm\Shepherd\Links;

require __DIR__ . '/../vendor/autoload.php';

try {
    $activity = Api::getRecentActivity();
    $boards = Api::getBoards();
} catch (DatabaseUnavailableException | PDOException $e) {
    if ($e instanceof PDOException) {
        error_log('Home page query failed - ' . $e->getMessage());
    }

    $activity = null;
    $boards = null;
}

// The degraded page must not be cached by a shared cache for minutes after the database is back.
header(
    $activity === null
        ? 'Cache-Control: no-store'
        : 'Cache-Control: public, max-age=60, stale-while-revalidate=300'
);

$now = time();
$github_url = Config::getInstance()->getGithubUrl();
$with_trend = $activity !== null && $activity['sparklines'];

$static_note = 'Repositories with at least 1,000 analysed expressions and a report in the last 90 days.';

/** Tab id => [label, note, repositories]. The tab order is the display order; the first one is selected. */
$tabs = [
    'recent' => [
        'Recently active',
        'Latest report on each repository, most recently reported first. The change next to the coverage is '
            . 'against that repository\'s previous report.',
        $activity['repositories'] ?? [],
    ],
    'level1' => ['Level 1 club', 'Psalm level 1 is the strictest. ' . $static_note, $boards['level1'] ?? []],
    'coverage' => ['Highest coverage', $static_note, $boards['coverage'] ?? []],
    'largest' => ['Largest codebases', 'By analysed expressions. ' . $static_note, $boards['largest'] ?? []],
];

$install_commands = ['composer require --dev vimeo/psalm', 'vendor/bin/psalm --init', 'vendor/bin/psalm --shepherd'];

$features = [
    [
        'Security analysis',
        'Follows user input through your code to find SQL injection, XSS and other vulnerabilities before attackers do.',
        'https://psalm.dev/docs/security_analysis/',
        'Read the guide',
        '<path d="M18 3l12 4v9c0 8-5 14-12 17C11 30 6 24 6 16V7z"/><path d="M12 15h6m0 0l-3-3m3 3l-3 3M22 15h2"/>',
    ],
    [
        'Plugins',
        'Symfony, Laravel, Doctrine, PHPUnit and Mockery plugins ship with Psalm. Find more, or write your own.',
        Links::PLUGINS,
        'Browse plugins',
        '<path d="M14 6h8v5a3 3 0 1 1 0 6v3h5v8H9v-8h5v-3a3 3 0 1 1 0-6z" transform="translate(0 1)"/>',
    ],
    [
        'Automatic fixes',
        'Psalm can also fix bugs automatically, so you can improve your code without breaking a sweat.',
        'https://psalm.dev/docs/manipulating_code/fixing/',
        'See how fixing works',
        '<path d="M6 30L24 12"/><path d="M22 6l1.5 3.5L27 11l-3.5 1.5L22 16l-1.5-3.5L17 11l3.5-1.5zM29 19l1 2.3 2.3 1-2.3 1-1 2.3-1-2.3-2.3-1 2.3-1z"/>',
    ],
    [
        'Editor support',
        'Run Psalm as a language server and see issues in your editor as you type.',
        'https://psalm.dev/docs/running_psalm/language_server/',
        'Set up your editor',
        '<rect x="4" y="6" width="28" height="20" rx="2"/><path d="M13 31h10M18 26v5M12 13l-3 3 3 3M24 13l3 3-3 3M19.5 12l-3 8"/>',
    ],
];

$renderHead = require __DIR__ . '/partials/head.php';
$renderNav = require __DIR__ . '/partials/nav.php';
$renderFooter = require __DIR__ . '/partials/footer.php';

$renderHead(
    'Shepherd · Type coverage for PHP, by Psalm',
    [],
    ['/assets/js/copy.js', '/assets/js/home.js'],
    ['ibm-plex-sans-400', 'ibm-plex-mono-500'],
    'Type coverage and Psalm level for every commit of your PHP project, from the Psalm run you already have in CI. '
        . 'Free for public GitHub projects.'
);
$renderNav();
?>
    <main id="main" class="container page" tabindex="-1">
        <div class="hero-grid">
            <section class="hero" aria-labelledby="hero-title">
                <p class="kicker">Psalm type coverage</p>
                <h1 id="hero-title">Watch your PHP get <em>stricter</em>.</h1>
                <p class="lead">Type coverage and error level for every commit, from the Psalm run you already have in CI. Free for public GitHub projects.</p>
                <div class="cta-row">
                    <a class="btn btn-psalm" href="<?= htmlspecialchars(Links::SHEPHERD_DOCS) ?>">Add your project</a>
                    <a class="btn btn-alt" href="<?= htmlspecialchars(Links::PSALM_INSTALL) ?>">New to Psalm? Try it in 2 minutes</a>
                </div>
            </section>
            <div class="term" role="group" aria-labelledby="term-title">
                <div class="term-bar">
                    <span id="term-title">Install in three commands</span>
                    <button type="button" class="copy-btn" data-copy="install-commands" aria-describedby="term-title" hidden>Copy</button>
                </div>
                <pre class="term-commands" tabindex="0"><code id="install-commands"><?= implode("\n", array_map(
                    static fn (string $command) : string => '<span class="term-command">' . htmlspecialchars($command) . '</span>',
                    $install_commands
                )) ?></code></pre>
                <p class="term-line">No errors found!</p>
                <p class="term-line term-ok">Results sent to Shepherd (shepherd.dev)</p>
            </div>
        </div>

        <section class="board" aria-labelledby="board-title">
            <h2 id="board-title" class="section-title">Projects reporting to Shepherd</h2>
<?php if ($activity === null) : ?>
            <p class="notice">Recent activity is unavailable right now. Please try again in a minute.</p>
<?php elseif ($activity['repositories'] === []) : ?>
            <p class="notice">No reports have been received yet.</p>
<?php else : ?>
            <div class="tablist" role="tablist" aria-label="Project leaderboards">
    <?php foreach ($tabs as $id => [$label]) : ?>
                <button type="button" role="tab" id="tab-<?= $id ?>" aria-controls="panel-<?= $id ?>" aria-selected="<?= $id === 'recent' ? 'true' : 'false' ?>"<?= $id === 'recent' ? '' : ' tabindex="-1"' ?>><?= htmlspecialchars($label) ?></button>
    <?php endforeach; ?>
            </div>
            <div class="board-panels">
    <?php foreach ($tabs as $id => [$label, $note, $repositories]) : ?>
                <section class="board-panel repo-table<?= $id === 'recent' ? ' is-active' : '' ?>" id="panel-<?= $id ?>" data-tab="<?= $id ?>" aria-labelledby="heading-<?= $id ?>"<?= $id === 'recent' ? ' data-live-src="/recent"' : '' ?>>
                    <h3 id="heading-<?= $id ?>" class="board-heading"><?= htmlspecialchars($label) ?></h3>
                    <p class="section-note"><?= htmlspecialchars($note) ?></p>
        <?php if ($repositories === []) : ?>
                    <p class="notice">No repositories match yet.</p>
        <?php else : ?>
                    <table class="board-table">
                        <colgroup>
                            <col class="c-repo">
                            <col class="c-cov">
                            <col class="c-lvl">
            <?php if ($with_trend) : ?>
                            <col class="c-trend col-trend">
            <?php endif; ?>
                            <col class="c-expr col-expr">
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="col-repo">Repository</th>
                                <th scope="col" class="col-num">Type coverage <span class="visually-hidden">and change since the previous report</span></th>
                                <th scope="col" class="col-num"><abbr title="Psalm level, 1 is the strictest">Level</abbr></th>
            <?php if ($with_trend) : ?>
                                <th scope="col" class="col-trend">Trend</th>
            <?php endif; ?>
                                <th scope="col" class="col-num col-expr">Expressions</th>
                            </tr>
                        </thead>
                        <?= Format::repositoryRows($repositories, $now, $github_url, $with_trend) ?>

                    </table>
        <?php endif; ?>
                </section>
    <?php endforeach; ?>
            </div>
<?php endif; ?>
        </section>

        <section class="board" aria-labelledby="features-title">
            <h2 id="features-title" class="section-title">Shepherd is one part of Psalm</h2>
            <p class="section-note">Psalm is a free and open-source static analysis tool for PHP.</p>
            <div class="features">
<?php foreach ($features as [$title, $text, $url, $link, $icon]) : ?>
                <div class="feature">
                    <svg viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                    <h3><?= htmlspecialchars($title) ?></h3>
                    <p><?= htmlspecialchars($text) ?></p>
                    <a href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($link) ?></a>
                </div>
<?php endforeach; ?>
            </div>
        </section>
    </main>
<?php $renderFooter(); ?>
</body>
</html>
