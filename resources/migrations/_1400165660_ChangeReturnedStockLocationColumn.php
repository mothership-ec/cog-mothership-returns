<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400165660_ChangeReturnedStockLocationColumn extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			CHANGE `return_stock_location_id` `returned_stock_location` varchar(255) DEFAULT NULL
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			CHANGE `returned_stock_location` `return_stock_location_id` int(11) unsigned DEFAULT NULL
		");
	}
}