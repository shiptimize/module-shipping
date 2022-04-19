<?php
namespace Shiptimize\Shipping\Setup;

use Shiptimize\Shipping\Model\ShiptimizeMagento;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;


class Recurring implements InstallSchemaInterface {

    public function __construct(
        ShiptimizeMagento $shiptimize
    ){

        $this->shiptimize = $shiptimize;
    }

    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {    

        $installer = $setup;
        $installer->startSetup();
        $connection = $installer->getConnection(); 

        // Check if Portugal regions are declared 
        $regionTable =  $setup->getTable('directory_country_region'); 
        $ptregions = $connection->fetchAll('select * from ' . $regionTable . ' where country_id="PT"'); 
        
        if (empty($ptregions)) {
            error_log("No regions where found for PT, declaring base regions");
            $connection->query("insert into " . $regionTable . '(country_id, code, default_name) VALUES ("PT","PTM","Madeira")');
            $connection->query("insert into " . $regionTable . '(country_id, code, default_name) VALUES ("PT","PTS","S. Miguel")');
            $connection->query("insert into " . $regionTable . '(country_id, code, default_name) VALUES ("PT","PTI","Outras Ilhas")');
            $connection->query("insert into " . $regionTable . '(country_id, code, default_name) VALUES ("PT","PTC","Continente")');
        }

        $this->shiptimize->refreshToken();  
    }
}