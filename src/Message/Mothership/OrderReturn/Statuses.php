<?php

namespace Message\Mothership\OrderReturn;

/**
 * Container classes for return status codes.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Statuses
{
	const AWAITING_RETURN = 2000;
	const RETURN_RECEIVED = 2100;
	const EXCHANGE_COMPLETED = 2600;
	const REFUND_COMPLETE = 2800;
}
