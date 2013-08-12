<?php

namespace Message\Mothership\OrderReturn;

use ReflectionClass;
use InvalidArgumentException;

use Message\Cog\DB;
use Message\Cog\ValueObject\DateTimeImmutable;

use Message\Mothership\Commerce\Order\Entity\Item\Item;
use Message\Mothership\Commerce\Product\Unit\Unit;

use Message\User\UserInterface;

/**
 * Order return creator.
 *
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Create
{
	protected $_query;
	protected $_loader;
	protected $_user;
	protected $_reasons;
	protected $_resolutions;

	protected $_item;
	protected $_reason;
	protected $_resolution;
	protected $_exchangeUnit;

	public function __construct(DB\Query $query, Loader $loader, UserInterface $user, Reasons $reasons,
		Resolutions $resolutions)
	{
		$this->_query  = $query;
		$this->_loader = $loader;
		$this->_user   = $user;
		$this->_reasons = $reasons;
		$this->_resolutions = $resolutions;
	}

	public function setItem(Item $item)
	{
		$this->_item = $item;
		return $this;
	}

	public function setReason($reason)
	{
		$this->_reason = $this->_reasons->get($reason);
		return $this;
	}

	public function setResolution($resolution)
	{
		$this->_resolution = $this->_resolutions->get($resolution);
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

		// Insert the return into the database
		$result = $this->_query->add('
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
			'orderID'          => $this->_item->order_id,
			'itemID'           => $this->_item->id,
			'createdAt'        => $return->authorship->createdAt(),
			'createdBy'        => $return->authorship->createdBy(),
			'reason'           => $this->_reason,
			'resolution'       => $this->_resolution,
			'exchange_unit_id' => $this->_exchangeUnit
		));

		// Get the return by the last insert id
		$return = $this->_loader->getByID($result->id());

		return $return;
	}

	protected function validate(OrderReturn $return)
	{
		// Ensure an item has been set for the return
		if (! $this->_item instanceof Item) {
			throw new InvalidArgumentException('Could not create order return: item is not set or invalid');
		}

		// Check the reason has been set and is valid
		if (! $this->_reasons->exists($this->_reason)) {
			throw new InvalidArgumentException('Could not create order return: reason is not set or invalid');
		}

		// Check the resolution has been set and is valid
		if (! $this->_resolutions->exists($this->_resolution)) {
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