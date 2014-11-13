<?php

namespace Message\Mothership\OrderReturn\Report\ReturnsData;

use Message\Cog\DB\QueryBuilderFactory;

class RefundsData
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
			->select('refund.refund_id AS ID')
			->select('refund.created_at')
			->select('refund.created_by AS user_id')
			->select('CONCAT(user.surname, ", ", user.forename) AS user')
			->select('currency_id as currency')
			->select('method')
			->select('-amount')
			->select('"Refund" AS type')
			->select('return_id AS order_return_id')
			->select('reference')
			->from('refund')
			->leftJoin('return_refund','refund.refund_id = return_refund.refund_id')
			->join('user','user.user_id = refund.created_by')		;

		return $data;
	}
}

