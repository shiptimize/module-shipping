<?php
namespace Shiptimize\Shipping\Model;

if (class_exists("GetAssignedStockIdForWebsite")) { 
    return;
}

use Magento\Sales\Model\Order; 
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;  

/**
 *  Handles creating a shipment if multi inventory is enabled 
 *  Remember to not create direct dependencies on Magento MultipleInventory classes as they may not exist 
 **/ 
class ShiptimizeShipMultiInventory 
{

    private \Magento\Framework\ObjectManagerInterface $objectmanager; 

	public function __construct(  
        ObjectManagerInterface $objectmanager, 
        StoreManagerInterface $storeManager,
        SearchCriteriaBuilder $searchCriteriaBuilder
    )
    { 
    	$this->objectmanager = $objectmanager;
    	$this->storeManager = $storeManager; 

        $this->searchCriteriaBuilder = $searchCriteriaBuilder; 
    }

    public function setOrder($order) {
    	$this->magentoOrder = $order; 
    }

    /** 
     * Returns the stock id associated with this order  
     **/
    public function getStockId() { 
        $storeId = $this->magentoOrder->getStoreId(); 
        try {
            $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId(); 
            $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();
        }
        catch(Exception $e) {
            error_log($e->getMessage());
        }

        $istockid = $this->objectmanager->create("Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite"); 
        return $istockid->execute($websiteCode);
    }

    /**
     * Return the sources associated with a stockid
     **/
    public function getSourceIds($stockId = '') {
    	if(!$stockId) {
    		$stockId = $this->getStockId(); 
    	}

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(\Magento\InventoryApi\Api\Data\StockSourceLinkInterface::STOCK_ID, $stockId)
            ->create();

        $result = [];
        $istocksrc = $this->objectmanager->create("\Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory");  

        foreach ($istocksrc->execute($searchCriteria)->getItems() as $link) {
            $result[$link->getSourceCode()] = $link->getData();
        }

        return $result;
    }
} 