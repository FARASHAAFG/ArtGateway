# Authentication

Configure `JWT_SECRET` in the API server environment before using authentication. Use a
cryptographically random value of at least 32 characters (for example, generate one with
`bin2hex(random_bytes(32))`). Do not commit the value to source control.

## Accounts

- `POST /register` creates a regular user and returns a bearer token.
- `POST /login` (or its `POST /token` alias) authenticates either a user or an admin by
  email and password.
- `GET /me` returns the account associated with the bearer token.
- Public read endpoints remain open. Creating, updating, or deleting artworks, artists, and
  museums requires an admin token.

## Authorization policy

| Caller | Permissions |
| --- | --- |
| Anonymous | Public GET routes, registration, and login/token issuance |
| `user` | The anonymous permissions plus `GET /me`; cannot create, update, or delete catalog records |
| `admin` | Public reads plus create, update, and delete operations for artists, artworks, and museums |

Registration ignores any submitted role and always creates a regular user. The API does not
trust identity or role headers; protected requests use the
JWT subject and role, then reload the account and confirm its current database role.

Artists, artworks, and museums are shared public catalog resources. Although the schema
contains `artworks.created_by` and user-owned collection fields, current catalog routes do
not define per-record ownership rules, and collection endpoints are not implemented.
No object-level ownership restrictions are applied to catalog records.

### Provisioning an administrator

There is deliberately no public administrator-registration endpoint. Create the account
through `POST /register` (it receives only the `user` role), verify its identity, then have
a trusted database administrator promote that exact account. For example, using a
parameterized statement in an administrative SQL client:

```sql
UPDATE users SET role = 'admin' WHERE email = ?;
```

Bind the verified account email as the parameter, verify exactly one row was changed, and
then have the administrator sign in through `/login`. Do not grant the application’s
runtime database account privileges beyond those required by the API, and do not set roles
from request payloads.

Passwords are limited to 72 bytes because the current PHP `PASSWORD_DEFAULT` algorithm
uses bcrypt, which otherwise silently ignores bytes after that limit. Registration also
requires at least 8 bytes.

## Request examples

Register:

```http
POST /register
Content-Type: application/json

{"full_name":"Alex Rivera","email":"alex@example.com","password":"correct-horse-42"}
```

Use the returned `access_token` on protected requests:

```http
Authorization: Bearer <access_token>
```

Tokens expire after one hour. This API uses stateless bearer tokens and has no server-side
logout endpoint; discard the token on the client and sign in again after it expires.
