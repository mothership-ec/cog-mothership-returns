# Changelog

## 5.4.0

- `Report\AppendQuery\Returns` loops through filters to check for ones that implement `ModifyQueryInterface`, allowing for more automated filtering
- `Report\Appendquery\Exchanges` checks from brand filter
- Increased Reports dependency to 2.1

## 5.3.0

- Prevent returns from being partially updated when an error is thrown during the process
- `return.edit` service is now a singleton
- When processing a balance, the transaction in the `Edit` class is overridden, and committed at the end of the controller
- Once a balance has been successfully processed, a confirmation email is sent to the customer, where before it was sent half way through the process
- Resolve issue where and error would be thrown when attempting to load a payment gateway if no payment is present
- Balance is now always saved when editing a return

## 5.2.0

- Resolve issue where returned items would appear in fulfillment even after the order had been completed
- Added `EventListener::checkStatus()` event listener to set returned item status accurately
- Removed broken `return.gateway` service
- Refactor `Controller\OrderReturn\Detail::processBalance()` to use gateway assigned to payment when refunding
- Update `cog-mothership-ecommerce` dependency to 3.7

## 5.1.1

- Error more gracefully when a user attempts to process a refund of zero

## 5.1.0

- Added `setDefaultAddress()` method to `Assembler` class
- If payable address is not set on `Assembler`, use default address

## 5.0.2

- Fix `_1391686934_CreateReturnAndReturnItemTables.php` migration by adding a default value to the `returned_value` column, which was causing migrations to break

## 5.0.1

- Fix issue where MySQL ID variable for return items watched the variable for the return itself

## 5.0.0

- Initial open source release