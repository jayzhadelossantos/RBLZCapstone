# PARDS — Kumpletong Pseudocode ng Kasalukuyang Sistema

**Property and Accountability Records and Documentation System (PARDS)**

> Ang dokumentong ito ay nakabase sa **aktwal na implementasyon** ng proyekto:
> `routes/web.php`, mga controller, form request, model, service, middleware, at migrations.
> Hindi ito naglalarawan ng planong feature na wala pa sa sistema.
>
> **Layunin:** Ipakita sa instructor/panel ang daloy ng bawat feature mula **INPUT → PROCESS → OUTPUT**,
> ang mga user role, database operation, validation, system response, at kung paano nag-uugnayan
> ang mga feature.

---

## 1. Pangkalahatang Ideya ng Sistema

Ang PARDS ay isang **Laravel 12 web application** para sa supply office ng paaralan. Pinamamahalaan nito ang:

- mga property record at pisikal na unit na may serial number at QR code,
- pagtatala ng accountability/assignment sa teacher o staff,
- mga request ng teacher para sa bagong property o replacement,
- pagbabalik (return) ng property,
- mga disposal request at desisyon ng admin,
- inventory balance at inventory logs,
- reports (web, PDF, CSV), at
- user accounts na may role-based access.

### 1.1 Technology Stack (aktwal)

| Layer | Ginagamit |
|---|---|
| Backend | PHP 8 / Laravel 12 |
| Database | MySQL (configurable sa `.env`; may SQLite sa tests) |
| Authentication | Laravel session auth (`Auth::login`, session regenerate) |
| Role control | Custom `RoleMiddleware` (`role:admin`, `role:staff`, `role:teacher`) |
| QR generation | `simplesoftwareio/simple-qrcode` (SVG) |
| PDF generation | `barryvdh/laravel-dompdf` |
| Email/OTP | Brevo HTTP API kung may `BREVO_API_KEY`; kung wala, Laravel Mail |
| Frontend | Blade, Bootstrap 5, vanilla JavaScript |
| Profile photo | Base64 content sa `profile_photos` table |

### 1.2 User Roles

| Role | Kakayahan sa kasalukuyang sistema |
|---|---|
| **Admin** | Lahat ng shared operations; user management; final approval ng property requests; desisyon sa disposal; pwedeng direktang mag-record ng completed disposal; reports; QR. |
| **Staff** | Property at unit management; assignment at return; staff review ng teacher requests; fulfillment ng approved request; pagsumite ng disposal request; reports; QR. Hindi pwedeng mag-manage ng users o mag-approve ng disposal. |
| **Teacher** | Registration, login, profile photo; sariling dashboard; tingnan ang sariling assigned properties; magsumite ng property request; tingnan ang status/receipt ng sariling request. Walang access sa staff/admin modules. |
| **Guest** | Registration, OTP verification, login, at public QR scan page. |

### 1.3 Role Permission Matrix (batay sa routes at middleware)

| Module / Route Group | Guest | Teacher | Staff | Admin |
|---|---:|---:|---:|---:|
| Register / Verify OTP / Login | ✅ | — | — | — |
| Logout, Profile | — | ✅ | ✅ | ✅ |
| Role dashboard | — | ✅ teacher | ✅ staff | ✅ admin |
| Users CRUD at activate/deactivate | — | — | — | ✅ |
| Properties CRUD | — | — | ✅ | ✅ |
| Assignments (index/create/store/show/edit/update) | — | — | ✅ | ✅ |
| Assignment print at mark returned | — | — | ✅ | ✅ |
| Returns list | — | — | ✅ | ✅ |
| Disposals index/create/store/show | — | — | ✅ | ✅ |
| Disposal status decision | — | — | — | ✅ |
| Property requests (staff/admin queue) | — | — | ✅ | ✅ |
| Property request status update | — | — | ✅ (review/fulfill) | ✅ (approve/reject/awaiting stock) |
| Property request (teacher submit/list) | — | ✅ | — | — |
| Request receipt | — | ✅ (own) | ✅ | ✅ |
| QR pages at download/print | — | — | ✅ | ✅ |
| Public QR scan `/qr/{token}` | ✅ | ✅ | ✅ | ✅ |
| Inventory history | — | — | ✅ | ✅ |
| Reports web/PDF/CSV | — | — | ✅ | ✅ |
| Teacher “My Properties” | — | ✅ | — | — |

---

## 2. Pangkalahatang Request Flow ng Laravel

Lahat ng feature ay dumadaan sa parehong basic pipeline:

```text
INPUT: Browser request (form submit, link click, QR scan)
  ↓
ROUTE (routes/web.php)
  ↓
MIDDLEWARE
  ├─ PreventPageCaching (lahat ng request)
  ├─ HandleAjaxFormResponses (web group)
  ├─ auth / guest
  └─ role:admin | role:admin,staff | role:staff | role:teacher
  ↓
FORM REQUEST (validation at authorization, kung may FormRequest)
  ↓
CONTROLLER
  ├─ query / filter
  ├─ DB::transaction + lockForUpdate (para sa stock-sensitive operations)
  ├─ Model operations
  ├─ Service (InventoryUnitService / RequestAssignmentService)
  └─ PropertyHistory / InventoryLog audit records
  ↓
DATABASE (MySQL)
  ↓
OUTPUT
  ├─ Blade view (HTML)
  ├─ redirect + flash message (`success`, `error`, `status`)
  ├─ JSON response para sa AJAX
  ├─ PDF download
  ├─ CSV stream download
  └─ SVG QR download
```

### 2.1 System-wide Responses at Errors

```text
KUNG walang authenticated user at protected route:
    redirect sa /login

KUNG role ng user ay wala sa allowed roles:
    HTTP 403 Unauthorized role

KUNG session/CSRF token expired (419):
    KUNG AJAX/JSON request:
        response JSON { message, csrf_refresh: true } status 419
    KUNG normal browser request:
        redirect sa login na may error message

KUNG AJAX form submission at may validation error:
    HTTP 422 JSON { message: unang error, errors: lahat ng field errors }

KUNG AJAX form submission at success:
    HTTP 200 JSON { message, redirect: target URL }

LAHAT ng GET/HEAD response:
    Cache-Control: no-store, no-cache, must-revalidate, private
    Pragma: no-cache
    Expires: 0
```

---

## 3. Database Structure na Ginagamit ng Features

### 3.1 Pangunahing Tables

| Table | Gamit sa sistema |
|---|---|
| `users` | Login accounts; `role` = admin/staff/teacher; `is_active`; OTP/2FA columns (2FA columns ay hindi ginagamit ng controllers). |
| `admins`, `staff`, `teachers` | Role-specific profile; isang profile bawat user; `syncRoleProfile()` ang nagpapanatili. |
| `profile_photos` | Base64 profile image ng user. |
| `properties` | Pangunahing property/product record; kasalukuyang available quantity; status; QR token; category/location links. |
| `property_units` | Bawat pisikal na item; unique serial number; QR token; status (available/assigned/disposed); link sa assignment. |
| `property_categories`, `locations`, `disposal_methods` | Normalized lookup tables; auto-created gamit ang `resolveId()`/`firstOrCreate`. |
| `property_requests` | Teacher request; new/replacement; status workflow; inventory reservation; result assignment. |
| `assignments` | Accountability record; `teacher_id` ang actual recipient (teacher o staff); quantity, dates, department, location, status. |
| `returns` | Return record ng assignment; returned_by; returned serial numbers; remarks. |
| `disposals` | Disposal request/record; quantity, method, reason, status, admin decision. |
| `property_histories` | Audit trail ng property: created, updated, assigned, returned, assignment_removed, disposed, atbp. |
| `inventories` | Isang row per property; `available_quantity` na ginagamit ng request reservation. |
| `inventory_logs` | Audit ng stock: stock_added, reserved, released, fulfilled. |
| `approvals` | Table at model na existing, ngunit **walang kasalukuyang controller/route** na gumagamit nito. |
| `password_reset_tokens`, `sessions` | Laravel defaults. |

### 3.2 Mahalagang Relationships

```text
users (1) ───< properties.created_by
users (1) ───< assignments.teacher_id        (actual recipient: teacher o staff)
users (1) ───< assignments.assigned_by      (nag-issue)
users (1) ───< disposals.disposed_by
users (1) ───< property_requests.requested_by

admins  (1) ── users
staff   (1) ── users
teachers(1) ── users

properties (1) ───< property_units
properties (1) ───< assignments
properties (1) ───< disposals
properties (1) ───1 inventory
properties (1) ───< property_histories

assignments (1) ───< returns
assignments (1) ───< property_units (current assignment)
assignments (1) ───< disposals
property_requests (1) ───0..1 assignment
property_requests (1) ───0..1 inventory reservation
```

### 3.3 Mahalagang Business Data Rules (aktwal)

- `properties.quantity` = **kasalukuyang available stock**, hindi total na pag-aari.
- Bawat pisikal na item ay may sariling `property_units` row at unique serial number.
- `Property::tracked_quantity` = `quantity + active assigned quantity + disposed quantity`
  (ginagamit sa reports bilang kabuuang bilang).
- `Property::syncInventoryStatus()` ang nag-a-update ng property status:
  - `assigned` kung may active assignment,
  - `disposed` kung `quantity = 0` at may na-dispose,
  - `available` kung wala sa dalawa.
- Ang `for_disposal` status ay existing sa enum at filters, ngunit **walang kasalukuyang workflow** na awtomatikong nagse-set nito.
- Ang `inventories.available_quantity` ay ginagamit ng property request reservation. Ito ay nababago ng request reserve/release, hindi ng assignment/return/disposal (ang `properties.quantity` ang binabago ng mga iyon).

---

## 4. Feature 1 — Registration na may Gmail at OTP Verification

**Routes:** `GET /register`, `POST /register`, `GET /register/verify`, `POST /register/verify`, `POST /register/resend-otp`  
**Controller:** `Auth\RegisteredUserController`  
**Service:** `RegistrationOtpSender`  
**Access:** Guest only

### 4.1 INPUT

```text
Form fields:
  name            : required, string, max 255
  email           : required, email, max 255, unique sa users, DAPAT magtapos sa @gmail.com
  role            : required, isa lamang sa [staff, teacher]   (walang admin self-registration)
  password        : required, min 8, confirmed
  password_confirmation
```

### 4.2 PROCESS — Pagpapadala ng OTP

