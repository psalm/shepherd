<?php declare(strict_types=1);

namespace Psalm\Shepherd\Test\Extension;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestErrorHook;
use Psalm\SourceControl\Git\GitInfo;

final class FailureTracker implements AfterTestErrorHook, AfterTestFailureHook, AfterLastTestHook
{
    /** @var ?array */
    private static $build_info = null;

    /** @var ?GitInfo */
    private static $git_info = null;

    /** @var array<int, string> */
    private $failed_tests = [];

    public function executeAfterTestFailure(string $test, string $message, float $time): void
    {
        $this->failed_tests[] = $test;
    }

    public function executeAfterTestError(string $test, string $message, float $time): void
    {
        $this->failed_tests[] = $test;
    }

    public function executeAfterLastTest(): void
    {
        if (!$this->failed_tests) {
            return;
        }

        $build_info = self::getBuildInfo();
        $git_info = self::getGitInfo();

        if ($build_info) {
            $data = [
                'build' => $build_info,
                'git' => $git_info->toArray(),
                'tests' => $this->failed_tests,
            ];

            $payload = json_encode($data);

            $base_address = 'https://shepherd.dev';

            if (parse_url($base_address, PHP_URL_SCHEME) === null) {
                $base_address = 'https://' . $base_address;
            }

            // Prepare new cURL resource
            $ch = curl_init($base_address . '/hooks/phpunit');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLINFO_HEADER_OUT, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

            // Set HTTP Header for POST request
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload),
                ]
            );

            // Submit the POST request
            $return = curl_exec($ch);

            if ($return !== '') {
                fwrite(STDERR, 'Error with PHPUnit Shepherd:' . PHP_EOL);

                if ($return === false) {
                    fwrite(STDERR, \Psalm\Plugin\Shepherd::getCurlErrorMessage($ch) . PHP_EOL);
                } else {
                    echo $return . PHP_EOL;
                    echo 'Git args: ' . var_export($git_info->toArray(), true) . PHP_EOL;
                    echo 'CI args: ' . var_export($build_info, true) . PHP_EOL;
                }
            } else {
                var_dump('successfully sent');
            }

            // Close cURL session handle
            curl_close($ch);
        }
    }

    private static function getBuildInfo(): array
    {
        if (!self::$build_info) {
            self::$build_info = (new \Psalm\Internal\ExecutionEnvironment\BuildInfoCollector($_SERVER))->collect();
        }

        return self::$build_info;
    }

    private static function getGitInfo(): GitInfo
    {
        if (!self::$git_info) {
            self::$git_info = (new \Psalm\Internal\ExecutionEnvironment\GitInfoCollector())->collect();
        }

        return self::$git_info;
    }
}
