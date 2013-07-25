<?php

$this->startSetup();
$this->register();
$this->run("
DROP TABLE IF EXISTS `{$this->getTable('grep_signifyd_case')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('grep_signifyd_case')}` (
  `case_id` int(10) unsigned NOT NULL auto_increment,
  `order_increment` varchar(255) NOT NULL,
  PRIMARY KEY (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");
$this->endSetup();
