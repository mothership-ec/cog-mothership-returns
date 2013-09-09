<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;
use Message\Mothership\OrderReturn\Statuses;

class Listing extends Controller
{
	public function all($status = null)
	{
		switch ($status) {
			case 'open':
				$returns = $this->get('return.loader')->getOpen();
				break;
			case 'completed':
				$returns = $this->get('return.loader')->getCompleted();
				break;
			case 'rejected':
				$returns = $this->get('return.loader')->getRejected();
				break;
			default:
				$returns = $this->get('return.loader')->getAll();
				break;
		}

		return $this->render('Message:Mothership:OrderReturn::return:listing:return-listing', array(
			'returns' => $returns,
		));
	}

	public function dashboard()
	{
		return $this->render('Message:Mothership:OrderReturn::return:listing:dashboard');
	}
}