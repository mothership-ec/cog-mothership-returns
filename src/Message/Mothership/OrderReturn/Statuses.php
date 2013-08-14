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
	const AWAITING_EXCHANGE_BALANCE_PAYMENT = 2400;
	const EXCHANGE_BALANCE_PAID = 2500;
	const RETURN_ITEM_EXCHANGED = 2600;

	// Item refund return statuses
	const AWAITING_REFUND = 2700;
	const REFUND_PAID = 2800;
	
	// Order return statuses
	const FULLY_RETURNED = 3000;
}