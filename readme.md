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