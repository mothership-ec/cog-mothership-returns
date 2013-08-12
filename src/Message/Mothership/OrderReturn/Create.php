<?php

namespace Message\Mothership\OrderReturn;

use Message\Cog\DB;
use ReflectionClass;
use InvalidArgumentException;
use Message\User\UserInterface;
use Message\Cog\ValueObject\DateTimeImmutable;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create implements DB\TransactionalInterface
{
	protected $_query;
	protected $_loader;
	protected $_user;
	protected $_reasons;
	protected $_resolutions;

	protected $_reason;
	protected $_resolution;
	protected $_exchangeUnit;

	public function __construct(DB\Transaction $query, Loader $loader, UserInterface $user, Reasons $reasons,
		Resolutions $resolutions)
	{
		$this->_query  = $query;
		$this->_loader = $loader;
		$this->_user   = $user;
		$this->_reasons = $reasons;
		$this->_resolutions = $resolutions;
	}

	public function setReason($reason)
	{
		$this->_reason = $reason;
		return $this;
	}

	public function setResolution($resolution)
	{
		$this->_resolution = $resolution;
		return $this;
	}

	public function setExchangeProduct(Unit $unit)
	{
		$this->_exchangeUnit = $unit;
		return $this;
	}

	public function create(OrderReturn $return)
	{
		$this->_validate($return);

		// Set create authorship data if not already set
		if (! $return->authorship->createdAt()) {
			$return->authorship->create(
				new DateTimeImmutable,
				$this->_user->id
			);
		}

		$this->_query->add('
			INSERT INTO
				order_item_return
			SET
				order_id         = :orderID?i,
				item_id          = :itemID?i,
				created_at       = :createdAt?i,
				created_by       = :createdBy?i,
				reason           = :reason?i,
				resolution       = :resolution?i,
				exchange_unit_id = :exchange_unit_id?i
		', array(
			'orderID'          => $return->order->id,
			'itemID'           => $return->item->id,
			'createdAt'        => $return->authorship->createdAt(),
			'createdBy'        => $return->authorship->createdBy(),
			'reason'           => $this->_reason,
			'resolution'       => $this->_resolution,
			'exchange_unit_id' => $this->_exchangeUnit
		));
	}

	protected function validate(OrderReturn $return)
	{
		// Ensure an item has been set for the return
		if (! $return->item instanceof Order\Entity\Item\Item) {
			throw new InvalidArgumentException('Could not create order return: item is not set or invalid');
		}

		// Check the reason has been set and is valid
		$reasons = new ReflectionClass($this->_reasons);
		if (! in_array($this->_reason, $reasons->getConstants())) {
			throw new InvalidArgumentException('Could not create order return: reason is not set or invalid');
		}

		// Check the resolution has been set and is valid
		$resolutions = new ReflectionClass($this->_resolutions);
		if (! in_array($this->_resolution, $resolutions->getConstants())) {
			throw new InvalidArgumentException('Could not create order return: resolution is not set or invalid');
		}

		// If this is an exchange, check an exchange unit has been set
		$resolutions = $this->_resolutions;
		if ($this->_resolution == $resolutions::EXCHANGE and ! $this->_exchangeUnit) {
			throw new InvalidArgumentException('Could not create order return: exchange unit required');
		}

		// ... any more?
	}

}