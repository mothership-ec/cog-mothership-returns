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

	public function setAsReceived(Entity\OrderReturn $return, $date = null)
	{
		$date = ($date !== null) ?: date('Y-m-d H:i:s');
		// notify customer?

		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_RECEIVED);
	}

	public function accept(Entity\OrderReturn $return)
	{
		$return->accepted = true;

		$this->_query->run('
			UPDATE
				order_item_return
			SET
				accepted = :accepted
			WHERE
				return_id = :returnID?i
		', array(
			'accepted' => $return->accepted,
			'returnID' => $return->id
		));

		return $return;
	}

	public function reject(Entity\OrderReturn $return)
	{
		$return->accepted = false;

		$this->_query->run('
			UPDATE
				order_item_return
			SET
				accepted = :accepted
			WHERE
				return_id = :returnID?i
		', array(
			'accepted' => $return->accepted,
			'returnID' => $return->id
		));

		return $return;
	}

	public function setBalance(Entity\OrderReturn $return, $balance)
	{
		$return->balance = $balance;

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				order_item_return
			SET
				balance = :balance?f
			WHERE
				return_id = :returnID?i
		', array(
			'balance' => $balance,
			'returnID' => $return->id
		));

		return $return;
	}

	public function clearBalance(Entity\OrderReturn $return)
	{
		return $this->setBalance($return, 0);
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

		$return->refund = $refund;

		return $return;
	}

	public function moveUnitStock(Product\Unit\Unit $unit, Product\Stock\Location\Location $location, Product\Stock\Movement\Reason\Reason $reason)
	{
		$this->_stockManager->setReason($reason);
		$this->_stockManager->setAutomated(false);
		
		$this->_stockManager->increment(
			$unit,
			$location
		);

		return $this->_stockManager->commit();
	}

	protected function _validate(Entity\OrderReturn $return)
	{
		// 
	}

}