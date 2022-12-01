<?php
namespace Shiptimize\Shipping\Model;

use Magento\Sales\Model\Order;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite; 
use Magento\Store\Model\StoreManagerInterface;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;


/**
 *  Handles creating a shipment if multi inventory is enabled 
 **/ 
class ShiptimizeShipMultiInventory 
{

	public function __construct( 
        GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite,
        StoreManagerInterface $storeManager,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        GetStockSourceLinksInterface $getStockSourceLinks
    )
    { 
    	$this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
    	$this->storeManager = $storeManager; 

        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->getStockSourceLinks = $getStockSourceLinks;
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

        return $this->getAssignedStockIdForWebsite->execute($websiteCode);
    }

    /**
     * Return the sources associated with a stockid
     **/
    public function getSourceIds($stockId = '') {
    	if(!$stockId) {
    		$stockId = $this->getStockId(); 
    	}

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(StockSourceLinkInterface::STOCK_ID, $stockId)
            ->create();

        $result = [];
        foreach ($this->getStockSourceLinks->execute($searchCriteria)->getItems() as $link) {
            $result[$link->getSourceCode()] = $link->getData();
        }

        return $result;
    }
}