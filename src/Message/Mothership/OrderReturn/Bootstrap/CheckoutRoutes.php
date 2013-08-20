<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class CheckoutRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.ecom.return']->setPrefix('/checkout/return'); // temp

		$router['ms.ecom.return']->add('ms.ecom.return', '/{orderID}', '::Controller:OrderReturn:Checkout:Checkout#view')
			->setMethod('GET');

		$router['ms.ecom.return']->add('ms.ecom.return.store', '/{orderID}', '::Controller:OrderReturn:Checkout:Payment#store')
			->setMethod('POST');
	}
}