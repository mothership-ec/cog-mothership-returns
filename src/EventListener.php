<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\EventListener as BaseListener;

use Message\Mothership\ControlPanel\Event\BuildMenuEvent;

use Message\Mothership\Commerce\Events as CommerceEvents;
use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event\BuildOrderTabsEvent;

use Message\Mothership\Report\Event as ReportEvents;
use Message\Mothership\Commerce\Order\Event\SetOrderStatusEvent;

use Message\Mothership\Commerce\Order\Statuses as OrderStatuses;

/**
 * Event listener for building the OrderReturn's menu.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return [
			BuildMenuEvent::BUILD_MAIN_MENU => [
				'registerMainMenuItems'
			],
			OrderEvents::BUILD_ORDER_TABS => [
				'registerTabItems'
			],
			CommerceEvents::SALES_REPORT => [
				'buildSalesReport'
			],
			CommerceEvents::TRANSACTIONS_REPORT => [
				'buildTransactionReport'
			],
			Events::CREATE_COMPLETE => [
		 		'saveDocument'
			],
			OrderEvents::SET_STATUS => array(
				array('checkStatus'),
			),
		];
	}

	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.return.dashboard', 'Returns', array('ms.returns'));
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildOrderTabsEvent $event The event
	 */
	public function registerTabItems(BuildOrderTabsEvent $event)
	{
		$event->addItem('ms.commerce.order.view.return', 'ms.commerce.return.listing-title');
	}

	public function buildSalesReport(ReportEvents\ReportEvent $event)
	{
		foreach ($this->get('return.report.sales-data') as $query) {
			$event->addQueryBuilder($query->setFilters($event->getFilters())->getQueryBuilder());
		}
	}

	public function buildTransactionReport(ReportEvents\ReportEvent $event)
	{
		foreach ($this->get('return.report.transaction-data') as $query) {
			$event->addQueryBuilder($query->setFilters($event->getFilters())->getQueryBuilder());
		}
	}

	public function saveDocument(Event $event)
	{
		$return     = $event->getReturn();
		$statusCode = $return->item->status->code;

		if ($statusCode === Statuses::AWAITING_RETURN) {
			$document = $this->get('file.return_slip')->save($return);

			$this->get('db.query')->run("
				UPDATE
					`return`
				SET
					document_id = :documentID?i
				WHERE
					return_id = :returnID?i
				", [
					'documentID' => $document->id,
					'returnID'   => $return->id,
				]
			);
		}
	}

	/**
	 * Update the order's overall status to the appropriate code. Very similar to
	 * the commerce EventListener but excludes return completed from the count.
	 *
	 * @param  Event\SetOrderStatusEvent $event The event object
	 * @see Message\Mothership\Commerce\Order\EventListener\StatusListener::checkStatus
	 */
	public function checkStatus(SetOrderStatusEvent $event)
	{
		// If there are any retuns on the order then it will be marked as PROCESSING
		if ($event->getStatus() !== OrderStatuses::PROCESSING) {
			return;
		}


		$itemStatuses = array_fill_keys(array_keys($this->get('order.item.statuses')->all()), 0);
		$numItems     = $event->getOrder()->items->count();

		// Group items by status
		foreach ($event->getOrder()->items as $item) {
			if (!array_key_exists($item->status->code, $itemStatuses)) {
				$itemStatuses[$item->status->code] = 0;
			}

			$status = $item->status->code;

			$itemStatuses[$item->status->code]++;
		}

		// Exclude return completed
		$numItems = $numItems - $itemStatuses[Statuses::RETURN_COMPLETED];

		// All items cancelled
		if ($numItems === $itemStatuses[OrderStatuses::CANCELLED]) {
			return $event->setStatus(OrderStatuses::CANCELLED);
		}

		// All items awaiting dispatch
		if ($numItems === $itemStatuses[OrderStatuses::AWAITING_DISPATCH]) {
			return $event->setStatus(OrderStatuses::AWAITING_DISPATCH);
		}

		// All items dispatched
		if ($numItems === $itemStatuses[OrderStatuses::DISPATCHED]) {
			return $event->setStatus(OrderStatuses::DISPATCHED);
		}

		// All items received
		if ($numItems === $itemStatuses[OrderStatuses::RECEIVED]) {
			return $event->setStatus(OrderStatuses::RECEIVED);
		}

		// Any items received
		if ($itemStatuses[OrderStatuses::RECEIVED] > 0) {
			return $event->setStatus(OrderStatuses::PARTIALLY_RECEIVED);
		}

		// Any items dispatched
		if ($itemStatuses[OrderStatuses::DISPATCHED] > 0) {
			return $event->setStatus(OrderStatuses::PARTIALLY_DISPATCHED);
		}

		// Currently being processed
		return $event->setStatus(OrderStatuses::PROCESSING);
	}
}