<?php

namespace Message\Mothership\OrderReturn\Transaction;

use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\Commerce\Order\Transaction\Transaction;
use Message\Mothership\OrderReturn\Events as Events;
use Message\Mothership\OrderReturn\Event;
use Message\Cog\Event\EventListener as BaseListener;

use Message\Cog\Event\SubscriberInterface;

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

		$payments = $return->item->order->payments->all();
		foreach ($payments as $payment) {
			if ($payment->return === $return) {
				$transaction->records->add($payment);
			}
		}

		// $refunds = $return->item->order->refunds->all();
		// foreach ($refunds as $refund) {
		// 	if ($refund->return === $return) {
		// 		$transaction->records->add($refund);
		// 	}
		// }

		$this->get('order.transaction.create')
			->setDbTransaction($event->getTransaction())
			->create($transaction);
	}
}