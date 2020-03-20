<?php
namespace Shiptimize\Shipping\Plugin;

/**
 * Class AddDataToOrdersGrid
 */
class AddDataToOrdersGrid
{ 

    /**
     * @param \Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory $subject
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $collection
     * @param $requestName
     * @return mixed
     */
    public function afterGetReport($subject, $collection, $requestName)
    { 
        if ($requestName !== 'sales_order_grid_data_source') {
            return $collection;
        }

        if ($collection->getMainTable() === $collection->getResource()->getTable('sales_order_grid')) {
            try {
                 
                $shiptimizeTable = $collection->getResource()->getTable('shiptimize');
               
                $collection->getSelect()->joinLeft(
                    ['shiptimize' => $shiptimizeTable],
                    'main_table.entity_id = shiptimize.shiptimize_order_id',
                    ['shiptimize_order_id','shiptimize_pickup_label','shiptimize_pickup_id','shiptimize_message','shiptimize_status']
                );

                error_log( $collection->getSelect() );
            } catch (\Zend_Db_Select_Exception $selectException) {
                // Do nothing in that case
               error_log($selectException);
            }
        }

        return $collection;
    }
}