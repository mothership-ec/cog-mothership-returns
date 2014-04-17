<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Balance;

use Message\Cog\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Message\Mothership\Commerce\Payable\PayableInterface;
use Message\Mothership\Commerce\Order\Entity\Payment\Payment;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Message\Mothership\Commerce\Order\Entity\Payment\MethodInterface;
use Message\Mothership\Ecommerce\Controller\Gateway\CompleteControllerInterface;

/**
 * Controller for completing a balance payment on a customer's exchange.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Complete extends Controller implements CompleteControllerInterface
{
	/**
	 * Complete an exchange balance payment reducing the return's remaining
	 * balance by the amount paid and appending a new payment to the
	 * related order.
	 *
	 * {@inheritDoc}
	 */
	public function complete(PayableInterface $payable, $reference, array $stages, MethodInterface $method)
	{
		// Adjust the return's balance
		$newBalance = ($payable->balance > 0)
			? $payable->balance - $payable->getPayableAmount()
			: $payable->balance + $payable->getPayableAmount();

		$this->get('return.edit')->setBalance($payable, $newBalance);

		// Append a new payment to the return's order
		$payment            = new Payment;
		$payment->method    = $method;
		$payment->amount    = $payable->getPayableAmount();
		$payment->reference = $reference;

		$payable->order->payments->append($payment);

		$salt = $this->get('cfg')->payment->salt;
		$successUrl = $this->generateUrl('ms.ecom.return.balance.success', array(
			'returnID' => $payable->id,
			'hash'     => $this->get('checkout.hash')->encrypt($payable->id, $salt)
		), UrlGeneratorInterface::ABSOLUTE_URL);

		// Create json response with the success url
		$response = new JsonResponse;
		$response->setData([
			'successUrl' => $successUrl,
		]);

		return $response;
	}

	/**
	 * Show the error page for an unsuccessful balance payment.
	 *
	 * @return \Message\Cog\HTTP\Response
	 */
	public function unsuccessful($returnID, $hash)
	{
		$salt = $this->get('cfg')->payment->salt;
		$checkHash = $this->get('checkout.hash')->encrypt($returnID, $salt);

		if ($hash != $checkHash) {
			throw $this->createNotFoundException();
		}

		$this->render('Message:Mothership:OrderReturn::return:balance:error');
	}

	/**
	 * Show the confirmation page for an successful balance payment.
	 *
	 * @return \Message\Cog\HTTP\Response
	 */
	public function successful($returnID, $hash)
	{
		$salt = $this->get('cfg')->payment->salt;
		$checkHash = $this->get('checkout.hash')->encrypt($returnID, $salt);

		if ($hash != $checkHash) {
			throw $this->createNotFoundException();
		}

		$return = $this->get('return.loader')->getByID($returnID);

		return $this->render('Message:Mothership:OrderReturn::return:balance:success', [
			'return' => $return
		]);
	}
}