<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;

/**
 * Decorator for deleting and restoring returns.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Delete implements DB\TransactionalInterface
{
	protected $_query;
	protected $_currentUser;

	protected $_transOverridden = false;

	/**
	 * Constructor.
	 *
	 * @param DB\Query            $query          The database query instance to use
	 * @param UserInterface       $currentUser    The currently logged in user
	 */
	public function __construct(DB\Query $query, UserInterface $user)
	{
		$this->_query           = $query;
		$this->_currentUser     = $user;
	}

	/**
	 * Sets transaction and sets $_transOverridden to true
	 *
	 * @param  DB\Transaction $trans transaction
	 * @return Create                $this for chainability
	 */
	public function setTransaction(DB\Transaction $trans)
	{
		$this->_query = $trans;
		$this->_transOverridden = true;

		return $this;
	}

	public function delete(OrderReturn $return)
	{
		$return->authorship->delete(new DateTimeImmutable, $this->_currentUser->id);

		$result = $this->_query->run('
			UPDATE
				order_return
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

		// Commit all the changes
		if (!$this->_transOverridden) {
			$this->_query->commit();
		}

		return $return;
	}

	public function restore(OrderReturn $return)
	{
		$return->authorship->restore();

		$result = $this->_query->run('
			UPDATE
				order_return
			SET
				deleted_at = NULL,
				deleted_by = NULL
			WHERE
				return_id = ?i
		', $return->id);

		// Commit all the changes
		if (!$this->_transOverridden) {
			$this->_query->commit();
		}

		return $return;
	}
}