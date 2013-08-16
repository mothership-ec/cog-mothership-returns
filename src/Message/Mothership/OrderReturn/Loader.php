<?php

namespace Message\Mothership\OrderReturn;

use Message\User;
use Message\Cog\DB;
use Message\Mothership\Commerce\Order;
use Message\Cog\ValueObject\DateTimeImmutable;

class Loader
{
	protected $_query;
	protected $_orderLoader;
	protected $_itemLoader;
	protected $_refundLoader;
	protected $_reasons;
	protected $_resolutions;
	protected $_statuses;

	public function __construct(DB\Query $query, $orderLoader, $itemLoader, $refundLoader, $reasons, $resolutions, $statuses)
	{
		$this->_query        = $query;
		$this->_orderLoader  = $orderLoader;
		$this->_itemLoader   = $itemLoader;
		$this->_refundLoader = $refundLoader;
		$this->_reasons      = $reasons;
		$this->_resolutions  = $resolutions;
		$this->_statuses     = $statuses;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
	}

	public function getAll()
	{
		$result = $this->_query->run('
			SELECT
				*
			FROM
				order_item_return
		');

		return $this->_load($result->flatten(), true);
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

	public function getByUser(User\User $user)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				order_item_return oir
			LEFT JOIN
				order_summary os ON oir.order_id = os.order_id
			WHERE
				os.user_id = ?i
		', $user->id);

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

			$entity->id = $result[$key]->return_id;

			// Add created authorship
			$entity->authorship->create(
				new DateTimeImmutable(date('c', $result[$key]->created_at)),
				$result[$key]->created_by
			);

			// Add updated authorship
			if ($result[$key]->updated_at) {
				$entity->authorship->update(
					new DateTimeImmutable(date('c', $result[$key]->updated_at)),
					$result[$key]->updated_by
				);
			}

			$entity->order = $this->_orderLoader->getByID($result[$key]->order_id);
			$entity->item = $this->_itemLoader->getByID($result[$key]->item_id);

			$entity->exchangeItem = $this->_itemLoader->getByID($result[$key]->exchange_item_id);

			$entity->reason = $this->_reasons->get($result[$key]->reason);
			$entity->resolution = $this->_resolutions->get($result[$key]->resolution);

			$entity->refunds = $this->_refundLoader->getByOrder($entity->order);

			// Add the entity into the order
			// $entity->order->addEntity($entity);

			$return[$result[$key]->return_id] = $entities[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

}