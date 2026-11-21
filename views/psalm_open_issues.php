<?php
require __DIR__ . '/../vendor/autoload.php';
$after = $_GET['after'] ?? null;
$issue_data = Psalm\Shepherd\GithubApi::fetchPsalmIssuesData(is_string($after) ? $after : null);

(require __DIR__ . '/partials/head.php')('Shepherd');
(require __DIR__ . '/partials/nav.php')();
?>
    <main id="main" class="container page" tabindex="-1">
        <div class="coverage_list">
            <?php foreach ($issue_data[0] as $issue_id => $changes) : ?>
                <p><a href="https://github.com/vimeo/psalm/issues/<?= htmlspecialchars((string) $issue_id) ?>">Link to issue</a></p>

                <?php foreach ($changes as $psalm_link => [$current_result, $original_result]) : ?>
                    <p><a href="<?= htmlspecialchars((string) $psalm_link) ?>">Psalm link</a></p>
                    <p>Before:</p>
                    <pre><?= htmlentities($original_result) ?></pre>
                    <p>Current:</p>
                    <pre><?= htmlentities($current_result) ?></pre>
                <?php endforeach ?>
            <?php endforeach ?>

            <p>
                <a href="/psalm_open_issues?after=<?= htmlspecialchars(urlencode((string) $issue_data[1])) ?>">Next</a>
            </p>
        </div>
    </main>
</body>
</html>
