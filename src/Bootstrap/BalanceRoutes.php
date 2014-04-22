<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class BalanceRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.ecom.return']->setPrefix('/return/balance');

		$router['ms.ecom.return']->add('ms.ecom.return.balance.process', '/{returnID}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Balance:Confirm#process')
			->setMethod('POST');
		$router['ms.ecom.return']->add('ms.ecom.return.balance', '/{returnID}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Balance:Confirm#index');

		$router['ms.ecom.return']->add('ms.ecom.return.balance.confirmation', '/{returnID}/confirmation/{hash}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Balance:Complete#confirmation');
		$router['ms.ecom.return']->add('ms.ecom.return.balance.error', '/{returnID}/error/{hash}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Balance:Complete#error');
	}
}