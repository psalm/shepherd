<?php

namespace Psalm\Shepherd\Test;

use PHPUnit\Framework\TestCase;
use Psalm\Shepherd\Format;

class FormatTest extends TestCase
{
    /**
     * @dataProvider providerCompactNumber
     */
    public function testCompactNumber(int $input, string $expected) : void
    {
        $this->assertSame($expected, Format::compactNumber($input));
    }

    /** @return array<string, array{int, string}> */
    public function providerCompactNumber() : array
    {
        return [
            'zero' => [0, '0'],
            'below a thousand' => [999, '999'],
            'a thousand' => [1000, '1K'],
            'one decimal' => [1234, '1.2K'],
            'rounds inside the unit' => [1949, '1.9K'],
            'just below a million' => [999949, '999.9K'],
            'carries into millions' => [999950, '1M'],
            'millions' => [238700000, '238.7M'],
            'billions' => [2500000000, '2.5B'],
        ];
    }

    /**
     * @dataProvider providerCoveragePercent
     */
    public function testCoveragePercent(float $input, string $expected) : void
    {
        $this->assertSame($expected, Format::coveragePercent($input));
    }

    /** @return array<string, array{float, string}> */
    public function providerCoveragePercent() : array
    {
        return [
            'full' => [100.0, '100.00%'],
            'always two decimals' => [97.4, '97.40%'],
            'zero' => [0.0, '0.00%'],
            'rounds' => [99.926, '99.93%'],
            'never shows 100 below 100' => [99.996, '99.99%'],
            'just below 100' => [99.99999, '99.99%'],
            'out of range is clamped' => [100.4, '100.00%'],
            'negative is clamped' => [-1.0, '0.00%'],
        ];
    }

    /**
     * @dataProvider providerCoverageBand
     */
    public function testCoverageBand(float $input, string $expected) : void
    {
        $this->assertSame($expected, Format::coverageBand($input));
    }

    /** @return array<string, array{float, string}> */
    public function providerCoverageBand() : array
    {
        return [
            'full' => [100.0, 'good'],
            'good threshold' => [90.0, 'good'],
            'rounds up into good' => [89.996, 'good'],
            'below good' => [89.99, 'warn'],
            'warn threshold' => [75.0, 'warn'],
            'below warn' => [74.99, 'bad'],
            'zero' => [0.0, 'bad'],
        ];
    }

    public function testCoverageBarWidth() : void
    {
        $this->assertSame('97.4%', Format::coverageBarWidth(97.43));
        $this->assertSame('100.0%', Format::coverageBarWidth(100.0));
        $this->assertSame('100.0%', Format::coverageBarWidth(99.996));
        $this->assertSame('0.0%', Format::coverageBarWidth(0.0));
    }

    /**
     * @dataProvider providerRelativeTime
     */
    public function testRelativeTime(int $age, string $expected) : void
    {
        $now = 1795264200;

        $this->assertSame($expected, Format::relativeTime($now - $age, $now));
    }

    /** @return array<string, array{int, string}> */
    public function providerRelativeTime() : array
    {
        $day = 86400;

        return [
            'future' => [-30, 'just now'],
            'now' => [0, 'just now'],
            'last second of just now' => [59, 'just now'],
            'one minute' => [60, '1 min ago'],
            'last second of minutes' => [3599, '59 min ago'],
            'one hour' => [3600, '1 h ago'],
            'last second of hours' => [$day - 1, '23 h ago'],
            'one day' => [$day, '1 d ago'],
            'last second of days' => [30 * $day - 1, '29 d ago'],
            'one month' => [30 * $day, '1 mo ago'],
            'last second of months' => [365 * $day - 1, '12 mo ago'],
            'one year' => [365 * $day, '1 y ago'],
            'years' => [800 * $day, '2 y ago'],
        ];
    }

    public function testTimeElement() : void
    {
        $this->assertSame(
            '<time datetime="2026-11-21T12:16:00Z" title="2026-11-21 12:16 UTC">14 min ago</time>',
            Format::timeElement(1795263360, 1795264200)
        );
    }

    public function testSparklineWithoutPoints() : void
    {
        $this->assertSame('', Format::sparkline([]));
    }

    public function testSparklineSinglePointIsACenteredDot() : void
    {
        $svg = Format::sparkline([97.5]);

        $this->assertStringNotContainsString('<polyline', $svg);
        $this->assertStringContainsString('<circle cx="48.0" cy="12.0" r="2"/>', $svg);
        $this->assertStringContainsString('Only one report so far', $svg);
    }

    public function testSparklineFlatSeriesIsACenteredLine() : void
    {
        $svg = Format::sparkline([99.9, 99.9, 99.9]);

        $this->assertStringContainsString('<polyline points="2.0,12.0 48.0,12.0 94.0,12.0"/>', $svg);
        $this->assertStringContainsString('<circle cx="94.0" cy="12.0" r="2"/>', $svg);
        $this->assertStringContainsString('no change', $svg);
    }

    public function testSparklineRisingSeriesSpansTheHeight() : void
    {
        $svg = Format::sparkline([90.0, 91.0]);

        $this->assertStringContainsString('<polyline points="2.0,21.0 94.0,3.0"/>', $svg);
        $this->assertStringContainsString('Trend over the last 2 reports: +1.00 points', $svg);
    }

    public function testSparklineFallingSeriesReportsNegativeChange() : void
    {
        $svg = Format::sparkline([91.0, 90.5, 90.0]);

        $this->assertStringContainsString('2.0,3.0', $svg);
        $this->assertStringContainsString('Trend over the last 3 reports: -1.00 points', $svg);
    }

    public function testSparklineIsDecorativeAndThemeable() : void
    {
        $svg = Format::sparkline([1.0, 2.0]);

        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertStringNotContainsString('stroke=', $svg);
        $this->assertStringContainsString('<span class="visually-hidden">', $svg);
    }
}
