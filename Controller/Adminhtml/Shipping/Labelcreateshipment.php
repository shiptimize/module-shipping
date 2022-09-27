<?php
namespace Shiptimize\Shipping\Controller\Adminhtml\Shipping;

use Shiptimize\Shipping\Model\ShiptimizeMagento; 
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Backend\App\Action\Context;

class Labelcreateshipment extends \Magento\Framework\App\Action\Action
{
    protected $shipments; 
    protected $shiptimize;  
    protected $redirectFactory; 
    protected $backendHelper; 

     /**
     * @param Context $context
     * @param \Magento\Ui\Component\MassAction\Filter $filter,
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
     * @param \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
     */
    public function __construct(
        Context $context, 
        RedirectFactory $redirectFactory,
        ShipmentRepositoryInterface $shipments,
        ShiptimizeMagento $shiptimize,
        \Magento\Backend\Helper\Data $backendHelper
    ) {
        parent::__construct($context);
 
        $this->redirectFactory = $redirectFactory; 
        $this->shipments = $shipments;
        $this->shiptimize = $shiptimize; 
        $this->backendHelper = $backendHelper;
    }
  

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {

        $shipmentid = $this->_request->getParam('shipment_id'); 

        # Get the order id from shipment_id 
        $shipment = $this->shipments->get($shipmentid); 
        $orderid = $shipment->getOrderId();

        $labelresponse =  $this->shiptimize->printLabel( array( 
            array(
                'orderid' => $orderid,
                'shipmentid' => $shipmentid
            )
        )); 
    
        $url = $this->backendHelper->getUrl('sales/shipment/view/', array(
                'shipment_id' => $shipmentid)
        ); 
        return $this->redirectFactory->create()->setPath($url, ['_current' => true]);
    }
}
