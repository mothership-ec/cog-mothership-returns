<?php

use Message\Cog\Migration\Adapter\MySQL\Migration;

class _1391686934_CreateReturnAndReturnItemTables extends Migration
{
	public function up()
	{
		$this->run("
			DROP TABLE IF EXISTS `order_item_return`
		");

		$this->run("
			CREATE TABLE IF NOT EXISTS `return` (
				`return_id`    int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`document_id`  int(11) unsigned NULL,
				`created_at`   int(11) unsigned NOT NULL KEY,
				`created_by`   int(11) unsigned DEFAULT NULL KEY,
				`updated_at`   int(11) unsigned DEFAULT NULL,
				`updated_by`   int(11) unsigned DEFAULT NULL,
				`deleted_at`   int(11) unsigned DEFAULT NULL,
				`deleted_by`   int(11) unsigned DEFAULT NULL,
				`completed_at` int(11) unsigned DEFAULT NULL KEY,
				`completed_by` int(11) unsigned DEFAULT NULL KEY
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");

		$this->run("
			CREATE TABLE IF NOT EXISTS `return_item` (
				`return_item_id`           int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
				`return_id`                int(11) unsigned NOT NULL KEY,
				`order_id`                 int(11) unsigned DEFAULT NULL KEY,
				`item_id`                  int(11) unsigned DEFAULT NULL KEY,
				`exchange_item_id`         int(11) unsigned DEFAULT NULL KEY,
				`note_id`                  int(11) unsigned DEFAULT NULL,
				`status_id`                int(11) NOT NULL KEY,
				`created_at`               int(11) unsigned DEFAULT NULL KEY,
				`created_by`               int(11) unsigned DEFAULT NULL KEY,
				`updated_at`               int(11) unsigned DEFAULT NULL KEY,
				`updated_by`               int(11) unsigned DEFAULT NULL KEY,
				`completed_at`             int(11) unsigned DEFAULT NULL KEY,
				`completed_by`             int(11) unsigned DEFAULT NULL KEY,
				`reason`                   varchar(255) NOT NULL,
				`resolution`               varchar(255) NOT NULL,
				`accepted`                 tinyint(1) DEFAULT NULL,
				`balance`                  decimal(10,2) DEFAULT NULL,
				`calculated_balance`       decimal(10,2) NOT NULL,
				`returned_value`           decimal(10,2) NOT NULL,
				`return_stock_location_id` int(11) unsigned DEFAULT NULL KEY
			) ENGINE=InnoDB DEFAULT CHARSET=utf8
		");
	}

	public function down()
	{
		$this->run("
			CREATE TABLE `order_item_return` (
				`return_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				`order_id` int(11) unsigned NOT NULL,
				`item_id` int(11) unsigned NOT NULL,
				`document_id` int(11) unsigned NOT NULL,
				`created_at` int(11) unsigned NOT NULL,
				`created_by` int(11) unsigned DEFAULT NULL,
				`updated_at` int(11) unsigned DEFAULT NULL,
				`updated_by` int(11) unsigned DEFAULT NULL,
				`completed_at` int(11) unsigned DEFAULT NULL,
				`completed_by` int(11) unsigned DEFAULT NULL,
				`exchange_item_id` int(11) unsigned DEFAULT NULL,
				`status_id` int(11) NOT NULL,
				`reason` varchar(255) NOT NULL DEFAULT '',
				`resolution` varchar(255) NOT NULL DEFAULT '',
				`balance` decimal(10,2) DEFAULT NULL,
				`calculated_balance` decimal(10,2) NOT NULL,
				`accepted` tinyint(1) DEFAULT NULL,
				`returned_value` decimal(10,2) unsigned DEFAULT NULL,
				`return_to_stock_location_id` int(11) unsigned DEFAULT NULL,
				`note_id` int(11) unsigned DEFAULT NULL,
				PRIMARY KEY (`return_id`),
				KEY `order_id` (`order_id`),
				KEY `item_id` (`item_id`),
				KEY `created_at` (`created_at`),
				KEY `created_by` (`created_by`),
				KEY `updated_at` (`updated_at`),
				KEY `updated_by` (`updated_by`),
				KEY `exchange_item_id` (`exchange_item_id`),
				KEY `return_to_stock_location_id` (`return_to_stock_location_id`),
				KEY `status_id` (`status_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=20037 DEFAULT CHARSET=utf8;
		");

		$this->run("
			DROP TABLE IF EXISTS `return`
		");

		$this->run("
			DROP TABLE IF EXISTS `return_item`
		");
	}
}
