<?php
namespace Shiptimize\Shipping\Model\Config\Source;

class StreetNumbers implements \Magento\Framework\Option\ArrayInterface
{ 
    /**
     * Return options array
     *
     * @param boolean $isMultiselect  
     * @return array
     */
    public function toOptionArray($isMultiselect = false)
    { 
       return [
            ['value' => '-1', 'label' => "-"],
            /*['value' => '0', 'label' => "1"],
            ['value' => '1', 'label' => "2"],*/
            ['value' => '2', 'label' => "3"],
            ['value' => '3', 'label' => "4"]
        ];
    }
}