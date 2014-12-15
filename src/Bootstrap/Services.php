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

		$services->extend('order.entities', function($entities, $c) {
			$entities['returns'] = new Commerce\Order\Entity\CollectionOrderLoader(
				new Commerce\Order\Entity\Collection,
				new OrderReturn\Loader(
					$c['db.query'],
					$c['return.reasons'],
					$c['order.item.statuses'],
					$c['refund.loader'],
					$c['payment.loader'],
					$c['stock.locations'],
					$c['product.unit.loader']
				)
			);
			return $entities;
		});

		$services['return.session'] = function($c) {
			if (!$c['http.session']->get('return.session')) {
				$returnAssembler = $c['return.assembler'];

				$c['http.session']->set('return.session', $returnAssembler);
			}

			return $c['http.session']->get('return.session');
		};

		$services['return.loader'] = $services->factory(function($c) {
			return $c['order.loader']->getEntityLoader('returns');
		});

		// Register empty reasons collection
		$services['return.reasons'] = function($c) {
			return new Collection\Collection(array());
		};

		// Register decorators

		$services['return.assembler'] = $services->factory(function($c) {
			$assembler = new OrderReturn\Assembler(
				$c['order.item.statuses'],
				$c['product.entity_loaders']->get('taxes');
			);

			$assembler->setCurrency('GBP');

			return $assembler;
		});

		$services['return.create'] = $services->factory(function($c) {
			return new OrderReturn\Create(
				$c['db.transaction'],
				$c['user.current'],
				$c['event.dispatcher'],

				$c['return.loader'],
				$c['product.unit.loader'],

				$c['order'],
				$c['order.create'],

				$c['order.item.create'],
				$c['order.item.edit'],
				$c['order.item.statuses'],

				$c['return.reasons'],
				$c['file.return_slip'],

				$c['stock.manager'],
				$c['stock.locations'],
				$c['stock.movement.reasons'],

				$c['order.note.create'],
				$c['payment.create'],
				$c['refund.create'],
				$c['order.payment.create'],
				$c['order.refund.create'],

				$c['order.item.specification.returnable']
			);
		});

		$services['return.edit'] = $services->factory(function($c) {
			return new OrderReturn\Edit(
				$c['db.transaction'],
				$c['user.current'],
				$c['order.item.edit'],
				$c['payment.create'],
				$c['order.payment.create'],
				$c['refund.create'],
				$c['order.refund.create']
			);
		});

		$services['return.delete'] = $services->factory(function($c) {
			return new OrderReturn\Delete(
				$c['db.query'],
				$c['user.current']
			);
		});

		// Register files
		$services['file.return_slip'] = $services->factory(function($c) {
			return new OrderReturn\File\ReturnSlip($c);
		});

		// Add basic item return statuses
		$services->extend('order.item.statuses', function($statuses) {
			$statuses
				->add(new Commerce\Order\Status\Status(
					OrderReturn\Statuses::AWAITING_RETURN,
					'Awaiting Return'
				))
				->add(new Commerce\Order\Status\Status(
					OrderReturn\Statuses::RETURN_RECEIVED,
					'Return Received'
				))
				->add(new Commerce\Order\Status\Status(
					OrderReturn\Statuses::RETURN_COMPLETED,
					'Return Completed'
				));

			return $statuses;
		});

		// Add transaction types & loaders
		$services->extend('order.transaction.types', function($types) {
			$types[OrderReturn\Transaction\Types::ORDER_RETURN] = 'Return';

			return $types;
		});

		$services->extend('order.transaction.loader', function($loader, $c) {
			$returnLoader = $c['return.loader'];

			$returnLoader->includeDeleted(true);

			$loader->addRecordLoader(
				OrderReturn\Entity\OrderReturn::RECORD_TYPE,
				$returnLoader
			);

			return $loader;
		});

		// Extend stock movement reasons
		$services->extend('stock.movement.reasons', function($reasons) {
			$reasons
				->add(new Commerce\Product\Stock\Movement\Reason\Reason(
					OrderReturn\StockMovementReasons::RETURNED,
					'Return'
				))
				->add(new Commerce\Product\Stock\Movement\Reason\Reason(
					OrderReturn\StockMovementReasons::EXCHANGE_ITEM,
					'Return with Exchange Item'
				));

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

		$services['order.item.specification.returnable'] = function($c) {
			return new OrderReturn\Specification\ItemIsReturnableSpecification;
		};
	}
}
