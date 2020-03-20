<?php
namespace Shiptimize\Shipping\Controller\Api;
 
use Magento\Framework\App\Action\Context;

class GetPickupPoints extends \Magento\Framework\App\Action\Action
{

    private $resultJsonFactory;
    private $shiptimize;

    /**
     * @param Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
     * @param \Magento\Framework\App\Request\Http $request,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory,
        \Magento\Framework\App\Request\Http $request,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
    ) {
        parent::__construct($context);
        $this->shiptimize = $shiptimize;
        $this->resultJsonFactory = $jsonResultFactory;
        $this->request = $request;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $address = $this->request->getParam('Address');
        $shipping_method_id = $this->request->getParam('CarrierId');
        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $response =  $this->shiptimize->getPickupLocations($address, $shipping_method_id);
        $resultJson->setData(json_decode($response));

        return $resultJson;
    }
}
