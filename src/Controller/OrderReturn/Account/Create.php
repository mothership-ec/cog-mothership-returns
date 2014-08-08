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
					return $this->redirectToRoute('ms.user.return.detail', [
						'returnID' => $return->id,
					]);
				}
			}
		}

		$form = $this->_createForm($item);

		return $this->render('Message:Mothership:OrderReturn::return:account:create', [
			'user' => $user,
			'item' => $item,
			'form' => $form
		]);
	}

	public function note($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);

		$createForm	= $this->_createForm($item);
		$data       = $createForm->getFilteredData();

		if (!$item || $item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Redirect to view a return if this item has already been returned and the return was not rejected.
		if ($exists = $this->get('return.loader')->getByItem($item)) {
			foreach ($exists as $return) {
				if (! $return->item->isRejected()) {
					return $this->redirectToRoute('ms.user.return.detail', [
						'returnID' => $return->id
					]);
				}
			}
		}

		if (!empty($data)) {
			$reason = $this->get('return.reasons')->get($data['reason']);

			$assembler = $this->get('return.session')
				->setReturnItem($item)
				->setReason($reason);

			if ($data['exchangeUnit']) {
				$exchangeUnit = $this->get('product.unit.loader')->getByID($data['exchangeUnit']);
				$assembler->setExchangeItem($exchangeUnit);
			}
		}

		$form = $this->_noteForm($itemID, 'ms.user.return.note.process');

		return $this->render('Message:Mothership:OrderReturn::return:account:note', [
			'form'	=> $form,
			'user'	=> $user,
			'item'	=> $item,
		]);
	}

	public function noteAction($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);
		$form = $this->_noteForm($itemID);

		if ($item->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Redirect to view a return if this item has already been returned and the return was not rejected.
		if ($exists = $this->get('return.loader')->getByItem($item)) {
			foreach ($exists as $return) {
				if (! $return->item->isRejected()) {
					return $this->redirectToRoute('ms.user.return.detail', [
						'returnID' => $return->id
					]);
				}
			}
		}

		if ($form->isValid() && $data = $form->getFilteredData()) {
			if (isset($data['note']) and !empty($data['note'])) {
				$note = new Note;
				$note->note = $data['note'];

				$this->get('return.session')->setNote($note);
			}

			return $this->redirectToRoute('ms.user.return.confirm', [
				'itemID' => $itemID,
			]);
		}

		return $this->redirectToRoute('ms.user.return.create', [
			'itemID' => $itemID,
		]);
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

		$return = $this->get('return.session')->getReturn();

		// Get translated messages for exchanges and refunds
		if ($return->item->isExchangeResolution()) {
			$resolutionMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.resolution.exchange', [
				'%item%' => $return->item->exchangeItem->getDescription(),
			]);
		} else {
			$resolutionMessage = $this->get('translator')->trans('ms.commerce.return.confirmation.resolution.refund');
		}

		$balance = $return->item->calculatedBalance;

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

		$confirmForm = $this->_confirmForm($item);

		return $this->render('Message:Mothership:OrderReturn::return:account:confirm', [
			'user'              => $user,
			'item'              => $item,
			'data'              => $return->item->note,
			'balance'           => $balance,
			'resolutionMessage' => $resolutionMessage,
			'balanceMessage'    => $balanceMessage,
			'confirmForm'       => $confirmForm,
		]);
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

		$return = $this->get('return.session')->getReturn();

		$return = $this->get('return.create')->create($return);

		return $this->redirect($this->generateUrl('ms.user.return.complete', [
			'returnID' => $return->id
		]));
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

		$this->get('http.session')->remove('return.session');

		return $this->render('Message:Mothership:OrderReturn::return:account:complete', [
			'user'   => $user,
			'return' => $return
		]);
	}

	/**
	 * Get the create return form.
	 *
	 * @param  Item $item
	 * @return [type]
	 */
	protected function _createForm($item)
	{
		$reasons = $units = [];

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

		$form->setAction($this->generateUrl('ms.user.return.note', ['itemID' => $item->id]));

		$form->add('reason', 'choice', 'Why are you returning the item?', [
			'choices' => $reasons,
			'empty_value' => '-- Please select a reason --'
		]);

		$form->add('resolution', 'choice', 'Do you require an exchange or refund?', [
			'choices' => [
				'exchange' => 'Exchange',
				'refund'   => 'Refund',
			],
			'empty_value' => '-- Please select a resolution --'
		]);

		$form->add('exchangeUnit', 'choice', 'Choose a replacement item', [
			'choices' => $units
		])->val()->optional();

		$form->add('note', 'textarea', 'Additional notes')->val()->optional();

		return $form;
	}

	protected function _noteForm($itemID, $action = 'ms.user.return.note')
	{
		$form = $this->get('form');

		$form->setMethod('POST')->setAction($this->generateUrl($action, ['itemID' => $itemID]));

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

		$form->setAction($this->generateUrl('ms.user.return.store', ['itemID' => $item->id]));

		$form->add('terms', 'checkbox', 'By clicking here you agree to the terms and conditions of returns');

		return $form;
	}
}