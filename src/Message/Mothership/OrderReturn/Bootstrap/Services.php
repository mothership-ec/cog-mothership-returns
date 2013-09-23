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
		$services['return'] = function($c) {
			return new OrderReturn\OrderReturn();
		};

		$services['order.entities'] = $services->share($services->extend('order.entities', function($entities, $c) {
			$entities['returns'] = new Commerce\Order\Entity\CollectionOrderLoader(
				new Commerce\Order\Entity\Collection,
				new OrderReturn\Loader($c['db.query'], $c['return.reasons'], $c['return.resolutions'],
					$c['order.item.statuses'])
			);
			return $entities;
		}));

		$services['return.loader'] = function($c) {
			return $c['order.loader']->getEntityLoader('returns');
		};

		// Register empty reasons collection
		$services['return.reasons'] = $services->share(function($c) {
			return new Collection\Collection(array());
		});

		// Register empty resolutions collection
		$services['return.resolutions'] = $services->share(function($c) {
			return new Collection\Collection(array());
		});

		// Register decorators
		$services['return.create'] = function($c) {
			return new OrderReturn\Create($c['db.query'], $c['user'], $c['return.loader'], $c['order.item.edit'],
				$c['return.reasons'], $c['return.resolutions'], $c['file.return_slip']);
		};

		$services['return.edit'] = function($c) {
			return new OrderReturn\Edit($c['db.query'], $c['user'], $c['order.item.edit'], $c['order.refund.create'],
				$c['stock.manager']);
		};

		// Register files
		$services['file.return_slip'] = function($c) {
			return new OrderReturn\File\ReturnSlip($c);
		};

		$services['order.item.statuses'] = $services->share($services->extend('order.item.statuses', function($statuses) {
			// Add basic item return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::AWAITING_RETURN, 'Awaiting Return'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_RECEIVED, 'Return Received'));

			return $statuses;
		}));

		// Extend stock movement reasons
		$services['stock.movement.reasons'] = $services->share($services->extend('stock.movement.reasons', function($reasons) {
			$reasons->add(new Commerce\Product\Stock\Movement\Reason\Reason('returned', 'Returned'));
			$reasons->add(new Commerce\Product\Stock\Movement\Reason\Reason('exchange_return', 'Exchange Return'));
			$reasons->add(new Commerce\Product\Stock\Movement\Reason\Reason('exchange_item', 'Exchange Item'));
			return $reasons;
		}));
	}

}