<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400584201_ChangeRemainingBalanceColumn extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			CHANGE `remaining_balance` `remaining_balance` decimal(10,2) DEFAULT NULL
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			CHANGE `remaining_balance` `remaining_balance` int(11) unsigned DEFAULT NULL
		");
	}
}