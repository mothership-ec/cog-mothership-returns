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
	const RETURN_RECEIVED = 2200;

	// Item exchange return statuses
	const AWAITING_RETURN_BALANCE_PAYMENT = 2300;
	const RETURN_BALANCE_REFUNDED = 2400;
	const AWAITING_EXCHANGE_DISPATCH = 2500;
	const EXCHANGE_DISPATCHED = 2600;

	// Item refund return statuses
	const REFUNDED = 2700;
	
	// Order return statuses
	const FULLY_RETURNED = 2800;
}