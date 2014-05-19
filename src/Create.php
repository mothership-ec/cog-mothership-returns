<?php

namespace Message\Mothership\OrderReturn;

use ReflectionClass;
use InvalidArgumentException;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Cog\Event\Dispatcher as EventDispatcher;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Commerce\Product\Stock\StockManager;
use Message\Mothership\Commerce\Refund\Create as RefundCreate;
use Message\Mothership\Commerce\Payment\Create as PaymentCreate;
use Message\Mothership\Commerce\Product\Unit\Loader as UnitLoader;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Order\Entity\Item\Edit as OrderItemEdit;
use Message\Mothership\Commerce\Order\Entity\Item\Create as OrderItemCreate;
use Message\Mothership\Commerce\Product\Stock\Location\Collection as StockLocations;
use Message\Mothership\Commerce\Order\Status\Collection as OrderItemStatusCollection;
use Message\Mothership\Commerce\Product\Stock\Movement\Reason\Collection as StockMovementReasons;

use Message\Mothership\Ecommerce\OrderItemStatuses;

use Message\Mothership\OrderReturn\File\ReturnSlip;
use Message\Mothership\OrderReturn\Loader as ReturnLoader;
use Message\Mothership\OrderReturn\Collection\Collection as ReasonsCollection;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;
	protected $_eventDispatcher;

	protected $_returnLoader;
	protected $_unitLoader;

	protected $_orderItemCreate;
	protected $_orderItemEdit;
	protected $_orderItemStatuses;

	protected $_reasons;
	protected $_returnSlip;

	protected $_stockManager;
	protected $_stockLocations;
	protected $_stockMovementReasons;

	protected $_transOverridden = false;

	public function __construct(
		DB\Transaction $query,
		UserInterface $currentUser,
		EventDispatcher $eventDispatcher,

		ReturnLoader $loader,
		UnitLoader $unitLoader,

		OrderItemCreate $orderItemCreate,
		OrderItemEdit $orderItemEdit,
		OrderItemStatusCollection $orderItemStatusCollection,

		ReasonsCollection $returnReasons,
		ReturnSlip $returnSlip,

		StockManager $stockManager,
		StockLocations $stockLocations,
		StockMovementReasons $stockMovementReasons,

		NoteCreate $noteCreate,

		PaymentCreate $paymentCreate,
		RefundCreate  $refundCreate
	) {
		$this->_query                = $query;
		$this->_currentUser          = $currentUser;
		$this->_eventDispatcher      = $eventDispatcher;

		$this->_loader               = $loader;
		$this->_unitLoader           = $unitLoader;

		$this->_orderItemCreate      = $orderItemCreate;
		$this->_orderItemEdit        = $orderItemEdit;
		$this->_orderItemStatusCollection = $orderItemStatusCollection;

		$this->_returnReasons        = $returnReasons;
		$this->_returnSlip           = $returnSlip;

		$this->_stockManager         = $stockManager;
		$this->_stockLocations       = $stockLocations;
		$this->_stockMovementReasons = $stockMovementReasons;

		$this->_noteCreate           = $noteCreate;

		$this->_paymentCreate        = $paymentCreate;
		$this->_refundCreate         = $refundCreate;
	}

	/**
	 * Sets transaction and sets $_transOverridden to true
	 *
	 * @param  DB\Transaction $trans transaction
	 * @return Create                $this for chainability
	 */
	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
		$this->_transOverridden = true;

		return $this;
	}

	/**
	 * Save a return entity into the database.
	 *
	 * @todo   Create an order when an exchange item is added to a standalone
	 *         return.
	 * @todo   Update to handle multiple return items.
	 * @todo   Create payments.
	 * @todo   Create refunds.
	 * @todo   Break this up into either events or smaller classes handling
	 *         each separate responsibility.
	 *
	 * @param  Entity\OrderReturn $return
	 * @return Entity\OrderReturn
	 */
	public function create(Entity\OrderReturn $return)
	{
		$this->_validate($return);

		$this->_noteCreate   ->setTransaction($this->_query);
		$this->_paymentCreate->setTransaction($this->_query);
		$this->_refundCreate ->setTransaction($this->_query);
		$this->_stockManager ->setTransaction($this->_query);
		$this->_orderItemEdit->setTransaction($this->_query);

		// Set create authorship data if not already set
		if (!$return->authorship->createdAt()) {
			$return->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		// Create the return
		$this->_query->run("
			INSERT INTO
				`return`
			SET
				created_at = :createdAt?i,
				created_by = :createdBy?i
		", [
			'createdAt' => $return->authorship->createdAt(),
			'createdBy' => $return->authorship->createdBy(),
		]);

		$this->_query->setIDVariable('RETURN_ID');
		$return->id = '@RETURN_ID';

		// Create the related note if there is one
		if ($return->item->note) {
			$return->item->note = $this->_noteCreate->create($return->item->note);
		}

		// Create the related payments if there are any
		if ($return->payments) {
			foreach ($return->payments as $payment) {
				$this->_paymentCreate->create($payment);

				$this->_query->run("
					INSERT INTO
						`return_payment`
					SET
						return_id  = :returnID,
						payment_id = :paymentID
				", [
					'returnID'  => $return->id,
					'paymentID' => $payment->id,
				]);
			}
		}

		// Create the related refunds if there are any
		if ($return->refunds) {
			foreach ($return->refunds as $refund) {
				$this->_refundCreate->create($refund);

				$this->_query->run("
					INSERT INTO
						`return_refund`
					SET
						return_id = :returnID,
						refund_id = :refundID
				", [
					'returnID' => $return->id,
					'refundID' => $refund->id,
				]);
			}
		}

		// Create the related exchange item, set the status and move it's stock
		if ($return->item->exchangeItem) {
			$stockLocations = $this->_stockLocations;

			// If there is an order related to this return, attach the
			// exchangeItem to this order
			if ($return->item->order) {
				$return->item->order->items->append($return->item->exchangeItem);
			}

			if (! $return->item->exchangeItem->status) {
				$return->item->exchangeItem->status = clone $this->_orderItemStatusCollection->get(OrderItemStatuses::HOLD);
			}

			if (! $return->item->exchangeItem->stockLocation) {
				$return->item->exchangeItem->stockLocation = $stockLocations->getRoleLocation($stockLocations::SELL_ROLE);
			}

			$this->_stockManager->setAutomated(true);

			$return->item->exchangeItem = $this->_orderItemCreate->create($return->item->exchangeItem);

			$unit = $this->_unitLoader
				->includeOutOfStock(true)
				->getByID($return->item->exchangeItem->unitID);

			$this->_stockManager->setNote(sprintf(
				'Order #%s, return #%s. Replacement item requested.',
				$return->item->order->id,
				$return->id
			));

			$this->_stockManager->setReason(
				$this->_stockMovementReasons->get('exchange_item')
			);

			// Decrement from sell stock
			$this->_stockManager->decrement(
				$unit,
				$return->item->exchangeItem->stockLocation
			);

			// Increment in hold stock
			$this->_stockManager->increment(
				$unit,
				$stockLocations->getRoleLocation($stockLocations::HOLD_ROLE)
			);

			$this->_stockManager->commit();
		}

		// Get the return item status
		$statusCode = ($return->item->status)
			? $return->item->status->code
			: Statuses::AWAITING_RETURN;

		// Get the values for the return item
		$returnItemValues = [
			'returnID'              => $return->id,
			'orderID'               => ($return->item->order) ? $return->item->order->id : null,
			'orderItemID'           => ($return->item->orderItem) ? $return->item->orderItem->id : null,
			'exchangeItemID'        => ($return->item->exchangeItem) ? $return->item->exchangeItem->id : null,
			'noteID'                => ($return->item->note) ? $return->item->note->id : null,
			'createdAt'             => $return->authorship->createdAt(),
			'createdBy'             => $return->authorship->createdBy(),
			'completedAt'           => ($statusCode == Statuses::RETURN_COMPLETED) ? $return->authorship->createdAt() : null,
			'completedBy'           => ($statusCode == Statuses::RETURN_COMPLETED) ? $return->authorship->createdBy() : null,
			'statusCode'            => $statusCode,
			'reason'                => $return->item->reason->code,
			'accepted'              => $return->item->accepted,
			'balance'               => $return->item->balance,
			'calculatedBalance'     => $return->item->calculatedBalance,
			'remainingBalance'      => $return->item->remainingBalance,
			'returnedValue'         => $return->item->returnedValue,
			'returnedStockLocation' => ($return->item->returnedStockLocation) ? $return->item->returnedStockLocation->name : null,
		];

		// Merge in the order item fields, from the orderItem if it is set or
		// just the item object.
		$orderItemFields = [
			'listPrice', 'net', 'discount', 'tax', 'gross', 'rrp', 'taxRate',
			'productTaxRate', 'taxStrategy', 'productID', 'productName',
			'unitID', 'unitRevision', 'sku', 'barcode', 'options', 'brand',
			'weight'
		];

		foreach ($orderItemFields as $field) {
			if ($return->item->orderItem
				and (!property_exists($return->item, $field)
					or !$return->item->$field)) {

				$returnItemValues[$field] = $return->item->orderItem->$field;
			} else {
				$returnItemValues[$field] = $return->item->$field;
			}
		}

		// Create the return item
		$itemResult = $this->_query->run("
			INSERT INTO
				`return_item`
			SET
				return_id               = :returnID?i,
				order_id                = :orderID?in,
				item_id                 = :orderItemID?in,
				exchange_item_id        = :exchangeItemID?in,
				note_id                 = :noteID?in,
				created_at              = :createdAt?i,
				created_by              = :createdBy?i,
				completed_at            = :completedAt?in,
				completed_by            = :completedBy?in,
				status_code             = :statusCode?i,
				reason                  = :reason?s,
				accepted                = :accepted?bn,
				balance                 = :balance?fn,
				calculated_balance      = :calculatedBalance?fn,
				remaining_balance       = :remainingBalance?fn,
				returned_value          = :returnedValue?f,
				returned_stock_location = :returnedStockLocation?s,
				list_price              = :listPrice?f,
				net                     = :net?f,
				discount                = :discount?f,
				tax                     = :tax?f,
				gross                   = :gross?f,
				rrp                     = :rrp?f,
				tax_rate                = :taxRate?f,
				product_tax_rate        = :productTaxRate?f,
				tax_strategy            = :taxStrategy?s,
				product_id              = :productID?i,
				product_name            = :productName?s,
				unit_id                 = :unitID?i,
				unit_revision           = :unitRevision?s,
				sku                     = :sku?s,
				barcode                 = :barcode?s,
				options                 = :options?s,
				brand                   = :brand?s,
				weight_grams            = :weight?i
		", $returnItemValues);

		// If there is a related order item update its status
		if ($return->item->orderItem) {
			$this->_orderItemEdit->updateStatus($return->item->orderItem, $statusCode);
		}

		$event = new Event($return);
		$event->setTransaction($this->_query);

		$return = $this->_eventDispatcher->dispatch(
			Events::CREATE_END,
			$event
		)->getReturn();

		if (!$this->_transOverridden) {
			$this->_query->commit();

			// Re-load the return to ensure it is ready to be passed to the return
			// slip file factory, and to be returned from the method.
			$return = $this->_loader->getByID($this->_query->getIDVariable('RETURN_ID'));

			if ($statusCode === Statuses::AWAITING_RETURN) {
				// This should probably be moved to an event ?
				// Create the return slip and attach it to the return item
				$document = $this->_returnSlip->save($return);
				$this->_query->run("
					UPDATE
						`return`
					SET
						document_id = :documentID?i
					WHERE
						return_id = :returnID?i
				", [
					'documentID' => $document->id,
					'returnID'   => $return->id,
				]);
			}
		}

		return $return;
	}

	/**
	 * Validate the return entity and it's item.
	 *
	 * @todo   Update to handle multiple return items.
	 *
	 * @param  Entity\OrderReturn       $return
	 * @throws InvalidArgumentException When one of the validation rules fails.
	 */
	protected function _validate(Entity\OrderReturn $return)
	{
		// Check the reason has been set and is valid
		if (! $this->_returnReasons->exists($return->item->reason->code)) {
			throw new InvalidArgumentException('Could not create return item: reason is not set or invalid');
		}
	}
}