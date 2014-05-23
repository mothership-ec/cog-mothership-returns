<?php

namespace Message\Mothership\OrderReturn\Transaction;

use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Statuses;

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
		return [
			OrderTransaction\Events::VOID => [
				['deleteReturns'],
				['removeReturnedItemFromDestinationStock'],
				['revertReturnedItemStatus'],
			],
		];
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

	/**
	 * Remove returned item from the stock detination it was put into when the
	 * transaction is voided.
	 *
	 * @param OrderTransaction\Event\TransactionalEvent $event
	 */
	public function removeReturnedItemFromDestinationStock(OrderTransaction\Event\TransactionalEvent $event)
	{
		$transaction = $event->getTransaction();

		// Skip if the transaction is not a return transaction
		if (Types::ORDER_RETURN !== $transaction->type) {
			return false;
		}

		$stockManager = $this->get('stock.manager');

		$stockManager->setTransaction($event->getDbTransaction());
		$stockManager->createWithRawNote(true);

		$stockManager->setReason($this->get('stock.movement.reasons')->get('void_transaction'));

		$event->getDbTransaction()->add("
			SET @STOCK_NOTE = CONCAT('Void transaction #', ?i)
		", $transaction->id);

		$stockManager->setNote('@STOCK_NOTE');
		$stockManager->setAutomated(true);

		foreach ($transaction->records->getByType(OrderReturn::RECORD_TYPE) as $return) {
			// Skip if the return is not completed (stock will not have been added)
			if (Statuses::RETURN_COMPLETED !== $return->item->status->code) {
				return false;
			}

			$stockManager->decrement($return->item->unit, $return->item->returnedStockLocation);
		}
	}

	/**
	 * Update the status of the returned item if there is one to whatever the
	 * status was before it was returned.
	 *
	 * Currently this just chooses the status before the current one, but it
	 * could be more robust.
	 *
	 * @todo Look into making this more robust, by knowing how many steps to
	 *       take back in the status history to get to just before the return
	 *       started.
	 *
	 * @param  OrderTransaction\Event\TransactionalEvent $event
	 */
	public function revertReturnedItemStatus(OrderTransaction\Event\TransactionalEvent $event)
	{
		$transaction = $event->getTransaction();

		// Skip if the transaction is not a return transaction
		if (Types::ORDER_RETURN !== $transaction->type) {
			return false;
		}

		$itemEdit = $this->get('order.item.edit');

		$itemEdit->setTransaction($event->getDbTransaction());

		foreach ($transaction->records->getByType(OrderReturn::RECORD_TYPE) as $return) {
			// Skip if the return was not for an order (i.e. it was a standalone return)
			if (!$return->item->order) {
				return false;
			}

			$item    = $return->item->orderItem;
			$history = $this->get('order.item.status.loader')->getHistory($item);

			// Skip if there was not more than 2 statuses on the item
			if (count($history) < 2) {
				continue;
			}

			// Get the status before the current one
			$previousStatus = array_shift($history);
			$previousStatus = array_shift($history);

			$itemEdit->updateStatus($item, $previousStatus->code);
		}
	}
}