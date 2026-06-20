# Surveyors Module

Complete surveyor (field agent) management system — registration, branches, permits, equipment, legal documents, and connection-based access control for Wasnaker.

## Category
Actors

## Key Features

- **Registration** — Web (Surveyorauth) + API (Surveyors_api via REST_Controller) with anti-spam: rate-limit, honeypot, CSRF, timing check, reCAPTCHA
- **Surveyor CRUD** — DataTable + Pipeline (kanban) views with statuses (pending / active / inactive)
- **Branch Management** — HQ + Branch model via Surveyor_branch controller
- **Permits** — Manage permits (active/pending/expired/revoked) with file upload per surveyor
- **Legal Documents** — 8 doc types: nib, npwp, akte_pendirian (+SK), akte_perubahan (+SK), bpjs_tk, bpjs_kes with file upload, notary_name meta, serve/download
- **Equipment Tracking** — Track unit_code, serial_number, location, cert_expired_date per surveyor
- **VAT** — Directly on `tblclients.vat` (not via tblentity_vat like customers)
- **Activity Log** — Sales activity with diff tracking (build_diff)
- **Inactive Company Modal** — Profile completeness check (8 fields) + restricted access until complete
- **Permit Expiry Reminder** — Cron-based (`after_cron_run`) sends reminders to connected customers
- **Connection-based Access** — Customer staff only sees connected surveyors via `tblclient_connections`
- **Global Search** — Searchable (HQ only, excludes branches)
- **Dashboard Widget** — Total surveyors mini-widget
- **Settings** — Finance settings tab with surveyor configuration
- **Email Scheduling** — Scheduled notifications via Email_schedule_surveyor

## Controllers

| Controller | Route | Description |
|---|---|---|
| Surveyors | `/admin/surveyors/` | Main CRUD, DataTable, pipeline, file uploads, legal docs |
| Surveyorauth | `/authentication/register/surveyor` | Web registration with anti-spam |
| Surveyors_api | `/api/surveyors/*` | REST API (CRUD) via CodeIgniter REST Server |
| Surveyor_branch | AJAX | Branch CRUD (save/delete) |
| Email_schedule_surveyor | `/admin/email_schedule_surveyor/` | Scheduled email management |

## Helpers (8)

| Helper | Hook |
|---|---|
| surveyors_menu_helper | `admin_init` → sidebar menu |
| surveyors_capability_helpers | Permissions, role capabilities, staff_can filter |
| surveyors_email_templates_helper | Register email templates |
| surveyors_datatables_helper | DataTable where filters + connection-based access |
| surveyor_relation_helpers | `get_relation_data` for surveyor relations |
| surveyors_helper | PDF, shortlink, format functions |
| surveyors_widgets_helper | Dashboard widget registration |
| api_helper | API helper utilities |

## Database Tables

- `tblclients` (client_type='surveyor')
- `tblsurveyor_permits` — Permits/documents
- `tblsurveyor_equipment` — Equipment records
- `tblsurveyor_activity` — Activity log (alias via `tblsales_activity`)
- `tblclient_legal_docs` — NIB/NPWP/Akte/BPJS docs
- `tblreg_ratelimit` — Registration rate-limit by IP

## Email Templates (12+)

- Surveyor_contact_verification, Surveyor_created_welcome_mail, Surveyor_registration_confirmed
- Surveyor_send_to_surveyor, Surveyor_expiration_reminder
- Permit_expiration_reminder — sent to connected customers when permit is expiring
- Surveyors_new_registration_to_admins, Entity_staff_registration_confirmed/rejected
- etc.

## Key Differences from Customers Module

| Aspect | Customers | Surveyors |
|---|---|---|
| VAT | `tblentity_vat` | `tblclients.vat` |
| Registration | `ClientsController` | `App_Controller` |
| API | `Api_base` (JWT) | `REST_Controller` (CodeIgniter RestServer) |
| Legal docs | 2 types (nib, npwp) | 8 types (+akte, bpjs) |
| Inactive modal | No | Yes (profile completeness) |
| Permit expiry | Not automatic | Cron-based to connected customers |
| Permits table | `tblcustomer_permits` | `tblsurveyor_permits` |
| Equipment table | `tblcustomer_equipment` | `tblsurveyor_equipment` |

## Dependencies

- `api` module (REST_Controller, Format, Authorization_token)
- `apps` module (entity helper, connection filtering)
- `equipments` module (items/equipment types)
- Core: `clients_model`, `staff_model`, `email_schedule_model`
