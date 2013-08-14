<?php

namespace Message\Mothership\OrderReturn;

/**
 * Container classes for return status codes.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Statuses
{
	// Basic item return statuses
	const AWAITING_RETURN = 2000;
	const RETURN_REJECTED = 2100;
	const RETURN_ACCEPTED = 2200;
	const RETURN_RECEIVED = 2300;

	// Item exchange return statuses
	const AWAITING_RETURN_BALANCE_PAYMENT = 2400;
	const RETURN_BALANCE_REFUNDED = 2500;
	const RETURN_ITEM_EXCHANGED = 2600;
	const AWAITING_EXCHANGE_DISPATCH = 2700;
	const EXCHANGE_DISPATCHED = 2800;

	// Item refund return statuses
	const REFUNDED = 2900;
	
	// Order return statuses
	const FULLY_RETURNED = 3000;
}