```text
SIMULA POST /register
  VALIDATE registration fields
  KUNG email ay hindi @gmail.com:
      IBALIK validation error sa email

  Gumawa ng pending registration payload:
      name     = trim(name)
      email    = lowercase(email)
      role     = role
      password = HASH(password)          // hindi plain text sa session

  Gumawa ng 6-digit OTP:
      otpCode    = random 000000–999999 (zero-padded)
      expiresAt  = now + config('services.registration_otp.expire', 10) minuto

  SUBUKAN ipadala ang OTP:
      KUNG may BREVO_API_KEY:
          POST sa Brevo email API gamit ang HTML template
      KUNG wala:
          Laravel Mail::to(email)->send(RegistrationOtpMail)

  KUNG pagpadala ay nabigo:
      report exception
      IBALIK validation error: "We could not send the OTP right now..."

  I-SAVE sa session ang `auth.pending_registration`:
      name, email, role, hashed password,
      otp_hash = HASH(otpCode),
      otp_expires_at = expiresAt

  REDIRECT sa /register/verify
  OUTPUT: status message na may email kung saan ipinadala ang OTP
```

### 4.3 PROCESS — Verification

```text
SIMULA GET /register/verify
  KUNG walang pending registration sa session:
      REDIRECT sa /register na may error
  IPAKITA ang verification form:
      email, masked email (unang 2 characters lang ang kita), expiry minutes

SIMULA POST /register/verify
  KUNG walang pending registration:
      REDIRECT sa /register na may error

  VALIDATE otp: required, exactly 6 digits

  KUNG now > otp_expires_at:
      IBALIK error: "The OTP has expired. Click resend..."
  KUNG HASH.check(input otp, otp_hash) = false:
      IBALIK error: "The OTP you entered is incorrect."

  KUNG email ay existing na sa users:
      BURAHIN ang pending registration session
      REDIRECT sa register na may error at old input

  GUMAWA ng User:
      name, email, role, hashed password, is_active = true
      email_verified_at = now
      SAVE
      user.syncRoleProfile(role)

  BURAHIN ang pending registration session
  REDIRECT sa /login
  OUTPUT: "Account created successfully. Your email has been verified through OTP."
```

### 4.4 Resend OTP

```text
SIMULA POST /register/resend-otp
  KUNG walang pending registration:
      error: session expired, register again

  Gumawa ng bagong OTP at bagong expiry
  Ipadala muli sa email
  I-update ang otp_hash at otp_expires_at sa session
  REDIRECT sa verify page
  OUTPUT: "A new OTP has been sent..."
```

### 4.5 OUTPUT

- Bagong `users` row na `email_verified_at` ay set at may tamang role profile
  (`staff` o `teachers` row).
- Redirect sa login; hindi automatic login pagkatapos ng registration.
- Hindi makakapag-register ng admin account sa public registration.

---

## 5. Feature 2 — Login at Logout

**Routes:** `GET /login`, `POST /login`, `POST /logout`  
**Controller:** `Auth\AuthenticatedSessionController`  
**Access:** Guest (login); Auth (logout)

### 5.1 INPUT

```text
Login:
  email           : required, valid email
  password        : required, string
  privacy_consent : accepted (checkbox)
  remember        : optional boolean
```

### 5.2 PROCESS — Login

```text
SIMULA POST /login
  VALIDATE email, password, privacy_consent

  Hanapin ang user sa users gamit ang email

  KUNG user ay existing PERO is_active = false:
      IBALIK error: "This account has been deactivated..."
      // sinusuri muna ang active status bago ang password

  KUNG user ay wala O password ay mali:
      SUBUKAN Hash.check(plain, user.password)
      KUNG RuntimeException (legacy hash):
          KUNG user.password = plain:
              i-rehash at i-save ang password
          KUNG hindi:
              mali ang credentials
      IBALIK error: "The provided credentials do not match our records."

  Auth.login(user, remember)
  session.regenerate()

  KUNG user ay walang profile photo:
      REDIRECT sa profile.edit
      OUTPUT: "Logged in successfully."
  KUNG hindi:
      target = intended URL O role-based dashboard:
          admin   → /admin/dashboard
          staff   → /staff/dashboard
          teacher → /teacher/dashboard
      REDIRECT sa target
      OUTPUT: "Logged in successfully."
```

### 5.3 PROCESS — Logout

```text
SIMULA POST /logout
  Auth guard logout
  session.invalidate()
  session.regenerateToken()
  REDIRECT sa /login
  OUTPUT: "Logged out successfully."
```

### 5.4 System Response

- Deactivated account: hindi makakalogin kahit tama ang password.
- Walang profile photo: unang dadalhin sa profile edit page.
- May photo: role dashboard o intended URL.
- `GET /dashboard`: nire-redirect din sa tamang role dashboard.

---

## 6. Feature 3 — Profile Photo Management

**Routes:** `GET /profile`, `PUT /profile`, `GET /profile/photo`  
**Controller:** `ProfileController`  
**Access:** Lahat ng authenticated users

### 6.1 INPUT

```text
photo : required, image, mimes: jpg,jpeg,png,webp, max 2048 KB,
        dimensions: max_width=4096, max_height=4096
```

### 6.2 PROCESS

```text
SIMULA GET /profile
  IPAKITA ang profile edit page (kasalukuyang photo kung meron)

SIMULA PUT /profile
  VALIDATE photo

  photo = uploaded file
  contents = base64_encode(file contents)
  mime_type = file mime type

  user.profilePhoto().updateOrCreate(
      [],
      { mime_type, contents }
  )

  REDIRECT sa /dashboard
  OUTPUT: "Profile picture saved."

SIMULA GET /profile/photo
  photo = user.profilePhoto().firstOrFail()
  IBALIK ang decoded image bytes
  Headers:
      Content-Type: photo.mime_type
      Cache-Control: private, no-store
      X-Content-Type-Options: nosniff
```

### 6.3 OUTPUT

- Isang profile photo row per user (unique `user_id`).
- Photo ay naka-store sa database bilang base64, kaya hindi ito nawawala kapag nag-rebuild ang container.
- Kung wala pang photo, 404 sa photo endpoint; ang login ang nag-uudyok na mag-upload.

---

## 7. Feature 4 — User Management (Admin)

**Routes:** `users.index/create/store/edit/update/destroy`, `PATCH /users/{user}/status`  
**Controller:** `UserController`  
**Requests:** `StoreUserRequest`, `UpdateUserRequest`  
**Access:** Admin only

### 7.1 INPUT

```text
Create/Update fields:
  name            : required, max 255
  email           : required, email, unique, DAPAT @gmail.com
  role            : required, admin | staff | teacher
  employee_number : optional, unique sa teachers at staff
  department      : optional (admin/staff profile)
  subject_area    : optional (teacher profile)
  password        : required sa create, optional sa update; min 8, confirmed
  is_active       : optional boolean
```

### 7.2 PROCESS — List at Create

```text
SIMULA GET /users
  users = User.latest().paginate(10)
  IPAKITA ang listahan

SIMULA GET /users/create
  IPAKITA form na may role choices

SIMULA POST /users
  AUTHORIZE: role = admin
  VALIDATE fields
  is_active = input is_active O default true

  userData = lahat ng validated MALIBAN sa:
      employee_number, department, subject_area

  user = User.create(userData)
  user.syncRoleProfile(validated)
      KUNG admin:
          i-create/update admins row (department)
          delete staff/teachers profile kung meron
      KUNG staff:
          i-create/update staff row (employee_number, department)
          delete admins/teachers profile
      KUNG teacher:
          i-create/update teachers row (employee_number, subject_area)
          delete admins/staff profile

  REDIRECT sa users.index
  OUTPUT: "<name> added successfully."
```

### 7.3 PROCESS — Update

```text
SIMULA PUT /users/{user}
  AUTHORIZE: admin
  VALIDATE fields (email unique maliban sa sarili; employee number unique maliban sa sarili)

  is_active = input is_active O default false
  userData = validated maliban sa role-profile fields

  KUNG password ay blank:
      alisin ang password sa userData   // hindi mababago ang lumang password

  user.update(userData)
  user.syncRoleProfile(validated)
  REDIRECT sa users.index
  OUTPUT: "<name> updated successfully."
```

### 7.4 PROCESS — Activate/Deactivate

```text
SIMULA PATCH /users/{user}/status
  KUNG ang target user ay ang kasalukuyang naka-login:
      REDIRECT na may error: "You cannot deactivate your own account..."

  user.is_active = NOT user.is_active
  SAVE
  OUTPUT: "<name> activated/deactivated successfully."
```

### 7.5 PROCESS — Delete User

```text
SIMULA DELETE /users/{user}
  KUNG target = kasalukuyang user:
      error: "You cannot delete your own account..."

  DB TRANSACTION:
      activeAssignments = user.teacherAssignments()
          WHERE status = active
          GET id, property_id, quantity_assigned

      PARA SA BAWAT assignment:
          property = Property.lockForUpdate().find(property_id)
          KUNG property ay existing:
              property.quantity += assignment.quantity_assigned
              gumawa ng PropertyHistory:
                  action_type = assignment_removed
                  reference   = user:<id>
                  remarks     = active assignment cleared because user deleted

      user.teacherAssignments().delete()
      user.delete()

      PARA SA BAWAT affected property:
          property.syncInventoryStatus()

  REDIRECT sa users.index
  OUTPUT: "<name> deleted successfully."
```

### 7.6 Database Effects at Tandaan

- `users` row ang bumababa; role profile (`admins`/`staff`/`teachers`) ay cascade-deleted.
- Ang active assignments ng user ay binubura; ang property quantity ay ibinabalik.
- Ayon sa foreign key, ang `property_units.assignment_id` ng mga affected units ay nagiging `NULL`
  (`nullOnDelete`). Ang controller ay hindi hiwalay na nagre-reset ng unit status.
- Ayon sa migrations, ang `assignments.teacher_id` at `property_requests.requested_by` ay
  `cascadeOnDelete`; ang `disposals.disposed_by/processed_by` at request reviewer/processor
  fields ay `nullOnDelete`. Kaya ang pag-delete ng user ay may epekto rin sa mga record na ito,
  kahit hindi ito hiwalay na pinoproseso ng controller.

---

## 8. Feature 5 — Role-Based Dashboards at Navigation

**Routes:** `/admin/dashboard`, `/staff/dashboard`, `/teacher/dashboard`, `/dashboard`  
**Controllers:** `DashboardController`, `Teacher\TeacherPropertyController`

### 8.1 Admin at Staff Dashboard

```text
INPUT: authenticated admin o staff
PROCESS:
  stats = {
      properties         : COUNT(properties),
      activeAssignments  : COUNT(assignments WHERE status = active),
      teachers           : COUNT(users WHERE role = teacher),
      staffUsers         : COUNT(users WHERE role IN [admin, staff]),
      disposed           : COUNT(disposals WHERE status IN [approved, completed])
  }

  recentProperties = properties ORDER BY updated_at DESC LIMIT 5

  roleActions:
      ADMIN:
          Approve Property Requests → property-requests.index
          Manage Users              → users.index
          Manage Property           → properties.index
          View Assign Property      → assignments.index
          View Disposal Request     → disposals.index
      STAFF:
          Confirm Teacher Requests  → property-requests.index
          View Property List        → properties.index
          Assign Property           → assignments.create
          Request Disposal          → disposals.create

OUTPUT:
  dashboard/index view na may stats, recent properties, role action cards,
  sidebar, navbar, at request notifications
```

