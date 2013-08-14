<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\User\UserInterface;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\Mothership\Commerce\Order;

/**
 * Order return editor.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Edit
{
	protected $_query;
	protected $_user;
	protected $_itemEdit;
	protected $_orderStatuses;
	protected $_itemStatuses;

	public function __construct(DB\Query $query, UserInterface $user, Order\Entity\Item\Edit $itemEdit)
	{
		$this->_query = $query;
		$this->_user  = $user;
		$this->_itemEdit = $itemEdit;
	}

	public function accept(Entity\OrderReturn $return)
	{
		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_ACCEPTED);
	}

	public function reject(Entity\OrderReturn $return)
	{
		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_REJECTED);
	}

	public function setAsReceived(Entity\OrderReturn $return, $date = null)
	{
		$date = ($date !== null) ?: date('Y-m-d H:i:s');
		// notify customer

		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_RECEIVED);
	}

	public function refund(Entity\OrderReturn $return, $amount)
	{
		// Create the refund
		$refund = new Refund;
		$refund->amount = $amount;
		$refund->reason = 'Returned Item: ' . $return->reason;
		$refund->order = $return->order;
		$refund = $this->_refundCreate->create($refund);

		$return->balance -= $refund->amount;

		// Update the return with the new balance
		$this->_query->run('
			UPDATE
				order_return_item
			SET
				balance = :balance?f
			WHERE
				return_id = :returnID?i
		', array(
			'balance' => $return->balance,
			'returnID' => $return->id
		));

		return $return;
	}

	public function exchange(Entity\OrderReturn $return)
	{
		// Exchange the items
		$return->item = $this->_itemEdit->updateStatus($return->item, Statuses::RETURN_ITEM_EXCHANGED);
		$return->exchangeItem = $this->_itemEdit->updateStatus($return->exchangeItem, Statuses::AWAITING_EXCHANGE_DISPATCH);

		return $return;
	}

	public function moveStock(Entity\OrderReturn $return, $location)
	{
		$return->item = $this->_itemEdit->moveStock($return->item, $location);
		return $return;
	}

	public function setAsAwaitingBalancePayment(Entity\OrderReturn $return)
	{
		
	}

	public function setBalance(Entity\OrderReturn $return, $balance)
	{
		$this->_query->run('
			UPDATE
				order_item_return
			SET
				balance = :balance?d
			WHERE
				return_id = :returnID?i
		', array(
			'balance' => $balance,
			'returnID' => $return->id
		));
	}

	public function setReturnStockLocation(Entity\OrderReturn $return, $location)
	{

	}

}