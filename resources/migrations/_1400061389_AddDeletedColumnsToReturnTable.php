<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1400061389_AddDeletedColumnsToReturnTable extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return`
			ADD `deleted_at` int(11) unsigned DEFAULT NULL,
			ADD `deleted_by` int(11) unsigned DEFAULT NULL
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return`
			DROP `deleted_at`,
			DROP `deleted_by`
		");
	}
}