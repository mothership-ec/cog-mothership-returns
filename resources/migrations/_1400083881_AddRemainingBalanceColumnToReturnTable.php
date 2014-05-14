<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400083881_AddRemainingBalanceColumnToReturnTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			ADD `remaining_balance` int(11) unsigned DEFAULT NULL AFTER `calculated_balance`
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			DROP `remaining_balance`
		");
	}
}