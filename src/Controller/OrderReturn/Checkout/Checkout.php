<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Checkout;

use Message\Cog\Controller\Controller;

/**
 * Checkout controller for returns.
 *
 * @deprecated  This controller has been modified to maintain backwards
 *              compatibility by forwarding the customer onto the new
 *              balance payment controller.
 */
class Checkout extends Controller
{

	/**
	 * Get the latest return related to an order and forward onto the balance
	 * payment controller with that return id.
	 *
	 * @param  int $orderID
	 * @return \Message\Cog\HTTP\RedirectResponse
	 */
	public function view($orderID)
	{
		$order = $this->get('order.loader')->getByID($orderID);

		$returns = $this->get('return.loader')->getByOrder($order);

		$latestReturn = array_pop($returns);

		return $this->redirectToRoute('ms.ecom.return.balance', [
			'returnID' => $latestReturn->id
		]);
	}
}