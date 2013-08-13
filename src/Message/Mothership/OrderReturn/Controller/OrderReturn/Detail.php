<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

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

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:detail', array(
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

		if ($data['accept_reject'] == 'accept') {
			$this->get('return.edit')->accept($return);
		}
		else {
			$this->get('return.edit')->reject($return);
		}
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

		if ($data['received'] == 1) {
			$this->get('return.edit')->setAsReceived($return, $data['received_date']);
		}
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

		if ($data['refund_approve']) {
			$return = $this->get('return.edit')->refund($return, $data['refund_amount']);
			$this->get('return.edit')->moveStock($return, $data['stock_location']);

			if ($data['refund_method'] == 'automatic') {
				// provider gateway integration
			}
		}
		else {
			// Feedback an error
		}
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

		$viewURL = $this->generateUrl('ms.commerce.order.view.returns', array('orderID' => $return->order->id));

		if ($data['balance'] != 0 and $data['balance_approve'] != true) {
			$this->addFlash('error', 'You must approve the balance if it is not 0');
			return $this->redirect($viewURL);
		}

		// Exchange the item
		$return = $this->get('return.edit')->exchange($return);

		// If the balance is in the customers favour, process the refund
		if ($data['balance_approve'] == true and $data['balance'] < 0) {
			$return = $this->get('return.edit')->refund($return, 0 - $data['balance']);

			// If payment is to be made automatically
			if ($data['refund_method'] == 'automatic') {
				// provider gateway integration
			}
		}

		if ($return->balance > 0) {
			// notify customer of remaining balance
		}
		elseif ($return->balance < 0) {
			// notify admin of remaining balance
		}

		return $this->redirect($viewURL);
	}

	protected function _acceptOrRejectForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.returns.edit.accept-or-reject', array('returnID' => $return->id)));

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

		$form->setAction($this->generateUrl('ms.commerce.order.returns.edit.received', array('returnID' => $return->id)));

		$form->add('received', 'checkbox', 'Received package?');
		$form->add('received_date', 'date', 'Date received', array(
			'widget' => 'single_text'
		));

		return $form;
	}

	protected function _refundForm($return)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.commerce.order.returns.edit.refund', array('returnID' => $return->id)));

		$form->add('refund_amount', 'text', ' ', array(
			'data' => $return->balance
		));
		$form->add('refund_approve', 'checkbox', 'Approve amount');
		$form->add('stock_location', 'choice', 'Destination', array(
			'choices' => array(
				1 => 'Stock',
				2 => 'Seconds',
				3 => 'Bin',
				4 => 'Repair A',
				5 => 'Repair B',
				6 => 'Back to customer',
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

		$form->setAction($this->generateUrl('ms.commerce.order.returns.edit.exchange', array('returnID' => $return->id)));

		$form->add('balance', 'money', 'Balance Payment', array(
			'currency' => 'GBP',
			'required' => false
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