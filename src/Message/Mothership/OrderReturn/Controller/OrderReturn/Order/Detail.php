<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Order;

use Message\Cog\Controller\Controller;

use Message\Mothership\Commerce\Order;

class Detail extends Controller
{
	/**
	 * Display the detail view of a return.
	 * 
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function view($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		$accepted_form = $this->_acceptOrRejectForm($return);
		$received_form = $this->_receivedForm($return);
		$refund_form   = $this->_refundForm($return);
		$exchange_form = $this->_exchangeForm($return);

		return $this->render('Message:Mothership:OrderReturn::return:order:detail', array(
			'return'        => $return,
			'accepted_form' => $accepted_form->getForm()->createView(),
			'received_form' => $received_form->getForm()->createView(),
			'refund_form'   => $refund_form->getForm()->createView(),
			'exchange_form' => $exchange_form->getForm()->createView(),
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
		$viewURL = $this->generateUrl('ms.commerce.order.view.return', array('orderID' => $return->order->id));

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
	public function received($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_receivedForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.order.view.return', array('orderID' => $return->order->id));

		if ($data['received'] == 1) {
			$this->get('return.edit')->setAsReceived($return, $data['received_date']);
		}

		return $this->redirect($viewURL);
	}

	/**
	 * Process the refund request.
	 * 
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function refund($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_refundForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.order.view.return', array('orderID' => $return->order->id));

		if ($data['refund_approve']) {
			$amount = $data['refund_amount']; // Since the balance was inverted to display as positive, keep it as positive here

			if ($data['refund_method'] == 'manual') {
				$method = $this->get('order.payment.methods')->get('manual');
			}
			else {
				$method = $this->get('order.payment.methods')->get('card');
			}

			// Refund the return
			$return = $this->get('return.edit')->refund($return, $method, $amount);

			// Move the item to the new stock location
			$unit = $this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->item->unitID);
			$location = $this->get('stock.locations')->get($data['stock_location']);
			$reason = $this->get('stock.movement.reasons')->get('returned');
			$this->get('return.edit')->moveUnitStock($unit, $location, $reason);

			if ($data['refund_method'] == 'automatic') {
				// Get the payment against the order
				foreach ($return->order->payments as $p) {
					$payment = $p;
				}

				try {
					// Send the refund payment
					$result = $this->get('commerce.gateway.refund')->refund($payment, $amount);

					// Update the refund with the payment
					$this->get('order.refund.edit')->setPayment($return->refund, $payment);

					// Inform the user the payment was sent successfully
					$this->addFlash($result->status, sprintf('%f was sent to %s', $result->amount, $result->user->name));
				}
				catch (Exception $e) {
					// If the payment failed, inform the user
					$this->addFlash('error', $e->getMessage());
				}
			}
		}
		else {
			$this->addFlash('error', 'You must approve the refund to enact it');
		}

		return $this->redirect($viewURL);
	}

	/**
	 * Process the exchange request.
	 * 
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function exchange($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);
		$form = $this->_exchangeForm($return);
		$data = $form->getFilteredData();
		$viewURL = $this->generateUrl('ms.commerce.order.view.return', array('orderID' => $return->order->id));

		if ($data['balance'] != 0 and $data['balance_approve'] != true) { // should move this validation somewhere else
			$this->addFlash('error', 'You must approve the balance if it is not 0');
			return $this->redirect($viewURL);
		}

		// Exchange the item
		$return = $this->get('return.edit')->exchange($return, $data['balance']);

		// If the balance requires the customer to pay
		if ($return->balance > 0) {
			// notify the customer with a link to the simple checkout payment page

		}
		// If the balance requires the client to pay
		elseif ($return->balance < 0) {
			$amount = 0 - $return->balance;

			if ($data['refund_method'] == 'manual') {
				$method = $this->get('order.payment.methods')->get('manual');
			}
			else {
				$method = $this->get('order.payment.methods')->get('card');
			}

			// Refund the return
			$return = $this->get('return.edit')->refund($return, $method, $amount);

			// Move the item to the new stock location
			$unit = $this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->item->unitID);
			$location = $this->get('stock.locations')->get($data['stock_location']);
			$reason = $this->get('stock.movement.reasons')->get('returned');
			$this->get('return.edit')->moveUnitStock($unit, $location, $reason);

			// Move the exchange item to the order
			$unit = $this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->exchangeItem->unitID);
			$location = $this->get('stock.locations')->get($data['stock_location']);
			$reason = $this->get('stock.movement.reasons')->get('returned');
			$this->get('return.edit')->moveUnitStock($unit, $location, $reason);

			// Get the payment against the order
			foreach ($return->order->payments as $p) {
				$payment = $p;
			}

			// If payment is to be made automatically
			if ($payment->method == $this->get('order.payment.methods')->get('card')) {
				try {
					// Send the refund payment
					$result = $this->get('commerce.gateway.refund')->refund($payment, $amount);

					// Update the refund with the payment
					$refund = $this->get('order.refund.edit')->setPayment($refund, $payment);

					// Inform the user the payment was sent successfully
					$this->addFlash('success', sprintf('%f was sent to %s', $amount, $return->order->user->getName()));
				}
				catch (Exception $e) {
					// If the payment failed, inform the user
					$this->addFlash('error', $e->getMessage());
				}
			}
			else {
				// Update the refund with the payment
				$refund = $this->get('order.refund.edit')->setPayment($refund, $payment);

				// Inform the user the payment is pending
				$this->addFlash('success', sprintf('You should now manually transfer %d to %s', $amount, $return->order->user->getName()));
			}
		}

		return $this->redirect($viewURL);
	}

	protected function _acceptOrRejectForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.return.edit.accept-or-reject', array('returnID' => $return->id)));

		$form->add('accept_reject', 'choice', ' ', array(
			'choices' => array(
				'accept' => 'Accept',
				'reject' => 'Reject'
			),
			'expanded' => true,
			'empty_value' => false
		));

		$form->add('message', 'textarea', 'Message to customer (optional)', array(
			'required' => false
		));

		return $form;
	}

	protected function _receivedForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.return.edit.received', array('returnID' => $return->id)));

		$form->add('received', 'checkbox', 'Received package?');
		$form->add('received_date', 'datetime', 'Date received', array(
			'date_widget' => 'single_text',
			'time_widget' => 'single_text',
			'data' => new \DateTime()
		));

		return $form;
	}

	protected function _refundForm($return)
	{
		$locations = array();
		foreach ($this->get('stock.locations') as $l) {
			$locations[$l->name] = $l->displayName;
		}

		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.return.edit.refund', array('returnID' => $return->id)));

		$form->add('refund_amount', 'money', ' ', array(
			'currency' => 'GBP',
			'data' => 0 - $return->balance // display the price as positive
		));
		$form->add('refund_approve', 'checkbox', 'Approve amount');
		$form->add('stock_location', 'choice', 'Destination', array(
			'choices' => array(
				$locations
			),
			'empty_value' => '-- Select stock destination --'
		));
		$form->add('refund_method', 'choice', 'Method', array(
			'choices' => array(
				'automatic' => 'Automatic (through payment gateway)',
				'manual' => 'Manual'
			),
			'expanded' => true,
			'empty_value' => false
		));

		return $form;
	}

	protected function _exchangeForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.return.edit.exchange', array('returnID' => $return->id)));

		$form->add('balance', 'money', 'Balance Payment', array(
			'currency' => 'GBP',
			'required' => false,
			'data' => $return->balance
		));
		$form->add('balance_approve', 'checkbox', 'Approve amount', array(
			'required' => false
		));
		$form->add('refund_method', 'choice', 'Method', array(
			'choices' => array(
				'automatic' => 'Automatic (through payment gateway)',
				'manual' => 'Manual'
			),
			'expanded' => true,
			'empty_value' => false
		));

		return $form;
	}
}