<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400151337_CreateReturnRefundTable extends Migration
{
	public function up()
	{
		$this->run("
			CREATE TABLE IF NOT EXISTS `return_refund` (
				`return_id` int(11) unsigned NOT NULL,
				`refund_id` int(11) unsigned NOT NULL,
				PRIMARY KEY (`return_id`, `refund_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");
	}

	public function down()
	{
		$this->run("
			DROP TABLE IF EXISTS `return_refund`
		");
	}
}