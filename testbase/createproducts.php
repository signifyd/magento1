<?php

include('app/Mage.php');

Mage::register('isSecureArea', 1);

Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$category = Mage::getModel('catalog/category');
$category->setPath('1/2') // set parent to be root category
    ->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
    ->setName('Test Category.')
    ->setIsActive(1)
    ->setIncludeInMenu(1)
    ->save();


$product = Mage::getModel('catalog/product');

$productid = Mage::getModel('catalog/product')->getIdBySku("ABC123");

if ($productid) {
    $product = Mage::getModel('catalog/product')->load($productid);
}
 
$product->setSku("ABC123");
$product->setName("Type 7 Widget");
$product->setDescription("This widget will give you years of trouble-free widgeting.");
$product->setShortDescription("High-end widget.");
$product->setPrice(70.50);
$product->setTypeId('simple');
$product->setAttributeSetId(4); // need to look this up
$product->setCategoryIds($category->getId()); // need to look these up
$product->setWeight(1.0);
$product->setTaxClassId(2); // taxable goods
$product->setVisibility(4); // catalog, search
$product->setStatus(1); // enabled
 
// assign product to the default website
$product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));

$stockData = $product->getStockData();
$stockData['qty'] = 10;
$stockData['is_in_stock'] = 1;
$stockData['manage_stock'] = 1;
$stockData['use_config_manage_stock'] = 0;
$product->setStockData($stockData);
 
$product->save();


