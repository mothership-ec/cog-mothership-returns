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

		$router['ms.user.account']->add('ms.user.return.note', '/return/note/{itemID}', '::Controller:OrderReturn:Account:Create#note')
			->setRequirement('itemID', '\d+');

		$router['ms.user.account']->add('ms.user.return.note.process', '/return/note/process/{itemID}', '::Controller:OrderReturn:Account:Create#noteAction')
			->setRequirement('itemID', '\d+')
			->setMethod('POST');

		$router['ms.user.account']->add('ms.user.return.confirm', '/return/confirm/{itemID}', '::Controller:OrderReturn:Account:Create#confirm')
			->setRequirement('itemID', '\d+');

		$router['ms.user.account']->add('ms.user.return.store', '/return/store/{itemID}', '::Controller:OrderReturn:Account:Create#store')
			->setRequirement('itemID', '\d+')
			->setMethod('POST');

		$router['ms.user.account']->add('ms.user.return.complete', '/return/complete/{returnID}', '::Controller:OrderReturn:Account:Create#complete')
			->setRequirement('itemID', '\d+');


		$router['ms.user.account']->add('ms.user.return.document', '/return/document/{returnID}', '::Controller:OrderReturn:Account:Detail#document')
			->setRequirement('returnID', '\d+');
	}
}