<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;
use Psalm\Shepherd\Format;

require __DIR__ . '/../vendor/autoload.php';

const HISTORY_PAGE_SIZE = 100;

$repository = Api::extractRepository($_SERVER['QUERY_STRING'] ?? '');

$github_url = Config::getInstance()->getGithubUrl();
$repository_url = $github_url . '/' . $repository;

// The name is plain text; the GitHub mark next to it links to the repository.
$renderRepoTitle = static function (string $repository, string $repository_url) : void {
    ?>
        <h1 class="repo-title">
            <?= htmlspecialchars($repository) ?>
            <a class="repo-github" href="<?= htmlspecialchars($repository_url) ?>" title="Open on GitHub" aria-label="<?= htmlspecialchars($repository) ?> on GitHub"><svg viewBox="0 0 16 16" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M6.766 11.328c-2.063-.25-3.516-1.734-3.516-3.656 0-.781.281-1.625.75-2.188-.203-.515-.172-1.609.063-2.062.625-.078 1.468.25 1.968.703.594-.187 1.219-.281 1.985-.281.765 0 1.39.094 1.953.265.484-.437 1.344-.765 1.969-.687.218.422.25 1.515.046 2.047.5.593.766 1.39.766 2.203 0 1.922-1.453 3.375-3.547 3.64.531.344.89 1.094.89 1.954v1.625c0 .468.391.734.86.547C13.781 14.359 16 11.53 16 8.03 16 3.61 12.406 0 7.984 0 3.563 0 0 3.61 0 8.031a7.88 7.88 0 0 0 5.172 7.422c.422.156.828-.125.828-.547v-1.25c-.219.094-.5.156-.75.156-1.031 0-1.64-.562-2.078-1.609-.172-.422-.36-.672-.719-.719-.187-.015-.25-.093-.25-.187 0-.188.313-.328.625-.328.453 0 .844.281 1.25.86.313.452.64.655 1.031.655s.641-.14 1-.5c.266-.265.47-.5.657-.656"/></svg></a>
        </h1>
    <?php
};

// An unrecognised repository name gets the same 404 notice as a repository without reports.
$total_count = $repository === null ? 0 : Api::getHistoryCount($repository);

$renderHead = require __DIR__ . '/partials/head.php';
$renderNav = require __DIR__ . '/partials/nav.php';
$renderFooter = require __DIR__ . '/partials/footer.php';
$page_title = 'Shepherd - ' . ($repository ?? 'unknown repository');

if ($repository === null || $total_count === 0) {
    http_response_code(404);
    $renderHead($page_title);
    $renderNav();
    ?>
    <main id="main" class="container page" tabindex="-1">
        <?php if ($repository !== null) : ?>
            <?php $renderRepoTitle($repository, $repository_url); ?>
        <?php endif; ?>
        <div class="notice">
            <h2>No type coverage reports yet</h2>
            <p>Shepherd has not recorded any Psalm reports for <?= $repository === null ? 'this repository' : htmlspecialchars($repository) ?>.
                Run Psalm in CI with the <code>--shepherd</code> option on your default branch to send them.</p>
        </div>
    </main>
    <?php $renderFooter(); ?>
</body>
</html>
    <?php
    return;
}

$total_pages = max(1, (int) ceil($total_count / HISTORY_PAGE_SIZE));
$requested_page = $_GET['page'] ?? 1;
$page = is_string($requested_page) ? filter_var($requested_page, FILTER_VALIDATE_INT) : false;
$page = max(1, min($page === false ? 1 : $page, $total_pages));

$rows = Api::getHistoryPage($repository, HISTORY_PAGE_SIZE, ($page - 1) * HISTORY_PAGE_SIZE);

$has_chart = $total_count > 1;

// Title and description of the page, from the newest report (the first row on page 1, else one extra row).
$latest = $page === 1 ? ($rows[0] ?? null) : (Api::getHistoryPage($repository, 1, 0)[0] ?? null);
$level = Api::getLevel($repository);
$page_title = $repository . ($latest !== null ? ': ' . Format::coveragePercent($latest['coverage']) . ' type coverage' : '')
    . ($level !== null ? ', Psalm level ' . $level : '') . ' · Shepherd';
$page_description = $latest !== null
    ? Format::compactNumber($latest['expressions']) . ' expressions analysed across ' . number_format($total_count)
        . ($total_count === 1 ? ' report.' : ' reports.')
    : '';

if ($has_chart) {
    // The chart's data request starts as soon as the headers arrive instead of after history.js has run.
    // Same URL as data-src below; the version is the report count, so a new report busts both caches.
    header('Link: </github/' . $repository . '/history.json?v=' . $total_count . '>; rel=preload; as=fetch; crossorigin; fetchpriority=low');
}

