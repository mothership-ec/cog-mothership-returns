<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class CheckoutRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		/*
		$router['ms.ecom.return']->setPrefix('/checkout/return'); // temp

		$router['ms.ecom.return']->add('ms.ecom.return.payment', '/{orderID}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Checkout#view')
			->setMethod('GET');

		$router['ms.ecom.return']->add('ms.ecom.return.payment.store', '/{orderID}/store', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Payment#store')
			->setMethod('POST');

		$router['ms.ecom.return']->add('ms.ecom.return.payment.response', '/{orderID}/response', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Payment#response');
		$router['ms.ecom.return']->add('ms.ecom.return.payment.success', '/{orderID}/success/{hash}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Payment#success');
		$router['ms.ecom.return']->add('ms.ecom.return.payment.error', '/{orderID}/error/hash}', 'Message:Mothership:OrderReturn::Controller:OrderReturn:Checkout:Payment#error');
		*/
	}
}