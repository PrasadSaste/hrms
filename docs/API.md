# REST API

Everything an employee can do from their phone is available under `/api/v1`,
authenticated with Laravel Sanctum personal access tokens. The web application
and the API run the same services and the same policies, so a rule enforced on
one screen is enforced on the endpoint too.

An importable Postman collection sits beside this file:
[`hrms-api.postman_collection.json`](hrms-api.postman_collection.json). Import
it, set `base_url`, run **Login**, and the token is captured into the
collection for every other request.

---

## Conventions

**Base URL** — `https://your-host/api/v1`.

**Headers** — send `Accept: application/json` on every request. Without it
Laravel answers a validation failure with an HTML redirect instead of JSON.
Authenticated calls add `Authorization: Bearer <token>`.

**Requests** — form-encoded or JSON, whichever you prefer. File uploads
(a leave attachment) must be `multipart/form-data`.

**Errors** — a consistent envelope:

```json
{ "message": "The given data was invalid.", "errors": { "email": ["..."] } }
```

| Status | Means |
| --- | --- |
| `200` / `201` | Success |
| `401` | Missing, expired or revoked token |
| `403` | Authenticated but not permitted — includes the verification gate below |
| `404` | Not found, or no employee record is linked to the account |
| `422` | Validation failed; `errors` names each field |
| `429` | Rate limited; `Retry-After` says how long |

**Dates and times** — dates are `YYYY-MM-DD`, timestamps are ISO 8601 in the
application's timezone (`Asia/Kolkata` by default), and durations are whole
minutes.

**Money** — plain numbers, two decimal places, in the currency named by the
payslip's `currency` field.

**Pagination** — list endpoints accept `per_page` and `page`, and answer with
`data` plus a `meta` object carrying `current_page`, `last_page` and `total`.

### The background verification gate

A new joiner who has been asked for verification cannot reach attendance, their
own leave, or payslips until it clears. Those endpoints answer `403` with:

```json
{
  "message": "My Attendance, Leave and Salary Slips open once your background verification is complete.",
  "background_check_status": "submitted"
}
```

Approving *other people's* leave is not gated. Someone never asked for
verification is never affected.

---

## Authentication

### `POST /login`

Public. Rate limited two ways: ten requests a minute per IP, and five failed
attempts per email-and-IP pair before a `429` that says how long to wait.

| Field | Rules |
| --- | --- |
| `email` | required, email |
| `password` | required |
| `device_name` | optional, ≤ 64 characters — names the token so it can be revoked on its own |

```bash
curl -X POST https://your-host/api/v1/login \
  -H 'Accept: application/json' \
  -d 'email=arjun.sharma@beyondsure.example' \
  -d 'password=Password123!' \
  -d 'device_name=Pixel 8'
```

```json
{
  "token": "12|Qy3f...",
  "expires_at": "2026-10-07T09:15:00+05:30",
  "must_change_password": false,
  "user": {
    "id": 9,
    "name": "Arjun Sharma",
    "email": "arjun.sharma@beyondsure.example",
    "phone": "+91 98xxxxxx01",
    "avatar_url": "https://your-host/storage/avatars/9.jpg",
    "roles": ["Employee"],
    "permissions": ["attendance.self", "leave.apply", "..."],
    "employee": { "id": 9, "employee_code": "BS0009", "full_name": "Arjun Sharma", "...": "..." }
  }
}
```

Tokens expire **30 days** after they are issued. `must_change_password` is true
for a joiner still on the password their welcome email carried — send them to
`change-password` before anything else. A deactivated account gets `403`.

Store the token in the platform keystore (Keychain, EncryptedSharedPreferences),
not in plain preferences: it is a bearer credential for 30 days.

### `GET /me`

The same `user` object, refreshed. Useful on app launch to confirm the token is
still good and pick up a role change.

### `POST /logout` · `POST /logout-all`

`logout` revokes the token that made the call. `logout-all` revokes every token
the user has, on every device.

### `POST /change-password`

| Field | Rules |
| --- | --- |
| `current_password` | required, must match |
| `password` | required, confirmed, must meet the password policy |
| `password_confirmation` | required, must match `password` |

Clears `must_change_password`. Existing tokens keep working.

---

## Attendance

