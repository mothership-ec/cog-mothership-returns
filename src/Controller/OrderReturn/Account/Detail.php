<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class Detail extends Controller
{
	public function view($returnID)
	{
		$user = $this->get('user.current');
		$return = $this->get('return.loader')->getByID($returnID);

		if ($return->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		return $this->render('Message:Mothership:OrderReturn::return:account:detail', array(
			'user'    => $user,
			'return'  => $return
		));
	}

	public function document($returnID)
	{
		$user = $this->get('user.current');
		$return = $this->get('return.loader')->getByID($returnID);

		if ($return->order->user->id != $user->id) {
			throw $this->createNotFoundException();
		}

		return $this->render('Message:Mothership:OrderReturn::return:blank', array(
			'content' => file_get_contents($return->document->file)
		));
	}
}