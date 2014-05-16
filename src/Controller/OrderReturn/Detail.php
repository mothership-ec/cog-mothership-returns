<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;
use Message\Mothership\Commerce\Order;
use Message\Mothership\OrderReturn;

class Detail extends Controller
{
	const PAYEE_NONE     = 'none';
	const PAYEE_CLIENT   = 'client';
	const PAYEE_CUSTOMER = 'customer';

	/**
	 * Display the detail view of a return.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function view($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		return $this->render('Message:Mothership:OrderReturn::return:detail:detail', array(
			'return'             => $return,
			'accepted_form'      => $this->_acceptOrRejectForm($return) ->getForm()->createView(),
			'received_form'      => $this->_receivedForm($return)       ->getForm()->createView(),
			'balance_form'       => $this->_balanceForm($return)        ->getForm()->createView(),
			'exchange_form'      => $this->_exchangeForm($return)       ->getForm()->createView(),
			'returned_item_form' => $this->_returnedItemForm($return)   ->getForm()->createView(),
		));
	}

	/**
	 * Process the accept / reject request.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function acceptOrReject($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_acceptOrRejectForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.return.view', array('returnID' => $return->id));

		if ($data['accept_reject'] == 'accept') {
			$this->get('return.edit')->accept($return);
		}
		else {
			$this->get('return.edit')->reject($return);
		}

		return $this->redirect($viewURL);
	}

	/**
	 * Process the received request.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function processReceived($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_receivedForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.return.view', array('returnID' => $return->id));

		if ($data['received']) {
			$this->get('return.edit')->setAsReceived($return, $data['received_date']);
		}

		if ($data['message']) {
			$message = $this->get('mail.message');
			$message->setTo($return->order->user->email, $return->order->user->getName());
			$message->setSubject('Your ' . $this->get('cfg')->app->defaultEmailFrom->name .' return has been received - ' . $return->getDisplayID());
			$message->setView('Message:Mothership:OrderReturn::return:mail:template', array(
				'message' => $data['message']
			));

			$dispatcher = $this->get('mail.dispatcher');

			$result = $dispatcher->send($message);
		}

		return $this->redirect($viewURL);
	}

	/**
	 * Process the balance request.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function processBalance($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_balanceForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.return.view', array('returnID' => $return->id));

		// Clear the balance
		if ($data['payee'] == 'none') {
			$this->get('return.edit')->clearBalance($return);
		}

		// Process refund to the customer
		elseif ($data['payee'] == 'customer') {
			// Ensure the amount has been approved
			if ($data['refund_approve'] == false) {
				$this->addFlash('error', 'You must approve the refund to enact it');
				return $this->redirect($viewURL);
			}

			if (! isset($data['refund_method']) or ! $data['refund_method']) {
				$this->addFlash('error', 'You must select a refund method');
				return $this->redirect($viewURL);
			}

			// Get the balance amount
			$amount = $data['balance_amount'];

			// Get the refund method
			if ($data['refund_method'] == 'manual') {
				$method = $this->get('order.payment.methods')->get('manual');
			}
			else {
				$method = $this->get('order.payment.methods')->get('card');
			}

			$payment = null;

			// If refunding automatically, process the payment
			if ($data['refund_method'] == 'automatic') {
				// Get the payment against the order
				foreach ($return->order->payments as $p) {
					$payment = $p;
				}

				if (null === $payment) {
					// If there are no payments to be refunded, inform the user
					$this->addFlash('error', "There are no recorded payments for this order, please try refunding
						manually");

					return $this->redirect($viewURL);
				}

				try {
					// Send the refund payment
					$result = $this->get('commerce.gateway.refund')->refund($payment, $amount);

					// Set the balance to 0 to indicate it has been fully refunded
					$return = $this->get('return.edit')->setBalance($return, 0);

					// Inform the user the payment was sent successfully
					$this->addFlash('success', 'Return refunded');
				}
				catch (Exception $e) {
					// If the payment failed, inform the user
					$this->addFlash('error', $e->getMessage());

					return $this->redirect($viewURL);
				}
			}
			else {
				// If refunding manually, just set the balance to 0 without checking for a payment
				$return = $this->get('return.edit')->setBalance($return, 0);
			}

			// Refund the return
			$return = $this->get('return.edit')->refund($return, $method, $amount, $payment);
		}

		// Notify customer they owe the outstanding balance
		elseif ($data['payee'] == static::PAYEE_CLIENT) {
			$this->get('return.edit')->setBalance($return, abs($data['balance_amount']));
		}

		// Send the message
		if ($data['message']) {
			$message = $this->get('mail.message');
			$message->setTo($return->order->user->email, $return->order->user->getName());
			$message->setSubject('Your return has been updated - ' . $this->get('cfg')->app->defaultEmailFrom->name);
			$message->setView('Message:Mothership:OrderReturn::return:mail:template', array(
				'message' => $data['message']
			));

			$dispatcher = $this->get('mail.dispatcher');

			$result = $dispatcher->send($message);
		}

		return $this->redirect($viewURL);
	}

	/**
	 * Process the exchange request.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function processExchange($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_exchangeForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.return.view', array('returnID' => $return->id));

		$locations = $this->get('stock.locations');


		$stockManager = $this->get('stock.manager');
		$stockManager->setReason($this->get('stock.movement.reasons')->get('exchange_item'));
		$stockManager->setNote(sprintf('Order #%s, return #%s. Replacement item ready for fulfillment.', $return->order->id, $returnID));
		$stockManager->setAutomated(true);

		$stockManager->decrement(
			$this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->exchangeItem->unitID),
			$locations->getRoleLocation($locations::HOLD_ROLE)
		);

		$stockManager->commit();

		$this->get('order.item.edit')->updateStatus($return->exchangeItem, Order\Statuses::AWAITING_DISPATCH);

		return $this->redirect($viewURL);
	}

	/**
	 * Process the returned item.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function processReturnedItem($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_returnedItemForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.return.view', array('returnID' => $return->id));

		$stockManager = $this->get('stock.manager');
		$stockManager->setReason($this->get('stock.movement.reasons')->get('returned'));
		$stockManager->setNote(sprintf('Return #%s', $returnID));
		$stockManager->setAutomated(true);

		$stockManager->increment(
			$this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->item->unitID), // unit
			$this->get('stock.locations')->get($data['stock_location']) // location
		);

		$stockManager->commit();

		// Complete the returned item
		$this->get('order.item.edit')->updateStatus($return->item, \Message\Mothership\OrderReturn\Statuses::RETURN_COMPLETED);

		return $this->redirect($viewURL);
	}

	protected function _acceptOrRejectForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.return.edit.accept-or-reject', array('returnID' => $return->id)));

		$form->add('accept_reject', 'choice', ' ', array(
			'choices' => array(
				'accept' => 'Accept',
				'reject' => 'Reject'
			),
			'expanded' => true,
			'empty_value' => false
		));

		return $form;
	}

	protected function _receivedForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.return.edit.received', array('returnID' => $return->id)));

		$form->add('received', 'checkbox', 'Received package?');
		$form->add('received_date', 'datetime', 'Date received', array(
			'date_widget' => 'single_text',
			'time_widget' => 'single_text',
			'data' => new \DateTime()
		));
		$form->add('message', 'textarea', 'Message to customer (optional)', array(
			'data' => $this->_getHtml('Message:Mothership:OrderReturn::return:mail:received', array(
				'return'      => $return,
				'companyName' => $this->get('cfg')->app->defaultEmailFrom->name,
				'email'       => $this->get('cfg')->merchant->email,
			))
		))->val()->optional();

		return $form;
	}

	protected function _balanceForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.return.edit.balance', array('returnID' => $return->id)));

		$payee = 'none';
		if ($return->item->payeeIsClient())   $payee = static::PAYEE_CLIENT;
		if ($return->item->payeeIsCustomer()) $payee = static::PAYEE_CUSTOMER;

		$form->add('payee', 'choice', 'Payee', array(
			'choices' => array(
				static::PAYEE_NONE     => 'Clear the balance',
				static::PAYEE_CUSTOMER => 'Refund the customer',
				static::PAYEE_CLIENT   => 'Notify customer of their outstanding balance'
			),
			'expanded' => true,
			'empty_value' => false,
			'data' => $payee
		));

		// payee == 'customer' || 'client'
		$form->add('balance_amount', 'money', ' ', array(
			'currency' => 'GBP',
			'required' => false,
			'data' => abs($return->item->calculatedBalance) // display the price as positive
		));

		// payee == 'customer' || 'client'
		$form->add('refund_approve', 'checkbox', 'Approve amount', array(
			'required' => false,
		));

		// payee == 'customer'
		$form->add('refund_method', 'choice', 'Method', array(
			'choices' => array(
				'automatic' => 'Automatic (through payment gateway)',
				'manual' => 'Manual'
			),
			'expanded' => true,
			'empty_value' => false
		))->val()->optional();

		$message = '';

		if ($return->item->hasCalculatedBalance() and $payee !== static::PAYEE_NONE) {
			$message = $this->_getHtml('Message:Mothership:OrderReturn::return:mail:payee-' . $payee, array(
				'return' => $return,
				'companyName' => $this->get('cfg')->app->defaultEmailFrom->name,
				'email' => $this->get('cfg')->merchant->email,
			));
		}

		$form->add('message', 'textarea', 'Message to customer (optional)', array(
			'data' => $message
		))->val()->optional();

		return $form;
	}

	protected function _exchangeForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.return.edit.exchange', array('returnID' => $return->id)));

		return $form;
	}

	protected function _returnedItemForm($return)
	{
		$locations = array();
		foreach ($this->get('stock.locations') as $l) {
			$locations[$l->name] = $l->displayName;
		}

		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.return.edit.returned-item', array('returnID' => $return->id)));

		$form->add('stock_location', 'choice', 'Destination', array(
			'choices' => array(
				$locations
			),
			'empty_value' => '-- Select stock destination --'
		));

		return $form;
	}

	protected function _getHtml($reference, $params)
	{
		$message = clone $this->get('mail.message');
		$message->setView($reference, $params);

		return $message->getBody();
	}
}