A day is made of **sessions** and **breaks**. Punching in opens a session,
punching out closes it, and either can happen several times a day. A break
suspends the day with a reason attached. The `attendance` object every endpoint
returns is the day's summary, rebuilt from those parts.

### `GET /attendance/today`

```json
{
  "date": "2026-09-07",
  "checked_in": true,
  "checked_out": false,
  "location_required": true,
  "attendance": {
    "id": 412,
    "date": "2026-09-07",
    "check_in": "2026-09-07T09:32:00+05:30",
    "check_out": null,
    "worked_minutes": 214,
    "worked_hours": 3.57,
    "late_minutes": 2,
    "early_leaving_minutes": 0,
    "overtime_minutes": 0,
    "status": "present",
    "status_label": "Present",
    "source": "api",
    "check_in_location": {
      "latitude": 17.4401, "longitude": 78.3489,
      "accuracy_metres": 18, "label": "Hyderabad office"
    },
    "remarks": null,
    "is_open": true
  }
}
```

Read `location_required` **before** drawing the punch button, so the app can ask
for the location permission at a sensible moment rather than at the punch.

### `POST /attendance/check-in` · `POST /attendance/check-out`

| Field | Rules |
| --- | --- |
| `latitude` | required when `location_required`, between -90 and 90 |
| `longitude` | required when `location_required`, between -180 and 180 |
| `accuracy` | optional, metres |
| `location` | optional label, ≤ 255 characters |

Send `location` when you have a place name to attach; leave it out and the
coordinates themselves become the label.

```bash
curl -X POST https://your-host/api/v1/attendance/check-in \
  -H 'Authorization: Bearer 12|Qy3f...' -H 'Accept: application/json' \
  -d 'latitude=17.4401' -d 'longitude=78.3489' -d 'accuracy=18'
```

`201` with a message and the rebuilt day. Check-in is refused if a session is
already open or a break is running; check-out is refused if nothing is open,
and auto-closes a break somebody forgot to end.

The location rule is enforced on the server, so a client that skips it is
refused rather than silently recorded. It can be turned off entirely under
Settings → Attendance, at which point both fields become optional.

**A punch made far from the branch still succeeds.** If the coordinates are
further from the employee's branch than that branch allows, the punch is
recorded exactly as any other, the distance is stored beside it, and the people
nominated in the settings are alerted. Nothing in the response changes and there
is nothing for the client to handle — a client visit is not an error.

### `GET /attendance/break-reasons`

Draw the picker from this rather than hard-coding it — the catalogue can grow.

```json
{ "data": [
  { "key": "lunch", "label": "Lunch", "description": "The midday break." },
  { "key": "outside_meeting", "label": "Outside meeting", "description": "A meeting away from the office." },
  { "key": "client_visit", "label": "Client visit", "description": "At a client's premises." },
  { "key": "client_call", "label": "Client — telephonic call", "description": "On a call with a client." },
  { "key": "personal_call", "label": "Personal telephonic call", "description": "A personal call." },
  { "key": "team_meeting", "label": "Team meeting", "description": "An internal meeting." },
  { "key": "product_training", "label": "Product training", "description": "Learning or delivering training." },
  { "key": "paperwork", "label": "Paperwork", "description": "Away from the system doing paperwork." },
  { "key": "other", "label": "Other", "description": "Anything else — say what in the comment." }
] }
```

### `POST /attendance/break/start`

| Field | Rules |
| --- | --- |
| `reason` | required, one of the keys above |
| `comment` | optional, ≤ 500 characters |

`201`. Refused if no session is open or a break is already running. Every
reason is deducted from working time; the reason is what makes an hour of
client visits readable in the reports rather than a gap in the day.

### `POST /attendance/break/end`

No body. Closes the running break and returns the day with `worked_minutes`
recomputed.

### `GET /attendance`

The caller's own history. Query: `from`, `to` (dates, `to` on or after `from`),
`per_page` (1–100, default 31). Newest day first.

### `GET /attendance/summary`

