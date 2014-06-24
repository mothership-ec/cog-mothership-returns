<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1399565209_RemoveResolutionFromReturnItem extends Migration
{
	public function up()
	{
		$this->run("
			ALTER TABLE `return_item`
			DROP resolution
		");
	}

	public function down()
	{
		$this->run("
			ALTER TABLE `return_item`
			ADD `resolution` varchar(255) NOT NULL
		");
	}
}