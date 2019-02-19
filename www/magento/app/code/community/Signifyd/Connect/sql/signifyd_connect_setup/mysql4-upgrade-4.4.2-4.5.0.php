<?php

$this->startSetup();

// Previsous versions use to create this table, but it was not been used on recent versions
// All references to this table on setup, XML and modules have been removed
// This script eliminates the table if it exists
$this->run("DROP TABLE IF EXISTS `{$this->getTable('signifyd_connect/retries')}`;");

$this->getConnection()->addColumn(
    $this->getTable('signifyd_connect/case'),
    'retries',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => false,
        'default' => 0,
        'comment' => 'Number of retries for current case magento_status'
    )
);

$this->endSetup();