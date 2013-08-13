<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class Listing extends Controller
{
	public function view($orderID)
	{
		$order = $this->get('order.loader')->getByID($orderID);
		$returns = $this->get('return.loader')->getByOrder($order);

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:listing', array(
			'order' => $order,
			'returns' => $returns,
		));
	}
}