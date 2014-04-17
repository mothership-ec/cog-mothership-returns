<?php

namespace Message\Mothership\OrderReturn\Balance;

use Message\Cog\Controller\Controller;

/**
 * Balance confirm details and process controller for customers to pay off
 * their remaining balance after an exchange.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Confirm extends Controller
{
	/**
	 * Show the return and balance details and the continue form.
	 *
	 * @param  int $returnID
	 * @return \Message\Cog\HTTP\Response
	 */
	public function index($returnID)
	{
		$user   = $this->get('user.current');
		$return = $this->get('return.loader')->getByID($returnID);

		if ($return->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		return $this->render('::return:balance:balance', array(
			'return'  => $return,
			'form'    => $this->_getPaymentForm($order),
			'amount'  => $return->getPayableAmount(),
		));
	}

	/**
	 * Process the balance continue form forwarding the customer to the
	 * gateway refund controller.
	 *
	 * @param  int $returnID
	 * @return \Message\Cog\HTTP\Response
	 */
	public function process($returnID)
	{
		$user   = $this->get('user.current');
		$return = $this->get('return.loader')->getByID($returnID);

		if ($return->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Forward the request to the gateway refund reference
		return $this->forward($gateway->getRefundControllerReference(), [
			'payable' => $return,
			'stages'  => [
				'cancelRoute'       => 'ms.ecom.return.payment.unsuccessful',
				'failureRoute'      => 'ms.ecom.return.payment.unsuccessful',
				'successRoute'      => 'ms.ecom.return.payment.successful',
				'completeReference' => 'Message:Mothership:OrderReturn::Controller:Balance:Complete#complete'
			],
		]);
	}
}