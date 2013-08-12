<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\Mothership\Commerce\Order;
use Message\Cog\ValueObject\DateTimeImmutable;

class Loader
{
	protected $_query;
	protected $_orderLoader;
	protected $_itemLoader;

	public function __construct(DB\Query $query, $orderLoader, $itemLoader)
	{
		$this->_query = $query;
		$this->_orderLoader = $orderLoader;
		$this->_itemLoader = $itemLoader;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
	}

	public function getByOrder(Order\Order $order)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				order_item_return
			WHERE
				order_id = ?i
		', $order->id);

		return $this->_load($result->flatten(), true);
	}

	protected function _load($ids, $alwaysReturnArray = false)
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
				order_item_return
			WHERE
				return_id IN (?ij)
		', array($ids));

		if (0 === count($result)) {
			return $alwaysReturnArray ? array() : false;
		}

		$entities = $result->bindTo('Message\\Mothership\\OrderReturn\\Entity\\OrderReturn');
		$return = array();

		foreach ($entities as $key => $entity) {

			// Add created authorship
			$entity->authorship->create(
				new DateTimeImmutable(date('c', $result[$key]->created_at)),
				$result[$key]->created_by
			);

			// Add updated authorship
			if ($result[$key]->updated_at) {
				$items[$key]->authorship->create(
					new DateTimeImmutable(date('c', $result[$key]->updated_at)),
					$result[$key]->updated_by
				);
			}

			$entity->order = $this->_orderLoader->getByID($result[$key]->order_id);
			$entity->item = $this->_itemLoader->getByID($result[$key]->item_id);

			// Add the entity into the order
			// $entity->order->addEntity($entity);

			$return[$entity->id] = $entities[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

}