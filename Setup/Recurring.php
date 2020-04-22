<?php
namespace Shiptimize\Shipping\Setup;

use Shiptimize\Shipping\Model\ShiptimizeMagento;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class Recurring implements InstallSchemaInterface {

    public function __construct(ShiptimizeMagento $shiptimize){

        $this->shiptimize = $shiptimize;
    }

    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {    
        $this->shiptimize->refreshToken(); 
    }
}