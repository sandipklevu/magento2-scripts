<?php
/**
 * From terminal, run as follows and share the <magento-root>/var/log/Klevu_Script.log file with us
 * php get_product_ids.php <store_id>
 */
ini_set('display_errors', 1);
ini_set('memory_limit', -1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;
use Magento\Store\Model\StoreManager;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;

$params[StoreManager::PARAM_RUN_CODE] = 'base'; //website code as same in admin panel

$params[StoreManager::PARAM_RUN_TYPE] = 'website';

$bootstrap = Bootstrap::create(BP, $params);

$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$objectManager = ObjectManager::getInstance();
$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');


//Change store id if needed
$storeID = $argv[1] ?? 1;

$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/Klevu_Script.log');
$logger = new \Zend\Log\Logger();
$logger->addWriter($writer);
$logger->info('Logger Started');
echo 'Logger started for storeID : ' . $storeID . PHP_EOL;

try {
    $storeObject = $storeManager->getStore($storeID);

    $magentoProductActions = $objectManager->get('Klevu\Search\Model\Product\MagentoProductActionsInterface');
    $deleteIds = $magentoProductActions->deleteProductCollection($storeObject);
    $logger->info('delete Product IDs');
    $logger->info(print_r($deleteIds, true));


    $updateIds = $magentoProductActions->updateProductCollection($storeObject);
    $logger->info('update Product IDs');
    $logger->info(print_r($updateIds, true));


    $addIds = $magentoProductActions->addProductCollection($storeObject);
    $logger->info('add Product IDs');
    $logger->info(print_r($addIds, true));

} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $logger->info('Exception Thrown'.$e->getMessage());
}

$logger->info('Logger Completed');
