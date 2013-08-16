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
				$returns = $this->get('return.loader')->getByStatus(array(
					Statuses::AWAITING_RETURN,
					Statuses::RETURN_ACCEPTED,
					Statuses::RETURN_RECEIVED,
					Statuses::AWAITING_EXCHANGE_BALANCE_PAYMENT,
					Statuses::AWAITING_REFUND,
				));
				break;
			case 'completed':
				$returns = $this->get('return.loader')->getByStatus(array(
					Statuses::EXCHANGE_BALANCE_PAID,
					Statuses::RETURN_ITEM_EXCHANGED,
					Statuses::REFUND_PAID,
				));
				break;
			case 'rejected':
				$returns = $this->get('return.loader')->getByStatus(array(
					Statuses::RETURN_REJECTED,
				));
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