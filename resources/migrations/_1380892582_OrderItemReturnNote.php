<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1380638641_UpdateOrderCreate extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE
				`order_item_return`
			ADD
				`note_id` INT(11)
			UNSIGNED  NULL  DEFAULT NULL  AFTER `return_to_stock_location_id`;
		");
	}

	public function down()
	{
		$this->run('
			ALTER TABLE `order_item_return` DROP `note_id`;
		');
	}
}
