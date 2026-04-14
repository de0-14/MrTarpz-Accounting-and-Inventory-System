# Customer Type Implementation TODO

## DB Migration ✅ (User to run SQL above)

## 1. Update orders.php ✅
- Replace customer select with type dropdown + optional name/phone inputs
- Update all AJAX queries to handle customer_type in SELECT/CASE
- Update add_order to insert customer_type

## 2. Update js/orders.js ✅
- Frontend form changes for type selection
- Update saveOrder() data
- Update display logic for customer names by type

## 3. Test [PENDING]
- Create orders for each type
- Verify table/details display
- Check existing orders

## 4. Check impacts [PENDING]
- reports.php, payments.php if needed