// Always carries ?page=N: history.js uses it to scroll back to the table after it rewrites the hash to the chart range.
$pageUrl = static function (int $n) use ($repository) : string {
    return '/github/' . $repository . '?page=' . $n . '#commits';
};

/**
 * Pager entries: first, previous, a window of numbers around the current page with gaps, next, last.
 *
 * @return list<array{kind: string, page: int, text: string, label: string, rel: string, current: bool}>
 */
$pagerItems = static function (int $current, int $last) : array {
    $item = static function (
        string $kind,
        int $page,
        string $text,
        string $label = '',
        string $rel = '',
        bool $is_current = false
    ) : array {
        return ['kind' => $kind, 'page' => $page, 'text' => $text, 'label' => $label, 'rel' => $rel, 'current' => $is_current];
    };

    $has_prev = $current > 1;
    $has_next = $current < $last;
    $prev_kind = $has_prev ? 'link' : 'off';
    $next_kind = $has_next ? 'link' : 'off';

    $items = [
        $item($prev_kind, 1, '&laquo;', 'First page'),
        $item($prev_kind, $current - 1, '&lsaquo;', 'Previous page', 'prev'),
    ];

    $previous = 0;

    foreach (range(1, $last) as $n) {
        if ($n !== 1 && $n !== $last && abs($n - $current) > 2) {
            continue;
        }

        if ($n - $previous > 1) {
            $items[] = $item('gap', 0, '&hellip;');
        }

        $items[] = $item('link', $n, (string) $n, 'Page ' . $n, '', $n === $current);
        $previous = $n;
    }

    $items[] = $item($next_kind, $current + 1, '&rsaquo;', 'Next page', 'next');
    $items[] = $item($next_kind, $last, '&raquo;', 'Last page');

    return $items;
};

$first_row = ($page - 1) * HISTORY_PAGE_SIZE + 1;
$pager_info = 'Showing ' . number_format($first_row) . '&ndash;' . number_format($first_row + count($rows) - 1)
    . ' of ' . number_format($total_count);

$renderPager = static function (string $position) use ($page, $total_pages, $pager_info, $pageUrl, $pagerItems) : void {
    ?>
            <div class="pager-wrap pager-wrap-<?= $position ?>">
                <p class="pager-info"><?= $pager_info ?></p>
    <?php if ($total_pages > 1) : ?>
                <nav class="pager" aria-label="Pagination (<?= $position ?>)">
                    <ul>
                    <?php foreach ($pagerItems($page, $total_pages) as $item) : ?>
                        <?php if ($item['kind'] === 'gap') : ?>
                        <li><span class="pager-gap" aria-hidden="true"><?= $item['text'] ?></span></li>
                        <?php elseif ($item['kind'] === 'off') : ?>
                        <li><span class="pager-off" aria-hidden="true"><?= $item['text'] ?></span></li>
                        <?php else : ?>
                        <li><a href="<?= htmlspecialchars($pageUrl($item['page'])) ?>" data-page="<?= $item['page'] ?>"<?= $item['rel'] !== '' ? ' rel="' . $item['rel'] . '"' : '' ?><?= $item['current'] ? ' aria-current="page"' : '' ?> aria-label="<?= $item['label'] ?>"><?= $item['text'] ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </ul>
                </nav>
    <?php endif; ?>
            </div>
    <?php
};

$renderHead(
    $page_title,
    $has_chart
        ? ['/assets/vendor/uplot/uPlot.min.css']
        : [],
    $has_chart
        ? ['/assets/vendor/uplot/uPlot.iife.min.js', '/assets/js/history.js']
        : [],
    ['ibm-plex-sans-400'],
    $page_description
);
$renderNav();
?>
    <main id="main" class="container page" tabindex="-1">
        <?php $renderRepoTitle($repository, $repository_url); ?>

        <p class="badges">
            <img src="/github/<?= htmlspecialchars($repository) ?>/coverage.svg" alt="Type coverage badge" height="20">
            <img src="/github/<?= htmlspecialchars($repository) ?>/level.svg" alt="Psalm level badge" height="20">
        </p>

        <p class="page-note">
            Type coverage is the share of analysed expressions whose type Psalm could infer as something other than
            <code>mixed</code>. Run <code>psalm --stats</code> to see it per file.
        </p>

