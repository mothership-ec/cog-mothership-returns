<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\Event;
use Message\Cog\Event\EventListener as BaseListener;

use Message\Mothership\ControlPanel\Event\BuildMenuEvent;

use Message\Mothership\Commerce\Events as CommerceEvents;
use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event\BuildOrderTabsEvent;

use Message\Mothership\Report\Event as ReportEvents;

/**
 * Event listener for building the OrderReturn's menu.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			BuildMenuEvent::BUILD_MAIN_MENU => array(
				'registerMainMenuItems'
			),
			OrderEvents::BUILD_ORDER_TABS => array(
				'registerTabItems'
			),
			CommerceEvents::SALES_REPORT => [
				'buildSalesReport'
			],
			CommerceEvents::TRANSACTIONS_REPORT => [
				'buildTransactionReport'
			],
		);
	}

	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.return.dashboard', 'Returns', array('ms.returns'));
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
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


}