<?php

namespace Psalm\Shepherd\Test;

use PDO;
use PHPUnit\Framework\TestCase;
use Psalm\Shepherd\Api;
use Psalm\Shepherd\Config;
use Psalm\Shepherd\DatabaseProvider;
use Psalm\Shepherd\GithubData;
use Psalm\Shepherd\Model\GithubRepository;
use Psalm\Shepherd\PsalmData;
use ReflectionMethod;
use ReflectionProperty;
use function array_column;
use function explode;
use function file_get_contents;
use function getenv;
use function gmdate;
use function sha1;

/**
 * Loads init.sql into a real MySQL/MariaDB server and runs the application queries against it, so that
 * schema and queries cannot drift apart. Skipped unless SHEPHERD_TEST_DB_HOST is set.
 *
 * WARNING: the shepherd_web database on that server is dropped and recreated.
 *
 * SHEPHERD_TEST_DB_HOST          host of the server, e.g. 127.0.0.1
 * SHEPHERD_TEST_DB_PORT          optional, defaults to 3306
 * SHEPHERD_TEST_DB_ROOT_PASSWORD password of the root user, used to apply init.sql
 */
class DatabaseTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $host = getenv('SHEPHERD_TEST_DB_HOST');

        if ($host === false || $host === '') {
            self::markTestSkipped('SHEPHERD_TEST_DB_HOST is not set');
        }

        $port = getenv('SHEPHERD_TEST_DB_PORT') ?: '3306';
        $root_password = (string) getenv('SHEPHERD_TEST_DB_ROOT_PASSWORD');

        $root = new PDO('mysql:host=' . $host . ';port=' . $port, 'root', $root_password);
        $root->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $root->exec('DROP DATABASE IF EXISTS `shepherd_web`');

        // an error in any statement after the first one is only raised when moving on to its result set
        $statement = $root->query(file_get_contents(__DIR__ . '/../init.sql'));
        do {
            // nothing to do, just raise errors
        } while ($statement->nextRowset());

        // the user and the password are the ones created by init.sql
        $config = new Config\Custom(
            'test-token',
            null,
            null,
            [
                'dsn' => 'mysql:dbname=shepherd_web;host=' . $host . ';port=' . $port,
                'user' => 'shepherd_user',
                'password' => 'shepherd_mysql_development_password',
            ]
        );

        (new ReflectionProperty(Config::class, 'config'))->setValue(null, $config);
    }

    public static function tearDownAfterClass(): void
    {
        (new ReflectionProperty(Config::class, 'config'))->setValue(null, null);
        (new ReflectionProperty(DatabaseProvider::class, 'connection'))->setValue(null, null);
    }

    public function testQueriesOnEmptyDatabase(): void
    {
        $this->assertNull(Api::getTypeCoverage('psalm/shepherd'));
        $this->assertNull(Api::getLevel('psalm/shepherd'));
        $this->assertSame(0, Api::getHistoryCount('psalm/shepherd'));
        $this->assertSame([], self::recentActivity()['repositories']);
    }

    public function testStoredReportIsReadBack(): void
    {
        $commit = '0123456789abcdef0123456789abcdef01234567';

        GithubData::setRepositoryForMasterCommit(
            $commit,
            new GithubRepository('psalm', 'shepherd'),
            '2026-01-02 03:04:05'
        );
        PsalmData::savePsalmData($commit, [], 10, 90, 3);

        $this->assertSame('90.0', Api::getTypeCoverage('psalm/shepherd'));
        $this->assertSame(3, Api::getLevel('psalm/shepherd'));
        $this->assertSame(1, Api::getHistoryCount('psalm/shepherd'));
        $recent = self::recentActivity();
        $this->assertSame(['psalm/shepherd'], array_column($recent['repositories'], 'name'));
        $this->assertSame(3, $recent['repositories'][0]['level']);
        $this->assertTrue($recent['sparklines'], 'init.sql creates the repo_recent index');
        $this->assertCount(1, Api::getHistoryPayload('psalm/shepherd')['t']);
    }

    public function testBoardsPickRepositoriesByTheRulesOfTheirTab(): void
    {
        $connection = DatabaseProvider::getConnection();
        $store = static function (
            string $name,
            int $mixed,
            int $nonmixed,
            ?int $level,
            int $age_days
        ) use ($connection): void {
            $commit = sha1($name . $mixed . $nonmixed);
            [$owner, $repo] = explode('/', $name);

            $repository = new GithubRepository($owner, $repo);

            GithubData::setRepositoryForMasterCommit($commit, $repository, gmdate('Y-m-d H:i:s'));
            PsalmData::savePsalmData($commit, [], $mixed, $nonmixed, $level);
            $connection->exec(
                'UPDATE psalm_reports SET created_on = NOW() - INTERVAL ' . $age_days . ' DAY'
                . ' WHERE git_commit = ' . $connection->quote($commit)
            );
        };
        // Every push to master is stored, also when CI never sends a report for it.
        $push = static function (string $name): void {
            [$owner, $repo] = explode('/', $name);

            GithubData::setRepositoryForMasterCommit(
                sha1($name . ' unreported'),
                new GithubRepository($owner, $repo),
                gmdate('Y-m-d H:i:s')
            );
        };

        $store('a/strict-high', 50, 4950, 1, 1);
        $store('a/strict-low', 200, 1800, 1, 2);
        $store('a/big-loose', 18000, 72000, 3, 3);
        $store('a/tiny', 0, 500, 1, 1);
        $store('a/stale', 10, 9990, 1, 100);
        $store('a/climbing', 2500, 2500, 2, 10);
        $store('a/climbing', 2000, 3000, 2, 1);
        $store('a/pending', 500, 9500, 2, 5);
        $push('a/pending');
        $push('a/ghost');

        $boards = self::boards();

        $this->assertTrue($boards['sparklines']);
        $this->assertSame(['a/strict-high', 'a/strict-low'], array_column($boards['level1'], 'name'));
        // a/pending: its newest commit has no report, so its latest reported numbers count. a/ghost has none at all.
        $this->assertSame(
            ['a/strict-high', 'a/pending', 'a/strict-low', 'a/big-loose', 'a/climbing'],
            array_column($boards['coverage'], 'name')
        );
        // 5,000 expressions each for strict-high and climbing: the tie is settled by name
        $this->assertSame(
            ['a/big-loose', 'a/pending', 'a/climbing', 'a/strict-high', 'a/strict-low'],
            array_column($boards['largest'], 'name')
        );

        $pending = $boards['coverage'][1];
        $this->assertEqualsWithDelta(95.0, $pending['coverage'], 0.0001);
        $this->assertSame(10000, $pending['expressions']);

        $climbing = $boards['coverage'][4];
        $this->assertEqualsWithDelta(60.0, $climbing['coverage'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $climbing['change'], 0.0001);
        $this->assertSame([50.0, 60.0], $climbing['points']);
        $this->assertNull($boards['level1'][0]['change'], 'one report only: nothing to compare with');
    }

    /**
     * The home page query itself, without the 60 s file cache in the system temp dir.
     *
     * @return array{repositories: list<array{name: string, level: int|null}>, sparklines: bool}
     */
    private static function recentActivity(): array
    {
        /** @var array{repositories: list<array{name: string, level: int|null}>, sparklines: bool} */
        return (new ReflectionMethod(Api::class, 'loadRecentActivity'))->invoke(null);
    }

    /**
     * The static leaderboards themselves, without the 300 s file cache in the system temp dir.
     *
     * @return array{
     *     level1: list<array{
     *         name: string, coverage: float, expressions: int, change: float|null, points: list<float>
     *     }>,
     *     coverage: list<array{
     *         name: string, coverage: float, expressions: int, change: float|null, points: list<float>
     *     }>,
     *     largest: list<array{
     *         name: string, coverage: float, expressions: int, change: float|null, points: list<float>
     *     }>,
     *     sparklines: bool
     * }
     */
    private static function boards(): array
    {
        /** @var array{level1: list<array{name: string, coverage: float, expressions: int, change: float|null, points: list<float>}>, coverage: list<array{name: string, coverage: float, expressions: int, change: float|null, points: list<float>}>, largest: list<array{name: string, coverage: float, expressions: int, change: float|null, points: list<float>}>, sparklines: bool} */
        return (new ReflectionMethod(Api::class, 'loadBoards'))->invoke(null);
    }
}
