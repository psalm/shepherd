<?php
namespace Psalm\Tests;

use PHPUnit\Framework\TestCase;

class GithubDiffLineTest extends TestCase
{
    /**
     * @dataProvider providerDiffLines
     */
    public function testValidCode(
        int $line_from,
        string $file_name,
        ?int $expected
    ) {
        $this->assertSame(
            \Psalm\Spirit\DiffLineFinder::getGitHubPositionFromDiff(
                $line_from,
                $file_name,
                file_get_contents(__DIR__ . '/test.diff')
            ),
            $expected
        );
    }

    public function providerDiffLines() : array
    {
        return [
            [
                1495,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                null
            ],
            [
                1496,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                1
            ],
            [
                1498,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                3
            ],
            [
                1499,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                7
            ],
            [
                1500,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                8
            ],
            [
                1502,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                10
            ],
            [
                1503,
                'src/Psalm/Internal/Analyzer/ClassAnalyzer.php',
                null
            ],
            [
                615,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                3
            ],
            [
                616,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                5
            ],
            [
                617,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                6
            ],
            [
                618,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                8
            ],
            [
                619,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                9
            ],
            [
                622,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                null
            ],
            [
                637,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                null
            ],
            [
                638,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                13
            ],
            [
                641,
                'src/Psalm/Internal/Analyzer/MethodAnalyzer.php',
                17
            ],
        ];
    }
}