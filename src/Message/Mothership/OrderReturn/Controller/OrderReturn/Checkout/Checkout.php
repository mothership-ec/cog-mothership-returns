<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Checkout;

use Message\Cog\Controller\Controller;

/**
 * Checkout controller for returns.
 */
class Checkout extends Controller
{
	public function view($orderID)
	{
		$user = $this->get('user.current');
		$order = $this->get('order.loader')->getByID($orderID);

		if ($order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		$returns = $this->get('return.loader')->getByOrder($order);

		foreach ($returns as $key => $return) {
			if ($return->payeeIsClient()) {
				unset($returns[$key]);
			}
		}

		return $this->render('::return:checkout:single-payment-checkout', array(
			'amount'  => $this->_getPaymentAmount($returns),
			'returns' => $returns,
			'form'    => $this->_getPaymentForm($order),
		));
	}

	protected function _getPaymentAmount($returns)
	{
		$balance = 0;

		foreach ($returns as $return) {
			if ($return->payeeIsCustomer()) {
				$balance += abs($return->balance);
			}
		}

		if ($balance > 0) {
			return $balance;
		}

		return false;
	}

	protected function _getPaymentForm($order)
	{
		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.ecom.return.payment.store', array(
			'orderID' => $order->id
		)));
		$form->setMethod('POST');

		return $form;
	}
}