### 8.2 Teacher Dashboard

```text
INPUT: authenticated teacher
PROCESS:
  assignedPropertiesCount = COUNT(properties WHERE may active assignment ng teacher)

  recentAssignments = assignments
      WHERE teacher_id = teacher.id AND status = active
      ORDER BY date_assigned DESC LIMIT 5

  propertyRequestCount = COUNT(property_requests WHERE requested_by = teacher.id)

  pendingPropertyRequestCount = COUNT(property_requests
      WHERE requested_by = teacher.id
      AND status IN [pending, awaiting_admin, awaiting_stock, approved])

  recentPropertyRequests = latest 5 requests ng teacher

OUTPUT:
  teacher dashboard na may counts, recent assignments, recent requests
```

### 8.3 Role Redirect

```text
SIMULA GET /dashboard
  KUNG may user:
      admin   → redirect admin.dashboard
      staff   → redirect staff.dashboard
      teacher → redirect teacher.dashboard
  KUNG wala:
      login
```

---

## 9. Feature 6 — Property Management (CRUD, Search, Filters)

**Routes:** `properties.index/create/store/show/edit/update/destroy`  
**Controller:** `PropertyController`  
**Requests:** `StorePropertyRequest`, `UpdatePropertyRequest`  
**Access:** Admin at Staff

### 9.1 INPUT — Property Form

```text
property_name    : required, max 255
property_code    : required, max 100, unique (manual check sa store)
serial_number    : optional, unique; kung blank ay auto-generate
category         : optional (lookup/firstOrCreate sa property_categories)
description      : optional text
brand            : optional
model            : optional
quantity         : required integer, min 1 (store) / min 0 (update), max 1000
unit             : required, isa sa measurement units (piece, set, box, atbp.)
unit_cost        : optional numeric >= 0
date_acquired    : optional date
condition_status : required: new | good | fair | poor | damaged
status           : required: available | assigned | for_disposal | disposed
office           : optional
department       : optional
location         : optional (lookup/firstOrCreate sa locations)
qr_token         : optional unique
qr_code_path     : optional
units            : optional array ng unit rows (id, serial_number)
```

### 9.2 PROCESS — List at Search

```text
SIMULA GET /properties
  search          = trim(query.search)
  category        = trim(query.category)
  status          = trim(query.status)
  conditionStatus = trim(query.condition_status)
  location        = trim(query.location)

  query properties WITH units
      WITH SUM(active assignments.quantity_assigned) AS active_quantity_assigned
      WITH SUM(finalized disposals.quantity_disposed) AS completed_quantity_disposed

      KUNG search ay may laman:
          WHERE property_name LIKE %search%
             OR property_code LIKE %search%
             OR serial_number LIKE %search%
             OR may unit na serial_number LIKE %search%
             OR brand LIKE %search%
             OR model LIKE %search%
             OR office LIKE %search%
             OR description LIKE %search%
             OR location LIKE %search%

      KUNG category/status/condition/location ay may laman:
          i-filter ayon sa field

      ORDER BY latest
      PAGINATE 10, with query string

  categories = distinct property_categories names
OUTPUT:
  properties/index view na may filters, pagination, at status/condition choices
```

### 9.3 PROCESS — Create Property

```text
SIMULA POST /properties
  AUTHORIZE: authenticated (route restricts sa admin/staff)
  VALIDATE StorePropertyRequest
      + manual duplicate check sa property_code
      + duplicate check sa qr_token kung may input

  PREPARE attributes:
      category, brand, model, office, department, location → trim o null
      serial_number:
          kung may input → gamitin
          kung wala → Property.generateSerialNumber()
      category → PropertyCategory.resolveId(category)
      location → Location.resolveId(location)
      staff_id:
          kung ang user ay may staff profile → staff profile id
          kung wala → null (o existing property staff_id sa update)
      created_by = current user id
      qr_token = input qr_token O bagong UUID
      qr_reference = input qr_reference O qr_token

  DB TRANSACTION:
      property = Property.create(attributes)

      InventoryUnitService.sync(property, units)
          // gumagawa/nag-a-update ng property_units at serial numbers

      KUNG property.inventory ay existing:
          InventoryLog.create:
              inventory_id = inventory.id
              property_id  = property.id
              user_id      = current user
              action       = stock_added
              quantity_change = property.quantity
              available_after = inventory.available_quantity
              notes        = "Property added to inventory."

      PropertyHistory.create:
          action_type = created
          reference   = property:<id>
          remarks     = "Property record created."

OUTPUT:
  REDIRECT sa properties.show
  "Property added successfully."
```

**Side effect:** Kapag na-create ang property na `quantity > 0` at status ay hindi
`disposed`/`for_disposal`, ang `Property::booted()` ay automatic na gumagawa ng
`inventories` row na may `available_quantity = quantity`.

### 9.4 PROCESS — Show, Edit, Update

```text
SIMULA GET /properties/{property}
  load active assigned sum at finalized disposed sum
  IPAKITA ang property details at units

SIMULA GET /properties/{property}/edit
  IPAKITA ang edit form na may kasalukuyang category (kasama ang custom category),
  statuses, condition statuses

SIMULA PUT /properties/{property}
  VALIDATE UpdatePropertyRequest:
      property_code unique maliban sa sarili
      serial_number unique maliban sa sarili
      qr_token unique maliban sa sarili
      quantity min 0

  PREPARE attributes (pareho sa create)
      serial_number:
          input O existing property serial O generate
      qr_token:
          input O existing property qr_token O bagong UUID
      qr_reference:
          existing qr_reference O qr_token

  DB TRANSACTION:
      property = Property.lockForUpdate().findOrFail(id)
      property.update(attributes)

      InventoryUnitService.sync(property, units)
      property.syncInventoryStatus()

      PropertyHistory.create:
          action_type = updated
          reference   = property:<id>
          remarks     = "Property record updated."

OUTPUT:
  REDIRECT sa properties.show
  "Property updated successfully."
```

### 9.5 PROCESS — Delete

```text
SIMULA DELETE /properties/{property}
  property.delete()

OUTPUT:
  REDIRECT sa properties.index
  "Property deleted successfully."
```

**DB effect:** Ayon sa foreign keys, ang property units, assignments, disposals,
at inventory ay cascade-deleted. Walang hiwalay na guard sa controller para sa
active assignments bago i-delete.

### 9.6 Validation Summary

| Field | Rule |
|---|---|
| `property_name` | required, string, max 255 |
| `property_code` | required, max 100, unique |
| `serial_number` | nullable, max 255, unique |
| `quantity` | store min 1; update min 0; max 1000 |
| `unit` | required, allowed measurement unit |
| `condition_status` | required, allowed condition |
| `status` | required, allowed property status |
| `units.*.serial_number` | nullable, max 255; case-insensitive uniqueness sa service |

---

## 10. Feature 7 — Physical Units, Serial Numbers, at Inventory Sync

**Service:** `InventoryUnitService`  
**Ginagamit ng:** Property create/update, Assignment store/update, Request fulfillment

### 10.1 `sync(property, units)` — Pagbuo at Pag-edit ng Units

**INPUT:** property record at optional array ng unit rows (`id`, `serial_number`)

```text
SIMULA sync
  availableUnits = property.availableUnits().lockForUpdate().get()
  quantity = property.quantity

  KUNG units ay ibinigay PERO bilang ng rows != quantity:
      IBALIK validation error:
          "Provide one serial number field for each available unit."

  KUNG units ay walang ibinigay:
      gumawa ng rows para sa bawat available slot:
          id            = existing available unit id kung meron
          serial_number = existing unit serial O property serial (first unit)
                          O auto-generate

  seenSerials = []
  retainedIds = []

  PARA SA BAWAT row (index, row):
      unit = existing available unit kung may valid id
      KUNG may id PERO unit ay wala o naulit:
          error: "This unit is no longer available. Reload..."

      serial = trim(row.serial_number)
      KUNG blank:
          serial = unit.serial_number O generateSerialNumber()

      key = lowercase(serial)

      KUNG key ay naulit sa loob ng request:
          error: "Each unit must have a different serial number..."
      KUNG may ibang PropertyUnit na may parehong serial (case-insensitive):
          error: parehong mensahe
      KUNG may ibang Property na may parehong serial (case-insensitive):
          error: parehong mensahe

      KUNG unit ay existing:
          unit.update(serial_number = serial)
      KUNG wala:
          unit = property.units().create(serial, status = available)

      idagdag ang unit.id sa retainedIds

  BURAHIN ang available units na wala sa retainedIds
  property.serial_number = unang unit serial (kung meron)

OUTPUT:
  updated/created/deleted property_units
  property.serial_number alignment
```

### 10.2 `assign(property, assignment, quantity, unitIds)` — Pagtatalaga ng Units

```text
SIMULA assign
  KUNG unitIds = null:
      // compatibility sa lumang records na walang per-unit tracking
      kulang = max(0, property.quantity - COUNT(available units))
      PARA SA bawat kulang:
          gumawa ng bagong available unit na may serial:
              property serial kung first at unique
              O auto-generate

  KUNG unitIds ay may laman:
      KUNG COUNT(unitIds) != quantity O may duplicate:
          error: "Select exactly <quantity> available unit(s)."

  units = property.availableUnits()
      WHERE assignment_id IS NULL
      KUNG unitIds ay may laman:
          WHERE id IN unitIds
      lockForUpdate()
      LIMIT quantity
      GET

  KUNG COUNT(units) != quantity:
      error: "One or more selected units are no longer available..."

  I-UPDATE ang mga units:
      assignment_id = assignment.id
      status        = assigned
      updated_at    = now

OUTPUT:
  property_units na naka-link sa assignment
```

### 10.3 Tandaan

- Ang unit status ay `available`, `assigned`, o `disposed` lamang.
- Kapag na-return o na-release, bumabalik sa `available` at `assignment_id = NULL`.
- Kapag na-dispose, nagiging `disposed` at `assignment_id = NULL`.

---

## 11. Feature 8 — QR Code Generation, Printing, at Scanning

**Routes:**
- `/qr-codes` (index)
- `/properties/{property}/qr`, `/qr/print`, `/qr/download`
- `/property-units/{propertyUnit}/qr`, `/qr/print`, `/qr/download`
- `GET /qr/{token}` (public scan)

**Controller:** `QrCodeController`  
**Access:** Admin/Staff sa management pages; public sa scan

### 11.1 INPUT

```text
property id o property_unit id
token mula sa na-scan na QR
print/download request
```

### 11.2 PROCESS — Property QR

