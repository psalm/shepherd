<?php
require '../vendor/autoload.php';

$config = Psalm\Shepherd\Config::getInstance();

$github_url = $config->gh_enterprise_url ?: 'https://github.com';
?>
<html>
<head>
<title>Shepherd</title>
<link rel="stylesheet" href="/assets/styles/main.css">
<link rel="stylesheet" type="text/css" href="https://cloud.typography.com/751592/7707372/css/fonts.css" />
<meta name="viewport" content="initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<link rel="icon" type="image/png" href="/assets/img/favicon.png">
</head>
<body>
	<nav>
		<div class="container">
			<h1><?php require('../assets/img/logo.svg'); ?> Shepherd</h1>
		</div>
	</nav>

	<div class="container front">
		<div class="intro">
			<p>Shepherd is a currently-experimental service to handle CI output from <a href="https://psalm.dev">Psalm</a>.</p>
			<p>It's being actively developed at <a href="https://github.com/psalm/shepherd">github.com/psalm/shepherd</a>.</p>
		</div>
		<div class="coverage_list">
			<h2>Github Repository coverage</h2>

			<ul>
			<?php foreach (Psalm\Shepherd\Api::getGithubRepositories() as $github_repository) : ?>
				<li>
					<a href="<?php echo $github_url . '/' . $github_repository ?>"><?php echo $github_repository ?></a>:<br>
					<a href="/github/<?php echo $github_repository ?>"/><img src="/github/<?php echo $github_repository ?>/coverage.svg"></a></li>
				</li>
			<?php endforeach; ?>
			</ul>
		</div>
	</div>
	<footer>
    	<p>Not quite sure what to put here yet</p>
	</footer>
</body>
</html>
