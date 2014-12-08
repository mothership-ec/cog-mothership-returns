<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1417011425_ReturnItemTaxes extends Migration
{
	public function up()
	{
		$this->run(
			"CREATE TABLE IF NOT EXISTS `return_item_tax` (
			  `return_item_id` int(11) unsigned NOT NULL,
			  `tax_type` varchar(30) NOT NULL,
			  `tax_rate` decimal(10,3) unsigned NOT NULL,
			  `tax_amount` decimal(10,2) unsigned NOT NULL,
			  PRIMARY KEY (`return_item_id`,`tax_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");

		$this->run(
			"INSERT INTO return_item_tax (return_item_id, tax_type, tax_rate, tax_amount)
			SELECT 
				return_item_id, 
				'VAT',
				tax_rate,
				tax
			FROM order_item WHERE tax > 0;
		");
	}

	public function down()
	{
		$this->run("DROP TABLE IF EXISTS `return_item_tax`; ");
	}
}