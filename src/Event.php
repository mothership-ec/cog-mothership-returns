<?php

namespace Message\Mothership\OrderReturn;

use Message\Mothership\OrderReturn\Entity\OrderReturn;

use Message\Cog\Event\Event as BaseEvent;
use Message\Cog\DB;

/**
 * Transactional base event for the return system.
 * Has setters and getters for a return and a db-transaction.
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
	 * @param OrderReturn $return The return to live in this event
	 */
	public function __construct(OrderReturn $return)
	{
		$this->setReturn($return);
	}

	/**
	 * Sets Transaction
	 *
	 * @param  DB\Transaction $transaction Transaction
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
	 * Set the return relating to this event.
	 *
	 * @param OrderReturn $return   Return
	 * @return Event                $this for chainability
	 */
	public function setReturn(OrderReturn $return)
	{
		$this->_return = $return;

		return $this;
	}

	/**
	 * Get the return relating to this event.
	 *
	 * @return OrderReturn
	 */
	public function getReturn()
	{
		return $this->_return;
	}
}