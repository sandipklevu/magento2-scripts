<?php
/**
 * From terminal, run as follows and share the <magento-root>/var/log/KlevuLogForLoadAttribute.log file with us
 * php loadAttribute.php <product_ids> i.e. 1,2,3,5,999
 */
ini_set('display_errors', 1);
ini_set('memory_limit', -1);

use Klevu\Search\Api\Service\Catalog\Product\StockServiceInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;

require __DIR__ . '/app/bootstrap.php';

$params = $_SERVER;

$bootstrap = Bootstrap::create(BP, $params);

$obj = $bootstrap->getObjectManager();

$state = $obj->get('Magento\Framework\App\State');
$state->setAreaCode('frontend');

$om = ObjectManager::getInstance();
$storeManager = ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');

$stockService = ObjectManager::getInstance()->get(StockServiceInterface::class);

//Change product id if needed
$product_ids = $argv[1] ? explode(',', $argv[1]) : '';

//Change store id if needed
$storeID = 1;

$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/Klevu_Script_LoadAttribute.log');
$logger = new \Zend\Log\Logger();
$logger->addWriter($writer);
$logger->info(' *********************** LoadAttribute Start ***********************');
$logger->info('Store ID: ' . $storeID);
$logger->info('Product IDs: ' . print_r($product_ids, true));

try {
    $data = ObjectManager::getInstance()
        ->create('Magento\Catalog\Model\ResourceModel\Product\Collection')
        ->addAttributeToSelect('*')
        ->addIdFilter($product_ids)
        ->setStore($storeManager->getStore())
        ->addStoreFilter()
        ->addMinimalPrice()
        ->addFinalPrice();
    $data->setFlag('has_stock_status_filter', false);
    $data->load()
        ->addCategoryIds();

    $logger->info('Query : ' . $data->getSelect());
    $logger->info('Store ID: ' . $data->getSelect());
    foreach ($product_ids as $product_id) {
        $item = $data->getItemById($product_id);
        if (!$item) {
            echo 'Product Data not available for given Product id : ' . $product_id . PHP_EOL;
            $logger->info(sprintf('Product Data not available for given Product id %s', $product_id));
            continue;
        }
        $logger->info('Entity ID : ' . $item->getData('entity_id'));
        $logger->info('Name: ' . $item->getData('name'));
        $logger->info('Stock Status isInStock : ' . $stockService->isInStock($item));
        $logger->info('Stock Status getKlevuStockStatus : ' . $stockService->getKlevuStockStatus($item));
    }
    exit;

} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $logger->info('Exception Thrown' . $e->getMessage());
}
