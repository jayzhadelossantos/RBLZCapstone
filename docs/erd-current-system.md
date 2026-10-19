# PARDS Current System ERD

This ERD reflects the current Laravel migrations and relationships used by the
Property and Accountability Records and Documentation System (PARDS). `PK`
means primary key, `FK` means foreign key, and `UQ` means unique value.

```mermaid
erDiagram
    USERS {
        bigint id PK
        string name
        string email UQ
        string password
        string role
        boolean is_active
        timestamp email_verified_at
        string two_factor_code
        timestamp two_factor_expires_at
        timestamp created_at
        timestamp updated_at
    }

    PROFILE_PHOTOS {
        bigint id PK
        bigint user_id FK_UQ
        string mime_type
        mediumtext contents
        timestamp created_at
        timestamp updated_at
    }

    ADMINS {
        bigint id PK
        bigint user_id FK_UQ
        string department
    }

    STAFF {
        bigint id PK
        bigint user_id FK_UQ
        string employee_number
        string department
    }

    TEACHERS {
        bigint id PK
        bigint user_id FK_UQ
        string employee_number
        string subject_area
    }

    PROPERTY_CATEGORIES {
        bigint id PK
        string category_name UQ
        text description
    }

    LOCATIONS {
        bigint id PK
        string location_name UQ
        string building
        string room
        text description
    }

    PROPERTIES {
        bigint id PK
        string property_code UQ
        string property_name
        bigint property_category_id FK
        bigint location_id FK
        bigint created_by FK
        bigint staff_id FK
        string serial_number UQ
        integer quantity
        string unit
        decimal unit_cost
        string status
        string condition_status
        string department
        string office
        date date_acquired
        string qr_token UQ
    }

    PROPERTY_UNITS {
        bigint id PK
        bigint property_id FK
        bigint assignment_id FK
        string serial_number UQ
        string qr_token UQ
        string status
        timestamp created_at
        timestamp updated_at
    }

    ASSIGNMENTS {
        bigint id PK
        bigint property_id FK
        bigint teacher_id FK
        bigint teacher_profile_id FK
        bigint assigned_by FK
        bigint staff_id FK
        bigint location_id FK
        integer quantity_assigned
        string department
        date date_assigned
        date expected_return_date
        date returned_at
        string status
    }

    PROPERTY_REQUESTS {
        bigint id PK
        bigint requested_by FK
        bigint reviewed_by FK
        bigint processed_by FK
        bigint selected_property_id FK
        bigint assignment_id FK_UQ
        string requested_item_name
        integer requested_quantity
        date needed_by
        text purpose
        string status
        timestamp reviewed_at
        timestamp processed_at
    }

    RETURNS {
        bigint id PK
        bigint assignment_id FK
        bigint returned_by FK
        date return_date
        string status
        text returned_serial_numbers
        text remarks
    }

    DISPOSAL_METHODS {
        bigint id PK
        string method_name UQ
        text description
    }

    DISPOSALS {
        bigint id PK
        bigint property_id FK
        bigint assignment_id FK
        bigint disposed_by FK
        bigint processed_by FK
        bigint admin_id FK
        bigint disposal_method_id FK
        integer quantity_disposed
        date disposal_date
        string status
        text disposal_reason
        timestamp processed_at
    }

    PROPERTY_HISTORIES {
        bigint id PK
        bigint property_id FK
        string action_type
        string reference
        date action_date
        text remarks
    }

    APPROVALS {
        bigint id PK
        bigint admin_id FK
        string reference_type
        string approval_status
        date approval_date
        text remarks
    }

    USERS ||--o| PROFILE_PHOTOS : has
    USERS ||--o| ADMINS : has_role_profile
    USERS ||--o| STAFF : has_role_profile
    USERS ||--o| TEACHERS : has_role_profile
    USERS ||--o{ PROPERTIES : creates
    STAFF o|--o{ PROPERTIES : manages
    PROPERTY_CATEGORIES o|--o{ PROPERTIES : classifies
    LOCATIONS o|--o{ PROPERTIES : stores

    PROPERTIES ||--o{ PROPERTY_UNITS : contains
    PROPERTIES ||--o{ ASSIGNMENTS : assigned_through
    PROPERTIES o|--o{ PROPERTY_REQUESTS : selected_for
    PROPERTIES ||--o{ DISPOSALS : disposed_through
    PROPERTIES ||--o{ PROPERTY_HISTORIES : records

    USERS ||--o{ ASSIGNMENTS : receives_as_assignee
    USERS o|--o{ ASSIGNMENTS : assigns
    TEACHERS o|--o{ ASSIGNMENTS : profile_for_assignee
    STAFF o|--o{ ASSIGNMENTS : staff_assignment_record
    LOCATIONS o|--o{ ASSIGNMENTS : assigned_at
    ASSIGNMENTS o|--o{ PROPERTY_UNITS : includes_units
    ASSIGNMENTS ||--o{ RETURNS : returned_through
    USERS o|--o{ RETURNS : records_return

    USERS ||--o{ PROPERTY_REQUESTS : requests
    USERS o|--o{ PROPERTY_REQUESTS : reviews
    USERS o|--o{ PROPERTY_REQUESTS : processes
    ASSIGNMENTS o|--o| PROPERTY_REQUESTS : fulfills

    ASSIGNMENTS o|--o{ DISPOSALS : source_assignment
    USERS o|--o{ DISPOSALS : requests_disposal
    USERS o|--o{ DISPOSALS : processes_disposal
    ADMINS o|--o{ DISPOSALS : admin_review
    DISPOSAL_METHODS o|--o{ DISPOSALS : method
    ADMINS o|--o{ APPROVALS : records
```

