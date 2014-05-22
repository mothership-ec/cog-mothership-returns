<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400690356_ChangeReturnedStockLocationColumn extends Migration
{
	public function up()
	{
		$this->run("
			UPDATE
				`return_item`
			SET
				`returned_stock_location` = 'web'
			WHERE
				`returned_stock_location` IS NULL
			OR	`returned_stock_location` = ''
		");

		$this->run("
			ALTER TABLE `return_item`
			CHANGE `returned_stock_location` `returned_stock_location` varchar(255) NOT NULL
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			CHANGE `returned_stock_location` `returned_stock_location` varchar(255) DEFAULT NULL
		");
	}
}