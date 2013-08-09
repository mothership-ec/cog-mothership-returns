<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use Message\User\UserInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order return editor.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Edit implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;

	public function __construct(DB\QueryableIterable $query, UserInterface $currentUser)
	{
		$this->_query = $query;
		$this->_currentUser = $currentUser;
	}

	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
	}

}