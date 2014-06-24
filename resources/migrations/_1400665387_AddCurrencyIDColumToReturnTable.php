<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400665387_AddCurrencyIDColumToReturnTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return`
			ADD `currency_id` char(3) DEFAULT NULL AFTER deleted_by
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return`
			DROP `currency_id`
		");
	}
}