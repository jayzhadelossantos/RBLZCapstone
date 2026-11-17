# PARDS — Short Pseudocode

A simple guide to the current working features of the Property Accountability and
Request Distribution System (PARDS).

---

## 1. System Overview

PARDS is a Laravel web system for the school supply office. It keeps track of
properties, physical units, assignments, requests, returns, disposals, and reports.

```text
MAIN IDEA:
  Record the property
      → assign it to a teacher or staff
      → track who is accountable
      → take it back or dispose it
      → keep history and reports
```

The system uses one login table with three roles: **Admin**, **Staff**, and
**Teacher**. Each role sees only the pages allowed for that role.

---

## 2. User Roles

| Role | What they can do |
|---|---|
| **Guest** | Register, verify OTP, login, and view a QR scan page. |
| **Teacher** | View own assigned properties, submit property requests, view request status and receipt. |
| **Staff** | Manage properties and units, create assignments, record returns, review teacher requests, fulfill approved requests, request disposal, view reports and QR pages. |
| **Admin** | Do all staff tasks, manage users, approve or reject property requests, decide on disposal requests, and record a disposal directly. |

```text
ACCESS RULE:
  IF user is not logged in
      → go to login page

  IF user role is not allowed on the page
      → show "403 Unauthorized"
```

---

## 3. Main Features

### 3.1 Registration with Gmail OTP

**INPUT:** name, Gmail address, role (staff or teacher), password, OTP

**PROCESS:**

```text
1. Guest fills out the registration form.
2. System checks:
      - email ends with "@gmail.com"
      - email is not yet used
      - role is staff or teacher
      - password is at least 8 characters and confirmed
3. System creates a 6-digit OTP and sends it by email.
4. System saves the pending registration in the session for 10 minutes.
5. Guest enters the OTP.
6. System checks that the OTP is correct and not expired.
7. System creates the user account and marks the email as verified.
8. System creates the matching role profile (staff or teacher).
```

**OUTPUT:** Account created. User is redirected to login.

```text
IF OTP is wrong or expired → show error, allow resend
IF email already exists    → ask guest to register again
```

---

### 3.2 Login and Logout

**INPUT:** email, password, privacy consent

**PROCESS:**

```text
1. System validates the email, password, and privacy consent.
2. System finds the user by email.
3. IF account is deactivated → show "Account deactivated" error.
4. IF email or password is wrong → show "Credentials do not match" error.
5. System logs the user in and refreshes the session.
6. IF user has no profile photo → go to Profile page.
   ELSE → go to the dashboard for the user's role.
```

**OUTPUT:** User is logged in. Logout clears the session and returns to login.

---

### 3.3 Profile Photo

**INPUT:** image file

**PROCESS:**

```text
1. User opens the Profile page.
2. System checks that the file is an image, max 2 MB, max 4096 × 4096.
3. System stores the photo and links it to the user.
```

**OUTPUT:** Profile photo is saved and shown in the system.

---

### 3.4 User Management (Admin Only)

**INPUT:** name, email, role, password, employee number, department, subject area,
active status

**PROCESS:**

```text
CREATE:
  Admin fills out the user form.
  System validates Gmail address, unique email, role, and employee number.
  System creates the user and the matching role profile.

UPDATE:
  Admin edits the user details.
  IF password is blank → keep the old password.
  System updates the user and role profile.

ACTIVATE / DEACTIVATE:
  Admin toggles the account status.
  Admin cannot deactivate their own account.

DELETE:
  Admin deletes another user.
  System returns the user's active assigned quantities to property stock.
  System deletes the user's assignments and the user account.
  System updates the affected property status and history.
```

**OUTPUT:** User list is updated with a success or error message.

---

### 3.5 Role Dashboards and Teacher Portal

**INPUT:** logged-in user

**PROCESS:**

```text
ADMIN / STAFF DASHBOARD:
  Count properties, active assignments, teachers, staff/admin users,
  and finalized disposals.
  Show the five most recently updated properties.
  Show quick links for the role.

TEACHER DASHBOARD:
  Count the teacher's active assigned properties and requests.
  Show the five latest assignments and requests.

TEACHER "MY PROPERTIES":
  Show only properties with an active assignment for this teacher.
  Teacher can open a property and print their accountability record.
```

**OUTPUT:** Role-specific dashboard or teacher property list.

```text
Teacher can only view their own active assignments.
Other properties return "403".
```

---

### 3.6 Property and Unit Inventory

**INPUT:** property details, quantity, category, location, condition, unit serial
numbers

**PROCESS:**

```text
CREATE PROPERTY:
  Staff/Admin fills out the property form.
  System validates the property code, serial number, quantity, and unit serials.
  System saves the property record.
  System creates or updates the physical units and inventory record.
  System records "stock added" in the inventory log and property history.

VIEW / SEARCH:
  System lists properties with search and filters:
      name, code, serial, brand, model, office, location,
      category, status, and condition.
  System shows available quantity, assigned quantity, and disposed quantity.

UPDATE:
  Staff/Admin edits the property and its units.
  System updates the units, inventory status, and property history.

DELETE:
  Staff/Admin deletes the property record.
```

