<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class ReturnRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.return']->add('ms.commerce.return.listing', 'return/view', '::Controller:OrderReturn:Listing#view');
	}
}