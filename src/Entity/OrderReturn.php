<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject;

use Message\Mothership\Commerce\Payable\PayableInterface;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;
use Message\Mothership\Commerce\Order\Transaction\RecordInterface;

use Message\Mothership\OrderReturn\Statuses;
use Message\Mothership\OrderReturn\Resolutions;

class OrderReturn implements EntityInterface, PayableInterface, RecordInterface
{
	const RECORD_TYPE = 'return';

	public $id;
	public $authorship;

	public $documentID;

	public $type;
	public $currencyID;

	public $item;
	public $payments = [];
	public $refunds = [];

	public function __construct()
	{
		$this->authorship = new ValueObject\Authorship;
	}

	public function getDisplayID()
	{
		return 'R' . $this->id;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordType()
	{
		return self::RECORD_TYPE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getRecordID()
	{
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableAmount()
	{
		return abs($this->item->balance);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableCurrency()
	{
		return $this->currencyID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableAddress($type)
	{
		return $this->item->order->getPayableAddress($type);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableTransactionID()
	{
		return 'RETURN-' . $this->id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayments()
	{
		return new $this->payments;
	}
}