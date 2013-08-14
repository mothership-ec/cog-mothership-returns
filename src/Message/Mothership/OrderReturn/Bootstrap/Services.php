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

		$services['return.loader'] = function($c) {
			return new OrderReturn\Loader($c['db.query'], $c['order.loader'], $c['order.item.loader'],
				$c['order.refund.loader'], $c['return.reasons'], $c['return.resolutions'], $c['order.item.statuses']);
		};

		// Register reasons collection
		$services['return.reasons'] = $services->share(function($c) {
			return new Collection\Collection(array(
				new Collection\Item(OrderReturn\Reasons::WRONG_ITEM, 'Wrong item'),
				new Collection\Item(OrderReturn\Reasons::FAULTY_ITEM, 'Faulty item'),
			));
		});

		// Register resolutions collection
		$services['return.resolutions'] = $services->share(function($c) {
			return new Collection\Collection(array(
				new Collection\Item(OrderReturn\Resolutions::REFUND, 'Refund'),
				new Collection\Item(OrderReturn\Resolutions::EXCHANGE, 'Exchange'),
			));
		});

		// Register decorators
		$services['return.create'] = function($c) {
			return new OrderReturn\Edit($c['db.query'], $c['user'], $c['return.loader'], $c['return.reasons'],
				$c['return.resolutions']);
		};

		$services['return.edit'] = function($c) {
			return new OrderReturn\Edit($c['db.query'], $c['user'], $c['order.item.edit'], $c['order.refund.create']);
		};

		$services['order.item.statuses'] = $services->share($services->extend('order.item.statuses', function($statuses) {
			// Add basic item return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::AWAITING_RETURN, 'Awaiting Return'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_REJECTED, 'Return Rejected'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_ACCEPTED, 'Return Accepted'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_RECEIVED, 'Return Received'));

			// Add item exchange return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::AWAITING_RETURN_BALANCE_PAYMENT, 'Awaiting Return Balance Payment'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_BALANCE_REFUNDED, 'Return Balance Refunded'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::AWAITING_EXCHANGE_DISPATCH, 'Awaiting Exchange Dispatch'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::RETURN_ITEM_EXCHANGED, 'Return Item Exchanged'));
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::EXCHANGE_DISPATCHED, 'Exchange Dispatched'));

			// Add item refund return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::REFUNDED, 'Refunded'));

			// Add order return statuses
			$statuses->add(new Commerce\Order\Status\Status(OrderReturn\Statuses::FULLY_RETURNED, 'Fully Returned'));

			return $statuses;
		}));
	}

}