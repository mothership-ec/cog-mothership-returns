<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\User\UserInterface;

use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Product;

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
	protected $_refundCreate;
	protected $_stockManager;

	public function __construct(DB\Query $query, UserInterface $user, Order\Entity\Item\Edit $itemEdit,
		Order\Entity\Refund\Create $refundCreate, $stockManager)
	{
		$this->_query = $query;
		$this->_user  = $user;
		$this->_itemEdit = $itemEdit;
		$this->_refundCreate = $refundCreate;
		$this->_stockManager = $stockManager;
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

	public function refund(Entity\OrderReturn $return, $method, $amount)
	{
		// Create the refund
		$refund = new Order\Entity\Refund\Refund;
		$refund->method = $method;
		$refund->amount = $amount;
		$refund->reason = 'Returned Item: ' . $return->reason;
		$refund->order = $return->order;
		$refund = $this->_refundCreate->create($refund);

		// Update the return with the new balance
		$this->_query->run('
			UPDATE
				order_item_return
			SET
				balance = :balance?f
			WHERE
				return_id = :returnID?i
		', array(
			'balance' => 0,
			'method' => $refund->method,
			'amount' => $refund->amount,
			'reason' => $refund->reason,
			'returnID' => $return->id
		));

		// Update item status
		$this->_itemEdit->updateStatus($return->item, Statuses::REFUND_PAID);

		$return->refund = $refund;

		return $return;
	}

	public function exchange(Entity\OrderReturn $return, $balance = 0)
	{
		// Exchange the items
		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_ITEM_EXCHANGED);

		if ($balance != 0) {
			$this->_itemEdit->updateStatus($return->exchangeItem, Statuses::AWAITING_EXCHANGE_BALANCE_PAYMENT);
		}
		else {
			$this->_itemEdit->updateStatus($return->exchangeItem, Statuses::AWAITING_DISPATCH);
		}

		return $return;
	}

	public function moveUnitStock(Order\Entity\Product\Unit\Unit $unit, Product\Stock\Location\Location $location, Product\Stock\Movement\Reason\Reason $reason)
	{
		$this->_stockManager->increment(
			$unit,
			$location,
			$reason
		);

		$this->_stockManager->setReason($reason);
		$this->_stockManager->setAutomated(false);

		return $this->_stockManager->commit();
	}

}