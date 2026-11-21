<?php

use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;

require __DIR__ . '/../vendor/autoload.php';

const HISTORY_PAGE_SIZE = 100;

$repository = Api::extractRepository($_SERVER['QUERY_STRING'] ?? '');

if ($repository === null) {
    throw new UnexpectedValueException('Repository format not recognised');
}

$github_url = Config::getInstance()->getGithubUrl();
$repository_url = $github_url . '/' . $repository;

$total_count = Api::getHistoryCount($repository);

/** @psalm-pure */
$formatCompact = static function (int $x) : string {
    if ($x < 1000) {
        return (string) $x;
    }

    $units = ['K', 'M', 'B', 'T'];
    $value = (float) $x / 1000.0;
    $unit = 0;

    while ($unit < 3 && round($value, 1) >= 1000) {
        $value /= 1000.0;
        ++$unit;
    }

    return preg_replace('/\.0$/', '', sprintf('%.1f', $value)) . $units[$unit];
};

$renderHead = require __DIR__ . '/partials/head.php';
$renderNav = require __DIR__ . '/partials/nav.php';
$page_title = 'Shepherd - ' . $repository;

if ($total_count === 0) {
    http_response_code(404);
    $renderHead($page_title);
    $renderNav();
    ?>
    <main id="main" class="container page" tabindex="-1">
        <h1 class="repo-title"><a href="<?= htmlspecialchars($repository_url) ?>"><?= htmlspecialchars($repository) ?></a></h1>
        <div class="notice">
            <h2>No type coverage reports yet</h2>
            <p>Shepherd has not recorded any Psalm reports for <?= htmlspecialchars($repository) ?>.
                Run Psalm in CI with the <code>--shepherd</code> option on your default branch to send them.</p>
        </div>
    </main>
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
                        <li><span class="pager-off" aria-disabled="true"><?= $item['text'] ?></span></li>
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
    $has_chart ? ['/assets/vendor/uplot/uPlot.min.css'] : [],
    $has_chart ? ['/assets/vendor/uplot/uPlot.iife.min.js', '/assets/js/history.js'] : []
);
$renderNav();
?>
    <main id="main" class="container page" tabindex="-1">
        <h1 class="repo-title"><a href="<?= htmlspecialchars($repository_url) ?>"><?= htmlspecialchars($repository) ?></a></h1>

        <p class="badges">
            <img src="/github/<?= htmlspecialchars($repository) ?>/coverage.svg" alt="Type coverage badge" height="20">
            <img src="/github/<?= htmlspecialchars($repository) ?>/level.svg" alt="Psalm level badge" height="20">
        </p>

<?php if ($has_chart) : ?>
        <section
            id="chart-card"
            class="chart-card js-only"
            aria-labelledby="chart-heading"
            data-src="/github/<?= htmlspecialchars($repository) ?>/history.json"
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
            <p class="chart-status" id="chart-status" role="status">Loading chart&hellip;</p>
            <div class="chart-body" id="chart-body" hidden>
                <div class="chart-wrap">
                    <div class="chart-main" id="chart-main" role="img" aria-label="Type coverage and analysed expressions over time"></div>
                    <div class="chart-tip" id="chart-tip" hidden></div>
                </div>
                <div class="chart-nav" id="chart-nav"></div>
            </div>
            <p class="chart-hint" id="chart-hint" hidden>
                <span class="mouse-only">Drag the lower navigator or its handles to change the range, drag on the chart to zoom. Click a point to open its commit. Reset restores the default range.</span>
                <span class="touch-only">Drag the lower navigator or its handles to change the range. Tap a point for details.</span>
            </p>
        </section>
<?php elseif ($total_count === 1) : ?>
        <p class="notice">Charts appear once there are at least two reports.</p>
<?php endif; ?>

        <section id="commits" class="commits" aria-labelledby="commits-heading">
            <h2 id="commits-heading">Commits (<b><?= number_format($total_count) ?></b>)</h2>

<?php $renderPager('top'); ?>

            <div class="table-scroll" role="region" aria-label="Commits table" tabindex="0">
                <table>
                    <thead>
                        <tr>
                            <th scope="col" class="col-date">Date (UTC)</th>
                            <th scope="col">Commit</th>
                            <th scope="col" class="col-num">Type Coverage</th>
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
                            <td class="col-num" title="<?= number_format($row['total']) ?>"><?= $formatCompact($row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

<?php $renderPager('bottom'); ?>
        </section>
    </main>
</body>
</html>
