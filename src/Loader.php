<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\Filesystem\File;

use Message\User;

use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Refund;
use Message\Mothership\Commerce\Payment;
use Message\Mothership\Commerce\Product\Stock;
use Message\Mothership\Commerce\Product\Unit\Loader as UnitLoader;


class Loader extends Order\Entity\BaseLoader implements
	Order\Entity\DeletableLoaderInterface,
	Order\Transaction\DeletableRecordLoaderInterface
{
	/**
	 * @var \Message\Cog\DB\Query
	 */
	protected $_query;

	/**
	 * @var Collection\Collection
	 */
	protected $_reasons;

	/**
	 * @var \Message\Mothership\Commerce\Order\Status\Collection
	 */
	protected $_statuses;

	/**
	 * @var \Message\Mothership\Commerce\Refund\Loader
	 */
	protected $_refundLoader;

	/**
	 * @var \Message\Mothership\Commerce\Payment\Loader
	 */
	protected $_paymentLoader;

	/**
	 * @var \Message\Mothership\Commerce\Product\Stock\Location\Collection
	 */
	protected $_stockLocations;

	/**
	 * @var bool
	 */
	protected $_includeDeleted = false;

	public function __construct(
		DB\Query $query,
		Collection\Collection $reasons,
		Order\Status\Collection $statuses,
		Refund\Loader $refundLoader,
		Payment\Loader $paymentLoader,
		Stock\Location\Collection $stockLocations,
		UnitLoader $unitLoader
	) {
		$this->_query          = $query;
		$this->_reasons        = $reasons;
		$this->_statuses       = $statuses;
		$this->_refundLoader   = $refundLoader;
		$this->_paymentLoader  = $paymentLoader;
		$this->_stockLocations = $stockLocations;
		$this->_unitLoader     = $unitLoader;

		$this->_unitLoader->includeOutOfStock(true);
		$this->_unitLoader->includeInvisible(true);
	}

	/**
	 * Set whether to load deleted returns. Also sets include deleted on order loader.
	 *
	 * @param  bool $bool True to load deleted refunds, false otherwise
	 *
	 * @return Loader     Returns $this for chainability
	 */
	public function includeDeleted($bool = true)
	{
		$this->_includeDeleted = (bool) $bool;
		$this->_orderLoader->includeDeleted($this->_includeDeleted);

		return $this;
	}

	public function getByID($id)
	{
		return $this->_load($id, false);
	}

	public function getByRecordID($id)
	{
		return $this->getByID($id);
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
					remaining_balance != 0
				) OR (
					accepted IS NULL AND
					remaining_balance IS NULL
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
				remaining_balance > 0 AND
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
				accepted = 1 AND
				completed_at IS NULL AND
				(
					remaining_balance < 0 OR
					(
						remaining_balance IS NULL AND
						calculated_balance < 0
					)
				)
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
				exchange_item_id IS NOT NULL AND
				accepted = 1 AND
				completed_at IS NULL
		');

		return $this->_load($result->flatten(), true);
	}

	public function getCompleted()
	{
		$result = $this->_query->run('
			SELECT
				return_id
			FROM
				return_item
			WHERE
				completed_at IS NOT NULL
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
			AND	status_code >= 2100
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
				*,
				return_id as id,
				document_id as documentID,
				currency_id as currencyID
			FROM
				`return`
			WHERE
				return_id IN (?ij)
			' . ($this->_includeDeleted ? '' : 'AND deleted_at IS NULL') . '
		', array($ids));

		$itemsResult = $this->_query->run('
			SELECT
				*,
				return_item_id          AS id,
				return_id               AS returnID,
				order_id                AS orderID,
				item_id                 AS orderItemID,
				exchange_item_id        AS exchangeItemID,
				note_id                 AS noteID,
				remaining_balance       AS remainingBalance,
				calculated_balance      AS calculatedBalance,
				returned_value          AS returnedValue,
				returned_stock          AS returnedStock,
				list_price              AS listPrice,
				actual_price            AS actualPrice,
				tax_rate                AS taxRate,
				product_tax_rate        AS productTaxRate,
				tax_strategy            AS taxStrategy,
				product_id              AS productID,
				product_name            AS productName,
				unit_id                 AS unitID,
				unit_revision           AS unitRevision,
				weight_grams            AS weight
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

			// Add deleted authorship
			if ($returnsResult[$key]->deleted_at) {
				$entity->authorship->delete(
					new DateTimeImmutable(date('c', $returnsResult[$key]->deleted_at)),
					$returnsResult[$key]->deleted_by
				);
			}

			// Load the first item into the return
			// @todo Make this an array of items
			foreach ($itemEntities as $itemKey => $item) {
				if ($item->returnID == $entity->id) {

					$entity->item = $this->_loadItem($itemsResult[$itemKey], $item, $entity);

					break;
				}

			}

			$entity->payments   = $this->_loadPayments($entity);
			$entity->refunds    = $this->_loadRefunds($entity);

			$return[$entity->id] = $entity;
		}

		return $alwaysReturnArray || count($return) > 1 ? $return : reset($return);
	}

	protected function _loadItem($itemResult, $itemEntity, $return)
	{
		// Cast decimals to float
		$itemEntity->balance           = ($itemEntity->balance) ? (float) $itemEntity->balance : null;
		$itemEntity->calculatedBalance = ($itemEntity->calculatedBalance) ? (float) $itemEntity->calculatedBalance : null;
		$itemEntity->remainingBalance  = ($itemEntity->remainingBalance) ? (float) $itemEntity->remainingBalance : null;
		$itemEntity->accepted          = (null !== $itemEntity->accepted) ? (bool) $itemEntity->accepted : null;
		$itemEntity->listPrice         = (float) $itemEntity->listPrice;
		$itemEntity->actualPrice       = (float) $itemEntity->actualPrice;
		$itemEntity->net               = (float) $itemEntity->net;
		$itemEntity->discount          = (float) $itemEntity->discount;
		$itemEntity->tax               = (float) $itemEntity->tax;
		$itemEntity->taxRate           = (float) $itemEntity->taxRate;
		$itemEntity->productTaxRate    = (float) $itemEntity->productTaxRate;
		$itemEntity->gross             = (float) $itemEntity->gross;
		$itemEntity->rrp               = (float) $itemEntity->rrp;

		// Only load the order and refunds if one is attached to the return
		if ($itemEntity->orderID) {
			$itemEntity->order = $this->_orderLoader->getByID($itemEntity->orderID);

			// Grab the item from the order for easy access
			if ($itemEntity->orderItemID) {
				$itemEntity->orderItem = $itemEntity->order->items[$itemEntity->orderItemID];

				$itemEntity->unit = $itemEntity->orderItem->getUnit();
			}

			$itemEntity->note = $this->_orderLoader->getEntityLoader('notes')->getByID($itemEntity->noteID, $itemEntity->order);
			// $itemEntity->document = $this->_orderLoader->getEntityLoader('documents')->getByID($itemResult->documentID, $itemEntity->order);
		}

		if ($itemEntity->exchangeItemID) {
			$itemEntity->exchangeItem = $this->_orderLoader->getEntityLoader('items')->getByID($itemEntity->exchangeItemID, ($itemEntity->order ?: null));
		}

		if (!$itemEntity->unit) {
			$itemEntity->unit = $this->_unitLoader->getByID($itemEntity->unitID);
		}

		$itemEntity->reason = $this->_reasons->get($itemResult->reason);
		$itemEntity->status = $this->_statuses->get($itemResult->status_code);

		if ($itemResult->returned_stock_location and $this->_stockLocations->exists($itemResult->returned_stock_location)) {
			$itemEntity->returnedStockLocation = $this->_stockLocations->get($itemResult->returned_stock_location);
		}

		$itemEntity->returnedStock = (bool) $itemEntity->returnedStock;

		$itemEntity = $this->_loadDocument($itemEntity, $return);

		return $itemEntity;
	}

	public function _loadPayments($return)
	{
		$paymentIDs = $this->_query->run("
			SELECT
				payment_id
			FROM
				return_payment
			WHERE
				return_id = :returnID?i
		", [
			'returnID' => $return->id,
		]);

		$paymentIDs = $paymentIDs->flatten('payment_id');

		$payments = $this->_paymentLoader->getByID($paymentIDs) ?: [];

		if (! is_array($payments)) $payments = [$payments];

		return $payments;
	}

	public function _loadRefunds($return)
	{
		$refundIDs = $this->_query->run("
			SELECT
				refund_id
			FROM
				return_refund
			WHERE
				return_id = :returnID?i
		", [
			'returnID' => $return->id,
		]);

		$refundIDs = $refundIDs->flatten('refund_id');

		$refunds = $this->_refundLoader->getByID($refundIDs) ?: [];

		if (! is_array($refunds)) $refunds = [$refunds];

		return $refunds;
	}

	/**
	 * @param Entity\OrderReturnItem $item
	 * @return Entity\OrderReturnItem
	 */
	protected function _loadDocument(Entity\OrderReturnItem $item, Entity\OrderReturn $return)
	{
		$documentLoader = $this->_orderLoader->getEntityLoader('documents');

		if ($item->orderID) {
			// The fact the document ID is saved against the return but the document object is part of the
			// item is SO STUPID
			$item->document = $documentLoader->getByID($return->documentID);
		}

		return $item;
	}
}