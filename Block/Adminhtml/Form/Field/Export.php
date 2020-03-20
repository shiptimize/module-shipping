<?php
namespace Shiptimize\Shipping\Block\Adminhtml\Form\Field;

class Export extends \Magento\Config\Block\System\Config\Form\Field
{ 
     /**
      * @override
      *
      * @param \Magento\Backend\Block\Template\Context $context
      * @param array $data
      * @param \Magento\Backend\Model\UrlInterface $backendUrl
      */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = [],
        \Magento\Backend\Model\UrlInterface $backendUrl
    ) {
        parent::__construct($context, $data); 
        $this->_backendUrl = $backendUrl; 
    }

   
    /**
     * Render element value - must return a table cell 
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _renderValue(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $url = $this->_backendUrl->getUrl("shiptimize/tableRates/export");
        return "<td><a href='".$url."'>Export</a></td>";
    }
}