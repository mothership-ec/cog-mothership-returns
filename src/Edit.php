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
	protected $_currentUser;
	protected $_itemEdit;
	protected $_refundCreate;

	public function __construct(
		DB\Query $query,
		UserInterface $currentUser,
		Order\Entity\Item\Edit $itemEdit,
		Order\Entity\Refund\Create $refundCreate
	) {
		$this->_query        = $query;
		$this->_currentUser  = $currentUser;
		$this->_itemEdit     = $itemEdit;
		$this->_refundCreate = $refundCreate;
	}

	public function setAsReceived(Entity\OrderReturn $return)
	{
		$return->authorship->update(new DateTimeImmutable, $this->_currentUser);

		$this->_itemEdit->updateStatus($return->item, Statuses::RETURN_RECEIVED);

		if ($return->item->orderItem) {
			$this->_itemEdit->updateStatus($return->item->orderItem, Statuses::RETURN_RECEIVED);
		}

		$this->_setUpdatedReturn($return);
		$this->_setUpdatedReturnItems($return);
	}

	public function accept(Entity\OrderReturn $return)
	{
		$return->item->accepted = true;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser);

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 1
			WHERE
				return_id  = :returnID?i,
				updated_at = :updatedAt?i,
				updated_by = :updatedBy?i
		', array(
			'returnID'  => $return->id,
			'updatedAt' => $return->authorship->updatedAt(),
			'updatedBy' => $return->authorship->updatedBy(),
		));

		$this->_setUpdatedReturnItems($return);

		return $return;
	}

	public function reject(Entity\OrderReturn $return)
	{
		$return->item->accepted = false;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser);

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 0
			WHERE
				return_id  = :returnID?i,
				updated_at = :updatedAt?i,
				updated_by = :updatedBy?i
		', array(
			'returnID'  => $return->id,
			'updatedAt' => $return->authorship->updatedAt(),
			'updatedBy' => $return->authorship->updatedBy(),
		));

		$this->_setUpdatedReturnItems($return);

		return $return;
	}

	public function setBalance(Entity\OrderReturn $return, $balance)
	{
		$return->item->balance = $balance;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser);

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				return_item
			SET
				balance    = :balance?f,
				updated_at = :updatedAt?i,
				updated_by = :updatedBy?i
			WHERE
				return_id = :returnID?i
		', array(
			'balance'   => $balance,
			'updatedAt' => $return->authorship->updatedAt(),
			'updatedBy' => $return->authorship->updatedBy(),
			'returnID'  => $return->id,
		));

		$this->_setUpdatedReturnItems($return);

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

		$this->_setUpdatedReturn($return);
		$this->_setUpdatedReturnItems($return);

		return $return;
	}

	protected function _validate(Entity\OrderReturn $return)
	{
		//
	}

	protected function _setUpdatedReturn(Entity\OrderReturn $return)
	{
		$this->_query->run("
			UPDATE
				return
			SET
				updated_at = :updatedAt?i,
				updated_by = :updatedBy?i
			WHERE
				return_id = :returnID?i
			", [
				'updatedAt' => $return->authorship->updatedAt(),
				'updatedBy' => $return->authorship->updatedBy(),
				'returnID'  => $return->id,
			]
		);
	}

	protected function _setUpdatedReturnItems(Entity\OrderReturn $return)
	{
		$this->_query->run("
			UPDATE
				return_item
			SET
				updated_at = :updatedAt?i,
				updated_by = :updatedBy?i
			WHERE
				return_id = :returnID?i
			", [
				'updatedAt' => $return->authorship->updatedAt(),
				'updatedBy' => $return->authorship->updatedBy(),
				'returnID'  => $return->id,
			]
		);
	}

}