<?php

namespace Message\Mothership\OrderReturn\Specification;

use Message\Mothership\Commerce\Order\Entity\Item\Item;
use Message\Mothership\Commerce\Order\Statuses as OrderStatuses;
use Message\Mothership\OrderReturn\Statuses as ReturnStatuses;

class ItemIsReturnableSpecification
{
	/**
	 * Whether item is returnable or not.
	 * 
	 * @param  Item    $item Item
	 * @return boolean       True if item's status is dispatched or higher, but
	 *                       not returned. 
	 */
	public function isSatisfiedBy(Item $item)
	{
		$statusCode = $item->status->code;

		return
			$statusCode >= OrderStatuses::DISPATCHED
			&& !(
				$statusCode >= ReturnStatuses::AWAITING_RETURN
				&& $statusCode <= ReturnStatuses::RETURN_COMPLETED
			);
	}

}