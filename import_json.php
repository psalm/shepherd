<?php

require 'vendor/autoload.php';

foreach (glob('database/github_pr_data/*.json') as $github_json_file) {
	$github_data = json_decode(file_get_contents($github_json_file), true);

    if (!isset($github_data['pull_request'])) {
        continue;
    }

	Psalm\Shepherd\GithubData::storePullRequestData(
		$github_data['pull_request']['head']['sha'],
		$github_data
	);
}


foreach (glob('database/psalm_data/*.json') as $psalm_json_file) {
	$psalm_data = json_decode(file_get_contents($psalm_json_file), true);

    if (!isset($psalm_data['coverage'])) {

        var_dump($psalm_data);
        continue;
    }

	Psalm\Shepherd\PsalmData::savePsalmData(
		$psalm_data['git']['head']['id'],
		$psalm_data['issues'],
		$psalm_data['coverage'][0],
		$psalm_data['coverage'][1]
	);

	if (!empty($psalm_data['build']['CI_REPO_OWNER'])
        && !empty($psalm_data['build']['CI_REPO_NAME'])
        && empty($psalm_data['build']['CI_PR_REPO_OWNER'])
        && empty($psalm_data['build']['CI_PR_REPO_NAME'])
        && ($psalm_data['build']['CI_BRANCH'] ?? '') === 'master'
        && isset($psalm_data['git']['head']['date'])
    ) {
        $repository = new \Psalm\Shepherd\Model\GithubRepository(
            $psalm_data['build']['CI_REPO_OWNER'],
            $psalm_data['build']['CI_REPO_NAME']
        );

        Psalm\Shepherd\GithubData::setRepositoryForMasterCommit(
        	$psalm_data['git']['head']['id'],
        	$repository,
        	date('Y-m-d H:i:s', $psalm_data['git']['head']['date'])
        );
    }
}
