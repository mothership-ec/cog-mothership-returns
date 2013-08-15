<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Cog\Bootstrap\RoutesInterface;

class BackendRoutes implements RoutesInterface
{
	public function registerRoutes($router)
	{
		$router['ms.order']->add('ms.commerce.order.view.returns', 'view/{orderID}/return', '::Controller:OrderReturn:Listing#view')
			->setRequirement('orderID', '\d+');

		$router['ms.order']->add('ms.commerce.order.returns.edit.accept-or-reject', 'return/{returnID}/edit/accept-or-reject', '::Controller:OrderReturn:Detail#acceptOrReject')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.returns.edit.received', 'return/{returnID}/edit/received', '::Controller:OrderReturn:Detail#received')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.returns.edit.refund', 'return/{returnID}/edit/refund', '::Controller:OrderReturn:Detail#refund')
			->setRequirement('returnID', '\d+');
		$router['ms.order']->add('ms.commerce.order.returns.edit.exchange', 'return/{returnID}/edit/exchange', '::Controller:OrderReturn:Detail#exchange')
			->setRequirement('returnID', '\d+');
	}
}