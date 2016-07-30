<?php

namespace Message\Mothership\OrderReturn\Report\AppendQuery;

use Message\Cog\DB\QueryBuilderFactory;
use Message\Mothership\Report\Filter;
use Message\Mothership\Report\Report\AppendQuery\FilterableInterface;

class Refunds implements FilterableInterface
{
	private $_builderFactory;
	private $_filters;

	/**
	 * Constructor.
	 *
	 * @param QueryBuilderFactory   $builderFactory
	 */
	public function __construct(QueryBuilderFactory $builderFactory)
	{
		$this->_builderFactory = $builderFactory;
	}

	/**
	 * Sets the filters from the report.
	 *
	 * @param Filter\Collection $filters
	 *
	 * @return  $this  Return $this for chainability
	 */
	public function setFilters(Filter\Collection $filters)
	{
		$this->_filters = $filters;

		return $this;
	}

	/**
	 * Gets all REFUND data.
	 *
	 * All columns must match the other sub-queries used in TRANSACTIONS_REPORT.
	 * This because all subqueries are UNIONED together.
	 *
	 * @return Query
	 */
	public function getQueryBuilder()
	{
		$data = $this->_builderFactory->getQueryBuilder();

		$data
			->select('refund.refund_id AS ID')
			->select('refund.created_at AS date')
			->select('refund.created_by AS user_id')
			->select('CONCAT(user.surname, ", ", user.forename) AS user')
			->select('currency_id as currency')
			->select('method')
			->select('-amount')
			->select('"Refund" AS type')
			->select('order_id AS order_id')
			->select('return_id AS return_id')
			->select('reference')
			->from('refund')
			->leftJoin('return_refund','refund.refund_id = return_refund.refund_id')
			->leftJoin('order_refund', 'refund.refund_id = order_refund.refund_id')
			->join('user','user.user_id = refund.created_by')
			->where('refund.deleted_at IS NULL')
		;

		// Filter dates
		if($this->_filters->exists('date_range')) {

			$dateFilter = $this->_filters->get('date_range');

			if($date = $dateFilter->getStartDate()) {
				$data->where('refund.created_at > ?d', [$date->format('U')]);
			}

			if($date = $dateFilter->getEndDate()) {
				$data->where('refund.created_at < ?d', [$date->format('U')]);
			}
		}

		return $data;
	}
}

