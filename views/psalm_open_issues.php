<?php
require '../vendor/autoload.php';
$issue_data = Psalm\Shepherd\GithubApi::fetchPsalmIssuesData($_GET['after'] ?? null);
?>
<html>
<head>
<title>Shepherd</title>
<link rel="stylesheet" href="/assets/styles/main.css?2">
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400&family=IBM+Plex+Sans&display=swap" rel="stylesheet">
<meta name="viewport" content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
</head>
<body>
    <nav>
        <div class="container">
            <h1><a href="/">Shepherd</a></h1>
        </div>
    </nav>

    <div class="container front">
        <div class="coverage_list">
            <?php foreach ($issue_data[0] as $issue_id => $changes) : ?>
                <p><a href="https://github.com/vimeo/psalm/issues/<?= $issue_id ?>">Link to issue</a></p>

                <?php foreach ($changes as $psalm_link => [$current_result, $original_result]): ?>
                    <p><a href="<?=$psalm_link ?>">Psalm link</a></p>
                    <p>Before:</p>
                    <pre><?= $original_result ?></pre>
                    <p>Current:</p>
                    <pre><?= $current_result ?></pre>
                <?php endforeach ?>
            <?php endforeach ?>

            <p>
                <a href="/psalm_open_issues?after=<?= $issue_data[1] ?>">Next</a>
            </p>
        </div>
    </div>
</body>
</html>