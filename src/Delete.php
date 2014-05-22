<?php

namespace Message\Mothership\OrderReturn;

use Message\User\UserInterface;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Decorator for deleting and restoring returns.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Delete implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;

	/**
	 * Constructor.
	 *
	 * @param DB\Query      $query       The database query instance to use
	 * @param UserInterface $currentUser The currently logged in user
	 */
	public function __construct(DB\Query $query, UserInterface $user)
	{
		$this->_query       = $query;
		$this->_currentUser = $user;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param  DB\Transaction $trans Database transaction
	 *
	 * @return Create                Returns $this for chainability
	 */
	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;

		return $this;
	}

	public function delete(OrderReturn $return)
	{
		$return->authorship->delete(new DateTimeImmutable, $this->_currentUser->id);

		$result = $this->_query->run('
			UPDATE
				`return`
			SET
				deleted_at = :at?d,
				deleted_by = :by?in
			WHERE
				return_id = :id?i
		', array(
			'at' => $return->authorship->deletedAt(),
			'by' => $return->authorship->deletedBy(),
			'id' => $return->id,
		));

		return $return;
	}

	public function restore(Entity\OrderReturn $return)
	{
		$return->authorship->restore();

		$result = $this->_query->run('
			UPDATE
				`return`
			SET
				deleted_at = NULL,
				deleted_by = NULL
			WHERE
				return_id = ?i
		', $return->id);

		return $return;
	}
}