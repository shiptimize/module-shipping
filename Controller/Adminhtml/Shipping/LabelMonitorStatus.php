<?php
namespace Shiptimize\Shipping\Controller\Adminhtml\Shipping;

use Shiptimize\Shipping\Controller\Adminhtml\Abstracts\Shiptimize;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Shiptimize\Shipping\Model\Core\ShiptimizeOrder;

class LabelMonitorStatus extends \Magento\Framework\App\Action\Action
{
     
    /**
     * @param Context $context
     * @param \Magento\Ui\Component\MassAction\Filter $filter,
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
     * @param \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Ui\Component\MassAction\Filter $filter,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $collectionFactory,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize,
        \Shiptimize\Shipping\Model\ShiptimizeOrderMagentoFactory $shiptimizeOrderFactory,
        \Magento\Framework\Controller\Result\JsonFactory $jsonfactory,
        \Magento\Sales\Api\Data\OrderInterfaceFactory $orderFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($context);

        $this->filter = $filter;
        $this->jsonfactory = $jsonfactory;
        $this->shiptimizeOrderFactory = $shiptimizeOrderFactory; 
        $this->shiptimize = $shiptimize; 
        $this->orderFactory = $orderFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
    }
  

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {  

        $callbackurl = $this->getRequest()->getParam("callbackUrl"); 
        $resultJson = $this->jsonfactory->create();

        if (!$callbackurl) {
            return $resultJson->setData(array(
                "Error" => "Empty callbackurl cannot get status"
            ));
        }

        error_log("Checking label status using $callbackurl");

        $response = $this->shiptimize->getApi()->monitorLabelStatus($callbackurl);  

        if (isset($response->response->Finished) && $response->response->Finished == 100) {
          if (isset($response->response->ClientReferenceCodeList)) {
            foreach($response->response->ClientReferenceCodeList as $labelresult) {
              $orderid = $labelresult->ReferenceCode; 
              $shipmentid = 0; 
              
              $this->shiptimize->log("Label print finished orderid $orderid " . stripos($orderid, "--"));
              
              if(stripos($orderid, "--") !== false) {
                $parts = explode('--', $orderid);
                $shipmentid = $parts[1];
                $orderid = $parts[0];

                $this->shiptimize->log("Label print finished found shipmentid  " . implode(" " , $parts));
              }

              $order = $this->shiptimizeOrderFactory->create();
              # Sometimes the client reference (increment_id) does not match the shopitemid (entity_id)
              $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $orderid, 'eq')->create();
              $results = $this->orderRepository->getList($searchCriteria)->getItems();

              # Make sure we're adding the entity_id not increment_id
              if (!empty($results)) {
                $searchedorder = array_pop($results);  
                $orderid = $searchedorder->getId();
              }

              $order->bootstrap($orderid, $shipmentid);
              $status = ShiptimizeOrder::$LABEL_STATUS_NOT_REQUESTED; 
              $msg =  '';
              $labelurl = ''; 

              if ($labelresult->Error->Id == 0 ) {
                $status = ShiptimizeOrder::$LABEL_STATUS_PRINTED; 
                $labelurl = $response->response->LabelFile; // all labels in this batch share the same url  
                $msg = $this->shiptimize->__('labelprinted');
              } 
              else {
                $status = ShiptimizeOrder::$LABEL_STATUS_ERROR; 
                $msg = $labelresult->Error->Info;
              }

              $labelresult->message = $msg; 
              $order->addMessage($msg); 
              $order->setStatus($status);
              $order->setTrackingId($labelresult->TrackingId,'');
            }    
          }  
        }

        return $resultJson->setData($response); 
    }
}
