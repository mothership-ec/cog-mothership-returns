<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1401184475_UpdateDataToMatchMigrationState extends Migration
{
	/**
	 * Updates old data to match the current migration state.
	 *
	 * - returned_value from order_item.actual_price
	 * - remaining_balance from balance
	 * - calculated_balance from order_item.actual_price - exchange_item.actual_price
	 * - balance from calculated_balance
	 * - actual_price from order_item.actual_price
	 * - completed_at from order_item_status.created_at (for 2200)
	 * - completed_by from order_item_status.created_by (for 2200)
	 */
	public function up()
	{
		// remaining_balance from balance
		$this->run("
			UPDATE
				return_item
			SET
				remaining_balance = balance
		");

		// returned_value from order_item.actual_price
		// calculated_balance from order_item.actual_price - exchange_item.actual_price
		// actual_price from order_item.actual_price
		// balance from calculated_balance
		$this->run("
			UPDATE
				return_item ri
			LEFT JOIN
				order_item oi ON (ri.item_id = oi.item_id)
			LEFT JOIN
				order_item ei ON (ri.exchange_item_id = ei.item_id)
			SET
				ri.returned_value     = oi.actual_price,
				ri.calculated_balance = oi.actual_price - IFNULL(ei.actual_price, 0),
				ri.actual_price       = oi.actual_price,
				ri.balance            = CASE
					WHEN ri.balance IS NOT NULL THEN oi.actual_price - IFNULL(ei.actual_price, 0)
					ELSE ri.balance = ri.balance
				END
		");

		// completed_at from order_item.status.created_at (for 2200)
		// completed_by from order_item.status.created_by (for 2200)
		$this->run("
			UPDATE
				return_item ri
			LEFT JOIN
				order_item_status ois ON (ri.item_id = ois.item_id AND ois.status_code = 2200)
			SET
				ri.completed_at = ois.created_at,
				ri.completed_by = ois.created_by
		");
	}

	public function down()
	{
		$this->run("

		");
	}
}