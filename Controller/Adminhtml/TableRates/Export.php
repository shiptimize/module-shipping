<?php
namespace Shiptimize\Shipping\Controller\Adminhtml\TableRates;
 
use Magento\Framework\App\Action\Context;

class Export extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Shiptimize\Shipping\Model\TableRatesModel $tableRates
     */
    private $tableRates;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Shiptimize\Shipping\Model\TableRatesModel $tableRates
     */
    public function __construct(Context $context, \Shiptimize\Shipping\Model\TableRatesModel $tableRates)
    {
        parent::__construct($context);
        $this->tableRates = $tableRates;
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $file = $this->tableRates->exportRates();

        header("Content-Description: File Transfer");
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: '.filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }
}
