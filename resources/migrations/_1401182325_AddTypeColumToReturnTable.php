<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1401182325_AddCurrencyIDColumToReturnTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return`
			ADD `type` varchar(255) NOT NULL DEFAULT 'web' AFTER deleted_by
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return`
			DROP `type`
		");
	}
}