<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;

class Terms extends Controller
{
	public function view()
	{
		return $this->render('Message:Mothership:OrderReturn::return:terms');
	}
}