<?php
namespace Shiptimize\Shipping\Controller\Api;

if (!interface_exists("Magento\Framework\App\CsrfAwareActionInterface")){
    return; 
}

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\CsrfAwareActionInterface; 

class Update extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
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
 
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
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
