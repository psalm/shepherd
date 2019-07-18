CREATE DATABASE IF NOT EXISTS  `shepherd_web`;

GRANT ALL ON `shepherd_web`.* TO 'shepherd_mysql_user'@'%' IDENTIFIED BY 'shepherd_mysql_development_password';

CREATE TABLE `test_failures` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `git_commit` varchar(40) NOT NULL DEFAULT '',
  `test_name` varchar(255) NOT NULL DEFAULT '',
  `created_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `branch` varchar(127) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commit_test` (`git_commit`,`test_name`),
  KEY `test_name` (`test_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
