<?php

namespace Message\Cog\Filesystem;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\Event;
use Message\Cog\Event\EventListener as BaseListener;
use Message\Cog\Service\ContainerInterface;

/**
 * Event listener for the OrderReturn component.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array();
	}

	public function registerSomething(Event $event)
	{
		
	}
}