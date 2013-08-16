<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

class Listing extends Controller
{
	public function view()
	{
		$user = $this->get('user.current');
		$returns = $this->get('return.loader')->getByUser($user);

		return $this->render('Message:Mothership:OrderReturn::account:return:listing', array(
			'user'    => $user,
			'returns' => $returns
		));
	}
}