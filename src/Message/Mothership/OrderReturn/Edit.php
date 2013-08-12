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

	public function __construct(DB\Query $query, UserInterface $user, Order\Entity\Item\Edit $itemEdit,
		$orderStatuses, $itemStatuses)
	{
		$this->_query = $query;
		$this->_user  = $user;
		$this->_itemEdit = $itemEdit;
		$this->__orderStatuses = $_orderStatuses;
		$this->_itemStatuses = $itemStatuses;
	}

	public function reject(OrderReturn $return)
	{
		$this->_itemEdit->updateStatus($return->item, $this->_itemStatuses->get(OrderStatuses::RETURN_REJECTED));
	}

	public function setAsReceived(OrderReturn $return)
	{
		$this->_itemEdit->updateStatus($return->item, $this->_itemStatuses->get(OrderStatuses::RETURN_ACCEPTED));
	}

	public function setAsAwaitingBalancePayment(OrderReturn $return)
	{
		
	}

	public function setBalance(OrderReturn $return, $balance)
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

	public function setReturnStockLocation(OrderReturn $return, $location)
	{

	}

}