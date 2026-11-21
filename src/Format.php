<?php

namespace Psalm\Shepherd;

use function abs;
use function count;
use function explode;
use function gmdate;
use function htmlspecialchars;
use function implode;
use function intdiv;
use function max;
use function min;
use function number_format;
use function round;
use function sprintf;
use function substr;

/**
 * Pure presentation helpers for the home page and the history page.
 * Every method returns a string that is already safe to echo.
 *
 * @psalm-import-type RecentRepository from Api
 */
final class Format
{
    private const MINUTE = 60;
    private const HOUR = 3600;
    private const DAY = 86400;

    /**
     * 999 -> "999", 1234 -> "1.2K", 235000 -> "235.0K", 999950 -> "1.0M" (the rounding is carried into the next
     * unit). Always one decimal, so values in a column line up and differ visibly.
     *
     * @psalm-pure
     */
    public static function compactNumber(int $x) : string
    {
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

        return sprintf('%.1f', $value) . $units[$unit];
    }

    /**
     * Always two decimals: "99.93%", "100.00%". Anything below 100 never shows as "100.00%",
     * because a full 100 is a claim ("every expression is typed") that rounding must not make.
     *
     * @psalm-pure
     */
    public static function coveragePercent(float $coverage) : string
    {
        return number_format(self::displayCoverage($coverage), 2, '.', '') . '%';
    }

    /**
     * Status colour bucket of a coverage, judged on the displayed value: >= 90 good, >= 75 warn, else bad.
     *
     * @return 'good'|'warn'|'bad'
     * @psalm-pure
     */
    public static function coverageBand(float $coverage) : string
    {
        $shown = self::displayCoverage($coverage);

        return $shown >= 90.0 ? 'good' : ($shown >= 75.0 ? 'warn' : 'bad');
    }

    /**
     * Bar fill as a CSS percentage, one decimal ("97.4%").
     *
     * @psalm-pure
     */
    public static function coverageBarWidth(float $coverage) : string
    {
        return number_format(self::displayCoverage($coverage), 1, '.', '') . '%';
    }

    /**
     * "just now", "14 min ago", "4 h ago", "6 d ago", "2 mo ago", "1 y ago" (months are 30 days, years 365).
     * Timestamps in the future (clock skew between MySQL and PHP) count as "just now".
     *
     * @psalm-pure
     */
    public static function relativeTime(int $timestamp, int $now) : string
    {
        $age = $now - $timestamp;

        if ($age < self::MINUTE) {
            return 'just now';
        }

        if ($age < self::HOUR) {
            return intdiv($age, self::MINUTE) . ' min ago';
        }

        if ($age < self::DAY) {
            return intdiv($age, self::HOUR) . ' h ago';
        }

        if ($age < 30 * self::DAY) {
            return intdiv($age, self::DAY) . ' d ago';
        }

        if ($age < 365 * self::DAY) {
            return intdiv($age, 30 * self::DAY) . ' mo ago';
        }

        return intdiv($age, 365 * self::DAY) . ' y ago';
    }

    /**
     * <time> with the machine-readable instant and the exact UTC time as tooltip.
     *
     * @psalm-pure
     */
    public static function timeElement(int $timestamp, int $now) : string
    {
        return '<time datetime="' . gmdate('Y-m-d\TH:i:s\Z', $timestamp) . '" title="'
            . gmdate('Y-m-d H:i', $timestamp) . ' UTC">'
            . htmlspecialchars(self::relativeTime($timestamp, $now)) . '</time>';
    }

    /**
     * Inline SVG sparkline for values ordered oldest to newest, plus visually hidden text with the change.
     * The line is centred on the middle of the data and spans at least one value unit, so a flat series is a
     * centred line and a wiggle of a few hundredths is not drawn as a cliff. One value is a centred dot.
     * Colours come from currentColor, so the caller's CSS decides (and dark mode works).
     *
     * @param list<float> $values
     * @psalm-pure
     */
    public static function sparkline(array $values, int $width = 96, int $height = 24) : string
    {
        if ($values === []) {
            return '';
        }

        $count = count($values);
        $w = (float) $width;
        $h = (float) $height;
        $pad_x = 2.0;
        $pad_y = 3.0;
        $min = min($values);
        $max = max($values);
        $span = max(1.0, $max - $min);
        $low = ($max + $min) / 2.0 - $span / 2.0;

        $points = [];

        foreach ($values as $i => $value) {
            $x = $count === 1 ? $w / 2.0 : $pad_x + (float) $i * ($w - 2.0 * $pad_x) / (float) ($count - 1);
            $y = $h - $pad_y - ($value - $low) / $span * ($h - 2.0 * $pad_y);
            $points[] = sprintf('%.1f,%.1f', $x, $y);
        }

        $last = explode(',', $points[$count - 1]);
        $line = $count > 1 ? '<polyline points="' . implode(' ', $points) . '"/>' : '';
        $delta = round($values[$count - 1] - $values[0], 2);

        if ($count === 1) {
            $summary = 'Only one report so far';
        } elseif ($delta === 0.0) {
            $summary = 'Trend over the last ' . $count . ' reports: no change';
        } else {
            $summary = 'Trend over the last ' . $count . ' reports: ' . ($delta > 0 ? '+' : '-')
                . number_format(abs($delta), 2, '.', '') . ' points';
        }

        return sprintf(
            '<svg class="spark" width="%d" height="%d" viewBox="0 0 %d %d" aria-hidden="true" focusable="false">'
            . '%s<circle cx="%s" cy="%s" r="2"/></svg><span class="visually-hidden">%s</span>',
            $width,
            $height,
            $width,
            $height,
            $line,
            $last[0],
            $last[1],
            $summary
        );
    }

