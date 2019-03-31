<html>
<head>
<title>Shepherd</title>
</head>
<body>
<h1>Psalm Shepherd</h1>

<h2>Repository coverage</h2>

<ul>
<?php foreach (Psalm\Shepherd\Api::getGithubRepositories() as $github_repository) : ?>
	<li><?php echo $github_repository ?>: <img src="/github/<?php echo $github_repository ?>/coverage.svg"></li>
<? endforeach; ?>
</ul>
</body>
</html>