<?php
/** 
 *  This class is used to append info to the order details 
 */ 
namespace Shiptimize\Shipping\Block\Adminhtml\Sales;
  
use \Shiptimize\Shipping\Model\ShiptimizeMagento;

class OrderExtraInfo extends \Magento\Framework\View\Element\Template
{  
    protected $shiptimize_order = null; 
    protected $shiptimize = null; 

    /**
     * CustomButton constructor.
     *
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param Context $context
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
     */
    public function __construct( 
        \Magento\Framework\View\Element\Template\Context $context,  
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagento $shiptimize_order,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        array $data = []
    ) {          
        parent::__construct($context, $data);
        $this->shiptimize_order = $shiptimize_order;
        $this->shiptimize = $shiptimize; 

        if($this->shiptimize->is_dev){
            error_log("\n\nOrderExtraInfo::__construct");
        }
    } 

    protected function _beforeToHtml()
    {
        $this->shiptimize_order->bootstrap($this->getRequest()->getParam('order_id'));

    }

    public function getPointTitle()
    {
        if($this->shiptimize->is_dev){
            error_log("\n\nOrderExtraInfo getPointTitle");
        }
        return $this->shiptimize->__('Pickup Point'); 
    }


    /** 
     * @return the shiptimize metadata for this order 
     */
    public function getShiptimizeMeta() 
    {  
        return $this->shiptimize_order->getOrderMeta();
    }


}

