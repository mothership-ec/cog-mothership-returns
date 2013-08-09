<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\User\UserInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create implements DB\TransactionalInterface
{

	public function __construct(DB\Transaction $query, Loader $loader, UserInterface $currentUser)
	{
		$this->_query       = $query;
		$this->_loader      = $loader;
		$this->_currentUser = $currentUser;
	}

	public function create(OrderReturn $return)
	{
		// Set create authorship data if not already set
		if (! $return->authorship->createdAt()) {
			$return->authorship->create(
				new DateTimeImmutable,
				$this->_currentUser->id
			);
		}

		$this->_validate($return);

		$this->_query->add('
			INSERT INTO
				order_item_return
			SET
				order_id = :orderID?i,
				item_id  = :itemID?i,
				created_at,
				created_by,
				exchange_item_id,
				status_id,
				reason,
				balance,
				calculated_balance,
				returned_value,
				return_to_stock_location_id
		', array(
			'orderID'   => $return->order->id,
			'itemID'    => $return->item->id,
			'createdAt' => $return->authorship->createdAt(),
			'createdBy' => $return->authorship->createdBy(),
			''
		));
	}

	protected function validate(OrderReturn $return)
	{
		if (! $return->item instanceof Order\Entity\Item\Item) {
			throw new \InvalidArgumentException('Can not create order return: item is not set or invalid');
		}

		// ... any more?
	}

}