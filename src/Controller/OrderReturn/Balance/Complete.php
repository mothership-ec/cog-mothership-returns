<?php

namespace Message\Mothership\OrderReturn\Balance;

use Message\Cog\Controller\Controller;
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

		// Append a new payment to the return's order

		$successUrl = $this->generateUrl('ms.ecom.return.balance.success', array(
			'returnID' => $payable->id,
		), true);

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
	public function unsuccessful()
	{
		$this->render('Message:Mothership:OrderReturn::return:balance:error');
	}

	/**
	 * Show the confirmation page for an successful balance payment.
	 *
	 * @return \Message\Cog\HTTP\Response
	 */
	public function successful($returnID)
	{
		return $this->render('Message:Mothership:OrderReturn::return:balance:success');
	}
}