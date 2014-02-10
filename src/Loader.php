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
				order_item_return
		');

		return $this->_load($result->flatten(), true);
	}

	public function getOpen()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				order_item_return
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
				r.return_id,
				s.status_code
			FROM
				order_item_return r
			RIGHT JOIN
				order_item_status s ON r.item_id = s.item_id
			ORDER BY
				s.created_at DESC
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
				order_item_return
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
				order_item_return
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
				return_id, exchange_item_id
			FROM
				order_item_return
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

	public function getRejected()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				order_item_return
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
				order_item_return
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
				order_item_return
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

			$entity->balance           = (float) $result[$key]->balance;
			$entity->calculatedBalance = (float) $result[$key]->calculated_balance;

			$entity->order = $this->_orderLoader->getByID($result[$key]->order_id);
			$entity->item = $this->_orderLoader->getEntityLoader('items')->getByID($result[$key]->item_id);

			$entity->exchangeItem = $this->_orderLoader->getEntityLoader('items')->getByID($result[$key]->exchange_item_id);

			$entity->reason = $this->_reasons->get($result[$key]->reason);
			$entity->resolution = $this->_resolutions->get($result[$key]->resolution);

			$entity->refunds = $this->_orderLoader->getEntityLoader('refunds')->getByOrder($entity->order);

			$entity->document = $this->_orderLoader->getEntityLoader('documents')->getByID($result[$key]->document_id);

			$entity->note = $this->_orderLoader->getEntityLoader('notes')->getByID($result[$key]->note_id);

			// Add the entity into the order
			// $entity->order->addEntity($entity);

			$return[$result[$key]->return_id] = $entities[$key];
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

}