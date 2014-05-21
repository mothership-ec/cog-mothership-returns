<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400665369_AddActualPriceToReturnItemTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			ADD `actual_price` decimal(10,2) unsigned NOT NULL AFTER list_price
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			DROP `actual_price`
		");
	}
}