## Relationship rules

- Every account is stored in `users`. A role of `admin`, `staff`, or `teacher`
  has at most one matching role-profile row. The role is validated by the
  application; the database does not require exactly one profile row.
- A property is a product record. `property_units` holds every physical item,
  so a quantity of five laptops has five unique serial numbers and QR tokens.
- An assignment gives one or more available property units to one user. The
  assignment's `teacher_id` is the actual assignee and supports both teacher
  and staff users despite its legacy column name.
- A property request belongs to its requester and moves through staff review,
  admin approval, property selection, and a single resulting assignment. Its
  `assignment_id` is unique, so an assignment can fulfill at most one request.
- A return belongs to an assignment. A disposal may refer to a property and,
  when applicable, the source assignment.

## Deliberately not drawn as foreign-key links

- `approvals.reference_type` identifies the kind of record being approved, but
  the current table has no `reference_id` foreign key. It is therefore not
  linked to another entity in the ERD.
- `properties.category`, `properties.location`, `assignments.location`, and
  older date/reference columns remain as compatibility fields. The normalized
  relationships use `property_category_id`, `location_id`, and the current
  foreign-key columns shown above.
- Laravel support tables (`sessions`, `password_reset_tokens`, `cache`,
  `jobs`, `job_batches`, and `failed_jobs`) are framework infrastructure and
  are excluded from the operational ERD.

## Text-only ERD relationship map

Use this version when Mermaid preview is unavailable. `1` means one record,
`0..1` means optional one record, and `0..*` means zero or many records.

```text
USERS
  1 ----- 0..1 ADMINS              (admins.user_id)
  1 ----- 0..1 STAFF               (staff.user_id)
  1 ----- 0..1 TEACHERS            (teachers.user_id)
  1 ----- 0..1 PROFILE_PHOTOS      (profile_photos.user_id)
  1 ----- 0..* PROPERTIES          (properties.created_by)
  1 ----- 0..* ASSIGNMENTS         (assignments.teacher_id: assignee)
  1 ----- 0..* ASSIGNMENTS         (assignments.assigned_by: issuer)
  1 ----- 0..* PROPERTY_REQUESTS   (requested_by, reviewed_by, processed_by)
  1 ----- 0..* RETURNS             (returns.returned_by)
  1 ----- 0..* DISPOSALS           (disposed_by, processed_by)

PROPERTY_CATEGORIES
  1 ----- 0..* PROPERTIES          (properties.property_category_id)

LOCATIONS
  1 ----- 0..* PROPERTIES          (properties.location_id)
  1 ----- 0..* ASSIGNMENTS         (assignments.location_id)

STAFF
  1 ----- 0..* PROPERTIES          (properties.staff_id)
  1 ----- 0..* ASSIGNMENTS         (assignments.staff_id)

TEACHERS
  1 ----- 0..* ASSIGNMENTS         (assignments.teacher_profile_id)

PROPERTIES
  1 ----- 0..* PROPERTY_UNITS      (property_units.property_id)
  1 ----- 0..* ASSIGNMENTS         (assignments.property_id)
  1 ----- 0..* PROPERTY_REQUESTS   (selected_property_id)
  1 ----- 0..* DISPOSALS           (disposals.property_id)
  1 ----- 0..* PROPERTY_HISTORIES  (property_histories.property_id)

ASSIGNMENTS
  1 ----- 0..* PROPERTY_UNITS      (property_units.assignment_id)
  1 ----- 0..* RETURNS             (returns.assignment_id)
  1 ----- 0..* DISPOSALS           (disposals.assignment_id)
  1 ----- 0..1 PROPERTY_REQUESTS   (property_requests.assignment_id is unique)

DISPOSAL_METHODS
  1 ----- 0..* DISPOSALS           (disposals.disposal_method_id)

ADMINS
  1 ----- 0..* DISPOSALS           (disposals.admin_id)
  1 ----- 0..* APPROVALS           (approvals.admin_id)

APPROVALS
  `reference_type` describes what was approved, but the current schema has no
  `reference_id`, so it does not have a database relationship to another table.
```

