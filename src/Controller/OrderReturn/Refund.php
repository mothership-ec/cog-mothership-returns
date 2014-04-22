<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

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
	public function success(PayableInterface $payable, $reference, MethodInterface $method)
	{
		$payment = null;

		foreach ($payable->order->payments as $p) {
			$payment = $p;
			break;
		}

		$this->get('return.edit')->refund($payable, $method, $payable->getPayableAmount(), $payment);

		$successUrl = $this->generateUrl('ms.commerce.return.view', array(
			'returnID' => $payable->id,
		), UrlGeneratorInterface::ABSOLUTE_URL);

		// Create json response with the success url
		$response = new JsonResponse;
		$response->setData([
			'url' => $successUrl,
		]);

		return $response;
	}

	public function cancel(PayableInterface $payable)
	{
		return $this->failure($payable);
	}

	public function failure(PayableInterface $payable)
	{
		return $this->redirectToRoute('ms.commerce.return.view', [
			'returnID' => $payable->id
		]);
	}
}