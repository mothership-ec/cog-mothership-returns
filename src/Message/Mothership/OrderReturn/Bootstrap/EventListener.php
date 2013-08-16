<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\Event;
use Message\Cog\Event\EventListener as BaseListener;
use Message\Mothership\ControlPanel\Event\BuildMenuEvent;

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
		);
	}

	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.return.dashboard', 'Returns', array('ms.returns'));
	}
}