<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\OrderReturn\Entity\OrderReturnItem;
use Message\Mothership\Commerce\Order;
use Message\Mothership\Commerce\Order\Entity\Item\Item;
use Message\Mothership\Commerce\Order\Entity\Note\Note;
use Message\Mothership\Ecommerce\OrderItemStatuses;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Create extends Controller
{
	/**
	 * View the create return form.
	 *
	 * @param  int $itemID
	 * @return [type]
	 */
	public function view($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);

		if ($item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Redirect to view a return if this item has already been returned and the return was not rejected.
		if ($exists = $this->get('return.loader')->getByItem($item)) {
			foreach ($exists as $return) {
				if (! $return->item->isRejected()) {
					return $this->redirectToRoute('ms.user.return.detail', array(
						'returnID' => $return->id
					));
				}
			}
		}

		$form = $this->_createForm($item);

		return $this->render('Message:Mothership:OrderReturn::return:account:create', array(
			'user' => $user,
			'item' => $item,
			'form' => $form
		));
	}

	public function note($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);

		$createForm	= $this->_createForm($item);
		$createData	= $createForm->getFilteredData();

		if (!empty($createData)) {
			$this->get('http.session')->set('return.data', $createData);
		}

		$form = $this->_noteForm($itemID, 'ms.user.return.note.process');

		if ($item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Redirect to view a return if this item has already been returned and the return was not rejected.
		if ($exists = $this->get('return.loader')->getByItem($item)) {
			foreach ($exists as $return) {
				if (! $return->item->isRejected()) {
					return $this->redirectToRoute('ms.user.return.detail', array(
						'returnID' => $return->id
					));
				}
			}
		}

		return $this->render('Message:Mothership:OrderReturn::return:account:note', array(
			'form'	=> $form,
			'user'	=> $user,
			'item'	=> $item,
		));
	}

	public function noteAction($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);
		$form = $this->_noteForm($itemID);

		$sessionData = (array) $this->get('http.session')->get('return.data');

		if ($item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Redirect to view a return if this item has already been returned and the return was not rejected.
		if ($exists = $this->get('return.loader')->getByItem($item)) {
			foreach ($exists as $return) {
				if (! $return->item->isRejected()) {
					return $this->redirectToRoute('ms.user.return.detail', array(
						'returnID' => $return->id
					));
				}
			}
		}

		if ($form->isValid() && $data = $form->getFilteredData()) {
			$data = array_merge($sessionData, $data);
			$this->get('http.session')->set('return.data', $data);
			return $this->redirectToRoute('ms.user.return.confirm', array(
				'itemID'	=> $itemID,
			));
		}

		return $this->redirectToRoute('ms.user.return.create', array(
			'itemID'	=> $itemID,
		));

	}

	/**
	 * View the confirm return page.
	 *
	 * @param  int $itemID
	 * @return [type]
	 */
	public function confirm($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);

		if ($item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		$data = $this->get('http.session')->get('return.data');
		$balance = 0;

		// Get translated messages for exchanges and refunds
		if ($data['resolution'] == 'exchange') {
			$exchangeUnit = $this->get('product.unit.loader')->getByID($data['exchangeUnit']);
			$resolutionMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.resolution.exchange', array(
				'%item%' => $exchangeUnit->product->name
			));
			$balance = $exchangeUnit->getPrice('retail', $item->order->currencyID) - $item->gross;
		}
		else {
			$balance = -$item->gross;
			$resolutionMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.resolution.refund');
		}

		if ($balance > 0) {
			$balanceMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.balance.pay');
		}
		elseif ($balance < 0) {
			$balance = -$balance;
			$balanceMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.balance.refund');
		}
		else {
			$balanceMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.balance.none');
		}

		// Set $data on session
		$this->get('http.session')->set('return.data', $data);

		$confirmForm = $this->_confirmForm($item);

		return $this->render('Message:Mothership:OrderReturn::return:account:confirm', array(
			'user' => $user,
			'item' => $item,
			'data' => $data,
			'balance' => $balance,
			'resolutionMessage' => $resolutionMessage,
			'balanceMessage' => $balanceMessage,
			'confirmForm' => $confirmForm,
		));
	}

	/**
	 * Store the return.
	 *
	 * @param  int $itemID
	 * @return [type]
	 */
	public function store($itemID)
	{
		$user = $this->get('user.current');
		$orderItem = $this->get('order.item.loader')->getByID($itemID);

		if (! $orderItem or $orderItem->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		$confirmForm = $this->_confirmForm($orderItem);

		if (! $confirmForm->isValid()) {
			return $this->redirectToReferer();
		}

		$data = $this->get('http.session')->get('return.data');

		$reason       = $this->get('return.reasons')->get($data['reason']);
		$exchangeUnit = $this->get('product.unit.loader')->getByID($data['exchangeUnit']);

		$return = $this->get('return.factory')
			->setReturnItem($orderItem)
			->setReason($reason)
			->setExchangeItem($exchangeUnit)
			->getReturn();


		// Create the return
		$return = new OrderReturn;

		// @todo make this an array of items
		$return->item = new OrderReturnItem;

		$return->item->order      = $orderItem->order;
		$return->item->orderItem  = $orderItem;
		$return->item->reason     = $reason->code;

		if (isset($data['note']) and ! empty($data['note'])) {
			// Add a note to the return
			$this->_addNote($return, $data['note']);
		}

		if ($data['exchangeUnit']) {
			// Add an exchange item to the return
			$this->_addExchangeItem($return, $data['exchangeUnit']);
		}
		else {
			// Set the balance as the list price of the returned item
			// for the refund
			$return->item->balance = 0 - $orderItem->gross;
		}

		// Save the return object
		$return = $this->get('return.create')->create($return);

		if ($return->item->exchangeItem) {
			// Create a stock movement for the return exchange
			$this->_moveStock($return);
		}

		return $this->redirect($this->generateUrl('ms.user.return.complete', array('returnID' => $return->id)));
	}

	/**
	 * View the return completed page.
	 *
	 * @param  int $returnID
	 * @return [type]
	 */
	public function complete($returnID)
	{
		$user = $this->get('user.current');
		$return = $this->get('return.loader')->getByID($returnID);

		$this->get('http.session')->remove('return.data');

		return $this->render('Message:Mothership:OrderReturn::return:account:complete', array(
			'user' => $user,
			'return' => $return
		));
	}

	/**
	 * Get the create return form.
	 *
	 * @param  Item $item
	 * @return [type]
	 */
	protected function _createForm($item)
	{
		$reasons = $units = array();

		foreach ($this->get('return.reasons') as $reason) {
			$reasons[$reason->code] = $reason->name;
		}

		foreach ($this->get('product.loader')->getAll() as $product) {
			$productUnits = $this->get('product.unit.loader')->getByProduct($product);
			if ($productUnits and count($productUnits)) {
				foreach ($productUnits as $unit) {
					$units[$product->displayName][$unit->id] = implode($unit->options, ',');
				}
			}
		}

		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.user.return.note', array('itemID' => $item->id)));

		$form->add('reason', 'choice', 'Why are you returning the item?', array(
			'choices' => $reasons,
			'empty_value' => '-- Please select a reason --'
		));

		$form->add('resolution', 'choice', 'Do you require an exchange or refund?', array(
			'choices' => [
				'exchange' => 'Exchange',
				'refund'   => 'Refund',
			],
			'empty_value' => '-- Please select a resolution --'
		));

		$form->add('exchangeUnit', 'choice', 'Choose a replacement item', array(
			'choices' => $units
		))->val()->optional();

		$form->add('note', 'textarea', 'Additional notes')->val()->optional();

		return $form;
	}

	protected function _noteForm($itemID, $action = 'ms.user.return.note')
	{
		$form = $this->get('form');

		$form->setMethod('POST')->setAction($this->generateUrl($action, array('itemID' => $itemID)));

		$form->add('note', 'textarea', 'Additional notes')->val()->optional();

		return $form;
	}

	/**
	 * Get the confirmation form.
	 *
	 * @param  Item $item
	 * @return [type]
	 */
	protected function _confirmForm($item)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.user.return.store', array('itemID' => $item->id)));

		$form->add('terms', 'checkbox', 'By clicking here you agree to the terms and conditions of returns');

		return $form;
	}

	/**
	 * Add a note to the return.
	 *
	 * @param OrderReturn $return
	 * @param string      $message
	 */
	protected function _addNote($return, $message)
	{
		$note = new Note;

		$note->order            = $return->item->order;
		$note->note             = $message;
		$note->raisedFrom       = 'return';
		$note->customerNotified = 0;

		$note = $this->get('order.note.create')->create($note);

		$return->item->note = $note;
	}

	/**
	 * Add an exchange item to the return.
	 *
	 * @param OrderReturn $return
	 * @param int         $unitID
	 */
	protected function _addExchangeItem($return, $unitID)
	{
		// Get the exchanged unit
		$unit = $this->get('product.unit.loader')->getByID($unitID);

		// Create an exchange item
		$exchangeItem = new Item;
		$exchangeItem->order = $return->item->order;

		// Populate the item from the unit
		$exchangeItem->populate($unit);

		$stockLocations = $this->get('stock.locations');

		$exchangeItem->stockLocation = $stockLocations->getRoleLocation($stockLocations::SELL_ROLE);
		$exchangeItem->status        = clone $this->get('order.item.statuses')->get(OrderItemStatuses::HOLD);

		$return->item->order->items->append($exchangeItem);

		$return->item->exchangeItem = $this->get('order.item.create')->create($exchangeItem);

		// Set the balance as the difference in price between the exchanged and returned items
		$return->item->balance = $return->item->exchangeItem->gross - $return->item->orderItem->gross;
	}

	/**
	 * Create a stock movement for the return item and exchange item.
	 *
	 * @param  OrderReturn $return
	 */
	protected function _moveStock($return)
	{
		$unit = $this->get('product.unit.loader')
					 ->includeOutOfStock(true)
					 ->getByID($return->item->exchangeItem->unitID);

		$stockManager = $this->get('stock.manager');
		$stockLocations = $this->get('stock.locations');

		$stockManager->setNote(sprintf(
			'Order #%s, return #%s. Replacement item requested.',
			$return->item->order->id,
			$return->id
		));

		$stockManager->setReason(
			$this->get('stock.movement.reasons')->get('exchange_item')
		);

		$stockManager->setAutomated(true);

		// Decrement from sell stock
		$stockManager->decrement(
			$unit,
			$return->item->exchangeItem->stockLocation
		);

		// Increment in hold stock
		$stockManager->increment(
			$unit,
			$stockLocations->getRoleLocation($stockLocations::HOLD_ROLE)
		);

		$stockManager->commit();
	}
}