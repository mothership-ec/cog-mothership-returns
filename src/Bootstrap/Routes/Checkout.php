<?php

namespace Message\Mothership\OrderReturn\Bootstrap\Routes;

use Message\Cog\Bootstrap\RoutesInterface;

/**
 * @deprecated  These routes are kept here to ease migration to the new routes
 *              and allow customers with older emails with links to view
 *              their return balance payment.
 */
class CheckoutRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['deprecated.ms.ecom.return']->setPrefix('/checkout/return');

		$router['deprecated.ms.ecom.return']->add('deprecated.ms.ecom.return.payment', '/{orderID}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Checkout#view')
			->setMethod('GET');
	}
}