<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\Event;
use Message\Cog\Event\EventListener as BaseListener;
use Message\Mothership\ControlPanel\Event\BuildMenuEvent;
use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event\BuildOrderTabsEvent;

/**
 * Event listener for the OrderReturn component.
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
}