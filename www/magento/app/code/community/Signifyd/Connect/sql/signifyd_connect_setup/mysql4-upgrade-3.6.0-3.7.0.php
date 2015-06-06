<?php

try {
    $this->startSetup();
    $this->run("ALTER TABLE `{$this->getTable('signifyd_connect_case')}` RENAME TO temp_signifyd;");
	$this->run("
CREATE TABLE `{$this->getTable('signifyd_connect_case')}` (
  `order_increment` varchar(255) NOT NULL,
  `signifyd_status` varchar(64) NOT NULL DEFAULT 'PENDING',
  `code` varchar(255) NOT NULL,
  `score` float DEFAULT NULL,
  `entries` text NOT NULL,
  `created` timestamp NULL DEFAULT NULL,
  `updated` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`order_increment`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;
");
	$this->run("
INSERT INTO `{$this->getTable('signifyd_connect_case')}` (`order_increment`, `signifyd_status`, `code`, `score`, `entries`, 
`created`, `updated`)
SELECT `order_increment`, `signifyd_status`, `code`, `score`, `entries`, 
     MIN(`created`) as `created`, MAX(`updated`) as `updated` 
FROM (SELECT `order_increment`, `signifyd_status`, `code`, `score`, `entries`, 
`created`, `updated` FROM temp_signifyd ORDER BY updated DESC) as temp_by_updated
GROUP BY `order_increment`;
");
    $this->run("DROP TABLE temp_signifyd");
    $this->endSetup();
} catch (Exception $e) {
    Mage::log('Signifyd_Connect upgrade: ' . $e->__toString(), null, 'signifyd_connect.log');
}
