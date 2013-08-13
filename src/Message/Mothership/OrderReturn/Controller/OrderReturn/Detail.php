<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class Detail extends Controller
{
	public function returnListing($orderID)
	{
		$order = $this->get('order.loader')->getByID($orderID);
		$returns = $this->get('return.loader')->getByOrder($order);

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:listing', array(
			'order' => $order,
			'returns' => $returns,
		));
	}

	public function listedItem($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		$accepted_form = $this->acceptedForm($return);
		$received_form = $this->receivedForm($return);
		$refund_form   = $this->refundForm($return);
		$exchange_form = $this->exchangeForm($return);

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:detail', array(
			'return'        => $return,
			'accepted_form' => $accepted_form,
			'received_form' => $received_form,
			'refund_form'   => $refund_form,
			'exchange_form' => $exchange_form,
		));
	}

	public function acceptedForm($return)
	{
		$form = $this->get('form');

		$form->add('message', 'textarea', 'Message to customer (optional)', array(
			'required' => false
		));

		return $form->getForm()->createView();
	}

	public function receivedForm($return)
	{
		$form = $this->get('form');

		$form->add('received', 'checkbox', 'Received package?');
		$form->add('received_date', 'date', 'Date received', array(
			'widget' => 'single_text'
		));

		return $form->getForm()->createView();
	}

	public function refundForm($return)
	{
		$form = $this->get('form');

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

		return $form->getForm()->createView();
	}

	public function exchangeForm($return)
	{
		$form = $this->get('form');

		return $form->getForm()->createView();
	}
}