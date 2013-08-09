<?php

namespace Message\Mothership\OrderReturn;

use Message\Mothership\Commerce\Order;

class Loader implements Order\Entity\LoaderInterface
{
	protected $_query;

	public function __construct(DB\Query $query)
	{
		$this->_query = $query;
	}

	public function getByID($id, Order\Order $order)
	{
		return $this->_load($id, false, $order);
	}

	public function getByOrder(Order\Order $order)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				order_return
			WHERE
				order_id = ?i
		', $order->id);

		return $this->_load($result->flatten(), true, $order);
	}

	protected function _load($ids, $alwaysReturnArray = false, Order\Order $order = null)
	{
		if (! is_array($ids)) {
			$ids = (array) $ids;
		}

		if (! $ids) {
			return $alwaysReturnArray ? array() : false;
		}

		$result = $this->_query->run('
			SELECT
				*
			FROM
				order_return
			WHERE
				return_id IN (?ij)
		', array($ids));

		if (0 === count($result)) {
			return $alwaysReturnArray ? array() : false;
		}

		$entities = $result->bindTo('Message\\Mothership\\OrderReturn\\Entity\\Return');
		$return = array();

		foreach ($result as $key => $row) {

			// Add created authorship
			$entities[$key]->authorship->create(
				new DateTimeImmutable(date('c', $row->created_at)),
				$row->created_by
			);

			// Add updated authorship
			if ($row->updated_at) {
				$items[$key]->authorship->create(
					new DateTimeImmutable(date('c', $row->updated_at)),
					$row->updated_by
				);
			}

			if ($order) {
				$entities[$key]->order = $order;
			}
			else {
				// TODO: load the order
			}

			$return[$row->id] = $entities[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

}