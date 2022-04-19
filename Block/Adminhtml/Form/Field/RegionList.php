<?php
namespace Shiptimize\Shipping\Block\Adminhtml\Form\Field;

class RegionList extends \Magento\Config\Block\System\Config\Form\Field
{
    private \Magento\Directory\Model\Country $country; 
     
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
        \Magento\Backend\Block\Template\Context $context,
        array $data = [],
        \Magento\Directory\Model\Country $country
    ) {
        parent::__construct($context, $data);
        $this->country = $country;
    }

    private function getRegions()
    {
        $html = '<table>
            <thead>
                <tr>
                    <td>Id</td>
                    <td>Country Code</td>
                    <td>Name</td> 
                </tr>
            </thead>';
        $html .= $this->getRegionsForCountry('pt'); 
        $html .= $this->getRegionsForCountry('es'); 
        $html .= $this->getRegionsForCountry('nl'); 
        $html .= $this->getRegionsForCountry('de'); 
        $html .= '</table>'; 
        return $html; 
    }

    private function getRegionsForCountry($isocode)
    {

        $regionsCol = $this->country->loadByCode($isocode)->getRegions();
        $regions = $regionsCol->loadData()->toOptionArray(false);
        $html = '';
        if(!empty($regions)) { 
            
            foreach ($regions as $reg)
            { 
                    $html .= '<tr>';  
                    if ($reg['value']) 
                    {
                          
                        $html .= '<td>' . $reg['value'] . '</td>'; 
                        $html .= '<td>' . $reg['country_id'] . '</td>'; 
                        $html .= '<td>' . $reg['label'] . '</td>'; 
                    }
            }
        } 
        return $html; 
    }

    /**
     * Render HTML
     * This should return a table row
     *
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $html = '<tr><td colspan="3"> 

        <div class="_collapsed _show" data-collapsible="true" role="tablist" id="carrierids" style="width:80%; margin:0 auto;">
                <div class="admin__page-nav-title title _collapsible" data-role="title" role="tab" aria-selected="true" aria-expanded="true" tabindex="0">
                    <strong>Regions</strong>
                </div>

                <ul class="admin__page-nav-items items" data-role="content" role="tabpanel" aria-hidden="false" style="display: block;">
                                                                <li class="admin__page-nav-item item
                            separator-top                                                         _last">';

        $html .= $this->getRegions();
        $html .= '</li></ul>

            </div>
            <script>
                function initCollapsibleRegions(){
                    var eRegions = jQuery("#regionlist"); 

                    if(typeof(eRegions.collapsible) == "undefined"){
                        setTimeout(initCollapsibleRegions, 500); 
                        return; 
                    }

                    eRegions.collapsible();
                }  

                setTimeout( () => {
                    initCollapsibleRegions();     
                },500);
                
            </script>';


        return $html . "</td></tr>";
    }
}