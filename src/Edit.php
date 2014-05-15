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

	public function __construct(
		DB\Query $query,
		UserInterface $user,
		Order\Entity\Item\Edit $itemEdit,
		Order\Entity\Refund\Create $refundCreate
	) {
		$this->_query = $query;
		$this->_user  = $user;
		$this->_itemEdit = $itemEdit;
		$this->_refundCreate = $refundCreate;
	}

	public function setAsReceived(Entity\OrderReturn $return)
	{
		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_RECEIVED);

		if ($return->item->orderItem) {
			$this->_itemEdit->updateStatus($return->item->orderItem, Statuses::RETURN_RECEIVED);
		}
	}

	public function accept(Entity\OrderReturn $return)
	{
		$return->item->accepted = true;

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 1
			WHERE
				return_id = :returnID?i
		', array(
			'returnID' => $return->id
		));

		return $return;
	}

	public function reject(Entity\OrderReturn $return)
	{
		$return->item->accepted = false;

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 0
			WHERE
				return_id = :returnID?i
		', array(
			'returnID' => $return->id
		));

		return $return;
	}

	public function setBalance(Entity\OrderReturn $return, $balance)
	{
		$return->item->balance = $balance;

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				return_item
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

	/**
	 * @todo Throw an exception if there is no associated order.
	 */
	public function refund(Entity\OrderReturn $return, $method, $amount)
	{
		// Create the refund
		$refund = new Order\Entity\Refund\Refund;
		$refund->method = $method;
		$refund->amount = $amount;
		$refund->reason = 'Returned Item: ' . $return->item->reason;
		$refund->order  = $return->item->order;
		$refund->return = $return;

		$refund = $this->_refundCreate->create($refund);

		$return->item->order->refunds->append($refund);

		return $return;
	}

	protected function _validate(Entity\OrderReturn $return)
	{
		//
	}

}