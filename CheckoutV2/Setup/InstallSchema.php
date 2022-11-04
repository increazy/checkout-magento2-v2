<?php
namespace Increazy\CheckoutV2\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema  implements InstallSchemaInterface
{
	public function install( SchemaSetupInterface $setup, ModuleContextInterface $context ) {
        $installer = $setup;
		$installer->startSetup();
        $connection = $setup->getConnection();

        if (!$connection->tableColumnExists('sales_order', 'increazy_transaction_id')) {
            $connection->addColumn(
                $setup->getTable('sales_order'),
                'increazy_transaction_id',
                [
                    'type' => Table::TYPE_TEXT,
                    'length' => 255,
                    'nullable' => true,
                    'default' => '',
                    'comment' => 'Increazy payment ID'
                ]
            );
        }

		$installer->endSetup();

	}
}
