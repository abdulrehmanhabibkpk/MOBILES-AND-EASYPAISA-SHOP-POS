# Firestore Security Specification

## 1. Data Invariants
- Products, product sales, transactions, expenses, daily balances, and app settings must adhere strictly to valid schema formats.
- String fields must have constrained max lengths to prevent denial of wallet attacks.
- Document IDs must pass `isValidId()` sanity checks.
- All writes require user authentication (`request.auth != null`).

## 2. Dirty Dozen Payload Verification
1. Payload with 1MB oversized string in `name` field -> Blocked by `maxLength` check.
2. Payload with unauthenticated write request -> Blocked by `isSignedIn()`.
3. Payload with invalid document ID containing special characters (`../`) -> Blocked by `isValidId()`.
4. Payload with invalid collection key insertion -> Blocked by schema validation.
5. Payload altering immutable document ID -> Blocked.
6. Payload missing required `purchasePrice` or `salePrice` -> Blocked by `isValidProduct()`.
7. Payload setting negative total amount in sales -> Blocked by non-negative number check.
8. Payload with illegal enum value in transaction type -> Blocked by enum check.
9. Payload with bad date format -> Blocked by string validation.
10. Payload attempting global catch-all write -> Blocked by default deny rule.
11. Payload exceeding max string limits in expenses -> Blocked.
12. Payload attempting anonymous modifications when unsigned -> Blocked.
