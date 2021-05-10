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
            <pre><?php var_dump($issue_data[0]); ?></pre>
            <p>
                <a href="/psalm_open_issues?after=<?= $issue_data[1] ?>">Next</a>
            </p>
        </div>
    </div>
</body>
</html>