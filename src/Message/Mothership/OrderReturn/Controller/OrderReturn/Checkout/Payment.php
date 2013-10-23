<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Checkout;

use Exception;
use Message\Cog\Controller\Controller;
use Message\Mothership\Commerce\Order;

/**
 * Payment controller for returns.
 */
class Payment extends Controller
{

	public function store($orderID)
	{
		$user = $this->get('user.current');
		$order = $this->get('order.loader')->getByID($orderID);

		if ($order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		$returns = $this->get('return.loader')->getByOrder($order);
		$balance = $this->_getPaymentAmount($returns);

		$salt = $this->_services['cfg']['checkout']->payment->salt;
		$hash = $this->get('checkout.hash')->encrypt($order->id, $salt);

		if (! $balance) {
			throw new Exception(sprintf("Payment balance must be greater than 0, %s '%s' passed.",
				gettype($balance), $balance));
		}

		// If in local mode then bypass the payment gateway
		// The `useLocalPayments` config also needs to be true
		if ($this->get('environment')->isLocal() and $this->get('cfg')->checkout->payment->useLocalPayments) {
			return $this->localPayment($order);
		}

		$gateway  = $this->get('commerce.gateway');
		$config   = $this->_services['cfg']['checkout']->payment;

		$billing  = $order->getAddress('billing');
		$delivery = $order->getAddress('delivery');

		$redirect = $this->generateUrl('ms.ecom.return.payment.response', array(
			'orderID' => $order->id,
			'hash'    => $hash,
		), true);

		$gateway->setUsername($config->username);
		$gateway->getGateway()->setTestMode($config->useTestPayments);

		$gateway->setBillingAddress($billing);
		$gateway->setDeliveryAddress($delivery);
		$gateway->setOrder($order);
		$gateway->setPaymentAmount($balance, $order->currencyID);
		$gateway->setRedirectUrl($redirect);

		$response = $gateway->send();
		$gateway->saveResponse();

		if ($response->isRedirect()) {
		    $response->redirect();
		} else {
			$this->addFlash('error', 'Couldn\'t connect to payment gateway');
		}

		return $this->redirectToRoute('ms.ecom.return.payment.error', array(
			'orderID' => $orderID,
			'hash'    => $hash,
		));
	}

	/**
	 * Process a local payment.
	 *
	 * @param  Order $order
	 * @return Response
	 */
	public function localPayment($order)
	{
		$returns = $this->get('return.loader')->getByOrder($order);
		$balance = $this->_getPaymentAmount($returns);

		// Set the payment type as manual for now for local payments
		$paymentMethod = $this->get('order.payment.methods')->get('manual');

		// Add the payment to the basket order
		$payment            = new Order\Entity\Payment\Payment;
		$payment->method    = $paymentMethod;
		$payment->amount    = $balance;
		$payment->order     = $order;
		$payment->reference = 'local payment';

		$this->get('order.payment.create')->create($payment);

		// Get the salt
		$salt  = $this->_services['cfg']['checkout']->payment->salt;

		// Generate a hash
		$hash = $this->get('checkout.hash')->encrypt($order->id, $salt);

		return $this->redirectToRoute('ms.ecom.return.payment.success', array(
			'orderID' => $order->id,
			'hash'    => $hash,
		));
	}

	/**
	 * Show the payment success view.
	 *
	 * @param  int    $orderID
	 * @param  strgin $hash
	 * @return Response
	 */
	public function success($orderID, $hash)
	{
		$salt = $this->_services['cfg']['checkout']->payment->salt;
		$generatedHash = $this->get('checkout.hash')->encrypt($orderID, $salt);

		// Check that the generated hash and the passed through hashes match
		if ($hash != $generatedHash) {
			throw new Exception('Return hash doesn\'t match');
		}

		$order = $this->get('order.loader')->getByID($orderID);
		$returns = $this->get('return.loader')->getByOrder($order);

		// Clear return balances
		foreach ($returns as $return) {
			$this->get('return.edit')->setBalance($return, 0);
		}

		return $this->render('::return:checkout:payment:success', array(
			'order'   => $order,
			'returns' => $returns,
		));
	}

	/**
	 * Show the payment error view.
	 *
	 * @param  int    $orderID
	 * @param  string $hash
	 * @return Response
	 */
	public function error($orderID, $hash)
	{
		$salt = $this->_services['cfg']['checkout']->payment->salt;
		$generatedHash = $this->get('checkout.hash')->encrypt($orderID, $salt);

		if ($hash != $generatedHash) {
			throw new Exception('Return hash doesn\'t match');
		}

		$order = $this->get('order.loader')->getByID($orderID);
		$returns = $this->get('return.loader')->getByOrder($order);

		return $this->render('::return:checkout:payment:error', array(
			'order'   => $order,
			'returns' => $returns,
		));
	}

	/**
	 * Handles the response from the payment gateway after payment.
	 *
	 * @param  int $orderID
	 * @return Response
	 */
	public function response($orderID)
	{
		$salt = $this->_services['cfg']['checkout']->payment->salt;
		$generatedHash = $this->get('checkout.hash')->encrypt($orderID, $salt);

		// Check that the generated hash and the passed through hashes match
		if ($hash != $generatedHash) {
			throw new Exception('Return hash doesn\'t match');
		}

		$config  = $this->get('cfg')->checkout->payment;
		$id      = $this->get('request')->get('VPSTxId');
		$gateway = $this->get('return.gateway');
		$gateway->setUsername($config->username);
		$gateway->getGateway()->setTestMode((bool) $config->useTestPayments);

		try {

			$data = $gateway->handleResponse($id);

			if (! $data) {
				throw new Exception('Payment data could not be retrieved');
			}

			$dataHash = $this->get('checkout.hash')->encrypt($data['order']->id, $salt);

			// Check that the data hash and the passed through hashes match
			if ($hash != $dataHash) {
				throw new Exception('Return payment data hash doesn\'t match');
			}

			$final = $gateway->completePurchase($data);

			if ($reference = $final->getTransactionReference()) {

				$final->confirm($this->generateUrl('ms.ecom.return.payment.success', array(
					'orderID' => $orderID,
					'hash'    => $hash,
				), true));

			} else {
				throw new Exception('Payment was unsuccessful');
			}

		} catch (Exception $e) {
			$this->_services['log.errors']->error('UniformWares:Repair:Payment', array(
				'exception' => $e,
			));

			return $this->redirectToRoute('ms.ecom.return.payment.error', array(
				'orderID' => $orderID,
				'hash'    => $hash,
			));
		}
	}

	protected function _getPaymentAmount($returns)
	{
		$balance = 0;

		foreach ($returns as $return) {
			if ($return->payeeIsClient()) {
				$balance += abs($return->balance);
			}
		}

		if ($balance > 0) {
			return $balance;
		}

		return false;
	}

	protected function _getUrl()
	{
		$http = $this->get('request')->server->get('HTTPS') ? 'https://' : 'http://';

		return $http.$this->get('request')->server->get('HTTP_HOST');
	}

}