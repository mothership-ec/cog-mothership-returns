<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\Event\SubscriberInterface;
use Message\Cog\Event\EventListener as BaseListener;
use Message\Mothership\ControlPanel\Event\BuildMenuEvent;
use Message\Mothership\Commerce\Order\Events as OrderEvents;
use Message\Mothership\Commerce\Order\Event\BuildOrderTabsEvent;

/**
 * Event listener for building the OrderReturn's menu.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class EventListener extends BaseListener implements SubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			BuildMenuEvent::BUILD_MAIN_MENU => [
				'registerMainMenuItems'
			],
			OrderEvents::BUILD_ORDER_TABS => [
				'registerTabItems'
			],
			Events::CREATE_COMPLETE => [
		 		'saveDocument'
			],
		);
	}

	public function registerMainMenuItems(BuildMenuEvent $event)
	{
		$event->addItem('ms.commerce.return.dashboard', 'Returns', array('ms.returns'));
	}

	/**
	 * Register items to the sidebar of the orders-pages.
	 *
	 * @param BuildMenuEvent $event The event
	 */
	public function registerTabItems(BuildOrderTabsEvent $event)
	{
		$event->addItem('ms.commerce.order.view.return', 'ms.commerce.return.listing-title');
	}

	public function saveDocument(Event $event)
	{
		$document = $this->get('file.return_slip')->save($event->getReturn());

		$statusCode = ($event->getReturn()->item->status)
			? $event->getReturn()->item->status->code
			: Statuses::AWAITING_RETURN;

		if ($statusCode === Statuses::AWAITING_RETURN) {

			// @todo Yes, I know the create decorator uses a transaction but it will have already been committed by
			// this point. Don't judge me please! It's all Laurence's fault! He wrote all this nasty code and then went
			// packing :(
			$this->get('db.query')->run("
				UPDATE
					`return`
				SET
					document_id = :documentID?i
				WHERE
					return_id = :returnID?i
				", [
					'documentID' => $document->id,
					'returnID'   => $event->getReturn()->id,
				]
			);
		}
	}
}