<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;
use Psalm\Shepherd\DatabaseUnavailableException;
use Psalm\Shepherd\Format;
use Psalm\Shepherd\Links;

require __DIR__ . '/../vendor/autoload.php';

try {
    $activity = Api::getRecentActivity();
} catch (DatabaseUnavailableException | PDOException $e) {
    if ($e instanceof PDOException) {
        error_log('Home page query failed - ' . $e->getMessage());
    }

    $activity = null;
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

$badge_markdown = static fn (string $repo) : string =>
    "[![Psalm type coverage](https://shepherd.dev/github/$repo/coverage.svg)](https://shepherd.dev/github/$repo)\n"
    . "[![Psalm level](https://shepherd.dev/github/$repo/level.svg)](https://shepherd.dev/github/$repo)";

/** Selectable text with an optional copy button (revealed by home.js). $template: home.js rewrites owner/repo. */
$renderSnippet = static function (string $id, string $title, string $text, bool $template = false) : void {
    ?>
                <div class="snippet">
                    <div class="snippet-head">
                        <span id="<?= $id ?>-title"><?= htmlspecialchars($title) ?></span>
                        <button type="button" class="copy-btn" data-copy="<?= $id ?>" aria-describedby="<?= $id ?>-title" hidden>Copy</button>
                    </div>
                    <pre tabindex="0" aria-labelledby="<?= $id ?>-title"><code id="<?= $id ?>"<?= $template ? ' data-tpl="' . htmlspecialchars($text) . '"' : '' ?>><?= htmlspecialchars($text) ?></code></pre>
                </div>
    <?php
};

$renderHead = require __DIR__ . '/partials/head.php';
$renderNav = require __DIR__ . '/partials/nav.php';
$renderFooter = require __DIR__ . '/partials/footer.php';

$renderHead('Shepherd', [], ['/assets/js/home.js']);
$renderNav();
?>
    <main id="main" class="container page" tabindex="-1">
        <section class="hero" aria-labelledby="hero-title">
            <h1 id="hero-title">Type coverage for PHP projects</h1>
            <p class="lead">Shepherd turns the reports that <a href="<?= htmlspecialchars(Links::PSALM) ?>">Psalm</a> sends from your CI into a type coverage history and README badges.</p>
        </section>

        <section class="repo-table" aria-labelledby="recent-title">
            <h2 id="recent-title" class="section-title">Recently active</h2>
<?php if ($activity === null) : ?>
            <p class="notice">Recent activity is unavailable right now. Please try again in a minute.</p>
<?php elseif ($activity['repositories'] === []) : ?>
            <p class="notice">No reports have been received yet.</p>
<?php else : ?>
            <p class="section-note">Latest report on each repository, most recently reported first.<?= $with_trend ? '<span class="col-trend"> Trend covers up to the last 24 reports.</span>' : '' ?></p>
            <div class="table-scroll" role="region" aria-label="Recently active repositories" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="col-repo">Repository</th>
                            <th scope="col" class="col-num">Type coverage</th>
                            <th scope="col" class="col-num"><abbr title="Psalm level, 1 is the strictest">Level</abbr></th>
    <?php if ($with_trend) : ?>
                            <th scope="col" class="col-trend">Trend</th>
    <?php endif; ?>
                            <th scope="col" class="col-num col-expr">Expressions</th>
                        </tr>
                    </thead>
                    <tbody>
    <?php foreach ($activity['repositories'] as $repository) : ?>
                        <tr>
                            <th scope="row" class="col-repo">
                                <a class="repo-name" href="/github/<?= htmlspecialchars($repository['name']) ?>"><?= htmlspecialchars($repository['name']) ?></a>
                                <span class="repo-sub">Last report <?= Format::timeElement($repository['reported_at'], $now) ?> &middot; <a href="<?= htmlspecialchars($github_url . '/' . $repository['name'] . '/commit/' . $repository['commit']) ?>"><?= htmlspecialchars(substr($repository['commit'], 0, 7)) ?></a></span>
                            </th>
                            <td class="col-num"><span class="cov band-<?= Format::coverageBand($repository['coverage']) ?>"><span class="cov-bar" aria-hidden="true"><i style="--p: <?= Format::coverageBarWidth($repository['coverage']) ?>"></i></span><span class="cov-num"><?= Format::coveragePercent($repository['coverage']) ?></span></span></td>
    <?php if ($repository['level'] === null) : ?>
                            <td class="col-num"><span title="Psalm level not reported">&ndash;</span></td>
    <?php else : ?>
                            <td class="col-num"><span class="lvl" title="Psalm level <?= $repository['level'] ?>, 1 is the strictest"><?= $repository['level'] ?></span></td>
    <?php endif; ?>
        <?php if ($with_trend) : ?>
                            <td class="col-trend"><?= Format::sparkline($repository['points']) ?></td>
        <?php endif; ?>
                            <td class="col-num col-expr" title="<?= number_format($repository['expressions']) ?>"><?= Format::compactNumber($repository['expressions']) ?></td>
                        </tr>
    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
<?php endif; ?>
        </section>

        <section class="start" aria-labelledby="start-title">
            <div>
                <h2 id="start-title" class="section-title">Add your project</h2>
                <ol class="steps">
                    <li>Run <code>vendor/bin/psalm --shepherd</code> in CI on your default branch. <a href="<?= htmlspecialchars(Links::SHEPHERD_DOCS) ?>">Read the guide</a>.</li>
                    <li>Open <code id="page-url" data-tpl="shepherd.dev/github/owner/repo">shepherd.dev/github/owner/repo</code> once the first report has arrived.</li>
                    <li>Add the badges to your README.</li>
                </ol>
            </div>
            <div>
<?php $renderSnippet('cmd', 'CI command', 'vendor/bin/psalm --shepherd'); ?>
                <div class="field needs-js">
                    <label for="repo">Your repository</label>
                    <input id="repo" type="text" placeholder="owner/repo" autocomplete="off" spellcheck="false">
                </div>
<?php $renderSnippet('badge', 'README badges (Markdown)', $badge_markdown('owner/repo'), true); ?>
            </div>
        </section>
        <span class="visually-hidden" role="status" id="copy-status"></span>
    </main>
<?php $renderFooter(); ?>
</body>
</html>