```text
SIMULA GET /properties/{property}/qr
  KUNG property.qr_token ay wala:
      property.qr_token = UUID
      SAVE

  load assignments.teacher
  activeAssignment = latest active assignment

  scanUrl = route('qr.scan', property.qr_token)
  qrPayload = scanUrl
  qrSvg = QrCode.format(svg).size(260).generate(qrPayload)

OUTPUT:
  qr/show view: SVG, scan URL, payload, active assignment

SIMULA GET /properties/{property}/qr/download
  parehong token/payload
  qrSvg = size 320
  RESPONSE:
      Content-Type: image/svg+xml
      Content-Disposition: attachment; filename="property-<code>-qr.svg"

SIMULA GET /properties/{property}/qr/print
  gumawa ng SVG size 220
  IPAKITA ang print-sticker view
  // Ang aktwal na pag-print ay ginagawa ng browser print dialog
```

### 11.3 PROCESS — Unit QR

```text
SIMULA GET /property-units/{unit}/qr
  KUNG unit.qr_token ay wala:
      unit.qr_token = PropertyUnit.generateQrToken()   // UUID, unique
      SAVE

  load property at assignment.assignee
  scanUrl = route('qr.scan', unit.qr_token)
  qrPayload = scanUrl
  gumawa ng SVG size 260
OUTPUT:
  qr/show-unit view

Download at print:
  parehong pattern; file name na nakabase sa serial number
```

### 11.4 PROCESS — Scan (Public)

```text
SIMULA GET /qr/{token}
  unit = PropertyUnit WHERE qr_token = token (with property at assignment)
  KUNG unit ay existing:
      OUTPUT: qr/scan-unit view na may unit, property, active assignment

  property = Property WHERE qr_token = token (with assignments.teacher)
  KUNG wala:
      HTTP 404

  activeAssignment = latest active assignment ng property
  OUTPUT: qr/scan view
```

### 11.5 Integration

- Ang QR payload ay ang **public scan URL**, hindi raw serial.
- Property QR at unit QR ay parehong dumadaan sa iisang scan route.
- Unit token ang unang sinusuri, kaya mas tiyak ang unit-level lookup.
- Ang scan page ay nagpapakita ng property details at kasalukuyang assignment
  (kung meron) para sa verification ng supply office.

---

## 12. Feature 9 — Property Request (Teacher Submission)

**Routes:**
- `GET /teacher/property-requests`
- `GET /teacher/property-requests/create`
- `POST /teacher/property-requests`

**Controller:** `PropertyRequestController`  
**Request:** `StorePropertyRequestSubmissionRequest`  
**Access:** Teacher only

### 12.1 INPUT

```text
request_type             : new (default) | replacement
request_source           : inventory | unavailable
available_property_id    : required kung source = inventory
requested_item_name      : required kung source != inventory
requested_quantity       : required integer min 1
needed_by                : optional date
purpose                  : required string
additional_notes         : optional
replacement_assignment_id: required kung type = replacement
issue_description        : required kung type = replacement
unit_ids / select_units  : hindi ginagamit sa submission
```

### 12.2 PROCESS — Create Form

```text
SIMULA GET /teacher/property-requests/create
  availableProperties = inventories WHERE available_quantity > 0
      AND property status NOT IN [disposed, for_disposal]
      WITH property
      idagdag ang inventory_available_quantity sa bawat property

  assignedProperties = active assignments ng teacher (with property)
  availableCategories = distinct categories ng availableProperties

OUTPUT:
  teacher/property-requests/create view
```

### 12.3 PROCESS — Submit Request

```text
SIMULA POST /teacher/property-requests
  AUTHORIZE: role = teacher
  VALIDATE request fields

  type = input request_type O "new"
  replacementAssignment = null
  availableProperty = null
  inventoryId = null

  KUNG type = replacement:
      replacementAssignment = activeAssignments(teacher).firstWhere(id)
      KUNG wala:
          error: "Select an active property assigned to you."
      KUNG requested_quantity > replacementAssignment.quantity_assigned:
          error: "Replacement quantity cannot exceed the quantity assigned to you."

  KUNG type != replacement AT request_source = inventory:
      inventory = inventories
          WHERE property_id = available_property_id
          AND available_quantity > 0
          WITH property
          first

      availableProperty = inventory.property
      inventoryId = inventory.id

      KUNG wala:
          error: "Select an available inventory item..."
      KUNG requested_quantity > inventory.available_quantity:
          error: "Requested quantity exceeds the available inventory quantity."

  requestedItemName =
      replacementAssignment.property.property_name
      O availableProperty.property_name
      O trim(input requested_item_name)

  DUPLICATE CHECK:
      may existing request ng teacher
      WHERE status IN [pending, awaiting_admin, awaiting_stock, approved]
      AND LOWER(requested_item_name) = LOWER(requestedItemName)
      KUNG meron:
          error: "You already have an active request for this item..."

  DB TRANSACTION:
      KUNG may inventoryId:
          inventory = Inventory.lockForUpdate().findOrFail(inventoryId)
          KUNG available_quantity < requested_quantity:
              error
          inventory.available_quantity -= requested_quantity
          // reserved na agad ang stock

      request = PropertyRequestRecord.create:
          requested_by            = teacher.id
          request_type            = type
          requested_item_name     = requestedItemName
          requested_quantity      = input
          needed_by               = input date
          purpose                 = trim(purpose)
          additional_notes        = trim o null
          issue_description       = kung replacement, trim o null
          replacement_assignment_id = replacementAssignment?.id
          inventory_id            = inventoryId
          status                  = pending

      KUNG may inventory:
          inventory.refresh()
          InventoryLog.create:
              inventory_id       = inventory.id
              property_id        = inventory.property_id
              property_request_id= request.id
              user_id            = teacher.id
              action             = reserved
              quantity_change    = -requested_quantity
              available_after    = inventory.available_quantity
              notes              = "Stock reserved for property request #<id>."

OUTPUT:
  REDIRECT sa teacher.property-requests.index
  "Property request submitted successfully."
```

### 12.4 Teacher Request List

```text
SIMULA GET /teacher/property-requests
  requests = property_requests
      WHERE requested_by = teacher.id
      WITH processedBy, reviewedBy
      ORDER BY latest
      PAGINATE 10

OUTPUT:
  listahan ng sariling requests at statuses
```

### 12.5 Tandaan

- Kapag `replacement`, hindi nagre-reserve ng inventory stock; ang replacement
  assignment ang reference at ang admin/staff ang maghahanap ng kapalit.
- Kapag `inventory`, agad na nababawasan ang `inventories.available_quantity`
  kahit `pending` pa lamang ang request.
- Kapag `unavailable`, wala pang stock reservation; maaaring `awaiting_stock`
  ang maging desisyon ng admin.

---

## 13. Feature 10 — Property Request Review, Approval, at Fulfillment

**Routes:**
- `GET /property-requests`
- `GET /property-requests/{request}`
- `PATCH /property-requests/{request}/status`
- `GET /property-requests/{request}/receipt`

**Controller:** `PropertyRequestController`  
**Request:** `UpdatePropertyRequestStatusRequest`  
**Service:** `RequestAssignmentService`  
**Access:** Admin at Staff

### 13.1 Status Workflow

```text
pending
  │  staff reviews/forwards
  ▼
awaiting_admin
  ├─ admin approves ─────────────► approved
  ├─ admin rejects ──────────────► rejected
  └─ admin marks awaiting stock ─► awaiting_stock
                                        │
                                        └─ admin approves ► approved

approved
  │  staff fulfills at nag-assign ng property
  ▼
fulfilled
```

| Status | Meaning |
|---|---|
| `pending` | Kakasubmit pa lang ng teacher; para sa staff review. |
| `awaiting_admin` | Na-forward na ng staff; hinihintay ang admin. |
| `awaiting_stock` | Kulang ang stock; hinihintay na magkaroon. |
| `approved` | Aprubado ng admin; hinihintay ang staff assignment. |
| `rejected` | Tinanggihan ng admin. |
| `fulfilled` | Na-assign na; may receipt na. |

### 13.2 PROCESS — Queue at Detail

```text
SIMULA GET /property-requests
  KUNG user = admin:
      requests = property_requests WHERE reviewed_at IS NOT NULL
  KUNG user = staff:
      requests = lahat ng property_requests

  KUNG may valid status filter:
      WHERE status = filter

  WITH requester, processedBy, selectedProperty,
       replacementAssignment.property, inventory.property
  ORDER BY latest, PAGINATE 10

OUTPUT:
  shared property-requests/index view

SIMULA GET /property-requests/{request}
  KUNG admin AT request.reviewed_at = null:
      HTTP 403
  load requester, processedBy, reviewedBy, assignment.property,
       selectedProperty, replacementAssignment.property, inventory.property

  properties = properties
      WHERE quantity >= max(1, requested_quantity)
      KUNG may inventory_id:
          WHERE id = inventory.property_id
      KUNG staff AT status = approved:
          WHERE id = selected_property_id
      WHERE status NOT IN [disposed, for_disposal]
      WITH availableUnits
      ORDER BY property_name

OUTPUT:
  detail view na may status action form at property choices
```

### 13.3 PROCESS — Staff Review (Forward sa Admin)

```text
SIMULA PATCH /property-requests/{request}/status (staff, status = awaiting_admin)
  DB TRANSACTION:
      record = property_requests.lockForUpdate().findOrFail(id)

      KUNG record.status != pending:
          error: "This request has already been forwarded or processed."

      record.update:
          status       = awaiting_admin
          reviewed_by  = staff.id
          reviewed_at  = now
          review_notes = trim(response_notes) O null

OUTPUT:
  "Confirmation saved successfully. Request forwarded to the admin for approval."
```

### 13.4 PROCESS — Admin Decision

```text
SIMULA PATCH /property-requests/{request}/status (admin)
  VALIDATE status:
      approved | rejected | awaiting_stock
  response_notes required kung rejected o awaiting_stock

  DB TRANSACTION:
      record = property_requests.lockForUpdate().findOrFail(id)

      KUNG record.reviewed_at = null
         O record.assignment_id != null
         O record.status NOT IN [awaiting_admin, awaiting_stock, approved]:
          error: "Only requests forwarded by staff and awaiting a decision..."

      KUNG status = approved:
          propertyId = record.inventory.property_id O input property_id
          property = properties.lockForUpdate().findOrFail(propertyId)

          KUNG property.quantity < record.requested_quantity
             O property.status IN [disposed, for_disposal]:
              error: "Not enough available stock. Keep this request awaiting stock..."

      KUNG status = rejected AT record.inventory_id:
          inventory = inventories.lockForUpdate().find(record.inventory_id)
          KUNG meron:
              inventory.available_quantity += record.requested_quantity
              inventory.refresh()
              InventoryLog.create:
                  action          = released
                  quantity_change = +requested_quantity
                  available_after = inventory.available_quantity
                  notes           = "Stock released after request rejection."

      record.update:
          status               = input status
          selected_property_id = kung approved:
                                     inventory.property_id O input property_id
                                 kung hindi: null
          response_notes       = trim O null
          processed_by         = admin.id
          processed_at         = now

OUTPUT:
  approved        → "Request approved for staff assignment."
  rejected        → "Request status updated."
  awaiting_stock  → "Request status updated."
```

