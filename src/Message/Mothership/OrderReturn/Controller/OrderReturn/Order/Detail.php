<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Order;

use Message\Cog\Controller\Controller;
use Message\Mothership\Commerce\Order;
use Message\Mothership\OrderReturn;

class Detail extends Controller
{
	/**
	 * Display the detail view of a return.
	 *
	 * @param  int $returnID
	 * @return Message\Cog\HTTP\Response
	 */
	public function view($returnID)
	{
		$return = $this->get('return.loader')->getByID($returnID);

		return $this->render('Message:Mothership:OrderReturn::return:order:detail', array(
			'return' => $return,
		));
	}
}