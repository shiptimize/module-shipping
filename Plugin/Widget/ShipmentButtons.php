<?php 
namespace Shiptimize\Shipping\Plugin\Widget; 

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\ObjectManagerInterface;
use Shiptimize\Shipping\Model\ShiptimizeMagento; 

class ShipmentButtons {
	
	private $shiptimize; 
    protected $object_manager;
    protected $backendHelper;

    /**
     * Context constructor.
     *
     * @param BackendTemplate             $template
     * @param ButtonListFactory           $buttonList
     * @param ShipmentRepositoryInterface $shipments
     * @param LabelRepositoryInterface    $labelRepository
     * @param ScopeConfigInterface        $scopeConfig
     */
    public function __construct(
        ObjectManagerInterface $om, 
        \Magento\Backend\Helper\Data $backendHelper,
        ShiptimizeMagento $shiptimize
    ) {
        $this->object_manager = $om;
        $this->backendHelper = $backendHelper;
        $this->shiptimize = $shiptimize; 
    }

    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {

        $this->_request = $context->getRequest();  
        if($this->_request->getFullActionName() == 'adminhtml_order_shipment_view')
        {

            $referenceblock = $context->getLayout()->getBlock('head.components'); 
            $referenceblock->addChild( 
                'shiptimize_scripts',
                'Magento\Framework\View\Element\Template', 
                array( 
                    'template' => 'Shiptimize_Shipping::Sales/shiptimize.phtml',
                    'class' => 'shiptimize-scripts'
                )
            );

            $shipmentid = $this->_request->getParam('shipment_id');
            
            $url = $this->backendHelper->getUrl('shiptimize/shipping/labelcreateshipment', array(
                'shipment_id' => $shipmentid)
            ); 

            $buttonList->add(
                'shiptimize_create_label_btn',
                [
                    'label' => 'Shiptimize - ' .  $this->shiptimize->__('Create Label'),
                    'onclick' => "window.open('$url', '_self')",
                    'class' => 'shiptimize-create-label save primary'
                ],
                -1
            );
        }
    } 
}