**Tandaan:** Sa approval, hindi pa binabawasan ang `properties.quantity`.
Ang aktwal na pagbawas at unit assignment ay nangyayari sa staff fulfillment.

### 13.5 PROCESS — Staff Fulfillment

```text
SIMULA PATCH /property-requests/{request}/status (staff, status = fulfilled)
  VALIDATE property_id required; unit_ids optional

  DB TRANSACTION:
      record = property_requests.lockForUpdate().findOrFail(id)

      KUNG record.status != approved
         O record.reviewed_at = null
         O record.processed_at = null
         O record.assignment_id != null:
          error: "The admin must approve this request before staff can assign..."

      KUNG record.selected_property_id != input property_id:
          error: "Select the property approved by the admin for this request."

      assignment = RequestAssignmentService.assign(
          record,
          input property_id,
          staff user,
          unit_ids kung select_units o may unit_ids
      )

      assignment.update:
          department = input department O assignment.department

      record.update:
          status               = fulfilled
          assignment_id        = assignment.id
          selected_property_id = assignment.property_id

      KUNG record.inventory_id:
          inventory = inventories.find(record.inventory_id)
          KUNG meron:
              InventoryLog.create:
                  action          = fulfilled
                  quantity_change = 0
                  available_after = inventory.available_quantity
                  notes           = "Property request fulfilled and assigned."

OUTPUT:
  "Property assigned successfully. The receiving receipt is ready to print."
```

### 13.6 `RequestAssignmentService.assign()` — Core ng Fulfillment

```text
INPUT: request record, property_id, issuing user, optional unitIds
PROCESS:
  property = properties.lockForUpdate().findOrFail(property_id)
  quantity = request.requested_quantity

  KUNG quantity < 1
     O property.quantity < quantity
     O property.status IN [disposed, for_disposal]:
      error: "This property does not have enough assignable stock..."

  recipient = request.requester
  KUNG recipient ay wala O inactive O hindi teacher:
      error: "The requester must be an active end-user."

  assignment = Assignment.create:
      property_id          = property.id
      teacher_id           = recipient.id
      teacher_profile_id   = recipient.teacherProfile?.id
      assigned_by          = issuer.id
      staff_id             = issuer.staffProfile?.id
      quantity_assigned    = quantity
      date_assigned        = today
      assigned_at          = today
      location             = property.location
      department           = property.department
      location_id          = property.location_id
      remarks              = "Approved property request #<id>: <item>"
      status               = active

  InventoryUnitService.assign(property, assignment, quantity, unitIds)
  property.quantity -= quantity
  property.refresh().syncInventoryStatus()

  PropertyHistory.create:
      action_type = assigned
      reference   = assignment:<id>
      action_date = today
      remarks     = assignment.remarks

OUTPUT:
  bagong active assignment
```

### 13.7 Receipt

```text
SIMULA GET /property-requests/{request}/receipt
  ALLOW kung admin O staff O ang teacher na may-ari ng request
  KUNG request.assignment_id = null:
      HTTP 404

  assignment = request.assignment()
      WITH property, propertyUnits, assignee, assignedBy

  admins = active admin users
  activeAdmin = kasalukuyang admin O defaultAdmin O unang admin
  approver = request.processedBy O activeAdmin

OUTPUT:
  assignments/print view:
      assignment details, property, units, signatories
      requestNumber = request.id
      approvedBy, verifyingAdmin
      backUrl depende sa role
```

---

## 14. Feature 11 — Assignment / Accountability Management

**Routes:**
- `assignments.index/create/store/show/edit/update`
- `GET /assignments/{assignment}/print`
- `PATCH /assignments/{assignment}/return`

**Controller:** `AssignmentController`  
**Requests:** `StoreAssignmentRequest`, `UpdateAssignmentRequest`, `ReturnAssignmentRequest`  
**Access:** Admin at Staff

### 14.1 INPUT

```text
property_id       : required, existing property
teacher_id        : optional, existing user (teacher)
staff_id          : optional, existing user (staff)
quantity_assigned : required integer min 1
date_assigned     : required date
location          : optional
department        : optional
unit_ids          : optional array ng units (distinct integers)
select_units      : optional boolean
remarks           : optional
serial_numbers    : prohibited (hindi tinatanggap; units ang gamit)
```

### 14.2 PROCESS — Index (Grouped by Assignee)

```text
SIMULA GET /assignments
  type filter = all | teachers | staff
  search = trim(query.search)

  teacherAssignees = users
      WHERE role = teacher
      AND may assignments
      KUNG search:
          name/email LIKE search
          O assignment.department LIKE search
          O assignment.property name/code/serial LIKE search
          O assignment unit serial LIKE search
      WITH teacherProfile, staffProfile, adminProfile
      WITH teacherAssignments (property, propertyUnits, assignedBy)
      ORDER BY name, id
      PAGINATE 8 (page name: teacher_page)

  staffAssignees = parehong query pero role = staff (page name: staff_page)

OUTPUT:
  assignments/index view na may dalawang assignee section at filters
```

### 14.3 PROCESS — Create Form

```text
SIMULA GET /assignments/create
  KUNG may teacher_id query:
      prefill teacher (valid teacher role)
  KUNG wala at may staff_id:
      prefill staff (valid staff role)

  properties = properties
      WITH available units (available, o kasalukuyang assignment units sa edit)
      WHERE quantity > 0
      WHERE status IN [available, assigned]
      i-filter kung may assignable quantity
      idagdag ang assignable_quantity:
          available_quantity + kasalukuyang quantity_assigned (sa edit, same property)

  teachers = active? hindi naka-filter sa is_active; lahat ng teacher role
  staffMembers = lahat ng staff role
      (kapag may prefilled fixed assignee, hindi na ipinapakita ang lists)

OUTPUT:
  assignments/create view
```

### 14.4 PROCESS — Store

```text
SIMULA POST /assignments
  AUTHORIZE: admin O staff
  VALIDATE StoreAssignmentRequest:
      quantity <= property.quantity
      property status != disposed
      exactly isa sa teacher_id/staff_id ang pipiliin
      teacher_id role = teacher
      staff_id role = staff

  DB TRANSACTION:
      property = properties.lockForUpdate().findOrFail(property_id)
      requestedQuantity = input quantity_assigned

      KUNG requestedQuantity > property.quantity:
          error: "Only <quantity> item(s) are available."

      assignment = Assignment.create:
          property_id         = property.id
          teacher_id          = teacher_id O staff_id   // actual recipient
          teacher_profile_id  = teacher profile id kung teacher
          assigned_by         = current user id
          staff_id            = current user's staff profile id
          quantity_assigned   = requestedQuantity
          date_assigned       = input date
          location            = input location
          department          = input department O property.department
          location_id         = Location.resolveId(input location)
          assigned_at         = input date
          remarks             = input remarks
          status              = active

      InventoryUnitService.assign(
          property, assignment, requestedQuantity,
          unit_ids kung select_units O may unit_ids
      )

      property.quantity -= requestedQuantity
      property.refresh()
      property.syncInventoryStatus()

      PropertyHistory.create:
          action_type = assigned
          reference   = assignment:<id>
          action_date = date_assigned O today
          remarks     = input remarks

OUTPUT:
  REDIRECT sa assignments.show
  "Property assigned successfully."
```

### 14.5 PROCESS — Show at Print

```text
SIMULA GET /assignments/{assignment}
  load property.activeAssignments, propertyUnits, assignee, assignedBy
OUTPUT:
  accountability details view

SIMULA GET /assignments/{assignment}/print
  load property, propertyUnits, assignee, assignedBy
  admins = active admins ordered by name

  verifyingAdmin:
      KUNG may verified_by query at valid admin → iyon
      KUNG wala → kasalukuyang admin O defaultAdmin O assignedBy

OUTPUT:
  printable accountability form
  printedAt = now
  admins choices at verifying admin
```

### 14.6 PROCESS — Edit/Update

```text
SIMULA GET /assignments/{assignment}/edit
  KUNG assignment.status != active:
      HTTP 404

  load property, assignee, assignedBy
  selectedTeacherId = kung assignee role teacher
  selectedStaffId   = kung assignee role staff
OUTPUT:
  edit form

SIMULA PUT /assignments/{assignment}
  AUTHORIZE admin/staff
  VALIDATE UpdateAssignmentRequest:
      active lang ang pwedeng i-edit
      quantity <= available + kasalukuyang quantity (kung same property)
      property not disposed
      assignee rules pareho sa store

  DB TRANSACTION:
      assignment = assignments.lockForUpdate().findOrFail(id)

      KUNG status != active:
          error: "Only active assignments can be edited."

      currentProperty = properties.lockForUpdate().findOrFail(assignment.property_id)
      targetProperty  = kung parehong property: currentProperty
                        kung iba: properties.lockForUpdate().findOrFail(new id)

      maxAssignable =
          targetProperty.quantity
          + assignment.quantity_assigned KUNG parehong property

      KUNG requestedQuantity > maxAssignable:
          error: "Only <max> item(s) are available for this update."

      KUNG select_units O may unit_ids:
          release lahat ng lumang units ng assignment
          currentProperty.quantity += lumang quantity
          targetProperty.refresh()
          assign new units sa target property
          targetProperty.quantity -= requestedQuantity

      KUNG hindi at parehong property:
          diff = requestedQuantity - lumang quantity
          KUNG diff > 0:
              assign ng karagdagang units
              targetProperty.quantity -= diff
          KUNG diff < 0:
              release ng |diff| units
              targetProperty.quantity += |diff|

      KUNG hindi at ibang property:
          release lumang units
          currentProperty.quantity += lumang quantity
          assign units sa target property
          targetProperty.quantity -= requestedQuantity

      assignment.update:
          property_id, teacher_id (assignee), teacher_profile_id,
          quantity_assigned, date_assigned, location, department,
          location_id, assigned_at, remarks

      currentProperty.refresh().syncInventoryStatus()
      KUNG lumipat ng property:
          PropertyHistory sa lumang property:
              action_type = assignment_updated
              remarks     = "Assignment moved to another property record."
      targetProperty.refresh().syncInventoryStatus()
      PropertyHistory sa target property:
          action_type = assignment_updated
          remarks     = input remarks O "Assignment details updated."

OUTPUT:
  REDIRECT sa assignments.show
  "Assignment updated successfully."
```

### 14.7 Assignee Rules

- Ang `assignments.teacher_id` ay ang **actual recipient** — maaaring teacher o staff.
- Ang `teacher_profile_id` ay may value lamang kapag teacher ang recipient.
- Ang `assigned_by` ay ang admin/staff na nag-issue.
- Ang `staff_id` ay ang staff profile ng nag-issue (hindi ng recipient), kung staff ang nag-issue.
- Dalawang active assignment sa parehong property ay posible sa magkaibang recipients,
  basta sapat ang quantity at available units.

