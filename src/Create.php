<?php

namespace Message\Mothership\OrderReturn;

use ReflectionClass;
use InvalidArgumentException;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\Item\Item as OrderItem;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Ecommerce\OrderItemStatuses;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create
{
	protected $_query;
	protected $_currentUser;
	protected $_loader;
	protected $_itemEdit;
	protected $_reasons;
	protected $_resolutions;
	protected $_returnSlip;

	public function __construct(
		DB\Query $query,
		UserInterface $user,
		Loader $loader,
		$itemEdit,
		Collection\Collection $reasons,
		Collection\Collection $resolutions,
		$returnSlip
	)
	{
		$this->_query       = $query;
		$this->_currentUser = $currentUser;
		$this->_loader      = $loader;
		$this->_itemEdit    = $itemEdit;
		$this->_reasons     = $reasons;
		$this->_resolutions = $resolutions;
		$this->_returnSlip  = $returnSlip;
	}

	/**
	 * Save a return entity into the database.
	 *
	 * @todo   Update to handle multiple return items.
	 *
	 * @param  Entity\OrderReturn $return
	 * @return Entity\OrderReturn
	 */
	public function create(Entity\OrderReturn $return)
	{
		$this->_validate($return);

		// Set create authorship data if not already set
		if (!$return->authorship->createdAt()) {
			$return->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		// Create the return
		$returnResult = $this->_query->run("
			INSERT INTO
				`return`
			SET
				created_at = :createdAt?i,
				created_by = :createdBy?i
		", [
			'createdAt' => $return->authorship->createdAt(),
			'createdBy' => $return->authorship->createdBy(),
		]);

		// Get the values for the return item
		$returnItemValues = [
			'createdAt'      => $return->authorship->createdAt(),
			'createdBy'      => $return->authorship->createdBy(),
			'returnID'       => $returnResult->id(),
			'orderID'        => ($return->item->order) ? $return->item->order->id : null,
			'orderItemID'    => ($return->item->orderItem) ? $return->item->orderItem->id : null,
			'exchangeItemID' => ($return->item->exchangeItem) ? $return->item->exchangeItem->id : null,
			'noteID'         => ($return->item->note) ? $return->item->note->id : null,
			'statusCode'     => Statuses::AWAITING_RETURN,
			'reason'         => $return->item->reason,
			'resolution'     => $return->item->resolution,
			'balance'        => $return->item->balance,
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
			if ($return->item->orderItem and (!property_exists($return->item, $field) or !$return->item->$field)) {
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
				created_at         = :createdAt?i,
				created_by         = :createdBy?i,
				return_id          = :returnID?i,
				order_id           = :orderID?in,
				item_id            = :orderItemID?in,
				exchange_item_id   = :exchangeItemID?in,
				note_id            = :noteID?in,
				status_code        = :statusCode?i,
				reason             = :reason?s,
				resolution         = :resolution?s,
				calculated_balance = :balance?f,
				list_price         = :listPrice?f,
				net                = :net?f,
				discount           = :discount?f,
				tax                = :tax?f,
				gross              = :gross?f,
				rrp                = :rrp?f,
				tax_rate           = :taxRate?f,
				product_tax_rate   = :productTaxRate?f,
				tax_strategy       = :taxStrategy?s,
				product_id         = :productID?i,
				product_name       = :productName?s,
				unit_id            = :unitID?i,
				unit_revision      = :unitRevision?s,
				sku                = :sku?s,
				barcode            = :barcode?s,
				options            = :options?s,
				brand              = :brand?s,
				weight_grams       = :weight?i
		", $returnItemValues);

		// Re-load the return to ensure it is ready to be passed to the return
		// slip file factory, and to be returned from the method.
		$return = $this->_loader->getByID($returnResult->id());

		// Create the return slip and attach it to the return item
		$document = $this->_returnSlip->save($return);
		$this->_query->run("
			UPDATE
				`return`
			SET
				document_id = :documentID?i
		", [
			'documentID' => $document->id
		]);

		// If there is a related order item update it's status
		if ($return->item->orderItem) {
			$this->_itemEdit->updateStatus($return->item->orderItem, Statuses::AWAITING_RETURN);
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
		// Ensure an item has been set for the return
		if ($return->item->orderItem and ! $return->item->orderItem instanceof OrderItem) {
			throw new InvalidArgumentException('Could not create return item: order item is not set or invalid');
		}

		if ($return->item->order and ! $return->item->order instanceof Order) {
			throw new InvalidArgumentException('Could not create return item: order is not set or invalid');
		}

		// Check the reason has been set and is valid
		if (! $this->_reasons->exists($return->item->reason)) {
			throw new InvalidArgumentException('Could not create return item: reason is not set or invalid');
		}

		// Check the resolution has been set and is valid
		if (! $this->_resolutions->exists($return->item->resolution)) {
			throw new InvalidArgumentException('Could not create return item: resolution is not set or invalid');
		}

		// If this is an exchange, check an exchange unit has been set
		if ($return->item->resolution == 'exchange' and ! $return->item->exchangeItem) {
			throw new InvalidArgumentException('Could not create return item: exchange item required');
		}
	}

}