<?php
namespace Shiptimize\Shipping\Controller\Adminhtml\Shipping;

use Shiptimize\Shipping\Controller\Adminhtml\Abstracts\Shiptimize;
use Magento\Framework\View\Result\PageFactory;

class ExportAll extends \Magento\Framework\App\Action\Action
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
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory
    ) {
        parent::__construct($context);

        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->shiptimize = $shiptimize;
        $this->redirectFactory = $redirectFactory;
    }
  

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $this->shiptimize->exportAll();
        return $this->redirectFactory->create()->setPath('sales/order/index', ['_current' => true]);
    }
}
