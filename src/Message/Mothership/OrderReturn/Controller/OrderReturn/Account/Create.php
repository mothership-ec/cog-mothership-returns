<?php

namespace Message\Mothership\OrderReturn\Controller\OrderReturn\Account;

use Message\Cog\Controller\Controller;

use Message\Mothership\OrderReturn\Reasons;
use Message\Mothership\OrderReturn\Resolutions;
use Message\Mothership\OrderReturn\Entity\OrderReturn;
use Message\Mothership\Commerce\Order\Entity\Item\Item;

class Create extends Controller
{
	public function view($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID); // need to check item belongs to an order that belongs to the user
		$form = $this->_createForm($item);

		return $this->render('Message:Mothership:OrderReturn::return:account:create', array(
			'user' => $user,
			'item' => $item,
			'form' => $form->getForm()->createView()
		));
	}

	public function store($itemID)
	{
		$user = $this->get('user.current');
		$item = $this->get('order.item.loader')->getByID($itemID);
		$form = $this->_createForm($item);
		$data = $form->getFilteredData();

		$reason = $this->get('return.reasons')->get($data['reason']);
		$resolution = $this->get('return.resolutions')->get($data['resolution']);

		$return = new OrderReturn;
		$return->item = $item;
		$return->order = $item->order;
		$return->reason = $reason->code;
		$return->resolution = $resolution->code;

		if ($resolution->code == Resolutions::EXCHANGE) {
			// Get the exchanged unit
			$unit = $this->get('product.unit.loader')->getByID($data['exchangeUnit']);

			// Add this unit to the order
			$exchangeItem = new Item;
			$exchangeItem->populate($unit);
			$exchangeItem->stockLocation = $this->get('stock.locations')->get('web'); // is this the correct location?
			$exchangeItem->order = $item->order;
			$item->order->items->append($exchangeItem);
			$return->exchangeItem = $this->get('order.item.create')->create($exchangeItem);

			// Set the balance as the difference in price between the exchanged and returned items
			$return->balance = $return->exchangeItem->listPrice - $item->listPrice;
		}
		elseif ($resolution->code == Resolutions::REFUND) {
			// Set the balance as the list price of the returned item
			$return->balance = 0 - $item->listPrice;
		}

		$return = $this->get('return.create')->create($return);

		if ($resolution->code == Resolutions::EXCHANGE) {
			// Move the exchange item to the order
			$unit = $this->get('product.unit.loader')->includeOutOfStock(true)->getByID($return->exchangeItem->unitID);
			$location = $this->get('stock.locations')->get($exchangeItem->stockLocation);
			$reason = $this->get('stock.movement.reasons')->get('exchange_item');
			$this->get('return.edit')->moveUnitStock($unit, $location, $reason);
		}

		return $this->redirect($this->generateUrl('ms.user.return.detail', array('returnID' => $return->id)));
	}

	protected function _createForm($item)
	{
		$reasons = $resolutions = $units = array();

		foreach ($this->get('return.reasons') as $reason) {
			$reasons[$reason->code] = $reason->name;
		}

		foreach ($this->get('return.resolutions') as $resolution) {
			$resolutions[$resolution->code] = $resolution->name;
		}

		foreach ($this->get('product.loader')->getAll() as $product) {
			$productUnits = $this->get('product.unit.loader')->getByProduct($product);
			if ($productUnits and count($productUnits)) {
				foreach ($productUnits as $unit) {
					$units[$product->displayName][$unit->id] = implode($unit->options, ',');
				}
			}
		}

		$form = $this->get('form');

		$form->setAction($this->generateUrl('ms.user.return.store', array('itemID' => $item->id)));

		$form->add('reason', 'choice', 'Why are you returning the item?', array(
			'choices' => $reasons,
			'empty_value' => '-- Please select a reason --'
		));

		$form->add('resolution', 'choice', 'What resolution would you like us to take?', array(
			'choices' => $resolutions,
			'empty_value' => '-- Please select a resolution --'
		));

		$form->add('exchangeUnit', 'choice', 'Choose a product in exchange', array(
			'choices' => $units
		));

		return $form;
	}
}