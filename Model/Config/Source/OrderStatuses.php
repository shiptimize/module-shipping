<?php
/**
 */
namespace Shiptimize\Shipping\Model\Config\Source;

/**
 * Options provider for statuses list
 *
 * @api
 * @since 100.0.2
 */
class OrderStatuses implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Statuses
     *
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\Collection
     */
    protected $_statusCollection;

    /**
     * Options array
     *
     * @var array
     */
    protected $_options;

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection
     */
    public function __construct(\Magento\Sales\Model\ResourceModel\Order\Status\Collection $statusCollection)
    {
        $this->_statusCollection = $statusCollection;
    }

    /**
     * Return options array
     *
     * @param boolean $isMultiselect
     * @param string|array $foregroundCountries
     * @return array
     */
    public function toOptionArray($isMultiselect = false, $foregroundCountries = '')
    {
        if (!$this->_options) {
            $this->_options = $this->_statusCollection->loadData()->toOptionArray(
                false
            );
        }

        $options = $this->_options;
        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => __('--Please Select--')]);
        }

        return $options;
    }
}
