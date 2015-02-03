<?php

namespace Message\Mothership\OrderReturn\Entity;

use Message\Cog\ValueObject\Authorship;

use Message\Mothership\Commerce\Payable\PayableInterface;
use Message\Mothership\Commerce\Refund\RefundableInterface;
use Message\Mothership\Commerce\Order\Entity\EntityInterface;
use Message\Mothership\Commerce\Order\Transaction\RecordInterface;

use Message\Mothership\OrderReturn\Statuses;
use Message\Mothership\OrderReturn\Resolutions;

class OrderReturn implements EntityInterface, PayableInterface, RecordInterface, RefundableInterface
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
		$this->authorship = new Authorship;
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
		if (null === $this->item->order) {
			return null;
		}

		return $this->item->order->getPayableAddress($type);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayableTransactionID()
	{
		return 'RETURN-' . $this->id;
	}

	public function getTax()
	{
		return $this->item->getTax();
	}
}