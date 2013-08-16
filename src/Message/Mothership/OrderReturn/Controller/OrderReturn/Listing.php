<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class Listing extends Controller
{
	public function all()
	{
		$returns = $this->get('return.loader')->getAll();

		return $this->render('Message:Mothership:OrderReturn::return:listing:return-listing', array(
			'returns' => $returns,
		));
	}
}