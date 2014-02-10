<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn;

use Message\Cog\Controller\Controller;
use Message\Mothership\OrderReturn\Statuses;

class Listing extends Controller
{
	public function all($status = null)
	{
		switch ($status) {
			case 'awaiting-return':
				$returns = $this->get('return.loader')->getByStatusCode(Statuses::AWAITING_RETURN);
				break;

			case 'received':
				$returns = $this->get('return.loader')->getByStatusCode(Statuses::RETURN_RECEIVED);

				// Filter out partially processed returns
				$returns = array_filter($returns, function($return) {
					return ! $return->hasBalance();
				});
				break;

			case 'awaiting-payment':
				$returns = $this->get('return.loader')->getAwaitingPayment();
				break;

			case 'pending-refund':
				$returns = $this->get('return.loader')->getPendingRefund();
				break;

			case 'pending-exchange':
				$returns = $this->get('return.loader')->getPendingExchange();
				break;

			case 'pending-returned-item-processing':
				$returns = $this->get('return.loader')->getPendingReturnedItemProcessing();
				break;

			case 'completed':
				$returns = $this->get('return.loader')->getByStatusCode(Statuses::RETURN_COMPLETED);
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
			'status'  => ucwords(str_replace('-', ' ', $status)),
		));
	}

	public function dashboard()
	{
		return $this->render('Message:Mothership:OrderReturn::return:listing:dashboard');
	}

	public function sidebar()
	{
		return $this->render('Message:Mothership:OrderReturn::return:listing:sidebar', array(
			'search_form' => $this->_getSearchForm(),
		));
	}

	public function searchAction()
	{
		$form = $this->_getSearchForm();
		if ($form->isValid() && $data = $form->getFilteredData()) {
			$returnID = $data['term'];

			if ($return = $this->get('return.loader')->getById($returnID)) {
				return $this->redirectToRoute('ms.commerce.return.view', array('returnID' => $return->id));
			} else {
				$this->addFlash('warning', sprintf('No search results were found for "%s"', $returnID));
				return $this->redirectToReferer();
			}
		}
	}

	protected function _getSearchForm()
	{
		$form = $this->get('form')
			->setName('return_search')
			->setMethod('POST')
			->setAction($this->generateUrl('ms.commerce.return.search.action'));

		$form->add('term', 'search', 'Search');


		return $form;
	}
}