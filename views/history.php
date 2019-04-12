<?php

require '../vendor/autoload.php';

$repository = $_SERVER['QUERY_STRING'];

if (!preg_match('/^[-\d\w._]+\/[-\d\w._]+$/', $repository)) {
	throw new \UnexpectedValueException('Repsitory format not recognised');
}

if (strpos($repository, '..') !== false) {
	throw new \UnexpectedValueException('Unexpected values in repository name');
}

$pct = Psalm\Shepherd\Api::getHistory($repository);

$config = Psalm\Shepherd\Config::getInstance();

$github_url = $config->gh_enterprise_url ?: 'https://github.com';
?>
<html>
<head>
<title>Shepherd - <?php echo $repository ?></title>
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
		<div class="coverage_list">
			<h2>Type coverage history for <?php echo $repository ?></h2>

			<ul>
			<?php foreach ($pct as $date => [$commit, $coverage]) : ?>
				<li><?php echo date('F j Y, H:i:s', $date) ?> - <a href="<?php echo $github_url . '/' . $repository . '/commit/' . $commit ?>"><?php echo substr($commit, 0, 7) ?></a>:<br />
					<?php echo number_format($coverage, 5) ?>
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
