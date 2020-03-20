<?php
namespace Shiptimize\Shipping\Controller\Api;

use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Update extends \Magento\Framework\App\Action\Action  implements \Magento\Framework\App\CsrfAwareActionInterface
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
     * @param RequestInterface $request
     *
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Validation is done using the private key and a hash on the apiUpdate method
     * @param RequestInterface $request
     *
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
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
