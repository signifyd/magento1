<?php

/*  @var $this Mage_Sales_Model_Mysql4_Setup  */
$this->startSetup();

$connection = $this->getConnection();

$connection->addColumn(
    $this->getTable('sales/order'),
    'origin_store_code',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable' => true,
        'length' => 32,
        'comment' => 'Store code used to place order'
    )
);

$this->endSetup();
