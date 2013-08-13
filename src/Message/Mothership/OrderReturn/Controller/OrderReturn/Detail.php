<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class Detail extends Controller
{
	/**
	 * Display the detail view of a return.
	 * 
	 * @param  int $returnID
	 * @return [type]
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
	 * @return [type]
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

	public function received()
	{
		$this->get('return.edit')->setAsReceived($return);
	}

	public function refund()
	{
		$request = $this->get('request');
		if ($request->get('refund_approve')) {

			if ($request->get('refund_method') == 'sagepay') {
				// SagePay integration
			}
			else {
				$this->get('return.edit')->refund($return, $request->get('refund_amount'));
				$this->get('return.edit')->moveStock($return, $request->get('stock_location'));
			}
		}
		else {
			// Feedback an error
		}
	}

	public function exchange()
	{

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
				'sagepay' => 'SagePay',
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

		return $form;
	}
}