<?php if ($has_chart) : ?>
        <section
            id="chart-card"
            class="chart-card needs-js"
            aria-labelledby="chart-heading"
            data-src="/github/<?= htmlspecialchars($repository) ?>/history.json?v=<?= $total_count ?>"
            data-commit-url="<?= htmlspecialchars($repository_url) ?>/commit/"
        >
            <div class="chart-head">
                <h2 id="chart-heading" class="visually-hidden">Type coverage history chart</h2>
                <p class="range-label" id="range-label">Selected range: <b>&hellip;</b> (UTC)</p>
                <div class="chart-controls">
                    <div class="presets" role="group" aria-label="Range presets">
                        <button type="button" data-months="1" aria-pressed="false" disabled>1M</button>
                        <button type="button" data-months="3" aria-pressed="false" disabled>3M</button>
                        <button type="button" data-months="6" aria-pressed="false" disabled>6M</button>
                        <button type="button" data-months="12" aria-pressed="false" disabled>1Y</button>
                        <button type="button" data-months="0" aria-pressed="false" disabled>All</button>
                    </div>
                    <button type="button" class="reset-btn" data-reset disabled>Reset</button>
                </div>
            </div>
            <div class="legend" role="group" aria-label="Series">
                <button type="button" class="pill pill-cov" data-series="1" aria-pressed="true" disabled>
                    <span class="sw" aria-hidden="true"></span><span class="lbl">Type Coverage</span><span class="v" data-latest="1"></span>
                </button>
                <button type="button" class="pill pill-expr" data-series="2" aria-pressed="true" disabled>
                    <span class="sw" aria-hidden="true"></span><span class="lbl">Analysed Expressions</span><span class="v" data-latest="2"></span>
                </button>
            </div>
            <div class="chart-body" id="chart-body">
                <div class="chart-wrap">
                    <div class="chart-main" id="chart-main" role="img" aria-label="Type coverage and analysed expressions over time"></div>
                    <div class="chart-tip" id="chart-tip" hidden></div>
                </div>
                <div class="chart-nav" id="chart-nav"></div>
                <p class="chart-status" id="chart-status" role="status">Loading chart&hellip;</p>
            </div>
            <p class="chart-hint" id="chart-hint">
                <span class="mouse-only">Drag the lower navigator or its handles to change the range, drag on the chart to zoom. Click a point to open its commit. Reset restores the default range.</span>
                <span class="touch-only">Drag the lower navigator or its handles to change the range. Tap a point for details.</span>
            </p>
        </section>
        <script>
            // The card is rendered at full size so loading does not shift the page. history.js reports its own
            // failures; this covers history.js itself failing to load or parse, which would leave "Loading chart".
            window.addEventListener('error', function (event) {
                var card = document.getElementById('chart-card');
                var src = (event.target && event.target.src) || event.filename || '';
                if (card.dataset.started || src.indexOf('/assets/js/history.js') === -1) {
                    return;
                }
                card.classList.add('is-failed');
                document.getElementById('chart-status').textContent = 'The chart could not be loaded. The table below has the full history.';
            }, true);
        </script>
<?php elseif ($total_count === 1) : ?>
        <p class="notice">Charts appear once there are at least two reports.</p>
<?php endif; ?>

        <section id="commits" class="commits" aria-labelledby="commits-heading">
            <h2 id="commits-heading">Reports (<b><?= number_format($total_count) ?></b>)</h2>

<?php $renderPager('top'); ?>

            <div class="table-scroll" role="region" aria-label="Reports table" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="col-date">Date (UTC)</th>
                            <th scope="col">Commit</th>
                            <th scope="col" class="col-num">Type Coverage</th>
                            <th scope="col" class="col-num" title="Percentage points since the previous report">Change</th>
                            <th scope="col" class="col-num">Analysed Expressions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php $timestamp = $row['created_at']; ?>
                        <tr>
                            <td class="col-date"><time datetime="<?= gmdate('Y-m-d\TH:i:s\Z', $timestamp) ?>"><?= gmdate('Y-m-d H:i:s', $timestamp) ?></time></td>
                            <td class="col-sha"><a href="<?= htmlspecialchars($repository_url . '/commit/' . $row['commit']) ?>"><?= htmlspecialchars(substr($row['commit'], 0, 7)) ?></a></td>
                            <td class="col-num"><?= sprintf('%.3f', $row['coverage']) ?>%</td>
                            <td class="col-num"><?= Format::coverageChange($row['coverage_change']) ?></td>
                            <td class="col-num" title="<?= number_format($row['expressions']) ?>"><?= Format::compactNumber($row['expressions']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

<?php $renderPager('bottom'); ?>
        </section>
        <script>
            // Pager links carry the chart range in the hash instead of #commits, so scroll here, before the first
            // paint: the page (and its view transition) lands on the table. Reload and back/forward keep their position.
            (function () {
                var navigation = window.performance.getEntriesByType ? window.performance.getEntriesByType('navigation')[0] : null;
                if (new URLSearchParams(window.location.search).has('page') && navigation && navigation.type === 'navigate') {
                    document.getElementById('commits').scrollIntoView();
                }
            }());
        </script>
    </main>
<?php $renderFooter(); ?>
</body>
</html>