## Text-only ERD entities with attributes

This is the ERD attribute view for documentation. The complete field types and
definitions are in `data-dictionary-current-system.md`.

```text
USERS
- id (PK)
- name
- email (UQ)
- password
- role [admin, staff, teacher]
- is_active
- email_verified_at
- created_at
- updated_at

ADMINS
- id (PK)
- user_id (FK, UQ)
- department

STAFF
- id (PK)
- user_id (FK, UQ)
- employee_number
- department

TEACHERS
- id (PK)
- user_id (FK, UQ)
- employee_number
- subject_area

PROFILE_PHOTOS
- id (PK)
- user_id (FK, UQ)
- mime_type
- contents
- created_at
- updated_at

PROPERTY_CATEGORIES
- id (PK)
- category_name (UQ)
- description

LOCATIONS
- id (PK)
- location_name (UQ)
- building
- room
- description

PROPERTIES
- id (PK)
- property_code (UQ)
- property_name
- property_category_id (FK)
- location_id (FK)
- created_by (FK)
- staff_id (FK)
- serial_number (UQ)
- quantity
- unit
- unit_cost
- status [available, assigned, for_disposal, disposed]
- condition_status
- department
- office
- date_acquired
- qr_token (UQ)
- created_at
- updated_at

PROPERTY_UNITS
- id (PK)
- property_id (FK)
- assignment_id (FK)
- serial_number (UQ)
- qr_token (UQ)
- status [available, assigned, disposed]
- created_at
- updated_at

ASSIGNMENTS
- id (PK)
- property_id (FK)
- teacher_id (FK)
- teacher_profile_id (FK)
- assigned_by (FK)
- staff_id (FK)
- location_id (FK)
- quantity_assigned
- department
- date_assigned
- expected_return_date
- returned_at
- status [active, returned, transferred, disposed]
- remarks
- created_at
- updated_at

PROPERTY_REQUESTS
- id (PK)
- requested_by (FK)
- reviewed_by (FK)
- processed_by (FK)
- selected_property_id (FK)
- assignment_id (FK, UQ)
- requested_item_name
- requested_quantity
- needed_by
- purpose
- additional_notes
- review_notes
- response_notes
- status [pending, awaiting_admin, awaiting_stock, approved, rejected, fulfilled]
- reviewed_at
- processed_at
- created_at
- updated_at

RETURNS
- id (PK)
- assignment_id (FK)
- returned_by (FK)
- return_date
- returned_serial_numbers
- status
- remarks

DISPOSAL_METHODS
- id (PK)
- method_name (UQ)
- description

DISPOSALS
- id (PK)
- property_id (FK)
- assignment_id (FK)
- disposed_by (FK)
- processed_by (FK)
- admin_id (FK)
- disposal_method_id (FK)
- quantity_disposed
- disposal_date
- disposal_reason
- disposal_method
- response_notes
- status [pending, approved, completed, cancelled]
- processed_at
- created_at
- updated_at

PROPERTY_HISTORIES
- id (PK)
- property_id (FK)
- action_type
- reference
- action_date
- remarks

APPROVALS
- id (PK)
- admin_id (FK)
- reference_type
- approval_status
- approval_date
- remarks
```

## Relationships and descriptions

`1 : 0..1` means one record is related to zero or one record. `1 : 0..*`
means one record may be related to many records.

