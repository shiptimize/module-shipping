<?php

namespace Shiptimize\Shipping\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class InstallSchema implements \Magento\Framework\Setup\InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        error_log(" Setup Shiptimize [" . $context->getVersion() . ']');
        if (version_compare($context->getVersion(), '3.0.0', '<')) {
            $connection = $installer->getConnection();
            
            error_log("Adding shiptimze custom table rates ");
            $ratesTable = $connection->newTable($installer->getTable('shiptimize_customtablerates'))
                ->addColumn(
                    'id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [ 'identity' => true, 'unsigned' => true, 'nullable' => false,
                                    'primary' => true, 'comment' => 'the rule id']
                )->addColumn(
                    'dest_country_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    4,
                    ['nullable' => true, 'comment' => 'iso2 or iso3']
                )->addColumn(
                    'dest_region_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    90,
                    ['nullable' => false, 'comment' => 'region code']
                )->addColumn(
                    'dest_zip',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    10,
                    ['nullable' => false, 'comment' => 'specific zip']
                )->addColumn(
                    'min_price',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    '10,4',
                    [  'unsigned' => true, 'nullable' => false,'comment' => 'min price']
                )->addColumn(
                    'min_weight',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    '10,4',
                    [  'unsigned' => true, 'nullable' => false,'comment' => 'min weight']
                )->addColumn(
                    'min_items',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [  'unsigned' => true, 'nullable' => false, 'comment' => 'min items']
                )->addColumn(
                    'carrier_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    90,
                    ['nullable' => true, 'comment' => 'the numeric id of the shiptimize carrier']
                )->addColumn(
                    'carrier_options',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    90,
                    ['nullable' => true, 'comment' => 'options']
                )->addColumn(
                    'price',
                    \Magento\Framework\DB\Ddl\Table::TYPE_DECIMAL,
                    '10,4',
                    [  'unsigned' => true, 'nullable' => false,'comment' => 'price for this rule']
                )->addColumn(
                    'display_name',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    90,
                    ['nullable' => true, 'comment' => 'what to show on checkout']
                )->addColumn(
                    'has_pickup',
                    \Magento\Framework\DB\Ddl\Table::TYPE_SMALLINT,
                    null,
                    [  'unsigned' => true, 'nullable' => false, 'comment' => 'does this rule provide Servicepoints']
                );

            $installer->getConnection()->createTable($ratesTable);

            error_log("Adding shiptimze table ");
            $table = $connection->newTable($installer->getTable('shiptimize'))
                ->addColumn(
                    'shiptimize_order_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [ 'identity' => false, 'unsigned' => true, 'nullable' => false, 'primary' => false,
                    'comment' => 'Order id of magento']
                )
                ->addColumn(
                    'shiptimize_status',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [ 'identity' => false, 'unsigned' => true, 'nullable' => true,
                                    'primary' => false, 'comment' => 'Exported to shiptimize Status']
                )->addColumn(
                    'shiptimize_tracking_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    90,
                    [ 'identity' => false, 'unsigned' => true, 'nullable' => true,
                                    'primary' => false, 'comment' => 'Tracking_id']
                )
                ->addColumn(
                    'shiptimize_carrier_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                    null,
                    [ 'identity' => false, 'unsigned' => true, 'nullable' => true,
                                    'primary' => false, 'comment' => 'Shiptimize carrier id']
                )
                ->addColumn(
                    'shiptimize_pickup_id',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    100,
                    [ 'identity' => false, 'unsigned' => true, 'nullable' => true,
                                    'primary' => false, 'comment' => 'Shiptimize carrier id']
                )
                ->addColumn(
                    'shiptimize_pickup_label',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable' => true, 'comment' => 'Pickup Label']
                )
                ->addColumn(
                    'shiptimize_pickup_extended',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    255,
                    ['nullable' => true, 'comment' => 'Pickup Extended info']
                )
                ->addColumn(
                    'shiptimize_message',
                    \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                    null,
                    ['nullable' => true, 'comment' => 'Export status']
                )
                ->addIndex(
                    $installer->getIdxName(
                        'identifier',
                        ['shiptimize_order_id', 'shiptimize_order_id'],
                        \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE
                    ),
                    ['shiptimize_order_id', 'shiptimize_order_id'],
                    ['type' => \Magento\Framework\DB\Adapter\AdapterInterface::INDEX_TYPE_UNIQUE]
                )
                ->setComment('Shiptimize status table');
                    $installer->getConnection()->createTable($table);
        }
        $setup->endSetup();
    }
}
