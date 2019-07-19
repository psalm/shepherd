CREATE DATABASE IF NOT EXISTS  `shepherd_web`;

GRANT ALL ON `shepherd_web`.* TO 'shepherd_mysql_user'@'%' IDENTIFIED BY 'shepherd_mysql_development_password';

CREATE TABLE `shepherd_web`.`test_failures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `git_commit` varchar(40) NOT NULL DEFAULT '',
  `test_name` varchar(255) NOT NULL DEFAULT '',
  `branch` varchar(127) NOT NULL DEFAULT '',
  `repository` varchar(255) DEFAULT '',
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commit_test` (`git_commit`,`test_name`),
  KEY `test_name` (`test_name`),
  KEY `git_commit` (`git_commit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `shepherd_web`.`github_pr_reviews` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `github_pr_url` varchar(255) NOT NULL DEFAULT '',
  `tool` enum('psalm','phpunit') NOT NULL DEFAULT 'psalm',
  `github_review_id` varchar(255) NOT NULL DEFAULT '',
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_for_tool` (`github_pr_url`,`tool`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `shepherd_web`.`github_pr_comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `github_pr_url` varchar(255) NOT NULL DEFAULT '',
  `tool` enum('psalm','phpunit') NOT NULL DEFAULT 'psalm',
  `github_comment_id` varchar(255) NOT NULL,
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `github_comment_tool` (`github_pr_url`,`tool`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;