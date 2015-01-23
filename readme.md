# Mothership Returns

_description_

## Installation

Add `"message/cog-mothership-returns": "1.1.*"` to your `composer.json`.


## Assembling a new return

The assembler helps you build a new return ready for passing to the create decorator.

This service is available via:

```php
$assembler = $this->get('return.assembler');
```

If you have an existing return you can pass this into the assembler with:

```php
$assembler->setReturn($return);
```

And retrieve the assembled return once you are finished building it:

```php
$return = $assembler->getReturn();
```

If your return has no associated order, i.e. is standalone, you should set the currency. This defaults to `'GBP'`:

```php
$assembler->setCurrency('EUR');
```

You can set the return item from either a instance of `Commerce\Order\Entity\Item\Item` or `Commerce\Product\Unit\Unit`.

```php
// Standard
$orderItem = $this->get('order.item.loader')->getByID(1);
$assembler->setReturnItem($orderItem);

// Standalone
$productUnit = $this->get('product.unit.loader')->getByID(1);
$assembler->setReturnItem($productUnit);
```




## Notes

Namespaces and classes are called `OrderReturn` due to PHP having `return` as a reserved word.

## License

Mothership E-Commerce
Copyright (C) 2015 Jamie Freeman

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program.  If not, see <http://www.gnu.org/licenses/>.
