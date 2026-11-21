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
use function file_get_contents;
use function getenv;

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
}
