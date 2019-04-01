<?php
require '../vendor/autoload.php';

$config = Psalm\Shepherd\Config::getInstance();

$github_url = $config->gh_enterprise_url ?: 'https://github.com';
?>
<html>
<head>
<title>Shepherd</title>
</head>
<body>
<h1>Psalm Shepherd</h1>

<h2>Github Repository coverage</h2>

<ul>
<?php foreach (Psalm\Shepherd\Api::getGithubRepositories() as $github_repository) : ?>
	<li><a href="<?php echo $github_url . '/' . $github_repository ?>"><?php echo $github_repository ?></a>:<br><img src="/github/<?php echo $github_repository ?>/coverage.svg"></li>
<?php endforeach; ?>
</ul>
</body>
</html>
