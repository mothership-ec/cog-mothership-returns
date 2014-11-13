<?php

namespace Message\Mothership\OrderReturn\Report\SalesData;

use Message\Cog\DB\QueryBuilderFactory;

class ReturnsData
{
	private $_builderFactory;

	public function __construct(QueryBuilderFactory $builderFactory)
	{
		$this->_builderFactory = $builderFactory;
	}

	public function getQueryBuilder()
	{
		$data = $this->_builderFactory->getQueryBuilder();

		$data
			->select('item.completed_at AS "Date"')
			->select('return.currency_id AS "Currency"')
			->select('IFNULL(-item.net, 0) AS "Net"')
			->select('IFNULL(-item.tax, 0) AS "Tax"')
			->select('IFNULL(-item.gross, 0) AS "Gross"')
			->select('return.type AS "Source"')
			->select('"Return" AS "Type"')
			->select('item.item_id AS "Item_ID"')
			->select('item.order_id AS "Order_ID"')
			->select('item.return_id AS "Return_ID"')
			->select('item.product_id AS "Product_ID"')
			->select('item.product_name AS "Product"')
			->select('item.options AS "Option"')
			->select('country AS "Country"')
			->select('user.forename AS "User_Forename"')
			->select('user.surname AS "User_Surname"')
			->select('user.email AS "Email"')
			->select('user.user_id AS "User_id"')
			->from('return_item AS item')
			->join('`return`', 'item.return_id = return.return_id')
			->leftJoin('order_address', 'item.order_id = order_address.order_id AND order_address.type = "delivery" AND order_address.deleted_at IS NULL')
			->leftJoin('user', 'return.created_by = user.user_id')
			->where('item.status_code >= 2200')
			->where('item.product_id NOT IN (9)')
			->where('item.completed_at BETWEEN UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 12 MONTH)) AND UNIX_TIMESTAMP(NOW())')
		;

		return $data;
	}
}



