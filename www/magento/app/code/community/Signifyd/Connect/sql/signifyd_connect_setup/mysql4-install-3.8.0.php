<?php

$this->startSetup();
$this->register();
$this->run("
DROP TABLE IF EXISTS `{$this->getTable('signifyd_connect_case')}`;
CREATE TABLE IF NOT EXISTS `{$this->getTable('signifyd_connect_case')}` (
  `order_increment` varchar(255) NOT NULL,
  `signifyd_status` varchar(64) NOT NULL DEFAULT 'PENDING',
  `code` varchar(255) NOT NULL,
  `score` float DEFAULT NULL,
  `guarantee`  VARCHAR( 64 ) NOT NULL DEFAULT 'N/A',
  `entries` text NOT NULL,
  `created` timestamp NULL DEFAULT NULL,
  `updated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`order_increment`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
");
$this->endSetup();
