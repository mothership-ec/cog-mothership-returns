<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class ReturnDetail extends Controller
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

		$received_form = $this->receivedForm($return);
		$refund_form   = $this->refundForm($return);
		// $exchange_form = $this->exchangeForm();

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:detail', array(
			'return'        => $return,
			'received_form' => $received_form,
			'refund_form'   => $refund_form,
		));
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

		$form->add('balance', 'text', 'Balance', array(
			'data' => $return->balance
		));

		return $form->getForm()->createView();
	}
}