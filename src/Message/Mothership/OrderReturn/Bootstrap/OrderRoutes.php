<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class OrderRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.order']->add('ms.commerce.order.view.return', 'view/{orderID}/return', '::Controller:OrderReturn:Order:Listing#view')
			->setRequirement('orderID', '\d+');

		$router['ms.order']->add('ms.commerce.order.return.edit.accept-or-reject', 'return/{returnID}/edit/accept-or-reject', '::Controller:OrderReturn:Order:Detail#acceptOrReject')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.return.edit.received', 'return/{returnID}/edit/received', '::Controller:OrderReturn:Order:Detail#processReceived')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.return.edit.balance', 'return/{returnID}/edit/balance', '::Controller:OrderReturn:Order:Detail#processBalance')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.return.edit.exchange', 'return/{returnID}/edit/exchange', '::Controller:OrderReturn:Order:Detail#processExchange')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.return.edit.returned-item', 'return/{returnID}/edit/returned-item', '::Controller:OrderReturn:Order:Detail#processReturnedItem')
			->setRequirement('returnID', '\d+');
	}
}