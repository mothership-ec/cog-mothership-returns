<?php

namespace Message\Mothership\OrderReturn;

use ReflectionClass;
use InvalidArgumentException;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\Mothership\Commerce\Order\Order;
use Message\Mothership\Commerce\Order\Entity\Item\Item;
use Message\Mothership\Commerce\Product\Unit\Unit;
use Message\Mothership\Ecommerce\OrderItemStatuses;

use Message\User\UserInterface;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create
{
	protected $_query;
	protected $_user;
	protected $_loader;
	protected $_itemEdit;
	protected $_reasons;
	protected $_resolutions;
	protected $_returnSlip;

	public function __construct(DB\Query $query, UserInterface $user, Loader $loader, $itemEdit, Collection\Collection $reasons,
		Collection\Collection $resolutions, $returnSlip)
	{
		$this->_query       = $query;
		$this->_user        = $user;
		$this->_loader      = $loader;
		$this->_itemEdit    = $itemEdit;
		$this->_reasons     = $reasons;
		$this->_resolutions = $resolutions;
		$this->_returnSlip  = $returnSlip;
	}

	public function create(Entity\OrderReturn $return)
	{
		$this->_validate($return);

		// Set create authorship data if not already set
		if (! $return->authorship->createdAt()) {
			$return->authorship->create(
				new DateTimeImmutable,
				$this->_user->id
			);
		}

		// Insert the return into the database
		$result = $this->_query->run('
			INSERT INTO
				order_item_return
			SET
				order_id           = :orderID?i,
				item_id            = :itemID?i,
				created_at         = :createdAt?i,
				created_by         = :createdBy?i,
				reason             = :reason?s,
				resolution         = :resolution?s,
				exchange_item_id   = :exchangeItemID?i,
				calculated_balance = :balance?f
		', array(
			'orderID'        => $return->order->id,
			'itemID'         => $return->item->id,
			'createdAt'      => $return->authorship->createdAt(),
			'createdBy'      => $return->authorship->createdBy(),
			'reason'         => $return->reason,
			'resolution'     => $return->resolution,
			'exchangeItemID' => ($return->exchangeItem) ? $return->exchangeItem->id : 0,
			'balance'        => $return->balance
		));

		// Get the return by the last insert id
		$return = $this->_loader->getByID($result->id());

		// Create the return slip
		$document = $this->_returnSlip->save($return);

		$this->_query->run('
			UPDATE
				order_item_return
			SET
				document_id = :documentID?i
		', array(
			'documentID' => $document->id
		));

		// Update item statuses
		$this->_itemEdit->updateStatus($return->item, Statuses::AWAITING_RETURN);
		if ($return->exchangeItem) {
			$this->_itemEdit->updateStatus($return->exchangeItem, OrderItemStatuses::HOLD);
		}

		return $return;
	}

	protected function _validate(Entity\OrderReturn $return)
	{
		// Ensure an item has been set for the return
		if (! $return->item instanceof Item) {
			throw new InvalidArgumentException('Could not create order return: item is not set or invalid');
		}

		if (! $return->order instanceof Order) {
			throw new InvalidArgumentException('Could not create order return: order is not set or invalid');
		}

		// Check the reason has been set and is valid
		if (! $this->_reasons->exists($return->reason)) {
			throw new InvalidArgumentException('Could not create order return: reason is not set or invalid');
		}

		// Check the resolution has been set and is valid
		if (! $this->_resolutions->exists($return->resolution)) {
			throw new InvalidArgumentException('Could not create order return: resolution is not set or invalid');
		}

		// If this is an exchange, check an exchange unit has been set
		if ($return->resolution == 'exchange' and ! $return->exchangeItem) {
			throw new InvalidArgumentException('Could not create order return: exchange item required');
		}

		// ... any more?
	}

}