<?php 

namespace Shiptimize\Shipping\Ui\Column;

use Shiptimize\Shipping\Model\Core\ShiptimizeOrder;

class ShiptimizeColumn extends \Magento\Ui\Component\Listing\Columns\Column {

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param SystemStore $systemStore
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     * @param string $storeKey
     */
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory, 
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        array $components = [],
        array $data = []
    ) { 
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->shiptimize = $shiptimize; 
    }


    /**
     * returns a representation of this status
     * @param int status_id
     * @param string message
     * @return string containing the html representation of this order's status
     */
    private function getStatusIcon($status, $message, $pickupLabel, $orderid = '')
    {     
        $class = 'shiptimize-icon shiptimize-tooltip-reference ';

        if ($status) {
            if (strlen($message > 100)) {
                $class .= ' shiptimize-message-large ';
            }

            switch ($status) {
                case ShiptimizeOrder::$STATUS_EXPORTED_SUCCESSFULLY:
                  $class .= 'shiptimize-icon-success';
                  break; 
                case ShiptimizeOrder::$STATUS_EXPORT_ERRORS:  
                  $class .= 'shiptimize-icon-error'; 
                  break;
                case ShiptimizeOrder::$STATUS_TEST_SUCCESSFUL: 
                  $class .= 'shiptimize-icon-test-successful';
                  break; 
                case ShiptimizeOrder::$LABEL_STATUS_PRINTED: 
                  $class .= 'shiptimize-icon-print-printed';
                  break; 

                case ShiptimizeOrder::$LABEL_STATUS_ERROR: 
                  $class .= 'shiptimize-icon-print-error'; 
                  break; 

                case ShiptimizeOrder::$LABEL_STATUS_NOT_REQUESTED: 
                  $class .= 'shiptimize-icon-print-notprinted'; 
                  break;

                default: //Not exported or no status 
                  $class .= ' shiptimize-icon-not-exported';   
                  $message = $this->shiptimize->__('Not Exported');
                  break;
            }
        } else {
            $message = $this->shiptimize->__('Not Exported');
            $class .= 'shiptimize-icon-not-exported';
        }

        if ($pickupLabel) {
            $message .= '<br/>'.$this->shiptimize->__('Pickup Point').' -  '.$pickupLabel;
        }

        return '<span class="shiptimize-status shiptimize-tooltip-wrapper"><span id="shiptimize-label' . $orderid . '" class="'.$class.'"></span><span class="shiptimize-tooltip-message"><span class="shiptimize-tooltip-message__arrow"></span><span class="shiptimize-tooltip__inner">'.$message.'</span></span></span>';
    } 

    /**
    * Prepare Data Source.
    *
    * @param array $dataSource
    *
    * @return array
    */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) { 
                $status = isset($item['shiptimize_status']) ? $item['shiptimize_status'] : ShiptimizeOrder::$STATUS_NOT_EXPORTED; 
                $item['shiptimize_order_id'] = html_entity_decode(  $this->getStatusIcon($status,$item['shiptimize_message'],$item['shiptimize_pickup_label'],$item['increment_id']) );  
            }
        }

        return $dataSource;
    }

}