Query: `year`, `month` (both default to today's).

```json
{
  "year": 2026, "month": 9,
  "totals": {
    "working_days": 22, "elapsed_working_days": 5,
    "present_days": 5.0, "half_days": 0, "late_days": 1, "absent_days": 0.0,
    "paid_leave_days": 1.0, "unpaid_leave_days": 0.0,
    "holiday_days": 1, "weekend_days": 8,
    "worked_minutes": 2410, "worked_hours": 40.17,
    "overtime_minutes": 0, "overtime_hours": 0,
    "late_minutes": 2, "attendance_percentage": 100
  },
  "days": [
    { "date": "2026-09-01", "kind": "working", "status": "present", "status_label": "Present",
      "check_in": "09:30", "check_out": "18:35", "worked_minutes": 480 }
  ]
}
```

`kind` distinguishes a working day from a weekly off or a holiday, so a client
can shade the calendar without guessing.

---

## Leave

### `GET /leave/types`

Only the types this employee may actually apply for — gender, employment type
and probation rules are applied server-side. Each carries `min_notice_days`,
`max_consecutive_days`, `allow_half_day`, `requires_attachment` and `is_paid`,
which is everything a client needs to validate the form before submitting.

### `GET /leave/balance`

Query: `year` (defaults to this one).

```json
{ "year": 2026, "data": [
  { "leave_type": { "id": 1, "name": "Casual Leave", "code": "CL", "color": "#0ea5e9" },
    "allocated": 12, "carried_forward": 2, "entitled": 14,
    "used": 5, "pending": 1, "remaining": 8 }
] }
```

`remaining` already has pending requests held against it, so an employee cannot
spend the same day twice.

### `POST /leave`

| Field | Rules |
| --- | --- |
| `leave_type_id` | required, existing type |
| `start_date` | required, date |
| `end_date` | required, on or after `start_date` |
| `day_type` | required — `full_day`, `first_half` or `second_half` |
| `reason` | required, 5–1000 characters |
| `contact_during_leave` | optional, ≤ 64 characters |
| `attachment` | optional file, ≤ 5 MB, `pdf` `jpg` `jpeg` `png` |

`201` with the created request and its `reference`. The service also refuses
overlapping requests, insufficient balance, notice periods and runs past the
type's consecutive-day limit — all as `422`.

### `GET /leave` · `GET /leave/{id}`

The caller's own requests. Query on the list: `status`, `year`, `per_page`.
`can_cancel` on each row says whether the cancel endpoint will accept it.

### `POST /leave/{id}/cancel`

Optional `cancel_reason` (≤ 500 characters). An approved future-dated request
can still be cancelled; the days go back to the balance.

### Approving — `GET /leave/pending-approvals`

Requires the `leave.approve` permission. Someone without `leave.view-all` sees
only their own reports and their own branch.

### `POST /leave/{id}/approve` · `POST /leave/{id}/reject`

`approver_remarks` is optional on approve, **required on reject** (5–500
characters), because a rejection without a reason is unanswerable. Both notify
the employee through the notification console, so wording stays under
administrative control.

---

## Payslips

### `GET /payslips`

An employee sees their own published slips. Somebody with
`payslips.view-all` sees everyone's and may filter with `employee_id`. Query:
`year`, `per_page` (default 12).

### `GET /payslips/{id}`

Adds the `earnings` and `deductions` line items — each `name`, `code` and
`amount` — alongside `working_days`, `paid_days`, `lop_days`, `gross_earnings`,
`total_deductions`, `net_pay` and `net_pay_words`.

### `GET /payslips/{id}/download`

Returns the PDF as an attachment (`application/pdf`), rendered with the
letterhead of the company that employed the person that month. Send the bearer
token with the request — the `download_url` on each payslip is not a public
link.

---

## Letters

### `GET /letters`

An employee sees the letters issued to them; somebody with `letters.view` sees
everyone's and may filter with `employee_id`. Query: `type`, `employee_id`,
`per_page` (default 20).

Each row is the letter **as it was issued** — `reference`, `subject`,
`issued_on`, `effective_from`, the frozen `signatory_name` and
`signatory_designation`, and the issuing `company`. The body is left out of the
list on purpose; twenty letters of prose is not a list.

### `GET /letters/types`

The letter types this account has actually been issued, each with a `label`.
For a filter control that offers only what exists.

### `GET /letters/{id}`

Adds `body` — the frozen text, never a fresh render of today's template, so
what a client shows is what was signed.

### `GET /letters/{id}/download`

The PDF as an attachment, on the letterhead of the company that issued it,
over the signature frozen onto the letter. Bearer token required; the
`download_url` on each letter is not a public link.

---

## Background verification

The one part of the system somebody uses **before** they properly work here,
often from a phone. It is deliberately the only area not behind the
verification gate — everything else returns `403` until a joiner clears, and
clearing it is what these endpoints are for.

### `GET /background-check`

The caller's own case: `status`, `status_label`, `due_on`, `open_to_employee`,
`awaiting_review`, `verified`, `ready_to_submit`, any `review_remarks`, and the
`items` checklist.

Each item carries what to ask for as well as what was given: `label`,
`description`, `is_required`, `has_upload`, `needs_work`, `remarks`, the
`details` already typed, and a `fields` array (`name`, `label`, `type`,
`required`) describing the form to draw beside the file. A client never needs
its own copy of the requirement catalogue.

`404` with a plain explanation if the account has no employee record, or was
never asked to verify.

### `POST /background-check/items/{item}`

Upload or replace one document. `multipart/form-data`: `file` (PDF, JPG or PNG,
up to 10 MB) and `details[<field>]` for each field the item asks for.

`file` is **optional once something has been uploaded**, so correcting a typed
date does not mean sending the whole scan again over mobile data. Returns the
whole case back, so a client can re-render without a second call. `403` once
the set has been submitted.

### `POST /background-check/submit`

Hands the set to HR. `422` with a `submit` error if any required document is
still missing.

### `GET /background-check/items/{item}/document`

Reads back a document one uploaded oneself, streamed from the private disk.
Identity documents are never reachable by URL.

---

## Assets

### `GET /my-assets`

Company property issued to the caller — name, tag, kind, its identifier (a
serial, an IMEI, a mobile number, whichever that kind is known by), the day it
was issued, how long they have had it and the condition it went out in.

`meta.to_return_on_exit` counts the ones the company actually wants back, which
is what a leaver has to hand over.

### `GET /my-assets/history`

The same shape for everything already handed back, each with its return date
and the condition it came back in. A receipt.

Both are read-only. Somebody marking their own laptop returned is exactly what
a register exists to prevent, so handing back goes through whoever receives it.

---

## Self Assistance guides

### `GET /help`

Every guide the caller may read, grouped by section. Query: `q` for a plain
substring search across titles, summaries and bodies.

A guide to a screen the account cannot open is not returned at all — not
merely hidden — so nobody learns from the help index that a screen exists.
Unpublished drafts are visible only to `help.manage`.

### `GET /help/{slug}`

One guide, with the body as both `body` (Markdown) and `body_html` (rendered).
A web client can drop the HTML straight in; a native client can style the
Markdown itself rather than embedding a browser to do it.

---

## Directory and home screen

### `GET /dashboard`

One call for a mobile home screen: today's punch state, the month's totals,
pending leave count, leave balances, the latest payslip, upcoming holidays,
recent announcements and this week's birthdays. `org` is populated only for
roles that may see organisation-wide figures.

### `GET /employees`

The colleague directory, already limited to what the caller may see — a branch
manager never receives another branch. Query: `search`, `branch_id`,
`department_id`, `per_page` (default 25).

### `GET /holidays`

Query: `year`. Scoped to the caller's branch, so a branch-specific holiday only
reaches the people it applies to.

### `GET /announcements`

Published announcements visible to the caller, pinned first. Each carries
`body` and a short `excerpt`.

### `GET /notifications` · `POST /notifications/{id}/read`

The in-app notification feed with `unread_count` for the badge. Each row's
`data` carries the rendered `type`, `title`, `message`, `icon` and a
deep-linkable `url` — rendered from the notification template an administrator
saved, so wording follows the console rather than being fixed in the client.

---

## Notes for client authors

**Refresh on 401, do not retry.** Tokens are not refreshable; a `401` means
sign in again.

**Honour `must_change_password`.** A joiner is expected to change the password
from their welcome email before using the app.

**Treat `403` with `background_check_status` as a state, not an error.** Show
the employee what is outstanding rather than a failure.

**Do not cache `break-reasons` forever.** It is a catalogue that grows; fetch it
on launch.

**Ask for location early.** `location_required` from `/attendance/today` tells
you whether the punch will need it, so the permission prompt can come before
the person is standing at the door.