---

## 15. Feature 12 — Return Management

**Routes:** `PATCH /assignments/{assignment}/return`, `GET /returns`  
**Controllers:** `AssignmentController@markReturned`, `ReturnController@index`  
**Request:** `ReturnAssignmentRequest`  
**Access:** Admin at Staff

### 15.1 INPUT

```text
returned_at : required date
```

### 15.2 PROCESS — Record Return

```text
SIMULA PATCH /assignments/{assignment}/return
  AUTHORIZE admin/staff
  VALIDATE returned_at

  DB TRANSACTION:
      assignment = assignments.lockForUpdate().findOrFail(id)

      KUNG assignment.status != active:
          error: "Only active assignments can be returned."

      assignment.update:
          returned_at = input date
          status      = returned

      returnedSerialNumbers = lahat ng serial ng propertyUnits ng assignment
          na pinagsama ng ", "

      ReturnRecord.create:
          assignment_id           = assignment.id
          returned_by             = current user id
          return_date             = input date O today
          status                  = returned
          returned_serial_numbers = serials O null
          remarks                 = assignment.remarks

      releasePropertyUnits(assignment)
          // lahat ng assigned units ay ibinabalik sa available,
          // assignment_id = null

      property.quantity += assignment.quantity_assigned
      property.refresh().syncInventoryStatus()

      PropertyHistory.create:
          action_type = returned
          reference   = assignment:<id>
          action_date = return date
          remarks     = "Property return recorded."

OUTPUT:
  REDIRECT sa assignments.show
  "Property return recorded successfully."
```

### 15.3 PROCESS — Returns List

```text
SIMULA GET /returns
  filters:
      search       : property name/code/serial/office/location,
                     unit serial, assignee name/email, returned_by name/email
      assignee_id  : teacher_id ng assignment
      returned_by  : user id
      status       : exact status
      date_from    : return_date >=
      date_to      : return_date <=

  returns = ReturnRecord
      WITH assignment.property, assignment.assignee,
           assignment.propertyUnits, returnedBy
      i-apply ang filters
      ORDER BY return_date DESC, id DESC
      PAGINATE 12

  assignees        = users role teacher/staff
  returnRecorders  = users role admin/staff
  statuses         = distinct return statuses

OUTPUT:
  returns/index view na may filters at listahan
```

### 15.4 Integration

- Ang return ay naka-link sa assignment at property.
- Ang `returned_serial_numbers` ay snapshot ng mga unit na naibalik.
- Ang property quantity at unit status ay sabay na na-u-update.
- Ang returns list ay read-only; walang edit/delete route para sa return record.

---

## 16. Feature 13 — Disposal Management

**Routes:**
- `disposals.index/create/store/show`
- `PATCH /disposals/{disposal}/status` (admin)

**Controller:** `DisposalController`  
**Requests:** `StoreDisposalRequest`, `UpdateDisposalStatusRequest`  
**Access:** Staff/Admin (create at list); Admin (decision)

### 16.1 INPUT

```text
property_id      : required existing property
assignment_id    : optional existing assignment
quantity_disposed: required integer min 1
disposal_date    : required date
disposal_reason  : required string
disposal_method  : required: auction | donation | recycling | transfer | condemnation | destruction
remarks          : optional

Admin decision:
  status         : approved | cancelled
  response_notes : optional
```

### 16.2 PROCESS — List

```text
SIMULA GET /disposals
  KUNG user = staff:
      WHERE disposed_by = staff.id     // sariling request lang
  KUNG admin:
      lahat ng disposals

  filters:
      search     : property name/code/serial/office/location,
                   disposedBy/processedBy name/email,
                   disposal_reason, remarks
      status     : exact
      method     : exact
      date_from  : disposal_date >=
      date_to    : disposal_date <=

  WITH property, disposedBy, processedBy
  ORDER BY disposal_date DESC, id DESC
  PAGINATE 10

OUTPUT:
  disposals/index view
  isAdmin, isStaff flags
```

### 16.3 PROCESS — Create Form

```text
SIMULA GET /disposals/create
  KUNG may assignment query:
      assignment = assignments WITH property, assignee findOrFail

  properties = properties WHERE status != disposed
      WITH available_quantity
      i-filter kung available_quantity > 0 O property ng assignment
      idagdag ang disposal_limit:
          kung property ng assignment → assignment.quantity_assigned
          kung hindi → property.available_quantity

OUTPUT:
  disposals/create view na may property at assignment choices
```

### 16.4 PROCESS — Store

```text
SIMULA POST /disposals
  AUTHORIZE admin/staff
  VALIDATE StoreDisposalRequest:
      property exists
      assignment exists kung may input
      quantity >= 1
      date required
      reason required
      method allowed
      remarks optional

  withValidator:
      KUNG may assignment:
          assignment.property_id = property.id
          assignment.status = active
          quantity_disposed <= assignment.quantity_assigned
      KUNG wala:
          quantity_disposed <= property.quantity
      property.status != disposed

  isAdmin = current user role admin

  DB TRANSACTION:
      assignment = kung may assignment_id:
                       assignments.lockForUpdate().findOrFail(id)
                   kung wala: null

      property = properties.lockForUpdate().findOrFail(property_id)

      KUNG assignment AT assignment.property_id != property.id:
          error: "The selected property does not match the assignment."
      KUNG assignment AT assignment.status != active:
          error: "Only active assignments can be moved to disposal."

      ensureDisposalAvailability(property, assignment, quantityDisposed)

      disposal = Disposal.create:
          property_id         = property.id
          assignment_id       = assignment?.id
          disposed_by         = current user id
          admin_id            = kung admin: user.adminProfile?.id
          processed_by        = kung admin: user.id
          quantity_disposed   = input
          disposal_date       = input date
          disposal_reason     = input
          disposal_method     = input
          disposal_method_id  = DisposalMethod.resolveId(input)
          remarks             = input remarks
          response_notes      = null
          processed_at        = kung admin: now
          status              = kung admin: completed
                               kung staff: pending

      KUNG admin:
          finalizeDisposal(disposal, property, assignment)
          // deretso ang stock effect; walang pending stage

OUTPUT:
  REDIRECT sa disposals.show
  admin   → "Disposal recorded successfully."
  staff   → "Disposal request submitted for admin review."
```

### 16.5 PROCESS — Show

```text
SIMULA GET /disposals/{disposal}
  KUNG user = staff AT disposal.disposed_by != user.id:
      HTTP 403

  load property, assignment, disposedBy, processedBy

  canReviewDisposal =
      user = admin AT disposal.status = pending

OUTPUT:
  disposals/show view na may review form kung admin at pending
```

### 16.6 PROCESS — Admin Approve/Cancel

```text
SIMULA PATCH /disposals/{disposal}/status
  AUTHORIZE: admin only
  VALIDATE status = approved | cancelled; response_notes optional

  DB TRANSACTION:
      disposal = disposals.lockForUpdate().findOrFail(id)

      KUNG disposal.status != pending:
          error: "Only pending disposal requests can be reviewed."

      property = properties.lockForUpdate().findOrFail(disposal.property_id)
      assignment = kung may assignment_id:
                       assignments.lockForUpdate().find(id)
                   kung wala: null

      reviewPayload:
          admin_id       = admin.adminProfile?.id
          processed_by   = admin.id
          processed_at   = now
          response_notes = trim(input) O null

      KUNG status = approved:
          ensureDisposalAvailability(property, assignment, disposal.quantity_disposed)
          disposal.forceFill(reviewPayload + status = approved).save()
          finalizeDisposal(disposal, property, assignment)
          // status remains approved; stock effect finalized

      KUNG status = cancelled:
          disposal.forceFill(reviewPayload + status = cancelled).save()
          // walang stock change

OUTPUT:
  approved → "Disposal request approved successfully."
  cancelled → "Disposal request disapproved successfully."
```

### 16.7 Availability Validation (`ensureDisposalAvailability`)

```text
KUNG may assignment:
    assignment.property_id DAPAT = property.id
    assignment.status DAPAT = active
    quantityDisposed DAPAT <= assignment.quantity_assigned
KUNG wala:
    property.status DAPAT != disposed
    quantityDisposed DAPAT <= property.quantity
```

### 16.8 Finalization (`finalizeDisposal`)

```text
KUNG may assignment:
    disposeAssignedPropertyUnits(assignment, quantityDisposed)
        // kunin ang assigned units (limit quantity), gawing disposed,
        // assignment_id = null

    remaining = max(0, assignment.quantity_assigned - quantityDisposed)

    idagdag sa remarks:
        "Disposed <n> item(s) on <date>."

    assignment.update:
        quantity_assigned = remaining
        remarks           = updated remarks
        status            = kung remaining > 0: active
                            kung 0: disposed
KUNG wala:
    ensureAvailablePropertyUnits(property)
        // kung mas kaunti ang available units kaysa property.quantity,
        // gumawa ng missing units na may auto-generated serial

    disposeAvailablePropertyUnits(property, quantityDisposed)
        // kunin ang available units (limit quantity), gawing disposed

    property.quantity -= quantityDisposed

property.refresh()
property.syncInventoryStatus()

PropertyHistory.create:
    action_type = disposed
    reference   = disposal:<id>
    action_date = disposal_date O today
    remarks     = response_notes O disposal remarks/reason
```

### 16.9 Status at Roles

| Status | Paano nangyayari |
|---|---|
| `pending` | Staff submission; hindi pa final. |
| `completed` | Admin direct submission (`store`); final agad. |
| `approved` | Admin approval ng pending staff request; final stock effect. |
| `cancelled` | Admin disapproval; walang stock effect. |

- Staff ay hindi pwedeng mag-approve/cancel; admin lang ang may route.
- Staff ay makakakita lamang ng sariling disposal records.

---

## 17. Feature 14 — Inventory Logs

**Route:** `GET /inventory-history`  
**Controller:** `InventoryLogController`  
**Access:** Admin at Staff

### 17.1 INPUT

```text
search : property name o property code
action : stock_added | reserved | released | fulfilled
page   : pagination
```

### 17.2 PROCESS

```text
SIMULA GET /inventory-history
  logs = inventory_logs
      WITH property, propertyRequest.requester, user
      KUNG search:
          WHERE property.name LIKE search O property.code LIKE search
      KUNG action ay valid:
          WHERE action = action
      ORDER BY latest
      PAGINATE 15

OUTPUT:
  inventory-logs/index view na may action filter
```

### 17.3 Kahulugan ng Actions

| Action | Kailan naitala | Quantity change |
|---|---|---|
| `stock_added` | Pagka-create ng property; may inventory row | +quantity |
| `reserved` | Teacher submits inventory-based request | −requested quantity |
| `released` | Admin rejects inventory-based request | +requested quantity |
| `fulfilled` | Staff fulfills approved request | 0 (reservation consumed) |

