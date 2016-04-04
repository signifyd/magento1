<?php

class Signifyd_Connect_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('signifyd_connect_grid');
        $this->setDefaultSort('increment_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }
    
    public function oldSupport()
    {
        $model = Mage::getSingleton('signifyd_connect/observer');
        
        return $model->oldSupport();
    }
    
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('sales/order_collection');
        
        if ($this->oldSupport()) {
            $collection->getSelect()->joinLeft(
                array('signifyd' => Mage::getSingleton('core/resource')->getTableName('signifyd_connect_case')),
                'signifyd.order_increment = e.increment_id',
                array(
                    'score' => 'score',
                )
            );
        } else {
            $collection->addExpressionFieldToSelect(
                'fullname',
                'CONCAT({{customer_firstname}}, \' \', {{customer_lastname}})',
                array('customer_firstname' => 'main_table.customer_firstname', 'customer_lastname' => 'main_table.customer_lastname')
            );
            
            $collection->getSelect()->joinLeft(
                array('signifyd' => Mage::getSingleton('core/resource')->getTableName('signifyd_connect_case')),
                'signifyd.order_increment = main_table.increment_id',
                array(
                    'score' => 'score',
                    'guarantee' => 'guarantee',
                )
            );
        }
        
        $this->setCollection($collection);
        parent::_prepareCollection();
        return $this;
    }
    
    protected function _prepareColumns()
    {
        $helper = Mage::helper('signifyd_connect');
        $currency = (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
        
        $this->addColumn('increment_id', array(
            'header' => $helper->__('Order #'),
            'index'  => 'increment_id'
        ));
        
        $this->addColumn('purchased_on', array(
            'header' => $helper->__('Purchased On'),
            'type'   => 'datetime',
            'index'  => 'created_at'
        ));
        
        if (!$this->oldSupport()) {
            $this->addColumn('fullname', array(
                'header'       => $helper->__('Name'),
                'index'        => 'fullname',
                'filter_index' => 'CONCAT(customer_firstname, \' \', customer_lastname)'
            ));
        }
        
        $this->addColumn('grand_total', array(
            'header'        => $helper->__('Grand Total'),
            'index'         => 'grand_total',
            'type'          => 'currency',
            'currency_code' => $currency
        ));
        
        $this->addColumn('shipping_method', array(
            'header' => $helper->__('Shipping Method'),
            'index'  => 'shipping_description'
        ));
        
        $this->addColumn('score', array(
            'header' => $helper->__('Signifyd Score'),
            'align' => 'left',
            'type' => 'text',
            'index' => 'score',
            'filter' => false,
            'renderer' => 'signifyd_connect/renderer',
            'width' => '100px',
        ));

        $this->addColumn('guarantee', array(
            'header' => $helper->__('Signifyd Guarantee Status'),
            'align' => 'left',
            'type' => 'text',
            'index' => 'guarantee',
            'filter' => false,
            'renderer' => 'signifyd_connect/renderer',
        ));
        
        $this->addColumn('order_status', array(
            'header'  => $helper->__('Status'),
            'index'   => 'status',
            'type'    => 'options',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
        ));
        
        return parent::_prepareColumns();
    }
    
    public function getRowUrl($row)
    {
        if (Mage::getSingleton('admin/session')->isAllowed('sales/order/actions/view')) {
            return $this->getUrl('*/sales_order/view', array('order_id' => $row->getId()));
        }
        
        return false;
    }
    
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }
}
