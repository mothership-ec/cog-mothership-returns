<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1402051241_AddReturnedStockColumn extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			ADD `returned_stock` tinyint(1) NOT NULL DEFAULT 0 AFTER `returned_stock_location`
		");

		$this->run("
			UPDATE `return_item`
			SET
				`returned_stock` = 1
			WHERE
				`status_code` = 2200
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			DROP `returned_stock`
		");
	}
}