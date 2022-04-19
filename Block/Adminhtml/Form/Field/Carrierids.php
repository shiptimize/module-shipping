<?php
namespace Shiptimize\Shipping\Block\Adminhtml\Form\Field;

class Carrierids extends \Magento\Config\Block\System\Config\Form\Field
{
    private $scopeConfig;
     
     /**
      * @var array $carriers, the carriers as received from the api
      */
    private $carriers = [];

     /**
      * @override
      *
      * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
      * @param \Magento\Backend\Block\Template\Context $context,
      * @param array $data
      */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig = $scopeConfig;
    }

    private function getCarrierTable()
    {
        if (empty($this->carriers)) {
            return;
        }

        $html = '<table>
        <thead>
            <tr>
                <th>Id</th> 
                <th>Name</th> 
                <th>Options</th> 
            </tr>
        </thead>
        <tbody>';

        foreach ($this->carriers as $carrier) {
            $optionsbody = '';
            if (isset($carrier->OptionList)) {

                foreach ($carrier->OptionList as $option) {
                    if($option->Type == 1) {
                        if(isset($option->OptionValues)) { 
                            $optionsbody.= '<tr><td><b>' . $option->Name . ' ></b></td><td>';  
                            foreach ( $option->OptionValues as $optionValue) {
                               $optionsbody.='<br/>' . $optionValue->Name; 
                            }
                            $optionsbody .='</td></tr>';
                        }
                    }
                    /* 
                        65 - avondlevering - postnl 
                        42 - "Evening delivery" - DHL (2C)
                    */  
                    if ($option->Id == 65 || $option->Id == 42) {
                        $optionsbody.= "<tr>
                        <td></td>
                        <td>AvondLevering </td>   
                        </tr>";
                    }
                }
            }

            if ($carrier->HasPickup) {
                $optionsbody.="<tr> 
                    <td> ServicePoint </td>  
                    <td> </td>
                </tr>";
            }

            $options = $optionsbody ? '<table>'.$optionsbody.'</table>' : '';

            $html .= "<tr>
                <td>{$carrier->Id}</td>
                <td>{$carrier->Name}</td> 
                <td>{$options}</td>
            </tr>";
        }
        return $html.'</tbody></table>';
    }

    /**
     * Render HTML
     * This should return a table row
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    { 
        $carrierjson = $this->scopeConfig->getValue(
                'shipping/shiptimizeshipping/carriers',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$carrierjson) {
            return '';
        }

        try { 
            $this->carriers = json_decode($carrierjson);
        } catch (Exception $e) {
            error_log("Error getting carriers ". $e->getMessage());
            return '';
        }

        if (empty($this->carriers)) {
            return '';
        }

        $html = '<tr><td colspan="3"> 

        <div class="_collapsed _show" data-collapsible="true" role="tablist" id="carrierids" style="width:80%; margin:0 auto;">
                <div class="admin__page-nav-title title _collapsible" data-role="title" role="tab" aria-selected="true" aria-expanded="true" tabindex="0">
                    <strong>Carrier Ids</strong>
                </div>

                <ul class="admin__page-nav-items items" data-role="content" role="tabpanel" aria-hidden="false" style="display: block;">
                                                                <li class="admin__page-nav-item item
                            separator-top                                                         _last">';

        $html .= $this->getCarrierTable();
        $html .= '</li></ul>

            </div>
            <script>
                function initCollapsibleCarriers(){
                    var eCarriers = jQuery("#carrierids"); 

                    if(typeof(eCarriers.collapsible) == "undefined"){
                        setTimeout(initCollapsibleCarriers, 500); 
                        return; 
                    }

                    eCarriers.collapsible();
                }   
                setTimeout( () => {
                    initCollapsibleCarriers(); 
                },500);
            </script>';


        return $html . "</td></tr>";
    }
}
