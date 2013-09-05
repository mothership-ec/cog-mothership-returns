<?php

namespace Message\Mothership\OrderReturn\Collection;

/**
 * Collection item model.
 *
 * @author Joe Holdcroft <joe@message.co.uk>
 * @author Laurence Roberts <laurence@message.co.uk>
 */
class Item
{
	public $code;
	public $name;

	/**
	 * Constructor
	 *
	 * @param string $code Item code
	 * @param string $name Item name
	 */
	public function __construct($code, $name)
	{
		$this->code = $code;
		$this->name = $name;
	}

	/**
	 * Get this item as a string.
	 *
	 * @return string The item code & name as "(code) name"
	 */
	public function __toString()
	{
		return sprintf('%s', $this->name);
	}
}