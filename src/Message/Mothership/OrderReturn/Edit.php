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

		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_ACCEPTED);
	}

	public function refund(Entity\OrderReturn $return, $amount = 0)
	{
		// insert a row into order_refund

		return $return;
	}

	public function exchange(Entity\OrderReturn $return, $balance = 0)
	{
		$this->setBalance($return, $balance);
		$return->balance = $balance;

		// exchange the item

		return $return;
	}

	public function moveStock(Entity\OrderReturn $return, $location)
	{
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