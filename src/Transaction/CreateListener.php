<?php

namespace Message\Mothership\OrderReturn\Transaction;

use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\Commerce\Order\Transaction\Transaction;
use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event;

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
		return array(
			OrderEvents::ENTITY_CREATE => array(
				array('entityCreated'),
			),
		);
	}

	public function entityCreated(Event\EntityEvent $event)
	{
		$return = $event->getEntity();
		if($return instanceof OrderReturn) {
			$transaction = new Transaction;
			$transaction->addRecord($return);

			if($return->refund) {
				$transaction->addRecord($return->refund);
			}

			$transaction->type = 'return';

			$this->get('order.transaction.create')->create($transaction);
		}
	}
}