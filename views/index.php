<?php
require '../vendor/autoload.php';
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
		<div class="intro">
			<p>Shepherd is a currently-experimental service to handle CI output from <a href="https://psalm.dev">Psalm</a>.</p>
			<p>It's being actively developed at <a href="https://github.com/psalm/shepherd">github.com/psalm/shepherd</a>.</p>
		</div>
		<div class="coverage_list">
			<h2>Recent type coverage</h2>

			<ul>
			<?php foreach (Psalm\Shepherd\Api::getRecentGithubRepositories() as $github_repository) : ?>
				<li>
					<a href="/github/<?php echo $github_repository ?>"/>
						<?php echo $github_repository ?></a><br>
						<img src="/github/<?php echo $github_repository ?>/coverage.svg">
						<img src="/github/<?php echo $github_repository ?>/level.svg">
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
	</div>
</body>
</html>
