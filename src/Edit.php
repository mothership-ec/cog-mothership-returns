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
		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_query->run("
			UPDATE
				return_item
			SET
				status_code = :status?i,
				updated_at  = :updatedAt?d,
				updated_by  = :updatedBy?in
			WHERE
				return_id = :returnID?i
		", [
			'status'    => Statuses::RETURN_RECEIVED,
			'updatedAt' => $return->authorship->updatedAt(),
			'updatedBy' => $return->authorship->updatedBy(),
			'returnID'  => $return->id,
		]);

		if ($return->item->orderItem) {
			$this->_itemEdit->updateStatus($return->item->orderItem, Statuses::RETURN_RECEIVED);
		}

		$this->_setUpdatedReturn($return);
	}

	public function accept(Entity\OrderReturn $return)
	{
		$return->item->accepted = true;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 1,
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
			WHERE
				return_id  = :returnID?i
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

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_query->run('
			UPDATE
				return_item
			SET
				accepted = 0,
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
			WHERE
				return_id  = :returnID?i
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

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				return_item
			SET
				balance    = :balance?f,
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
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
	 * @todo Make this work with the base refund entity not order refunds.
	 */
	public function refund(Entity\OrderReturn $return, $method, $amount, Order\Entity\Payment\Payment $payment = null, $reference = null)
	{
		// Create the refund
		$refund = new Order\Entity\Refund\Refund;
		$refund->method    = $method;
		$refund->amount    = $amount;
		$refund->reason    = 'Returned Item: ' . $return->item->reason;
		$refund->order     = $return->item->order;
		$refund->payment   = $payment;
		$refund->return    = $return;
		$refund->reference = $reference;

		$refund = $this->_refundCreate->create($refund);

		$return->item->order->refunds->append($refund);

		$this->_setUpdatedReturn($return);
		$this->_setUpdatedReturnItems($return);

		// Set the new balance of the return
		$this->setBalance($return, $return->balance + $amount);

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
				`return`
			SET
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
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
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
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