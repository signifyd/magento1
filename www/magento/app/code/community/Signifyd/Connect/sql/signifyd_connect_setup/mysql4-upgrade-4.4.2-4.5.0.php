<?php

$this->startSetup();

// Previsous versions use to create this table, but it was not been used on recent versions
// All references to this table on setup, XML and modules have been removed
// This script eliminates the table if it exists
$this->run("DROP TABLE IF EXISTS `{$this->getTable('signifyd_connect/retries')}`;");

$this->endSetup();