# Laravel API Flow Pattern (Mandatory)

## Purpose
This guide defines the mandatory flow to follow before and during implementation of every Laravel REST API in this project.

All API work must follow this pattern:

`Route -> Request Validation -> Controller -> Service/Action -> Model -> Resource -> Response`

---

## 1) Pre-Implementation Analysis (Do This First)

Before writing any code, document the feature flow in brief:

1. Objective
- What this API does.

2. Actor
- Who can call this endpoint (`auth:sanctum`, role, permission).

3. Status/Business Flow
- Define valid state transitions.
- Define blocked transitions.

4. Ownership/Authorization
- Who owns the resource.
- Which policy/permission check is required.

5. Input/Output Contract
- Required payload fields.
- Response shape (`success`, `message`, `data` / `errors`).

Do not start coding until this flow is clear.

---

## 2) Route Layer Rules

- Define API routes in `routes/api.php`.
- Use proper HTTP verbs:
  - `GET` list/show
  - `POST` create
  - `PUT/PATCH` update
  - `DELETE` delete
- Use route model binding where applicable.
- Protect private routes with `auth:sanctum`.
- Add permission middleware when needed.

Example:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('jobs', JobController::class);
});
```

---

## 3) Request Validation Rules

- Use Form Request classes (`StoreXRequest`, `UpdateXRequest`).
- Do not place large validation in controllers.
- Include meaningful validation rules.
- Add custom messages only when they improve clarity.
- Put authorization in Form Request `authorize()` when suitable.

---

## 4) Controller Rules

Controllers must stay thin.

Controller should only:
- Receive validated request.
- Trigger authorization/policy checks (if not already covered).
- Call Service/Action.
- Return API Resource response.

Controller must not:
- Contain heavy business logic.
- Return raw Eloquent model arrays directly.

---

## 5) Service/Action Layer Rules

Create a Service class when logic is beyond simple CRUD.

Service handles:
- Transactions.
- Status transitions.
- Multi-model updates.
- Complex conditional logic.
- File upload and side effects.

Recommended path:
- `app/Services/{Feature}Service.php`

---

## 6) Model Layer Rules

- Define `fillable` safely.
- Add `casts` for dates/booleans/json/etc.
- Define Eloquent relationships.
- Add query scopes for reusable filters.
- Avoid raw queries unless necessary.

---

## 7) Resource Layer Rules

Always return API Resources.

- Single item: `FeatureResource`
- List: `FeatureResource::collection(...)`
- Paginated list: resource collection + pagination metadata

Do not return raw models from controllers.

---

## 8) Response Format Rules

Use consistent response format.

Success:

```json
{
  "success": true,
  "message": "Operation completed successfully.",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Something went wrong.",
  "errors": {}
}
```

If project helper methods exist (`success()`, `error()`), use them consistently.

---

## 9) Transaction Safety Rules

For multi-step operations:
- Use `DB::beginTransaction()`
- `DB::commit()` on success
- `DB::rollBack()` in `catch`
- Return safe error responses

Never allow partial writes when any step fails.

---

## 10) Query/Listing Rules

For index APIs, support where relevant:
- Search
- Status filtering
- Sorting (allowlist columns)
- Pagination

Put repeated query logic in model scopes.

---

## 11) Authentication and Authorization Rules

- Never trust `user_id` from request when ownership should come from auth user.
- Use `auth()->id()` for ownership-based records.
- Enforce policies/permissions for read/update/delete.
- Return proper status codes for forbidden/unauthorized access.

---

## 12) Error Handling Rules

Use appropriate status codes:
- `200` OK
- `201` Created
- `400` Bad Request
- `401` Unauthenticated
- `403` Forbidden
- `404` Not Found
- `422` Validation Error
- `500` Server Error

Never expose sensitive exception details in production responses.

---

## 13) Required Delivery Order For Every API Task

When implementing a feature, present output in this order:

A. API Flow Explanation
B. Database/Migration Changes (if any)
C. Model Updates (`fillable`, `casts`, relationships, scopes)
D. Form Request(s)
E. Service/Action class (if needed)
F. Controller method(s)
G. API Resource/Collection
H. Route definitions
I. Example request body
J. Example success response
K. Example error response
L. Edge cases and assumptions

---

## 14) API Implementation Checklist (Use Before Marking Complete)

- [ ] Route added in `routes/api.php` with correct verb.
- [ ] Route protected with `auth:sanctum` if required.
- [ ] Form Request(s) created and used.
- [ ] Controller is thin and service-driven.
- [ ] Business logic moved to Service/Action where needed.
- [ ] Transaction used for multi-step writes.
- [ ] Model relationships/fillable/casts/scopes updated.
- [ ] API Resource used for response serialization.
- [ ] Response format is consistent (`success/error`).
- [ ] Authorization/ownership checks enforced.
- [ ] Proper status codes returned.
- [ ] Search/filter/sort/pagination handled for index API (if required).
- [ ] Edge cases documented.

---

## 15) Request Template (To Start Any New API Feature)

Use this template before implementation:

```text
Feature:
- ...

Existing models/tables:
- ...

Business rules/status flow:
- ...

Auth/permission requirements:
- ...

Expected request payload:
- ...

Expected response fields:
- ...

Notes/constraints:
- ...
```

This document is mandatory for all contributors implementing APIs in this repository.