    /**
     * Coverage change in percentage points with three decimals, as in the history table: "+0.012" (class
     * change-up), "-0.340" (change-down), "0.000". Null (no earlier report) is a dash.
     *
     * @psalm-pure
     */
    public static function coverageChange(?float $change) : string
    {
        if ($change === null) {
            return '<span class="change" title="First report">&ndash;</span>';
        }

        $rounded = round($change, 3);

        if ($rounded === 0.0) {
            return '<span class="change">0.000</span>';
        }

        return sprintf(
            '<span class="change %s">%s%s</span>',
            $rounded > 0 ? 'change-up' : 'change-down',
            $rounded > 0 ? '+' : '-',
            number_format(abs($rounded), 3, '.', '')
        );
    }

    /**
     * Coverage change since the previous report for the home page tables, two decimals: "+0.36" (class up),
     * "−0.12" (class down, with a real minus sign), "no change" once it rounds to zero. Null (no earlier report)
     * is a dash.
     *
     * @psalm-pure
     */
    public static function coverageDelta(?float $change) : string
    {
        if ($change === null) {
            return '<span class="chg flat" title="First report">&ndash;</span>';
        }

        $rounded = round($change, 2);

        if ($rounded === 0.0) {
            return '<span class="chg flat">no change</span>';
        }

        return sprintf(
            '<span class="chg %s">%s%s</span>',
            $rounded > 0 ? 'up' : 'down',
            $rounded > 0 ? '+' : '&minus;',
            number_format(abs($rounded), 2, '.', '')
        );
    }

    /**
     * One row of a home page leaderboard: repository with its last report, coverage with the change since the
     * previous report, level, trend (only with $with_trend) and expressions. Used for every tab and for the
     * fragment that refreshes "Recently active", so both always look the same.
     *
     * @param RecentRepository $repository
     * @psalm-pure
     */
    public static function repositoryRow(
        array $repository,
        int $now,
        string $github_url,
        bool $with_trend
    ) : string {
        $name = htmlspecialchars($repository['name']);
        $commit = $repository['commit'];
        $level = $repository['level'];

        return '<tr data-repo="' . $name . '" data-sha="' . htmlspecialchars($commit) . '">'
            . '<th scope="row" class="col-repo" title="' . $name . '">'
            . '<a class="repo-name" href="/github/' . $name . '">' . $name . '</a>'
            . '<span class="repo-sub">Last report ' . self::timeElement($repository['reported_at'], $now)
            . ' &middot; <a href="'
            . htmlspecialchars($github_url . '/' . $repository['name'] . '/commit/' . $commit) . '">'
            . htmlspecialchars(substr($commit, 0, 7)) . '</a></span></th>'
            . '<td class="col-num"><span class="cov-line"><span class="cov band-'
            . self::coverageBand($repository['coverage'])
            . '"><span class="cov-bar" aria-hidden="true"><i style="--p: '
            . self::coverageBarWidth($repository['coverage']) . '"></i></span><span class="cov-num">'
            . self::coveragePercent($repository['coverage']) . '</span></span>'
            . self::coverageDelta($repository['change']) . '</span></td>'
            . '<td class="col-num">' . ($level === null
                ? '<span title="Psalm level not reported">&ndash;</span>'
                : '<span class="lvl" title="Psalm level ' . $level . ', 1 is the strictest">' . $level . '</span>')
            . '</td>'
            . ($with_trend ? '<td class="col-trend">' . self::sparkline($repository['points']) . '</td>' : '')
            . '<td class="col-num col-expr" title="' . number_format($repository['expressions']) . '">'
            . self::compactNumber($repository['expressions']) . '</td></tr>';
    }

    /**
     * The <tbody> of a home page leaderboard.
     *
     * @param list<RecentRepository> $repositories
     * @psalm-pure
     */
    public static function repositoryRows(
        array $repositories,
        int $now,
        string $github_url,
        bool $with_trend
    ) : string {
        $rows = '';

        foreach ($repositories as $repository) {
            $rows .= "\n" . self::repositoryRow($repository, $now, $github_url, $with_trend);
        }

        return '<tbody>' . $rows . "\n</tbody>";
    }

    /** @psalm-pure */
    private static function displayCoverage(float $coverage) : float
    {
        $shown = round(max(0.0, min(100.0, $coverage)), 2);

        return $shown >= 100.0 && $coverage < 100.0 ? 99.99 : $shown;
    }
}
