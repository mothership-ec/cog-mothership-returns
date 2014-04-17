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
 * Controller for completing a refund on a return.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Refund extends Controller implements CompleteControllerInterface
{
	/**
	 *
	 *
	 * {@inheritDoc}
	 */
	public function complete(PayableInterface $payable, $reference, array $stages, MethodInterface $method)
	{
		foreach ($payable->order->payments as $payment) {
			break;
		}

		$this->get('return.edit')->refund($payable, $method, $payable->getPayableAmount(), $payment);

		$salt = $this->get('cfg')->payment->salt;
		$successUrl = $this->generateUrl('ms.ecom.return.refund.success', array(
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
	 * Add an error message and redirect back to the return detail view.
	 *
	 * @return \Message\Cog\HTTP\Response
	 */
	public function unsuccessful($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		$this->render('Message:Mothership:OrderReturn::return:balance:error');
	}

	/**
	 * Add a success message and redirect back to the return detail view.
	 *
	 * @return \Message\Cog\HTTP\Response
	 */
	public function successful($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		return $this->render('Message:Mothership:OrderReturn::return:balance:success', [
			'return' => $return
		]);
	}
}