**OUTPUT:** Property list or property detail with updated stock and history.

```text
IMPORTANT:
  properties.quantity = current available stock only.
  Each physical item has its own record in property_units.
```

---

### 3.7 QR Code Generation and Scanning

**INPUT:** property or property unit

**PROCESS:**

```text
1. Staff/Admin opens a property or unit QR page.
2. IF the record has no QR token → system creates one.
3. System builds a public scan link: /qr/<token>.
4. System generates an SVG QR code.
5. Staff/Admin can view, print, or download the QR sticker.
6. Anyone can scan the QR code.
7. System finds the unit or property by token.
8. System shows the item details and its current active assignee, if any.
```

**OUTPUT:** QR sticker (SVG) and a read-only public scan page.

---

### 3.8 Property Request

**INPUT:** request type (new or replacement), item, quantity, needed date, purpose,
notes, and optional replacement assignment

**PROCESS — Teacher submits:**

```text
1. Teacher chooses an available inventory item, an unavailable item,
   or a replacement for an active assignment.
2. System checks:
      - requested quantity is valid
      - replacement assignment belongs to the teacher and is active
      - inventory has enough available quantity
      - teacher has no other active request for the same item
3. IF the request uses inventory → system reserves the stock.
4. System saves the request with status "pending".
5. System records the stock reservation in the inventory log.
```

**PROCESS — Staff reviews:**

```text
1. Staff opens the pending request.
2. Staff checks the details and forwards it to the admin.
3. Request status becomes "awaiting_admin".
4. System records the staff reviewer and review time.
```

**PROCESS — Admin decides:**

```text
1. Admin opens a request forwarded by staff.
2. Admin chooses:
      APPROVE        → status "approved", select the property
      REJECT         → status "rejected"
      NEED MORE STOCK → status "awaiting_stock"
3. IF rejected and stock was reserved → system returns the stock.
4. System records the admin, decision time, and notes.
```

**PROCESS — Staff fulfills:**

```text
1. Staff opens an approved request.
2. Staff assigns the approved property and selects units if needed.
3. System creates an assignment for the teacher.
4. System marks the units as assigned and reduces available stock.
5. System links the assignment to the request.
6. Request status becomes "fulfilled".
7. System records property history and inventory log entries.
```

**OUTPUT:** Request is fulfilled. Teacher can view and print the receipt.

```text
STATUS FLOW:
  pending → awaiting_admin → approved → fulfilled
                           → rejected
                           → awaiting_stock → approved
```

---

### 3.9 Assignment (Accountability)

**INPUT:** property, assignee (teacher or staff), quantity, date, location,
department, optional unit serials, remarks

**PROCESS:**

```text
CREATE:
  Staff/Admin selects a property with available stock.
  System checks that the quantity is available and the property is not disposed.
  System checks that exactly one assignee is selected and the role matches.
  System creates an active assignment.
  System assigns the selected physical units to the assignee.
  System reduces the property's available quantity.
  System records the assignment in property history.

VIEW:
  System groups assignments by teacher and by staff.
  System shows the property, units, assignee, issuer, and dates.

UPDATE:
  Staff/Admin edits an active assignment.
  System adjusts the units and stock if the property or quantity changes.
  System records the change in property history.

PRINT:
  Staff/Admin prints the accountability form with signatories.
```

**OUTPUT:** Active assignment record and printable accountability form.

```text
IMPORTANT:
  assignments.teacher_id = the actual receiver (teacher or staff).
  assignments.assigned_by = the user who issued the property.
```

---

### 3.10 Return

**INPUT:** assignment, return date

**PROCESS:**

```text
1. Staff/Admin opens an active assignment.
2. Staff/Admin records the return date.
3. System checks that the assignment is still active.
4. System marks the assignment as "returned".
5. System saves a return record with the returned serial numbers.
6. System releases the units back to "available".
7. System adds the returned quantity back to property stock.
8. System records the return in property history.
```

**OUTPUT:** Assignment closed, units available again, return listed in `/returns`.

```text
Only active assignments can be returned.
```

---

### 3.11 Disposal

**INPUT:** property or assignment, quantity, disposal date, reason, method, remarks

**PROCESS — Staff requests disposal:**

```text
1. Staff selects a property or an active assignment.
2. System checks that the quantity is available for disposal.
3. System saves the disposal with status "pending".
4. Admin reviews the request.
5. IF approved → status "approved" and disposal is finalized.
6. IF disapproved → status "cancelled", no stock change.
```

**PROCESS — Admin records disposal directly:**

```text
1. Admin fills out the disposal form.
2. System checks the quantity.
3. System saves the disposal with status "completed".
4. System finalizes the disposal immediately.
```

**FINALIZE DISPOSAL:**

```text
IF disposal came from an assignment:
    mark the disposed units as "disposed"
    reduce the assignment quantity
    IF no quantity remains → assignment status "disposed"

IF disposal came from available stock:
    mark the available units as "disposed"
    reduce property available quantity

System updates the property status.
System records the disposal in property history.
```

