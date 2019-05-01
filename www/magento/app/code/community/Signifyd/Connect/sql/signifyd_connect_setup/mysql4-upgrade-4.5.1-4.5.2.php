<?php

/** @var Signifyd_Connect_Model_Setup $this */
$this->startSetup();

$table = $this->getConnection()
    ->newTable($this->getTable('signifyd_connect/fulfillment'))
    ->addColumn(
        'id',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        50,
        array(
            'nullable'  => false,
            'primary'   => true,
        ),
        'Fulfillment (Shipment) ID'
    )
    ->addColumn(
        'order_id',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        32,
        array(
            'nullable' => false
        ),
        'Order ID'
    )
    ->addColumn(
        'created_at',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        30,
        array(
            'nullable' => false
        ),
        'Created at'
    )
    ->addColumn(
        'delivery_email',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable' => true,
            'default' => null
        ),
        'Delivery e-mail'
    )
    ->addColumn(
        'fulfillment_status',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        30,
        array(
            'nullable' => false
        ),
        'Fulfillment status'
    )
    ->addColumn(
        'tracking_numbers',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable' => true
        ),
        'Tracking numbers'
    )
    ->addColumn(
        'tracking_urls',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        null,
        array(
            'nullable' => true
        ),
        'Traching URLs'
    )
    ->addColumn(
        'products',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        false,
        array(
            'nullable' => true
        ),
        'Products'
    )
    ->addColumn(
        'shipment_status',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        30,
        array(
            'nullable' => true
        ),
        'Shipment status'
    )
    ->addColumn(
        'delivery_address',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        null,
        array(
            'nullable' => true
        ),
        'Delivery address'
    )
    ->addColumn(
        'recipient_name',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable' => true
        ),
        'Recipient name'
    )
    ->addColumn(
        'confirmation_name',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable' => true
        ),
        'Confirmation name'
    )
    ->addColumn(
        'confirmation_phone',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        50,
        array(
            'nullable' => true
        ),
        'Confirmation phone'
    )
    ->addColumn(
        'shipping_carrier',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        255,
        array(
            'nullable' => true
        ),
        'Shipping carrier'
    )
    ->addColumn(
        'magento_status',
        Varien_Db_Ddl_Table::TYPE_TEXT,
        50,
        array('nullable' => false, 'default' => 'waiting_submission'),
        'Magento Status'
    )
    ->setComment('Signifyd Fulfillments');

$this->getConnection()->createTable($table);

$this->endSetup();