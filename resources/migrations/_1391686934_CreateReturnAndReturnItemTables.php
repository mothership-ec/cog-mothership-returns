<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1391686934_CreateReturnAndReturnItemTables extends Migration
{
	public function up()
	{
		// 1. Create the new tables

		$this->run("
			CREATE TABLE IF NOT EXISTS `return` (
				return_id    int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				document_id  int(11) unsigned NULL,
				created_at   int(11) unsigned NOT NULL,
				created_by   int(11) unsigned NULL,
				updated_at   int(11) unsigned NULL,
				updated_by   int(11) unsigned NULL,
				completed_at int(11) unsigned NULL,
				completed_by int(11) unsigned NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");

		$this->run("
			CREATE TABLE IF NOT EXISTS return_item (
				return_item_id           int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				return_id                int(11) unsigned NOT NULL,
				order_id                 int(11) unsigned NULL,
				item_id                  int(11) unsigned NULL,
				exchange_item_id         int(11) unsigned NULL,
				note_id                  int(11) unsigned NULL,
				status_code              int(11) NOT NULL,
				created_at               int(11) unsigned NULL,
				created_by               int(11) unsigned NULL,
				updated_at               int(11) unsigned NULL,
				updated_by               int(11) unsigned NULL,
				completed_at             int(11) unsigned NULL,
				completed_by             int(11) unsigned NULL,
				reason                   varchar(255) NOT NULL,
				resolution               varchar(255) NOT NULL,
				accepted                 tinyint(1) NULL,
				balance                  decimal(10,2) NULL,
				calculated_balance       decimal(10,2) NOT NULL,
				returned_value           decimal(10,2) NOT NULL,
				return_stock_location_id int(11) unsigned NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");


		// 2. Port the data from the existing table into the new tables

		$this->run("
			INSERT INTO `return` (
				return_id,
				document_id,
				created_at,
				created_by,
				completed_at,
				completed_by
			)
			SELECT
				oir.return_id,
				oir.document_id,
				os.created_at,
				os.created_by,
				IFNULL(oir.completed_at, ois.created_at),
				IFNULL(oir.completed_by, ois.created_by)
			FROM order_item_return oir
			LEFT JOIN order_item_status ois ON (
				oir.item_id = ois.item_id AND
				ois.status_code = 2200
			)
			LEFT JOIN order_summary os ON (
				oir.order_id = os.order_id
			);
		");

		// i hate mysql sometimes
		$statuses = $this->_query->run("
			SELECT
				oir.return_id,
				MAX(ois.created_at) as latest_at,
				ois.created_by
			FROM order_item_status ois
			LEFT JOIN order_item_return oir ON (
				oir.item_id = ois.item_id
			)
			GROUP BY oir.item_id
		");
		foreach ($statuses as $status) {
			$this->_query->run("
				UPDATE `return`
				SET
					updated_at = :updatedAt,
					updated_by = :updatedBy
				WHERE
					return_id = :returnID
			", [
				'updatedAt' => $status->latest_at,
				'updatedBy' => $status->created_by,
				'returnID' => $status->return_id
			]);
		}

		$this->run("
			INSERT INTO return_item (
				return_id,
				order_id,
				item_id,
				exchange_item_id,
				note_id,
				status_code,
				created_at,
				created_by,
				updated_at,
				updated_by,
				completed_at,
				completed_by,
				reason,
				resolution,
				accepted,
				balance,
				calculated_balance,
				returned_value,
				return_stock_location_id
			)
			SELECT
				oir.return_id,
				oir.order_id,
				oir.item_id,
				oir.exchange_item_id,
				oir.note_id,
				(
					SELECT ois.status_code
					FROM order_item_status ois
					WHERE ois.item_id = oir.item_id
					ORDER BY created_at DESC
					LIMIT 1
				),
				r.created_at,
				r.created_by,
				r.updated_at,
				r.updated_by,
				r.completed_at,
				r.completed_by,
				oir.reason,
				oir.resolution,
				oir.accepted,
				oir.balance,
				oir.calculated_balance,
				oir.returned_value,
				oir.return_to_stock_location_id
			FROM order_item_return oir
			LEFT JOIN `return` r ON (
				r.return_id = oir.return_id
			)
		");


		// 3. Drop the old table

		$this->run("
			DROP TABLE IF EXISTS order_item_return
		");
	}

	public function down()
	{
		$this->run("
			CREATE TABLE order_item_return (
				return_id int(11) unsigned NOT NULL AUTO_INCREMENT,
				order_id int(11) unsigned NOT NULL,
				item_id int(11) unsigned NOT NULL,
				document_id int(11) unsigned NOT NULL,
				created_at int(11) unsigned NOT NULL,
				created_by int(11) unsigned DEFAULT NULL,
				updated_at int(11) unsigned DEFAULT NULL,
				updated_by int(11) unsigned DEFAULT NULL,
				completed_at int(11) unsigned DEFAULT NULL,
				completed_by int(11) unsigned DEFAULT NULL,
				exchange_item_id int(11) unsigned DEFAULT NULL,
				status_id int(11) NOT NULL,
				reason varchar(255) NOT NULL DEFAULT '',
				resolution varchar(255) NOT NULL DEFAULT '',
				balance decimal(10,2) DEFAULT NULL,
				calculated_balance decimal(10,2) NOT NULL,
				accepted tinyint(1) DEFAULT NULL,
				returned_value decimal(10,2) unsigned DEFAULT NULL,
				return_to_stock_location_id int(11) unsigned DEFAULT NULL,
				note_id int(11) unsigned DEFAULT NULL,
				PRIMARY KEY (return_id),
				KEY order_id (order_id),
				KEY item_id (item_id),
				KEY created_at (created_at),
				KEY created_by (created_by),
				KEY updated_at (updated_at),
				KEY updated_by (updated_by),
				KEY exchange_item_id (exchange_item_id),
				KEY return_to_stock_location_id (return_to_stock_location_id),
				KEY status_id (status_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		");

		$this->run("
			DROP TABLE IF EXISTS `return`
		");

		$this->run("
			DROP TABLE IF EXISTS return_item
		");
	}
}
