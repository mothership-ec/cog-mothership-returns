<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

use Message\Mothership\OrderReturn\Reasons;
use Message\Mothership\OrderReturn\Resolutions;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
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
				if (! $return->isRejected()) {
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
				if (! $return->isRejected()) {
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
				if (! $return->isRejected()) {
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
			$balance = $item->listPrice - $exchangeUnit->getPrice('retail', $item->order->currencyID);
		}
		else {
			$balance = $item->listPrice;
			$resolutionMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.resolution.refund');
		}

		if ($balance < 0) {
			$balance = -$balance;
			$balanceMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.balance.pay');
		}
		elseif ($balance > 0) {
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
		$item = $this->get('order.item.loader')->getByID($itemID);

		if ($item->order->user->id != $user->id) {
			throw new UnauthorizedHttpException('You are not authorised to view this page.', 'You are not authorised to
				view this page.');
		}

		$confirmForm = $this->_confirmForm($item);

		if (! $confirmForm->isValid()) {
			return $this->redirectToReferer();
		}

		$data = $this->get('http.session')->get('return.data');

		if (isset($data['note']) and ! empty($data['note'])) {
			// Add the note to order
			$note = new Note;
			$note->order = $item->order;
			$note->note = $data['note'];
			$note->raisedFrom = 'return';
			$note->customerNotified = 0;

			$note = $this->get('order.note.create')->create($note);
		}

		$stockLocations = $this->get('stock.locations');

		$reason = $this->get('return.reasons')->get($data['reason']);
		$resolution = $this->get('return.resolutions')->get($data['resolution']);

		$return = new OrderReturn;
		$return->item = $item;
		$return->order = $item->order;
		$return->reason = $reason->code;
		$return->resolution = $resolution->code;
		$return->note = isset($note) ? $note : null;

		if ($resolution->code == 'exchange') {
			// Get the exchanged unit
			$unit = $this->get('product.unit.loader')->getByID($data['exchangeUnit']);

			// Add this unit to the order
			$exchangeItem = new Item;
			$exchangeItem->order = $item->order;
			$exchangeItem->populate($unit);
			$exchangeItem->stockLocation = $stockLocations->getRoleLocation($stockLocations::SELL_ROLE);
			$exchangeItem->status = clone $this->get('order.item.statuses')->get(OrderItemStatuses::HOLD);
			$item->order->items->append($exchangeItem);
			$return->exchangeItem = $this->get('order.item.create')->create($exchangeItem);

			// Set the balance as the difference in price between the exchanged and returned items
			$return->balance = $item->gross - $return->exchangeItem->gross;
		}
		elseif ($resolution->code == 'refund') {
			// Set the balance as the list price of the returned item
			$return->balance = 0 - $item->gross;
		}

		$return = $this->get('return.create')->create($return);

		if ($resolution->code == 'exchange') {
			// Change stock for replacement item
			$unit         = $this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->exchangeItem->unitID);
			$stockManager = $this->get('stock.manager');

			$stockManager->setNote(sprintf('Order #%s, return #%s. Replacement item requested.', $return->order->id, $return->id));

			$stockManager->setReason(
				$this->get('stock.movement.reasons')->get('exchange_item')
			);

			$stockManager->setAutomated(true);

			// Decrement from sell stock
			$stockManager->decrement(
				$unit,
				$exchangeItem->stockLocation
			);

			// Increment in hold stock
			$stockManager->increment(
				$unit,
				$stockLocations->getRoleLocation($stockLocations::HOLD_ROLE)
			);

			$stockManager->commit();
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
		$reasons = $resolutions = $units = array();

		foreach ($this->get('return.reasons') as $reason) {
			$reasons[$reason->code] = $reason->name;
		}

		foreach ($this->get('return.resolutions') as $resolution) {
			$resolutions[$resolution->code] = $resolution->name;
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
			'choices' => $resolutions,
			'empty_value' => '-- Please select a resolution --'
		));

		$form->add('exchangeUnit', 'choice', 'Choose a replacement item', array(
			'choices' => $units
		))->val()->optional();

		return $form;
	}

	public function _noteForm($itemID, $action = 'ms.user.return.note')
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
	public function _confirmForm($item)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.user.return.store', array('itemID' => $item->id)));

		$form->add('terms', 'checkbox', 'By clicking here you agree to the terms and conditions of returns');

		return $form;
	}
}