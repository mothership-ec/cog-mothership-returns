<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class Routes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.order']->add('ms.commerce.order.view.returns', 'view/{orderID}/return', '::Controller:OrderReturn:Detail#returnListing')
			->setRequirement('orderID', '\d+');
	}
}