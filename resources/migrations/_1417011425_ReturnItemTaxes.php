<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1417011425_ReturnItemTaxes extends Migration
{
	public function up()
	{
		$this->run("
			CREATE TABLE IF NOT EXISTS `return_item_tax` (
			  `return_item_id` int(11) unsigned NOT NULL,
			  `tax_type` varchar(30) NOT NULL,
			  `tax_rate` decimal(10,3) unsigned NOT NULL,
			  `tax_amount` decimal(10,2) unsigned NOT NULL,
			  PRIMARY KEY (`return_item_id`,`tax_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}

	public function down()
	{
		$this->run("DROP TABLE IF EXISTS `return_item_tax`; ");
	}
}