**OUTPUT:** Disposal record, updated units, updated stock, and history entry.

---

### 3.12 Reports and Inventory History

**INPUT:** report type and filters (search, status, method, date range)

**PROCESS:**

```text
PROPERTY REPORT:
  List properties with stock, assigned quantity, and disposed quantity.

ASSIGNMENT REPORT:
  List assignments with property, assignee, issuer, quantity, and dates.

DISPOSAL REPORT:
  List disposals with property, quantity, reason, method, and decision.

INVENTORY HISTORY:
  List stock movements: added, reserved, released, and fulfilled.
```

**OUTPUT:** Report on screen, PDF download, or CSV download.

---

## 4. Important Processes

### 4.1 Stock Rules

```text
Property stock:
  properties.quantity goes down when a property is assigned or disposed.
  properties.quantity goes up when a property is returned or an assignment is deleted.

Request stock:
  inventories.available_quantity goes down when a request reserves stock.
  It goes up when the admin rejects the request.

Unit stock:
  property_units.status = available | assigned | disposed
```

### 4.2 Request Status

| Status | Meaning |
|---|---|
| `pending` | Teacher submitted the request; waiting for staff review. |
| `awaiting_admin` | Staff forwarded it; waiting for the admin. |
| `awaiting_stock` | Not enough stock; waiting for new stock. |
| `approved` | Admin approved it; waiting for staff to assign. |
| `rejected` | Admin rejected it. |
| `fulfilled` | Property was assigned; receipt is ready. |

### 4.3 Audit Trail

```text
property_histories records:
  created, updated, assigned, returned, assignment_updated,
  assignment_removed, disposed

inventory_logs records:
  stock_added, reserved, released, fulfilled
```

### 4.4 Safety Rules

```text
- Stock-sensitive actions run inside a database transaction.
- Property rows are locked while stock is being changed.
- A user cannot delete or deactivate their own account.
- A teacher can only see their own assignments and requests.
- Staff can only open their own disposal records.
- Only the admin can approve requests and decide on disposals.
```

---

## 5. System Integration Flow

```text
Registration / Login
        │
        ▼
   Role Dashboard
        │
        ├──────────────► Property and Units ◄──── QR Codes
        │                      │
        │                      ▼
        │                 Assignments ──────────► Teacher Portal
        │                   │        │
        │                   ▼        ▼
        │                Returns   Disposals
        │                   │        │
        └──────────────► Property Requests
                           (teacher → staff → admin → staff)
                                  │
                                  ▼
                            New Assignment
                                  │
                                  ▼
                     History + Inventory Logs
                                  │
                                  ▼
                         Reports (HTML/PDF/CSV)
```

### 5.1 Teacher Request to Assignment

```text
1. Teacher registers with Gmail and OTP, then logs in.
2. Teacher submits a property request.
3. System reserves stock if the request uses inventory.
4. Staff reviews and forwards the request to the admin.
5. Admin approves, rejects, or marks it awaiting stock.
6. Staff fulfills the approved request.
7. System creates an assignment for the teacher.
8. Units are assigned and property stock is reduced.
9. Teacher views the receipt and assigned property.
10. The assignment appears in reports and the teacher portal.
```

### 5.2 Assignment to Return

```text
1. Staff/Admin assigns a property and its units.
2. System reduces available stock and records history.
3. Staff/Admin prints the accountability form.
4. When the property comes back, staff/admin records the return.
5. System marks the assignment returned.
6. Units become available and stock increases.
7. Return appears in the returns list and reports.
```

### 5.3 Assignment to Disposal

```text
1. Staff selects an active assignment for disposal.
2. Staff submits the disposal request.
3. Admin approves or cancels it.
4. If approved, units are marked disposed and the assignment quantity is reduced.
5. If all assigned quantity is disposed, the assignment is closed.
6. Property history and the disposal report are updated.
```

### 5.4 Shared Data

| Action | Data changed |
|---|---|
| Create property | property, units, inventory, inventory log, property history |
| Assign property | assignment, units, property stock, property history |
| Submit request | request; inventory stock and log if stock is reserved |
| Approve request | request only; stock is not reduced yet |
| Reject request | request; reserved stock is returned |
| Fulfill request | request, assignment, units, property stock, history, inventory log |
| Return property | assignment, return record, units, property stock, history |
| Dispose property | disposal, units, assignment or property stock, history |
| Delete user | user, role profile, assignments, property stock, history |
| Generate QR | QR token of the property or unit |

---

## Summary

PARDS follows one main process:

```text
REGISTER / LOGIN
      → MANAGE PROPERTY AND UNITS
      → ASSIGN PROPERTY
      → TRACK REQUESTS, RETURNS, AND DISPOSALS
      → KEEP HISTORY AND LOGS
      → GENERATE REPORTS
```

Every action is role-based, stock changes are recorded, and all important
property movements are kept in the audit trail.
