<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class AccountRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.user.account']->setPrefix('/account/returns'); // temp

		$router['ms.user.account']->add('ms.user.return.listing', '', '::Controller:OrderReturn:Account:Listing#view');
		$router['ms.user.account']->add('ms.user.return.detail', '/view/{returnID}', '::Controller:OrderReturn:Account:Detail#view')
			->setRequirement('returnID', '\d+');

		$router['ms.user.account']->add('ms.user.return.create', '/create/{itemID}', '::Controller:OrderReturn:Account:Create#view')
			->setRequirement('itemID', '\d+')
			->setMethod('GET');
		$router['ms.user.account']->add('ms.user.return.store', '/store/{itemID}', '::Controller:OrderReturn:Account:Create#store')
			->setRequirement('itemID', '\d+')
			->setMethod('POST');
	}
}