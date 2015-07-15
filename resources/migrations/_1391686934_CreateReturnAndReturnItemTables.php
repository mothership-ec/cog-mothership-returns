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

				created_at               int(11) unsigned NULL,
				created_by               int(11) unsigned NULL,
				updated_at               int(11) unsigned NULL,
				updated_by               int(11) unsigned NULL,
				completed_at             int(11) unsigned NULL,
				completed_by             int(11) unsigned NULL,

				status_code              int(11) NOT NULL,
				reason                   varchar(255) NOT NULL,
				resolution               varchar(255) NOT NULL,
				accepted                 tinyint(1) NULL,
				balance                  decimal(10,2) NULL,
				calculated_balance       decimal(10,2) NOT NULL,
				returned_value           decimal(10,2) NOT NULL DEFAULT 0,
				return_stock_location_id int(11) unsigned NULL,

				list_price               decimal(10,2) unsigned NOT NULL,
				net                      decimal(10,2) unsigned NOT NULL,
				discount                 decimal(10,2) unsigned NOT NULL,
				tax                      decimal(10,2) unsigned NOT NULL,
				gross                    decimal(10,2) unsigned NOT NULL,
				rrp                      decimal(10,2) unsigned DEFAULT NULL,
				tax_rate                 decimal(4,2) unsigned NOT NULL,
				product_tax_rate         decimal(4,2) unsigned NOT NULL,
				tax_strategy             varchar(10) NOT NULL DEFAULT 'inclusive',

				product_id               int(11) unsigned DEFAULT NULL,
				product_name             varchar(255) DEFAULT NULL,
				unit_id                  int(11) unsigned DEFAULT NULL,
				unit_revision            int(11) unsigned DEFAULT NULL,
				sku                      varchar(100) DEFAULT NULL,
				barcode                  varchar(13) DEFAULT NULL,
				options                  varchar(255) DEFAULT NULL,
				brand                    varchar(255) DEFAULT NULL,
				weight_grams             int(11) unsigned DEFAULT NULL
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
				return_stock_location_id,
				list_price,
				net,
				discount,
				tax,
				gross,
				rrp,
				tax_rate,
				product_tax_rate,
				tax_strategy,
				product_id,
				product_name,
				unit_id,
				unit_revision,
				sku,
				barcode,
				`options`,
				brand,
				weight_grams
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
				) as status_code,
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
				oir.return_to_stock_location_id,
				oi.list_price,
				oi.net,
				oi.discount,
				oi.tax,
				oi.gross,
				oi.rrp,
				oi.tax_rate,
				oi.product_tax_rate,
				oi.tax_strategy,
				oi.product_id,
				oi.product_name,
				oi.unit_id,
				oi.unit_revision,
				oi.sku,
				oi.barcode,
				oi.options,
				oi.brand,
				oi.weight_grams
			FROM order_item_return oir
			LEFT JOIN `return` r ON (
				r.return_id = oir.return_id
			)
			LEFT JOIN order_item oi ON (
				oi.item_id = oir.item_id
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
