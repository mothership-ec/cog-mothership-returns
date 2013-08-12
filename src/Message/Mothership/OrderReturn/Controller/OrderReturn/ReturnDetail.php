<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class ReturnDetail extends Controller
{
	protected $_order;
	protected $_returns;

	public function returnListing($orderID)
	{
		$this->_order = $this->get('order.loader')->getById($orderID);
		$this->_returns = $this->get('return.loader')->getByOrder($this->_order);

		return $this->render('Message:Mothership:OrderReturn::order:detail:return:listing', array(
			'order' => $this->_order,
			'returns' => $this->_returns,
		));
	}
}