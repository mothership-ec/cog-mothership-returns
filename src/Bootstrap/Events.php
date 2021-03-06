<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Mothership\OrderReturn;

use Message\Cog\Bootstrap\EventsInterface;
use Message\Cog\Service\ContainerInterface;
use Message\Cog\Service\ContainerAwareInterface;


class Events implements EventsInterface, ContainerAwareInterface
{
	protected $_services;

	public function setContainer(ContainerInterface $container)
	{
		$this->_services = $container;
	}

	public function registerEvents($dispatcher)
	{
		$dispatcher->addSubscriber(new OrderReturn\EventListener);
		$dispatcher->addSubscriber(new OrderReturn\Transaction\CreateListener);
		$dispatcher->addSubscriber(new OrderReturn\Transaction\VoidListener);
	}
}