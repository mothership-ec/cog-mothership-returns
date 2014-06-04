<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;
use Message\User\UserInterface;

use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Refund;
use Message\Mothership\Commerce\Product;
use Message\Mothership\Commerce\Payment;

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
	protected $_orderRefundCreate;
	protected $_paymentCreate;
	protected $_orderPaymentCreate;

	public function __construct(
		DB\Query $query,
		UserInterface $currentUser,
		Order\Entity\Item\Edit $itemEdit,
		Payment\Create $paymentCreate,
		Order\Entity\Payment\Create $orderPaymentCreate,
		Refund\Create $refundCreate,
		Order\Entity\Refund\Create $orderRefundCreate
	) {
		$this->_query              = $query;
		$this->_currentUser        = $currentUser;
		$this->_itemEdit           = $itemEdit;
		$this->_paymentCreate      = $paymentCreate;
		$this->_orderPaymentCreate = $orderPaymentCreate;
		$this->_refundCreate       = $refundCreate;
		$this->_orderRefundCreate  = $orderRefundCreate;
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

		return $return;
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

		return $return;
	}

	public function setBalance(Entity\OrderReturn $return, $balance)
	{
		$return->item->balance = $balance;
		$return->item->remainingBalance = $balance;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				return_item
			SET
				balance           = :balance?f,
				remaining_balance = :balance?f,
				updated_at        = :updatedAt?d,
				updated_by        = :updatedBy?in
			WHERE
				return_id = :returnID?i
		', array(
			'balance'   => $balance,
			'updatedAt' => $return->authorship->updatedAt(),
			'updatedBy' => $return->authorship->updatedBy(),
			'returnID'  => $return->id,
		));

		return $return;
	}

	public function setRemainingBalance(Entity\OrderReturn $return, $remainingBalance)
	{
		$return->item->remainingBalance = $remainingBalance;

		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

		$this->_validate($return);

		$this->_query->run('
			UPDATE
				return_item
			SET
				remaining_balance = :remainingBalance?f,
				updated_at        = :updatedAt?d,
				updated_by        = :updatedBy?in
			WHERE
				return_id = :returnID?i
		', array(
			'remainingBalance' => $remainingBalance,
			'updatedAt'        => $return->authorship->updatedAt(),
			'updatedBy'        => $return->authorship->updatedBy(),
			'returnID'         => $return->id,
		));

		return $return;
	}

	public function clearRemainingBalance(Entity\OrderReturn $return)
	{
		return $this->setRemainingBalance($return, 0);
	}

	public function addPayment(Entity\OrderReturn $return, $method, $amount, $reference)
	{
		// Create the payment
		$payment = new Payment\Payment;

		$payment->method    = $method;
		$payment->amount    = $amount;
		$payment->reference = $reference;
		$payment->currencyID = $return->currencyID;

		$this->_paymentCreate->create($payment);

		$this->_query->run("
			INSERT INTO
				`return_payment`
			SET
				return_id  = :returnID?i,
				payment_id = :paymentID?i
		", [
			'returnID'  => $return->id,
			'paymentID' => $payment->id,
		]);

		if ($return->item->order) {
			$orderPayment = new Order\Entity\Payment\Payment($payment);
			$orderPayment->order = $return->item->order;
			$return->item->order->payments->append($orderPayment);

			$this->_orderPaymentCreate->create($orderPayment);
		}

		$this->_setUpdatedReturn($return);
		$this->_setUpdatedReturnItems($return);

		// Set the new remaining balance of the return
		$this->setRemainingBalance($return, $return->item->remainingBalance - $amount);
	}

	public function refund(Entity\OrderReturn $return, $method, $amount, Order\Entity\Payment\Payment $payment = null, $reference = null)
	{
		// Create the refund
		$refund = new Refund\Refund;

		$refund->method     = $method;
		$refund->amount     = $amount;
		$refund->reason     = 'Returned Item: ' . $return->item->reason;
		$refund->payment    = $payment;
		$refund->reference  = $reference;
		$refund->currencyID = $return->currencyID;

		$this->_refundCreate->create($refund);

		$this->_query->run("
			INSERT INTO
				`return_refund`
			SET
				return_id = :returnID?i,
				refund_id = :refundID?i
		", [
			'returnID' => $return->id,
			'refundID' => $refund->id,
		]);

		if ($return->item->order) {
			$orderRefund = new Order\Entity\Refund\Refund($refund);
			$orderRefund->order = $return->item->order;
			$return->item->order->refunds->append($orderRefund);

			$this->_orderRefundCreate->create($orderRefund);
		}

		$this->_setUpdatedReturn($return);
		$this->_setUpdatedReturnItems($return);

		// Set the new remaining balance of the return
		$this->setRemainingBalance($return, $return->item->balance + $amount);

		return $return;
	}

	public function complete(Entity\OrderReturn $return)
	{
		$return->item->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

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
			'status'    => Statuses::RETURN_COMPLETED,
			'updatedAt' => $return->item->authorship->updatedAt(),
			'updatedBy' => $return->item->authorship->updatedBy(),
			'returnID'  => $return->id,
		]);

		// Complete the returned item
		$this->_itemEdit->updateStatus($return->item->orderItem, Statuses::RETURN_COMPLETED);

		return $return;
	}

	protected function _validate(Entity\OrderReturn $return)
	{
		//
	}

	protected function _setUpdatedReturn(Entity\OrderReturn $return)
	{
		$return->authorship->update(new DateTimeImmutable, $this->_currentUser->id);

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
		$return->item->authorship->update($return->authorship->updatedAt(), $return->authorshipo->updatedBy());

		$this->_query->run("
			UPDATE
				return_item
			SET
				updated_at = :updatedAt?d,
				updated_by = :updatedBy?in
			WHERE
				return_id = :returnID?i
			", [
				'updatedAt' => $return->item->authorship->updatedAt(),
				'updatedBy' => $return->item->authorship->updatedBy(),
				'returnID'  => $return->id,
			]
		);
	}

}