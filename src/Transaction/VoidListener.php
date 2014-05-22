<?php

namespace Message\Mothership\OrderReturn\Transaction;

use Message\Mothership\OrderReturn\Entity\OrderReturn;

use Message\Mothership\Commerce\Order\Transaction as OrderTransaction;

use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Event\SubscriberInterface;

/**
 * Event listener for voiding transactions.
 *
 * @todo dont inherit baselistener, instead inject the dependencies we need
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 */
class VoidListener extends BaseListener implements SubscriberInterface
{
	/**
	 * {@inheritdoc}
	 */
	static public function getSubscribedEvents()
	{
		return array(
			OrderTransaction\Events::VOID => array(
				array('deleteReturns'),
			),
		);
	}

	/**
	 * Deletes any records of type "return" when a transaction is voided.
	 *
	 * @param OrderTransaction\Event\TransactionalEvent $event
	 */
	public function deleteReturns(OrderTransaction\Event\TransactionalEvent $event)
	{
		$transaction = $event->getTransaction();
		$delete      = $this->get('return.delete');

		$delete->setTransaction($event->getDbTransaction());

		foreach ($transaction->records->getByType(OrderReturn::RECORD_TYPE) as $item) {
			$delete->delete($item);
		}
	}
}