<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Mothership\OrderReturn;

use Message\Cog\Bootstrap\ServicesInterface;

class Services implements ServicesInterface
{

	public function registerServices($services)
	{
		$services['return'] = function($c) {
			return new OrderReturn\OrderReturn();
		};

		$services['return.loader'] = function($c) {
			return new OrderReturn\Loader($c['db.query'], $c['order.loader'], $c['order.item.loader'],
				$c['return.reasons'], $c['return.resolutions']);
		};

		$services['return.reasons'] = function ($c) {
			return new OrderReturn\Reasons();
		};

		$services['return.resolutions'] = function($c) {
			return new OrderReturn\Resolutions();
		};
	}

}