---

## 18. Feature 15 — Reports (Web, PDF, CSV)

**Routes:** `/reports`, `/reports/properties|assignments|disposals`, at `/pdf`/`/csv` variants  
**Controller:** `ReportController`  
**Access:** Admin at Staff

### 18.1 INPUT

```text
report type : properties | assignments | disposals
format      : HTML | PDF | CSV
```

### 18.2 PROCESS — Report Index

```text
SIMULA GET /reports
  propertyCount   = COUNT(properties)
  assignmentCount = COUNT(assignments)
  disposalCount   = COUNT(disposals)
OUTPUT:
  reports/index view na may report cards
```

### 18.3 PROCESS — Property Report

```text
query properties:
    WITH units
    WITH SUM(active assignments.quantity_assigned) AS active_quantity_assigned
    ORDER BY latest

HTML:
    IPAKITA ang reports/properties view
    kasama ang activeAdmin signatory

PDF:
    DomPDF.loadView('reports.pdf.properties')
        title = "Property List Report"
        properties, activeAdmin
    setPaper(A4, landscape)
    DOWNLOAD "property-list-report.pdf"

CSV:
    STREAM DOWNLOAD "property-list-report.csv"
    Header:
        Property Code, Serial Number, Property Name, Category, Brand, Model,
        Quantity, Available Quantity, Assigned Quantity, Unit, Office, Location,
        Condition, Status
    PARA SA BAWAT property:
        isulat ang row
```

### 18.4 PROCESS — Assignment Report

```text
query assignments:
    WITH property.activeAssignments, propertyUnits, teacher, assignedBy
    ORDER BY date_assigned DESC

HTML / PDF:
    title = "Assigned Items Report"
    PDF paper A4 landscape
    DOWNLOAD "assigned-items-report.pdf"

CSV:
    Header:
        Property Code, Serial Number, Property Name, Teacher,
        Quantity Assigned, Date Assigned, Assigned By, Status
```

### 18.5 PROCESS — Disposal Report

```text
query disposals:
    WITH property, disposedBy
    ORDER BY disposal_date DESC

HTML / PDF:
    title = "Disposal Report"
    PDF paper A4 landscape
    DOWNLOAD "disposal-report.pdf"

CSV:
    Header:
        Property Code, Property Name, Quantity Disposed, Disposal Date,
        Method, Disposed By, Status
```

### 18.6 Output Notes

- HTML reports ay printable views na may print header at signatories.
- PDF ay generated gamit ang DomPDF at landscape A4.
- CSV ay direktang streamed sa browser; hindi naka-store sa server.
- Walang report filters sa kasalukuyang controller; buong dataset ang nilalabas.

---

## 19. Feature 16 — Teacher Portal (My Properties)

**Routes:**
- `GET /teacher/my-properties`
- `GET /teacher/my-properties/print`
- `GET /teacher/my-properties/{property}`

**Controller:** `Teacher\TeacherPropertyController`  
**Access:** Teacher only

### 19.1 PROCESS — My Properties List

```text
SIMULA GET /teacher/my-properties
  properties = properties
      WITH assignments:
          WHERE teacher_id = teacher.id
          AND status = active
          WITH propertyUnits
          ORDER BY date_assigned DESC
      WITH activeAssignments
      WHERE may assignment na teacher.id at active
      ORDER BY latest
      PAGINATE 10

OUTPUT:
  teacher/properties/index view
```

### 19.2 PROCESS — Property Detail

```text
SIMULA GET /teacher/my-properties/{property}
  load assignments ng teacher (active) WITH assignedBy, propertyUnits
  load activeAssignments

  KUNG walang assignment ng teacher sa property:
      HTTP 403

  assignment = kung may `assignment` query:
                   hanapin sa loaded assignments
               kung wala: unang assignment

  KUNG wala:
      HTTP 404

OUTPUT:
  teacher/properties/show view
```

### 19.3 PROCESS — Printable Accountability

```text
SIMULA GET /teacher/my-properties/print
  VALIDATE verified_by:
      optional integer, existing active admin user

  admins = active admins ordered by name
  verifyingAdmin =
      verified_by kung valid
      O defaultAdmin
      O unang admin

  properties = properties ng teacher na may active assignment
      WITH assignments (assignedBy, propertyUnits)
      ORDER BY property_name

  KUNG may `pdf` query na true:
      DomPDF.loadView('teacher.properties.print')
          setPaper(A4, landscape)
      DOWNLOAD "my-accountabilities.pdf"

  KUNG wala:
      OUTPUT teacher/properties/print view
```

### 19.4 Access Control

- Teacher ay makakakita lamang ng property na may **aktibong assignment** sa kanya.
- Hindi siya makakapasok sa staff/admin property records.
- Ang print/PDF ay may verifying admin signatory.

---

## 20. Feature 17 — In-App Request Notifications

**View partial:** `resources/views/property-requests/notification.blade.php`  
**Ginagamit sa:** layouts/sidebar o page header (shared views)

### 20.1 PROCESS

```text
KUNG user = staff:
    pendingCount = COUNT(property_requests WHERE status = pending)
    KUNG > 0:
        notice: "<n> new property request(s) need staff review."

    approvedCount = COUNT(property_requests
        WHERE status = approved
        AND reviewed_at IS NOT NULL
        AND assignment_id IS NULL)
    KUNG > 0:
        notice: "<n> admin-approved request(s) are waiting for staff assignment."

KUNG user = admin:
    reviewCount = COUNT(property_requests
        WHERE status = awaiting_admin
        AND reviewed_at IS NOT NULL)
    KUNG > 0:
        notice: "<n> request(s) forwarded by staff need your approval."

OUTPUT:
  alert banners na may link sa property-requests.index na naka-filter sa status
```

### 20.2 Integration

- Ito ay **computed on page render**, hindi persistent notification table.
- Direktang sumasalamin sa status ng `property_requests`.
- Staff at admin lang ang may notice; teacher ay may sariling dashboard counters.

---

## 21. Feature 18 — Middleware at Shared System Behavior

### 21.1 Role Middleware

**File:** `app/Http/Middleware/RoleMiddleware.php`  
**Alias:** `role`

```text
SIMULA handle(request, next, ...roles)
  user = request.user

  KUNG user = null:
      HTTP 403

  KUNG user.role ay wala sa roles:
      HTTP 403 "Unauthorized role."

  IBALIK next(request)
```

### 21.2 AJAX Form Responses

**File:** `app/Http/Middleware/HandleAjaxFormResponses.php`

```text
SIMULA handle(request, next)
  response = next(request)

  KUNG request ay hindi AJAX
     O GET request
     O response ay hindi RedirectResponse:
      IBALIK response

  errors = session errors

  KUNG may errors:
      BURAHIN ang errors sa session
      IBALIK JSON 422:
          message = unang error
          errors  = lahat ng field errors

  KUNG may session 'error':
      IBALIK JSON 422 na may message

  message = session 'success' O 'status' O default
  BURAHIN ang success/status/error sa session
  IBALIK JSON 200:
      message  = message
      redirect = response target URL
```

### 21.3 Prevent Page Caching

**File:** `app/Http/Middleware/PreventPageCaching.php`

```text
SIMULA handle(request, next)
  response = next(request)

  KUNG request method ay GET o HEAD:
      Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
      Pragma: no-cache
      Expires: 0

  IBALIK response
```

### 21.4 CSRF 419 Handling

**File:** `bootstrap/app.php`

```text
KUNG HttpException status = 419:
    message = "Your session expired. Please try logging in again."

    KUNG request expects JSON O AJAX:
        IBALIK JSON 419 { message, csrf_refresh: true }

    KUNG normal request:
        REDIRECT sa login na may email error
```

---

## 22. Paano Nag-uugnayan ang Lahat ng Features

### 22.1 Integration Overview

```text
                        ┌────────────────────────────┐
                        │  Registration / OTP / Login │
                        │  Users, Role Profiles       │
                        └─────────────┬──────────────┘
                                      │ identity + role
                                      ▼
        ┌─────────────────────────────────────────────────────────┐
        │                     ROLE DASHBOARDS                     │
        │        Admin / Staff stats        Teacher stats         │
        └───────┬─────────────────────┬───────────────────┬───────┘
                │                     │                   │
                ▼                     ▼                   ▼
      ┌──────────────────┐   ┌──────────────────┐   ┌──────────────────┐
      │ PROPERTY & UNITS │   │ PROPERTY REQUESTS│   │ TEACHER PORTAL   │
      │ serials, QR,     │◄──┤ teacher submits  │──►│ my properties,   │
      │ inventory        │   │ staff reviews    │   │ receipts, print  │
      └──────┬───────────┘   │ admin approves   │   └──────────────────┘
             │               │ staff fulfills   │
             │               └────────┬─────────┘
             │                        │ creates assignment
             ▼                        ▼
      ┌──────────────────────────────────────────┐
      │ ASSIGNMENTS / ACCOUNTABILITY             │
      │ units assigned, quantities reduced,      │
      │ history, printable receipt               │
      └──────┬───────────────────────┬───────────┘
             │ return                │ disposal
             ▼                       ▼
      ┌──────────────┐        ┌──────────────────┐
      │ RETURNS      │        │ DISPOSALS        │
      │ units back   │        │ staff request    │
      │ quantity up  │        │ admin decision   │
      └──────┬───────┘        │ units disposed   │
             │                │ quantity down    │
             └────────┬───────┴─────────┬────────┘
                      ▼                 ▼
             ┌─────────────────┐  ┌──────────────────┐
             │ PROPERTY HISTORY│  │ INVENTORY LOGS   │
             │ audit trail     │  │ stock movements  │
             └────────┬────────┘  └────────┬─────────┘
                      │                    │
                      ▼                    ▼
             ┌──────────────────────────────────────┐
             │ REPORTS: HTML / PDF / CSV            │
             │ QR: property at unit stickers/scan   │
             └──────────────────────────────────────┘
```

### 22.2 End-to-End Flow A — Teacher Request hanggang Accountability

```text
1. Teacher ay nag-register (Gmail + OTP) at nag-login.
2. Teacher ay pumunta sa "New Property Request":
       - pumili ng available inventory item, O
       - mag-request ng unavailable item, O
       - mag-request ng replacement sa existing assignment.
3. Sistemang nagre-reserve ng inventory stock (kung inventory-based)
   at gumagawa ng property_requests row na status = pending.
4. Staff dashboard ay may notification; staff reviews at nagse-set ng
   status = awaiting_admin (reviewed_by, reviewed_at).
5. Admin dashboard ay may notification; admin approves at pumipili ng property
   (status = approved, selected_property_id), o rejects/awaiting_stock.
6. Staff fulfills:
       RequestAssignmentService ang gumagawa ng assignment,
       nag-a-assign ng property_units, nagbabawas ng property.quantity,
       at nagre-record ng PropertyHistory.
       Request status = fulfilled; assignment_id ay naka-link.
7. Teacher ay makakapasok sa receipt page at makakapag-print ng
   accountability form.
8. Assignment ay lalabas sa staff/admin assignment list, teacher portal,
   at reports. QR stickers ay pwedeng i-print para sa property/units.
```

