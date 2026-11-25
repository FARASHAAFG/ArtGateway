# Database Initialization

Docker Compose initializes a new MariaDB data directory with the phpMyAdmin tables in
`00-phpmyadmin.sql` and the ArtGateway schema from `data/sql/artgateway_db.sql`.
The schema is mounted read-only, so there is one canonical schema file.

## Notes

- The ArtGateway schema is imported only when MariaDB initializes an empty data directory.
- Existing database volumes are not deleted, replaced, or automatically migrated.
- Do not use `docker compose down -v` to apply schema changes; it deletes the database volume.
- Back up and inspect an existing database before manually importing schema. The schema
  contains non-idempotent `CREATE TABLE` statements and must only be imported into an empty
  `artgateway_db`.
- If adding another initialization script, mount it explicitly in `docker-compose.yml` and
  give it a filename ordered after `00-phpmyadmin.sql` and before or after the ArtGateway
  schema as its dependencies require.
