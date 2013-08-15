<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

class Listing extends Controller
{
	public function view()
	{
		$user = null;
		$returns = $this->get('return.loader')->getByUser($user);

		return $this->render('Message:Mothership:OrderReturn::account:return:listing', array(
			'returns' => $returns
		));
	}
}