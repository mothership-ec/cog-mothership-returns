<?php

namespace Message\Mothership\OrderReturn;

use Message\User;
use Message\Cog\DB;
use Message\Mothership\Commerce\Order\Transaction\RecordLoaderInterface;
use Message\Mothership\Commerce\Order;
use Message\Cog\ValueObject\DateTimeImmutable;

class OrderReturnItemLoader implements RecordLoaderInterface
{
	protected $_query;
	protected $_orderLoader;
	protected $_reasons;
	protected $_resolutions;
	protected $_statuses;

	public function __construct(
		DB\Query $query,
		// Order\Loader $orderLoader,
		$reasons,
		$resolutions,
		$statuses
	) {
		$this->_query       = $query;
		// $this->_orderLoader = $orderLoader;
		$this->_reasons     = $reasons;
		$this->_resolutions = $resolutions;
		$this->_statuses    = $statuses;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
	}

	/**
	 * Alias of getByID for Order\Transaction\RecordLoaderInterface
	 * @param  int $id                                  record id
	 * @return OrderReturn\Entity\OrderReturnItem|false        The return item, or false if it doesn't exist
	 */
	public function getByRecordID($id)
	{
		return $this->getByID($id);
	}

	public function getByOrderReturn(Entity\OrderReturn $return)
	{
		$result = $this->_query->run('
			SELECT
				return_item_id
			FROM
				return_item
			WHERE
				return_id = ?i
		', array($return->id));

		de($result->flatten());

		return $this->_load($result->flatten());
	}

	protected function _load($ids, $alwaysReturnArray = false)
	{
		if (! is_array($ids)) {
			$ids = (array) $ids;
		}

		if (! $ids) {
			return $alwaysReturnArray ? array() : false;
		}

		$itemsResult = $this->_query->run('
			SELECT
				*,
				return_item_id     AS returnItemID,
				return_id          AS returnID,
				order_id           AS orderID,
				item_id            AS orderItemID,
				exchange_item_id   AS exchangeItemID,
				note_id            AS noteID,
				calculated_balance AS calculatedBalance,
				list_price         AS listPrice,
				tax_rate           AS taxRate,
				product_tax_rate   AS productTaxRate,
				tax_strategy       AS taxStrategy,
				product_id         AS productID,
				product_name       AS productName,
				unit_id            AS unitID,
				unit_revision      AS unitRevision,
				weight_grams       AS weight
			FROM
				return_item
			WHERE
				return_item_id IN (?ij)
		', array($ids));

		if (0 === count($itemsResult)) {
			return $alwaysReturnArray ? array() : false;
		}

		$itemEntities   = $itemsResult->bindTo('Message\\Mothership\\OrderReturn\\Entity\\OrderReturnItem');

		$items = array();

		foreach ($itemEntities as $key => $itemEntity) {
			$itemResult = $itemsResult[$key];

			// Cast decimals to float
			$itemEntity->listPrice      = (float) $itemEntity->listPrice;
			$itemEntity->net            = (float) $itemEntity->net;
			$itemEntity->discount       = (float) $itemEntity->discount;
			$itemEntity->tax            = (float) $itemEntity->tax;
			$itemEntity->taxRate        = (float) $itemEntity->taxRate;
			$itemEntity->productTaxRate = (float) $itemEntity->productTaxRate;
			$itemEntity->gross          = (float) $itemEntity->gross;
			$itemEntity->rrp            = (float) $itemEntity->rrp;

			// Only load the order and refunds if one is attached to the return
			if ($itemEntity->orderID) {
				$itemEntity->order   = $this->_orderLoader->getByID($itemEntity->orderID);
				$itemEntity->refunds = $this->_orderLoader->getEntityLoader('refunds')->getByOrder($itemEntity->order);

				// Grab the item from the order for easy access
				if ($itemEntity->orderItemID) {
					$itemEntity->orderItem = $itemEntity->order->items[$itemEntity->orderItemID];
				}
			}

			// Only load the exchange item if one is attached to the return item
			if ($itemEntity->exchangeItemID) {
				$itemEntity->exchangeItem = $this->_orderLoader->getEntityLoader('items')->getByID(
					$itemEntity->exchangeItemID,
					$itemEntity->order
				);
			}

			$itemEntity->reason     = $this->_reasons->get($itemResult->reason);
			$itemEntity->resolution = $this->_resolutions->get($itemResult->resolution);
			// $itemEntity->document   = $this->_orderLoader->getEntityLoader('documents')->getByID($itemResult->document_id, $itemEntity->order);
			$itemEntity->note       = $this->_orderLoader->getEntityLoader('notes')->getByID(
				$itemEntity->noteID,
				$itemEntity->order
			);
			$itemEntity->status     = $this->_statuses->get($itemResult->status_code);

			$items[$itemEntity->id] = $itemEntity;
		}

		return $alwaysReturnArray || count($items) > 1 ? $items : reset($items);
	}
}