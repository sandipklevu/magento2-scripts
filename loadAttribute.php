<?php
/**
 * From terminal, run as follows and share the <magento-root>/var/log/Klevu_Script_LoadAttribute.log file with us
 * php klevu_loadAttribute.php <comma separated product_ids> i.e. 1,2,3,5,999 <store_id> <parent_id>
 * where store_id and parent_id are optional arguments
 * default store_id will be 1 and default parent_id will be 0
 */
ini_set('display_errors', 1);
ini_set('memory_limit', -1);


use Klevu\Search\Helper\Config;
use Klevu\Search\Helper\Stock;
use Klevu\Search\Model\Product\ProductInterface;
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
$productService = ObjectManager::getInstance()->get(ProductInterface::class);
$_searchHelperConfig = ObjectManager::getInstance()->get(Config::class);
$_stockHelper = ObjectManager::getInstance()->get(Stock::class);

$product_ids = $argv[1] ? explode(',', $argv[1]) : '';
$parent_id = $argv[3] ?? 0;

//Change store id if needed
$storeID = $argv[2] ?? 1;
$storeObject = '';


$writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/Klevu_Script_LoadAttribute.log');
$logger = new \Laminas\Log\Logger();
$logger->addWriter($writer);

try {
    $storeObject = $storeManager->getStore($storeID);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $logger->info('Store not found: ' . $e->getMessage());
}
try {
    $storeManager->setCurrentStore($storeObject);
} catch (\Exception $e) {
    $logger->info($e->getMessage());
}


$logger->info(' *********************** LoadAttribute Start ***********************');
$logger->info('Before Store ID: ' . $storeObject->getId());
$logger->info('Product IDs: ' . print_r($product_ids, true));

try {
    /**
     * Collection being used in Klevu search module 2.6.3
     */
    $data = ObjectManager::getInstance()
        ->create('Magento\Catalog\Model\ResourceModel\Product\Collection')
        //->addAttributeToSelect('*')
        ->addIdFilter($product_ids)
        ->setStore($storeObject->getId())
        ->addStoreFilter()
        ->addMinimalPrice()
        ->addFinalPrice();

    $data->setFlag('has_stock_status_filter', false);
    $data->load()->addCategoryIds();

    $logger->info('After Store ID: ' . $storeObject->getId());

    $logger->info('Product data query : ' . $data->getSelect());
    foreach ($product_ids as $product_id) {
        $item = $data->getItemById($product_id);
        if (!$item) {
            echo 'ALERT:: Product data query did not return any data for given Product ID : ' . $product_id . PHP_EOL;
            $logger->info(sprintf('ALERT:: Product Data not available for given Product ID: %s', $product_id));
            continue;
        }

        if ($_searchHelperConfig->isCollectionMethodEnabled()) {
            $logger->info('Start: Data loading using collection method.');
            $parent = $data->getItemById($parent_id) ?? null;
            $logger->info('End: Data loading using collection method.');
        } else {
            $logger->info('Start: Data loading using object method.');
            $item = \Magento\Framework\App\ObjectManager::getInstance()->create('\Magento\Catalog\Model\Product')
                ->load($product_id);
            $item->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID);
            $parent = ($parent_id != 0) ?
                \Magento\Framework\App\ObjectManager::getInstance()->create('\Magento\Catalog\Model\Product')
                    ->load($parent_id)
                    ->setCustomerGroupId(\Magento\Customer\Model\Group::NOT_LOGGED_IN_ID) : null;

            $logger->info('End : Data loading using object method.');
        }

        echo 'Product Data is available for given Product id : ' . $product_id . PHP_EOL;

        $product = [
            'product_id' => $item->getData('entity_id'),
            'image' => '',
        ];

        $logger->info('Entity ID : ' . $item->getData('entity_id'));

        $logger->info('klevuId : ' .
            print_r($productService->getId($product_id, $parent_id), true)
        );
        $logger->info('Name : ' . $item->getData('name'));
        $logger->info('Status : ' . $item->getData('status'));
        $logger->info(
            'Sku : ' .
            print_r($productService->getSku('sku', ['sku'], $parent, $item, $product), true)
        );
        $logger->info(
            'inStock: ' . print_r($_stockHelper->getKlevuStockStatus($parent, $item), true)
        );

        $logger->info('getProductType : ' . $productService->getProductType($parent, $item));

        $logger->info('isCustomOptionsAvailable : ' .
            $productService->isCustomOptionsAvailable($parent, $item)
        );

        $logger->info(
            'Visibility : ' . print_r($item->getData('visibility'), true)
        );

        $logger->info(
            'getCategory : ' . print_r($productService->getCategory($parent, $item), true)
        );

        $logger->info('getListCategory : ' .
            print_r($productService->getListCategory($parent, $item), true));

        $logger->info('getAllCategoryId : ' .
            print_r($productService->getAllCategoryId($parent, $item), true));

        $logger->info('getAllCategoryPaths : ' .
            print_r($productService->getAllCategoryPaths($parent, $item), true));

        $logger->info(
            'getGroupPricesData: ' .
            print_r($productService->getGroupPricesData($item), true)
        );

    }
    exit;

} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $logger->info('Exception Thrown' . $e->getMessage());
}
