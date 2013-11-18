<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class ReturnRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		// Frontend
		$router['ms.return']->setPrefix('/return');
		$router['ms.return']->add('ms.return.terms', '/terms', '::Controller:OrderReturn:Terms#view');

		// Control Panel
		$router['ms.cp.return']->setParent('ms.cp')->setPrefix('/return');
		$router['ms.cp.return']->add('ms.commerce.return.dashboard', '', '::Controller:OrderReturn:Listing#dashboard');
		$router['ms.cp.return']->add('ms.commerce.return.listing.status', '/view/{status}', '::Controller:OrderReturn:Listing#all');
		$router['ms.cp.return']->add('ms.commerce.return.search.action', '/search', '::Controller:OrderReturn:Listing#searchAction');

		$router['ms.cp.return']->add('ms.commerce.return.view', '/{returnID}/view', '::Controller:OrderReturn:Detail#view')
			->setRequirement('returnID', '\d+');
		$router['ms.cp.return']->add('ms.commerce.return.edit.accept-or-reject', '/{returnID}/edit/accept-or-reject', '::Controller:OrderReturn:Detail#acceptOrReject')
			->setRequirement('returnID', '\d+');
		$router['ms.cp.return']->add('ms.commerce.return.edit.received', '/{returnID}/edit/received', '::Controller:OrderReturn:Detail#processReceived')
			->setRequirement('returnID', '\d+');
		$router['ms.cp.return']->add('ms.commerce.return.edit.balance', '/{returnID}/edit/balance', '::Controller:OrderReturn:Detail#processBalance')
			->setRequirement('returnID', '\d+');
		$router['ms.cp.return']->add('ms.commerce.return.edit.exchange', '/{returnID}/edit/exchange', '::Controller:OrderReturn:Detail#processExchange')
			->setRequirement('returnID', '\d+');
		$router['ms.cp.return']->add('ms.commerce.return.edit.returned-item', '/{returnID}/edit/returned-item', '::Controller:OrderReturn:Detail#processReturnedItem')
			->setRequirement('returnID', '\d+');
	}
}