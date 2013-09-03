<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class AccountRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.user.account']->add('ms.user.return.detail', '/return/view/{returnID}', '::Controller:OrderReturn:Account:Detail#view')
			->setRequirement('returnID', '\d+');

		$router['ms.user.account']->add('ms.user.return.create', '/return/create/{itemID}', '::Controller:OrderReturn:Account:Create#view')
			->setRequirement('itemID', '\d+')
			->setMethod('GET');
		
		$router['ms.user.account']->add('ms.user.return.store', '/return/store/{itemID}', '::Controller:OrderReturn:Account:Create#store')
			->setRequirement('itemID', '\d+')
			->setMethod('POST');

		$router['ms.user.account']->add('ms.user.return.document', '/return/document/{returnID}', '::Controller:OrderReturn:Account:Detail#document')
			->setRequirement('returnID', '\d+');
	}
}