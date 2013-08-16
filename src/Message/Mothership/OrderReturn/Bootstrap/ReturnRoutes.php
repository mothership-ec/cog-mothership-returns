<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class ReturnRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.return']->setParent('ms.cp')->setPrefix('/return');
		$router['ms.return']->add('ms.commerce.return.listing', '/view', '::Controller:OrderReturn:Listing#all');
		$router['ms.return']->add('ms.commerce.return.listing.status', '/view/{status}', '::Controller:OrderReturn:Listing#all');
	}
}