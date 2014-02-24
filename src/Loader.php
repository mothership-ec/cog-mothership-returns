<?php

namespace Message\Mothership\OrderReturn;

use Message\User;
use Message\Cog\DB;
use Message\Mothership\Commerce\Order;
use Message\Cog\ValueObject\DateTimeImmutable;

class Loader extends Order\Entity\BaseLoader
{
	protected $_query;
	protected $_reasons;
	protected $_resolutions;
	protected $_statuses;

	public function __construct(DB\Query $query, $reasons, $resolutions, $statuses)
	{
		$this->_query          = $query;
		$this->_reasons        = $reasons;
		$this->_resolutions    = $resolutions;
		$this->_statuses       = $statuses;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
	}

	public function getAll()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return
		');

		return $this->_load($result->flatten(), true);
	}

	public function getOpen()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				(
					accepted != 0 AND
					balance != 0
				) OR (
					accepted IS NULL AND
					balance IS NULL
				)
		');

		return $this->_load($result->flatten(), true);
	}

	public function getByStatusCode($code)
	{
		$result = $this->_query->run('
			SELECT
				return_id,
				status_code
			FROM
				return_item
			ORDER BY
				created_at DESC
		');

		$unique = array();

		foreach ($result as $r) {
			if (! isset($unique[$r->return_id])) {
				$unique[$r->return_id] = $r;
			}
		}

		$unique = array_filter($unique, function($r) use ($code) {
			return $code == $r->status_code;
		});

		$ids = array_keys($unique);

		return $this->_load($ids, true);
	}

	public function getAwaitingPayment()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				balance IS NOT NULL AND
				balance < 0 AND
				accepted = 1
		');

		return $this->_load($result->flatten(), true);
	}

	public function getPendingRefund()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				balance IS NOT NULL AND
				balance > 0 AND
				accepted = 1
		');

		return $this->_load($result->flatten(), true);
	}

	public function getPendingExchange()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				exchange_item_id > 0 AND
				accepted = 1
		');

		$returns = $this->_load($result->flatten(), true);

		foreach ($returns as $i => $return) {
			if (Statuses::RETURN_RECEIVED > $return->item->status->code or
				Order\Statuses::DISPATCHED <= $return->exchangeItem->status->code
			) {
				unset($returns[$i]);
			}
		}

		return $returns;
	}

	public function getCompleted()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				balance = 0
		');

		return $this->_load($result->flatten(), true);
	}

	public function getRejected()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				accepted = 0
		');

		return $this->_load($result->flatten(), true);
	}

	public function getByOrder(Order\Order $order)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				order_id = ?i
		', $order->id);

		return $this->_load($result->flatten(), true);
	}

	public function getByItem(Order\Entity\Item\Item $item)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				item_id = ?i
		', $item->id);

		return $this->_load($result->flatten(), true);
	}

	public function getByUser(User\User $user)
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return
			WHERE
				created_by = ?i
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

		$returnsResult = $this->_query->run('
			SELECT
				*
			FROM
				`return`
			WHERE
				return_id IN (?ij)
		', array($ids));

		$itemsResult = $this->_query->run('
			SELECT
				*
			FROM
				return_item
			WHERE
				return_id IN (?ij)
		', array($ids));

		if (0 === count($returnsResult)) {
			return $alwaysReturnArray ? array() : false;
		}

		$returnEntities = $returnsResult->bindTo('Message\\Mothership\\OrderReturn\\Entity\\OrderReturn');
		$itemEntities   = $itemsResult->bindTo('Message\\Mothership\\OrderReturn\\Entity\\OrderReturnItem');

		$return = array();

		foreach ($returnEntities as $key => $entity) {

			$entity->id = $returnsResult[$key]->return_id;

			// Add created authorship
			$entity->authorship->create(
				new DateTimeImmutable(date('c', $returnsResult[$key]->created_at)),
				$returnsResult[$key]->created_by
			);

			// Add updated authorship
			if ($returnsResult[$key]->updated_at) {
				$entity->authorship->update(
					new DateTimeImmutable(date('c', $returnsResult[$key]->updated_at)),
					$returnsResult[$key]->updated_by
				);
			}

			foreach ($itemsResult as $key => $itemResult) {
				if ($itemResult->return_id == $entity->id) {
					$entity->item = $this->_loadItem($itemResult, $itemEntities[$key]);

					break; // only load the first item
				}
			}

			$return[$entity->id] = $entity;
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

	protected function _loadItem($itemResult, $itemEntity)
	{
		// Reformat under_score to camelCase
		$itemEntity->returnID = $itemResult->return_id;
		$itemEntity->calculatedBalance = $itemResult->calculated_balance;

		// Only load the order and refunds if one is attached to the return
		if ($itemResult->order_id) {
			$itemEntity->order   = $this->_orderLoader->getByID($itemResult->order_id);
			$itemEntity->refunds = $this->_orderLoader->getEntityLoader('refunds')->getByOrder($itemEntity->order);
		}

		// Only load item item if one is attached to the return item
		if ($itemResult->item_id) {
			$itemEntity->item = $this->_orderLoader->getEntityLoader('items')->getByID($itemResult->item_id, $itemEntity->order);
		}

		// Only load the exchange item if one is attached to the return item
		if ($itemResult->exchange_item_id) {
			$itemEntity->exchangeItem = $this->_orderLoader->getEntityLoader('items')->getByID($itemResult->exchange_item_id, $itemEntity->order);
		}

		$itemEntity->reason     = $this->_reasons->get($itemResult->reason);
		$itemEntity->resolution = $this->_resolutions->get($itemResult->resolution);
		// $itemEntity->document   = $this->_orderLoader->getEntityLoader('documents')->getByID($itemResult->document_id, $itemEntity->order);
		$itemEntity->note       = $this->_orderLoader->getEntityLoader('notes')->getByID($itemResult->note_id, $itemEntity->order);

		$itemEntity->status     = $this->_statuses->get($itemResult->status_code);

		return $itemEntity;
	}

}