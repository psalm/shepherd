<?php

return (new PhpCsFixer\Config())
	->setIndent("\t")
	->setFinder(
		PhpCsFixer\Finder::create()
			->in([
				__DIR__ . '/src',
				__DIR__ . '/tests',
				__DIR__ . '/views',
			])
			->append([__FILE__])
	)
	->setRules([
		'@PSR12' => true,
	])
	->setUsingCache(false);
