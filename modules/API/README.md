# Gibbon Agent API

Additional module that lets authorised agent tools read and write Gibbon data **as the token owner**, using the same role permissions as the website.

Phase 1 covers **lesson plans (Planner)** and **timetable slots (Timetable Admin)**.

## Install

1. Copy `modules/API` into your Gibbon `modules/` folder (already in this tree).
2. Keep the new root file `api.php` in the Gibbon document root.
3. In **System Admin → Manage Modules**, install **API**.
4. Optional pretty URLs (`/api/v1/...` instead of `/api.php/v1/...`): Apache must allow the bundled `.htaccess` rewrite. Ubuntu’s default is `AllowOverride None` for `/var/www`, which **ignores `.htaccess`**. Add this to the site vhost (or equivalent) and reload Apache:

```
<Directory /var/www/gibbon-core>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

Without that, `/api/...` is a missing filesystem path and Apache returns its own HTML 404. `/api.php/v1/...` always works because it uses PHP `PATH_INFO`, not rewrite.

## Create a token

1. Sign in to Gibbon.
2. Open **API → Manage API Tokens**.
3. Add a token and **lock one role** (Teacher vs Timetable Admin need separate tokens).
4. Copy the `gib_pat_...` value immediately. It is shown once.

Administrators can disable the API and revoke anyone’s tokens under **API → API Settings**.

## Call the API

```
Authorization: Bearer gib_pat_...
Content-Type: application/json
```

Examples (replace the base URL):

```
GET  {base}/api.php/v1/me
GET  {base}/api.php/v1/openapi.json
GET  {base}/api.php/v1/classes
GET  {base}/api.php/v1/planner/classes/{gibbonCourseClassID}/coverage?from=2026-08-01&to=2026-12-31
POST {base}/api.php/v1/planner/lessons
```

Errors are JSON. Unknown routes return **404** with `path`, `method`, and a `hint`. Other failures include the exception message and `status`; non-Production installs also include `file` and `line`.

Create a lesson:

```json
{
  "gibbonCourseClassID": "00000001",
  "date": "2026-08-19",
  "timeStart": "09:00:00",
  "timeEnd": "09:45:00",
  "name": "Fractions",
  "description": "Add and subtract fractions",
  "homework": "Y",
  "homeworkDetails": "Exercises 1–10",
  "homeworkDueDateTime": "2026-08-22 21:00:00"
}
```

The spec at `{base}/api.php/v1/openapi.json` does **not** require a token (Bruno, Cursor OpenAPI bridge, etc. fetch it unauthenticated). All other routes still need `Authorization: Bearer`.

## Permissions

The token never grants more than the locked role can do in the UI.

- Lesson write requires `Lesson Planner_viewAllEditMyClasses` or `Lesson Planner_viewEditAllClasses`. Teachers can only edit classes they teach.
- Timetable slot write requires Timetable Admin **Manage Timetables**.
- Coverage statistics compare timetable slots to existing `gibbonPlannerEntry` rows.

## Security notes

- Use HTTPS.
- Revoke a token if it leaks.
- Tokens are stored as SHA-256 hashes.
- Each request is written to `gibbonAPIAuditLog`.
- API requests do not reuse browser session cookies.

## Later (not implemented)

- OAuth 2 authorization-code flow (tables `gibbonAPIClient` and `gibbonAPIAuthorizationCode` are reserved).
- A dedicated MCP server wrapping this REST API.
