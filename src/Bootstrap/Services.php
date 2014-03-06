<?php

namespace Message\Mothership\OrderReturn\Bootstrap;

use Message\Mothership\OrderReturn;
use Message\Mothership\OrderReturn\Collection as Collection;

use Message\Cog\Bootstrap\ServicesInterface;

use Message\Mothership\Commerce;

class Services implements ServicesInterface
{
	public function registerServices($services)
	{
		$services['return'] = $services->factory(function($c) {
			return new OrderReturn\OrderReturn();
		});

		$services->extend('order.entities', function($entities, $c) {
			$entities['returns'] = new Commerce\Order\Entity\CollectionOrderLoader(
				new Commerce\Order\Entity\Collection,
				new OrderReturn\Loader($c['db.query'], $c['return.reasons'], $c['return.resolutions'],
					$c['order.item.statuses'])
			);
			return $entities;
		});

		$services['return.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('returns');
		});

		// Register empty reasons collection
		$services['return.reasons'] = function($c) {
			return new Collection\Collection(array());
		};

		// Register empty resolutions collection
		$services['return.resolutions'] = function($c) {
			return new Collection\Collection(array());
		};

		// Register decorators
		$services['return.create'] = $services->factory(function($c) {
			return new OrderReturn\Create($c['db.query'], $c['user'], $c['return.loader'], $c['order.item.edit'],
				$c['return.reasons'], $c['return.resolutions'], $c['file.return_slip']);
		});

		$services['return.edit'] = $services->factory(function($c) {
			return new OrderReturn\Edit($c['db.query'], $c['user'], $c['order.item.edit'], $c['order.refund.create']);
		});

		// Register files
		$services['file.return_slip'] = $services->factory(function($c) {
			return new OrderReturn\File\ReturnSlip($c);
		});

		$services->extend('order.item.statuses', function($statuses) {
			// Add basic item return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::AWAITING_RETURN, 'Awaiting Return'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_RECEIVED, 'Return Received'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_COMPLETED, 'Return Completed'));

			return $statuses;
		});

		// Extend stock movement reasons
		$services->extend('stock.movement.reasons', function($reasons) {
			$reasons->add(new Commerce\Product\Stock\Movement\Reason\Reason('returned', 'Returned'));
			$reasons->add(new Commerce\Product\Stock\Movement\Reason\Reason('exchange_item', 'Exchange Item'));
			return $reasons;
		});

		$services['return.gateway'] = $services->factory(function($c) {
			return new \Message\Mothership\Commerce\Gateway\Sagepay(
				'SagePay_Server',
				$c['user.current'],
				$c['http.request.master'],
				$c['cache'],
				$c['order'],
				$c['cfg']
			);
		});
	}
}