| Parent table | Child table | Connected fields | Relationship | Description |
| --- | --- | --- | --- | --- |
| Users | Admins | `users.id` → `admins.user_id` | 1 : 0..1 | A user account may have one administrator profile. |
| Users | Staff | `users.id` → `staff.user_id` | 1 : 0..1 | A user account may have one staff profile. |
| Users | Teachers | `users.id` → `teachers.user_id` | 1 : 0..1 | A user account may have one teacher profile. |
| Users | Profile Photos | `users.id` → `profile_photos.user_id` | 1 : 0..1 | A user may save one profile picture. |
| Users | Properties | `users.id` → `properties.created_by` | 1 : 0..* | A user may create many property records. |
| Staff | Properties | `staff.id` → `properties.staff_id` | 1 : 0..* | A staff profile may manage many property records. |
| Property Categories | Properties | `property_categories.id` → `properties.property_category_id` | 1 : 0..* | A category can classify many properties. |
| Locations | Properties | `locations.id` → `properties.location_id` | 1 : 0..* | A location can contain many property records. |
| Properties | Property Units | `properties.id` → `property_units.property_id` | 1 : 0..* | A property record contains physical units. Each unit has its own serial number and QR token. |
| Properties | Assignments | `properties.id` → `assignments.property_id` | 1 : 0..* | A property may appear in many assignments throughout its lifecycle. |
| Users | Assignments | `users.id` → `assignments.teacher_id` | 1 : 0..* | A teacher or staff user may receive many property assignments. |
| Teachers | Assignments | `teachers.id` → `assignments.teacher_profile_id` | 1 : 0..* | A teacher profile may be linked to many assignments. |
| Users | Assignments | `users.id` → `assignments.assigned_by` | 1 : 0..* | A user may issue many assignments. |
| Staff | Assignments | `staff.id` → `assignments.staff_id` | 1 : 0..* | A staff profile may process many assignments. |
| Locations | Assignments | `locations.id` → `assignments.location_id` | 1 : 0..* | A location may be used in many assignments. |
| Assignments | Property Units | `assignments.id` → `property_units.assignment_id` | 1 : 0..* | An assignment can include one or more selected physical units. |
| Assignments | Returns | `assignments.id` → `returns.assignment_id` | 1 : 0..* | An assignment can have return records. |
| Users | Returns | `users.id` → `returns.returned_by` | 1 : 0..* | A user may record many return transactions. |
| Users | Property Requests | `users.id` → `property_requests.requested_by` | 1 : 0..* | A teacher/end user may submit many property requests. |
| Users | Property Requests | `users.id` → `property_requests.reviewed_by` | 1 : 0..* | A staff member may review and forward many requests. |
| Users | Property Requests | `users.id` → `property_requests.processed_by` | 1 : 0..* | An administrator may approve, reject, or mark many requests as awaiting stock. |
| Properties | Property Requests | `properties.id` → `property_requests.selected_property_id` | 1 : 0..* | An administrator can select a property for a request when inventory is available. |
| Assignments | Property Requests | `assignments.id` → `property_requests.assignment_id` | 1 : 0..1 | A fulfilled request creates at most one assignment and receiving receipt. |
| Properties | Disposals | `properties.id` → `disposals.property_id` | 1 : 0..* | A property may have disposal records. |
| Assignments | Disposals | `assignments.id` → `disposals.assignment_id` | 1 : 0..* | A disposal can refer to an active assignment when an assigned item is disposed. |
| Users | Disposals | `users.id` → `disposals.disposed_by` | 1 : 0..* | A user may submit many disposal requests. |
| Users | Disposals | `users.id` → `disposals.processed_by` | 1 : 0..* | An administrator/user may process many disposal decisions. |
| Admins | Disposals | `admins.id` → `disposals.admin_id` | 1 : 0..* | An administrator profile may review many disposal records. |
| Disposal Methods | Disposals | `disposal_methods.id` → `disposals.disposal_method_id` | 1 : 0..* | One disposal method can be used by many disposal records. |
| Properties | Property Histories | `properties.id` → `property_histories.property_id` | 1 : 0..* | A property may have many history entries, including assignment, return, and disposal activities. |
| Admins | Approvals | `admins.id` → `approvals.admin_id` | 1 : 0..* | An administrator profile may record many approval entries. |

`approvals.reference_type` identifies the type of record approved, but the
current schema has no `reference_id`. It therefore has no direct foreign-key
connection to a property request, assignment, or disposal record.
