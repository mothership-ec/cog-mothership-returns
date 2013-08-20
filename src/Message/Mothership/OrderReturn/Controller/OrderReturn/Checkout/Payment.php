<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Checkout;

use Message\Cog\Controller\Controller;

use Message\Mothership\Ecommerce\Controller\Checkout\Payment as EcommercePayment;
use Message\Mothership\Commerce\Order;

/**
 * Payment controller for returns.
 */
class Payment extends Controller
{

	public function store($orderID)
	{
		$order = $this->get('order.loader')->getByID($orderID);
		$returns = $this->get('return.loader')->getByOrder($order);

		$balance = $this->getPaymentAmount($returns);

		if (! $balance) {
			// nope
			return;
		}

		// copied (ish) from Message\Mothership\Ecommerce\Controller\Checkout\Payment

		// If in local mode then bypass the payment gateway
		// The `useLocalPayments` config also needs to be true
		if ($this->get('environment')->isLocal()
		 && $this->get('cfg')->checkout->payment->useLocalPayments
		) {
			return $this->localPayment($return);
		}

		$gateway  = $this->get('commerce.gateway');
		$config   = $this->_services['cfg']['checkout']->payment;
		$order    = $return->order;

		$billing  = $order->getAddress('billing');
		$delivery = $order->getAddress('delivery');

		$gateway->setUsername($config->username);
		$gateway->getGateway()->setTestMode($config->useTestPayments);

		$gateway->setBillingAddress($billing);
		$gateway->setDeliveryAddress($delivery);
		$gateway->setOrder($order);
		$gateway->setPaymentAmount($return->balance, $order->currencyID);
		$gateway->setRedirectUrl('http://82.44.182.93/checkout/payment/response');

		$response = $gateway->send();
		$gateway->saveResponse();

		if ($response->isRedirect()) {
		    $response->redirect();
		} else {
			$this->addFlash('error', 'Couldn\'t connect to payment gateway');
		}

		return $this->redirectToRoute('ms.ecom.checkout.delivery');
	}

	public function localPayment($return)
	{
		// Set the payment type as manual for now for local payments
		$paymentMethod = $this->get('order.payment.methods')->get('manual');
		// Get the order
		$order = $return->order;

		// Add the payment to the basket order
		$payment            = new Order\Entity\Payment\Payment;
		$payment->method    = $paymentMethod;
		$payment->amount    = $return->balance;
		$payment->order     = $order;
		$payment->reference = 'local payment';

		$this->get('order.payment.create')->create($payment);

		// Get the salt
		$salt  = $this->_services['cfg']['checkout']->payment->salt;
		// Generate a hash and set the redirect url
		$url = $this->generateUrl('ms.ecom.checkout.payment.successful', array(
			'orderID' => $order->id,
			'hash' => $this->get('checkout.hash')->encrypt($order->id, $salt)
		));

		return $this->redirect($url);
	}

	public function getPaymentAmount($returns)
	{
		$balance = 0;

		foreach ($returns as $return) {
			if ($return->balance > 0) {
				$balance += $return->balance;
			}
		}

		if ($balance > 0) {
			return $balance;
		}

		return false;
	}

}