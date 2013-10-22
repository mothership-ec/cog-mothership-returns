<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Order;

use Message\Cog\Controller\Controller;

class Listing extends Controller
{
	function view($orderID)
	{
		$order = $this->get('order.loader')->getByID($orderID);
		if (!$order) {
			throw $this->createNotFoundException(
				$this->trans(
					'ms.commerce.order.feedback.general.failure.non-existing-order',
					array('%orderID%' => $orderID)
				),
				null,
				404
			);
		}

		$returns = $this->get('return.loader')->getByOrder($order);

		return $this->render('Message:Mothership:OrderReturn::return:order:listing', array(
			'order' => $order,
			'returns' => $returns,
		));
	}
}