<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class ReturnRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.return']->setParent('ms.cp')->setPrefix('/return');
		$router['ms.return']->add('ms.commerce.return.dashboard', '', '::Controller:OrderReturn:Listing#dashboard');
		$router['ms.return']->add('ms.commerce.return.listing.status', '/view/{status}', '::Controller:OrderReturn:Listing#all');
		$router['ms.return']->add('ms.commerce.return.search.action', '/search', '::Controller:OrderReturn:Listing#searchAction');

		$router['ms.return']->add('ms.commerce.return.view', '/{returnID}/view', '::Controller:OrderReturn:Detail#view')
			->setRequirement('returnID', '\d+');
		$router['ms.return']->add('ms.commerce.return.edit.accept-or-reject', '/{returnID}/edit/accept-or-reject', '::Controller:OrderReturn:Detail#acceptOrReject')
			->setRequirement('returnID', '\d+');
		$router['ms.return']->add('ms.commerce.return.edit.received', '/{returnID}/edit/received', '::Controller:OrderReturn:Detail#processReceived')
			->setRequirement('returnID', '\d+');
		$router['ms.return']->add('ms.commerce.return.edit.balance', '/{returnID}/edit/balance', '::Controller:OrderReturn:Detail#processBalance')
			->setRequirement('returnID', '\d+');
		$router['ms.return']->add('ms.commerce.return.edit.exchange', '/{returnID}/edit/exchange', '::Controller:OrderReturn:Detail#processExchange')
			->setRequirement('returnID', '\d+');
		$router['ms.return']->add('ms.commerce.return.edit.returned-item', '/{returnID}/edit/returned-item', '::Controller:OrderReturn:Detail#processReturnedItem')
			->setRequirement('returnID', '\d+');
	}
}