<?php
namespace Shiptimize\Shipping\Controller\Adminhtml\Shipping;

use Shiptimize\Shipping\Controller\Adminhtml\Abstracts\Shiptimize;
use Magento\Framework\View\Result\PageFactory;

class Labelcreate extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
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
        parent::__construct($context, $filter);

        $this->collectionFactory = $collectionFactory;
        $this->shiptimize = $shiptimize;
        $this->redirectFactory = $redirectFactory;
    }


    protected function massAction(\Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection $collection)
    {
        error_log("massAction::labelcreate");
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $items = $collection->getItems();

        if(!empty($items)){
            $orderIds = array();
            foreach ( $items as $item ) {
                $data = $item->getData();
                array_push($orderIds, $data['entity_id']);
            }

            $this->shiptimize->printLabel($orderIds,0,1);
        }
     
        return $this->redirectFactory->create()->setPath('sales/order/index', ['_current' => true]);
    }
}
