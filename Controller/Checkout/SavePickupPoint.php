<?php
namespace Shiptimize\Shipping\Controller\Checkout;
  
class SavePickupPoint extends \Magento\Framework\App\Action\Action
{
    /**
     * @param Context $context
     * @param \Magento\Checkout\Model\Session $_checkoutSession,
     * @param \Magento\Framework\App\Request\Http $request,
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Magento\Framework\App\Request\Http $request,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $_checkoutSession;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
    }

    /**
     * The goal of this endpoint is to return a geolocatable address
     * correct up to the region we don't  care about exact locations
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
      
        $this->_checkoutSession->setShiptimizePickupId($this->request->getParam("PointId"));
        $this->_checkoutSession->setShiptimizePickupLabel($this->request->getParam("Label"));
        $this->_checkoutSession->setShiptimizePickupExtendedInfo($this->request->getParam("Extendedinfo"));
  
        $resultJson->setData(array("tekst"=>"OK"));
        return $resultJson;
    }
}
