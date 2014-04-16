<?php

namespace Message\Mothership\OrderReturn;

use Message\Mothership\OrderReturn\Entity\OrderReturn;

use Message\Cog\Event\Event as BaseEvent;
use Message\Cog\DB;

/**
 * Base event for the return system. Allows a return to be set & get.
 *
 * @author Iris Schaffer <iris@message.co.uk>
 */
class Event extends BaseEvent implements DB\TransactionalInterface
{
	protected $_return;
	protected $_transaction;

	/**
	 * Constructor.
	 *
	 * @param Order $order The order to live in this event
	 */
	public function __construct(OrderReturn $return)
	{
		$this->setReturn($return);
	}

	/**
	 * Sets Transaction
	 *
	 * @param  DB\Transaction $transaction transaction
	 * @return Event                       $this for chainability
	 */
	public function setTransaction(DB\Transaction $transaction)
	{
		$this->_transaction = $transaction;

		return $this;
	}

	/**
	 * Gets Transaction
	 *
	 * @return DB\Transaction Transaction
	 */
	public function getTransaction()
	{
		return $this->_transaction;
	}

	/**
	 * Get the order relating to this event.
	 *
	 * @return Order
	 */
	public function getReturn()
	{
		return $this->_return;
	}

	/**
	 * Set the order relating to this event.
	 *
	 * @param Order $order
	 */
	public function setReturn(OrderReturn $return)
	{
		$this->_return = $return;

		return $this;
	}
}