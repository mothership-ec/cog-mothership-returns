<?php

namespace Message\Mothership\OrderReturn\Transaction;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\EventListener as BaseListener;

use Message\Mothership\OrderReturn\Events;
use Message\Mothership\OrderReturn\Event;
use Message\Mothership\OrderReturn\Entity\OrderReturn;

use Message\Mothership\Commerce\Order\Transaction\Transaction;
use Message\Mothership\Commerce\Order\Entity\Payment\Payment as OrderPayment;
use Message\Mothership\Commerce\Order\Entity\Refund\Refund as OrderRefund;

/**
 * Event listener to add transaction when a return is created
 *
 * @author Iris Schaffer <iris@message.co.uk>
 */
class CreateListener extends BaseListener implements SubscriberInterface
{
	/**
	 * {@inheritdoc}
	 */
	static public function getSubscribedEvents()
	{
		return [
		Events::CREATE_END => [
				['returnCreated',],
			],
		];
	}

	public function returnCreated(Event $event)
	{
		$return = $event->getReturn();
		$transaction = new Transaction;
		$transaction->type = Types::ORDER_RETURN;

		$transaction->records->add($return);

		foreach ($return->payments as $payment) {
			if ($return->item->order) {
				$payment = new OrderPayment($payment);
				$payment->order = $return->item->order;
			}
			$transaction->records->add($payment);
		}

		foreach ($return->refunds as $refund) {
			if ($return->item->order) {
				$refund = new OrderRefund($refund);
				$refund->order = $return->item->order;
			}
			$transaction->records->add($refund);
		}

		$this->get('order.transaction.create')
			->setDbTransaction($event->getTransaction())
			->create($transaction);
	}
}