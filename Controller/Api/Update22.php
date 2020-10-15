<?php
namespace Shiptimize\Shipping\Controller\Api;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

/** 
* Requests in magento 2.2 have a different format 
*/
class Update22 extends \Magento\Framework\App\Action\Action
{
 
    private $shiptimize;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @param Context $context
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
     * @param \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Shiptimize\Shipping\Model\ShiptimizeMagento $shiptimize
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->shiptimize = $shiptimize;
    }
 

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultJsonFactory->create();
        $response =  $this->shiptimize->apiUpdate();
        $resultJson->setData($response);

        return $resultJson;
    }
}
