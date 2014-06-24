<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400496399_CreateReturnPaymentTable extends Migration
{
	public function up()
	{
		$this->run("
			CREATE TABLE IF NOT EXISTS `return_payment` (
				`return_id` int(11) unsigned NOT NULL,
				`payment_id` int(11) unsigned NOT NULL,
				PRIMARY KEY (`return_id`, `payment_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}

	public function down()
	{
		$this->run("
			DROP TABLE IF EXISTS `return_payment`
		");
	}
}