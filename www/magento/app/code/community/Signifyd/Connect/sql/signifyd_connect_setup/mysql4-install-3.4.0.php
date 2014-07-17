<?php

$this->startSetup();
$this->register();
$this->run("
DROP TABLE IF EXISTS `{$this->getTable('signifyd_connect_case')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('signifyd_connect_case')}` (
  `case_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_increment` varchar(255) NOT NULL,
  `signifyd_status` varchar(64) NOT NULL DEFAULT 'PENDING',
  `code` varchar(255) NOT NULL,
  `score` float DEFAULT NULL,
  `entries` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`case_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;

CREATE INDEX signifyd_connect_case_order ON `{$this->getTable('signifyd_connect_case')}` (order_increment);
");
$this->endSetup();
