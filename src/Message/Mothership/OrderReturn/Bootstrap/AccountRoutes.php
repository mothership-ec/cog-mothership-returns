<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class AccountRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.user.account']->add('ms.user.return.listing', '/returns', '::Controller:OrderReturn:Account:Listing#view');
		$router['ms.user.account']->add('ms.user.return.detail', '/returns/view/{returnID}', '::Controller:OrderReturn:Account:Detail#view')
			->setRequirement('returnID', '\d+');

		$router['ms.user.account']->add('ms.user.return.create', '/returns/create/{itemID}', '::Controller:OrderReturn:Account:Create#view')
			->setRequirement('itemID', '\d+')
			->setMethod('GET');
		$router['ms.user.account']->add('ms.user.return.store', '/returns/store/{itemID}', '::Controller:OrderReturn:Account:Create#store')
			->setRequirement('itemID', '\d+')
			->setMethod('POST');
	}
}