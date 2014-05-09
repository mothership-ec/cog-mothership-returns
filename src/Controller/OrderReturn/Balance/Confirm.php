<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Balance;

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

		if (! $return) {
			throw $this->createNotFoundException();
		}

		if ($return->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		$form = $this->get('form')->setMethod('POST');
		$form->setAction($this->generateUrl('ms.ecom.return.balance.process', array(
			'returnID' => $return->id
		)));

		return $this->render('::return:balance:balance', array(
			'form'    => $form,
			'return'  => $return,
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

		if ($return->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		// Forward the request to the gateway payment reference
		$controller = 'Message:Mothership:OrderReturn::Controller:OrderReturn:Balance:Complete';
		return $this->forward($this->get('gateway')->getPurchaseControllerReference(), [
			'payable'   => $return,
			'stages'    => [
				'cancel'   => $controller . '#cancel',
				'failure'  => $controller . '#failure',
				'success'  => $controller . '#success',
			],
		]);
	}
}