### 22.3 End-to-End Flow B — Assignment → Return

```text
1. Staff/Admin ay pumili ng property na may available units.
2. Nag-assign ng quantity at pumili ng specific unit serial numbers.
3. System:
       - gumagawa ng assignments row (active),
       - naglalagay ng assignment_id sa property_units at status = assigned,
       - nagbabawas ng properties.quantity,
       - nagre-record ng PropertyHistory (assigned).
4. Pwedeng i-print ang accountability form.
5. Kapag bumalik ang property, staff/admin ay nagre-record ng return date.
6. System:
       - assignment.status = returned,
       - gumagawa ng returns row kasama ang returned serial numbers,
       - nagre-release ng units pabalik sa available,
       - nagdadagdag ng properties.quantity,
       - nagre-record ng PropertyHistory (returned).
7. Return ay lalabas sa /returns at reports.
```

### 22.4 End-to-End Flow C — Disposal

```text
1. Staff ay pumipili ng property o active assignment para i-dispose.
2. Nag-submit ng quantity, date, reason, at method.
3. System:
       - staff request → disposals row status = pending,
       - admin direct → status = completed at agad na finalize.
4. Admin reviews pending request:
       - approve → status = approved at finalize,
       - cancel → status = cancelled, walang stock change.
5. Finalize:
       - assigned units → status = disposed,
       - assignment quantity ay nababawasan; zero → assignment disposed,
       - o available units → status = disposed at property.quantity bumababa,
       - property status ay nagsi-sync,
       - PropertyHistory (disposed) ay naitala.
6. Disposal ay lalabas sa disposal report at property history.
```

### 22.5 End-to-End Flow D — QR

```text
1. Staff/Admin ay nag-generate ng QR para sa property o unit.
2. QR payload ay public URL: /qr/<token>.
3. Pwedeng i-print bilang sticker o i-download bilang SVG.
4. Sinuman na may QR ay makakapag-scan at makakakita ng:
       - property details,
       - unit serial (kung unit QR),
       - kasalukuyang active assignment/assignee (kung meron).
5. Walang login na kailangan sa scan page; read-only ang output.
```

### 22.6 Shared Data at Cross-Feature Effects

| Pangyayari | Apektadong data |
|---|---|
| Property create | `properties`, `property_units`, `inventories`, `inventory_logs`, `property_histories` |
| Property update | `properties`, `property_units`, `property_histories` |
| Assignment create/update | `assignments`, `property_units`, `properties.quantity`, `properties.status`, `property_histories` |
| Request submit (inventory) | `property_requests`, `inventories.available_quantity`, `inventory_logs` |
| Request approval | `property_requests` (walang stock decrement pa) |
| Request rejection | `property_requests`, `inventories.available_quantity`, `inventory_logs` |
| Request fulfillment | `property_requests`, `assignments`, `property_units`, `properties`, `property_histories`, `inventory_logs` |
| Return | `assignments`, `returns`, `property_units`, `properties`, `property_histories` |
| Disposal create/finalize | `disposals`, `property_units`, `assignments`, `properties`, `property_histories` |
| User delete | `users`, role profiles, `assignments`, `properties.quantity`, `properties.status`, `property_histories` |
| QR generation | `properties.qr_token` o `property_units.qr_token` |

### 22.7 Isang Property, Maraming Records

```text
Property (product record)
   ├─ quantity            → kasalukuyang available
   ├─ property_units      → isa bawat pisikal na item
   ├─ assignments         → kasaysayan ng pagtatalaga
   ├─ returns             → kasaysayan ng pagbabalik
   ├─ disposals           → kasaysayan ng disposal
   ├─ property_histories  → audit ng lahat ng aksyon
   └─ inventory           → reservation balance para sa requests
```

---

## 23. Status Reference

### 23.1 Property Status

```text
available   → may stock, walang active assignment
assigned    → may active assignment
for_disposal→ existing enum/filter; walang kasalukuyang automatic workflow
disposed    → quantity = 0 at may na-dispose
```

### 23.2 Property Unit Status

```text
available → hindi naka-assign at hindi disposed
assigned  → may assignment_id
disposed  → na-dispose na
```

### 23.3 Assignment Status

```text
active      → kasalukuyang may accountability
returned    → naibalik na
transferred → existing enum; walang kasalukuyang workflow na nagse-set
disposed    → naubos/nadispose ang lahat ng quantity sa assignment
```

### 23.4 Property Request Status

```text
pending        → teacher submitted; staff review
awaiting_admin → staff forwarded; admin decision
awaiting_stock → admin: kulang ang stock
approved       → admin approved; staff assignment
rejected       → admin rejected
fulfilled      → na-assign na; may receipt
```

### 23.5 Disposal Status

```text
pending   → staff request; admin review
approved  → admin approved at na-finalize
completed → admin direct record; final agad
cancelled → admin disapproved; walang stock effect
```

### 23.6 Inventory Log Actions

```text
stock_added → bagong stock mula sa property creation
reserved    → stock na inilaan sa request
released    → stock na ibinalik dahil rejected
fulfilled   → natupad ang request
```

---

## 24. Validation at Authorization Summary

### 24.1 Authorization

| Operations | Rule |
|---|---|
| Public registration | guest middleware |
| Login | guest middleware |
| Profile | auth |
| Users | role:admin |
| Properties, assignments, returns, disposals, requests queue, QR, reports, inventory logs | role:admin,staff |
| Disposal status update | admin only (route + FormRequest) |
| Teacher portal at request submission | role:teacher |
| Request status update | admin/staff; status choices depende sa role |
| Receipt | admin, staff, o may-ari ng request |
| Disposal detail | staff: sarili lang; admin: lahat |
| Teacher property detail | sariling active assignment lang |

### 24.2 Validation Highlights

| Feature | Highlights |
|---|---|
| Registration | Gmail only; staff/teacher roles only; password min 8 confirmed; unique email |
| Login | privacy consent accepted; active account check; credentials check |
| User management | Gmail only; unique email; employee number unique sa teacher/staff; role in list; password optional sa update |
| Property | unique code; unique serial; unique QR token; quantity limits; allowed unit/condition/status; unit serial uniqueness (case-insensitive) |
| Assignment | property exists; quantity available; not disposed; exactly one assignee; assignee role match; unit count = quantity |
| Return | return date required; active assignment only |
| Disposal | property/assignment match; active assignment; quantity limit; valid method; pending-only review |
| Request submission | teacher only; replacement assignment must be active at kanya; quantity limits; inventory availability; duplicate active request check |
| Request status | role-specific status choices; staff review only pending; admin decision only forwarded; approval requires sufficient stock |
| Profile photo | image type; size max 2 MB; max dimensions 4096×4096 |

---

## 25. Mga Tandaan para sa Panel Presentation

1. **Role-based ang buong sistema.** Iisang `users` table na may `role`; ang bawat role ay may
   hiwalay na dashboard at pinaghihigpitang routes.
2. **Ang property ay may dalawang antas:** product record (`properties`) at pisikal na item
   (`property_units`). Mahalaga ito sa serial numbers at QR stickers.
3. **Ang accountability ay assignment record.** Dito naka-link ang property, recipient,
   issuer, quantity, dates, department, at units.
4. **May audit trail:** `property_histories` para sa property actions at `inventory_logs`
   para sa stock reservation movements.
5. **May transaction at row locking** sa stock-sensitive operations (assignment, update,
   return, disposal, request reservation/fulfillment) para maiwasan ang sabay-sabay na
   pagbabago ng quantity.
6. **Ang request ay may tatlong role na dumadaan:** teacher → staff → admin → staff.
   Ipinapakita nito ang segregation of duties.
7. **Ang QR ay read-only lookup.** Hindi ito nagbabago ng data; ang scan page ay nagpapakita
   ng kasalukuyang impormasyon at assignment.
8. **Ang reports ay walang filter sa kasalukuyang code.** Buong dataset ang nilalabas sa
   HTML, PDF, at CSV.
9. **Ang `approvals` table at `for_disposal`/`transferred` statuses ay existing sa schema**
   ngunit wala pang aktibong workflow sa controllers. Hindi ito dapat ipresenta bilang
   gumaganang feature.
10. **Ang `two_factor_code` at `two_factor_expires_at` columns ay existing** ngunit hindi
    ginagamit ng login flow; OTP lamang ng registration ang aktibong verification.

---

## 26. Buod ng Lahat ng Features

| # | Feature | Pangunahing Role | Pangunahing Tables |
|---|---|---|---|
| 1 | Registration na may Gmail OTP | Guest | `users`, `teachers`, `staff` |
| 2 | Login / Logout | Lahat | `users`, sessions |
| 3 | Profile photo | Lahat | `profile_photos` |
| 4 | User management | Admin | `users`, `admins`, `staff`, `teachers`, `assignments`, `properties`, `property_histories` |
| 5 | Role-based dashboards | Lahat | `properties`, `assignments`, `users`, `disposals`, `property_requests` |
| 6 | Property management | Admin, Staff | `properties`, `property_units`, `property_categories`, `locations`, `property_histories`, `inventories`, `inventory_logs` |
| 7 | Units at serial numbers | Admin, Staff | `property_units` |
| 8 | QR generation at scanning | Admin, Staff; public scan | `properties`, `property_units`, `assignments` |
| 9 | Teacher property request | Teacher | `property_requests`, `inventories`, `inventory_logs` |
| 10 | Request review/approval/fulfillment | Staff, Admin | `property_requests`, `assignments`, `property_units`, `properties`, `property_histories` |
| 11 | Assignment/accountability | Admin, Staff | `assignments`, `property_units`, `properties`, `property_histories` |
| 12 | Return management | Admin, Staff | `assignments`, `returns`, `property_units`, `properties`, `property_histories` |
| 13 | Disposal management | Staff, Admin | `disposals`, `disposal_methods`, `property_units`, `assignments`, `properties`, `property_histories` |
| 14 | Inventory logs | Admin, Staff | `inventory_logs`, `inventories` |
| 15 | Reports (HTML/PDF/CSV) | Admin, Staff | `properties`, `assignments`, `disposals` |
| 16 | Teacher portal | Teacher | `properties`, `assignments`, `property_units` |
| 17 | In-app request notices | Staff, Admin | `property_requests` |
| 18 | Middleware at system responses | Lahat | sessions, errors |

---

*Wakas ng PSEUDOCODE.md — nakabase sa aktwal na source code ng PARDS. Walang source file na binago.*
