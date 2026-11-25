- [Starter Template](#starter-template)
  - [How Do I Use/Deploy this Template?](#how-do-i-usedeploy-this-template)
    - [Option 1: Using Composer (Recommended)](#option-1-using-composer-recommended)
    - [Option 2: Using Docker (macOS/Linux/Windows)](#option-2-using-docker-macoslinuxwindows)
    - [Option 3: Manual Installation](#option-3-manual-installation)
    - [Option 4: Using Nginx (Native Server)](#option-4-using-nginx-native-server)
  - [How Do I Configure My Database Connection?](#how-do-i-configure-my-database-connection)
  - [How do I Use Composer with Wampoon?](#how-do-i-use-composer-with-wampoon)
  - [On Using Environment Variables](#on-using-environment-variables)
  - [Useful VS Code Keybindings](#useful-vs-code-keybindings)
  
# Starter Template

This repository contains an application skeleton for creating REST-based Web services using the latest version of the [**Slim micro framework**](https://www.slimframework.com/).

## ArtGateway Docker Setup

The Docker application and MariaDB use the `artgateway_db` database. On the first start
with a new MariaDB data directory, Compose imports the schema from
`data/sql/artgateway_db.sql`. MariaDB runs initialization scripts only for a new data
directory; existing volumes are preserved and are not migrated automatically.

From the `artgateway-api` directory, create a private Compose environment file and fill in
the required values:

```powershell
Copy-Item .env.example .env
notepad .env
```

Set `DB_PASSWORD` and `MARIADB_ROOT_PASSWORD` to unique private database
passwords.
Generate `JWT_SECRET` with:

```powershell
& 'C:\Wampoon\apps\php\php.exe' -n -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Paste the generated value into `.env`. Never commit `.env`; it is ignored by Git.

Validate the Compose configuration and start the services:

```powershell
docker compose config
docker compose up -d --build
docker compose ps
```

The API is available at `http://localhost:8080/` and phpMyAdmin at
`http://localhost:8081/`. Verify the initialized tables without changing data:

```powershell
docker compose exec db mariadb -u root -p -e "SHOW TABLES FROM artgateway_db;"
```

MariaDB prompts for the `MARIADB_ROOT_PASSWORD` from `.env`. Do not run
`docker compose down -v` to apply schema changes: `-v` deletes the persistent database
volume. If a volume already exists, first inspect and back it up. Import the schema
manually only into an empty `artgateway_db`; the SQL contains non-idempotent table
creation statements. Existing volumes retain their current database users and passwords;
the values in `.env` do not rotate them. Use the existing credentials when reusing a
volume, and do not assume a `slim_app` volume has been migrated to `artgateway_db`.

### Local Wampoon configuration

The default `APP_ROOT_DIR` remains `ArtGateway/artgateway-api` for the Wampoon URL path.
Set the following process environment variables before starting the API if your local
database differs from the example in `config/env.example.php`:

```powershell
$env:DB_HOST = 'localhost'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'artgateway_db'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
$env:JWT_SECRET = & 'C:\Wampoon\apps\php\php.exe' -n -r 'echo bin2hex(random_bytes(32));'
$env:APP_ROOT_DIR = 'ArtGateway/artgateway-api'
.\composer.bat run start
```

The local database must already contain the ArtGateway schema. Import
`data/sql/artgateway_db.sql` only into an empty `artgateway_db`.

## Automated tests

The test suite uses PHPUnit 11 (PHP 8.2+) and separates component/HTTP tests from
MariaDB-backed endpoint integration tests. `APP_ENV=test` loads only
`config/settings.test.php`; it does not read `config/env.php`, the root `env.php`, or
the normal `DB_*`/`JWT_SECRET` variables.

Install dependencies and run all tests (integration tests are reported as skipped if
the isolated MariaDB service is not running):

```powershell
.\composer.bat install
.\composer.bat test
```

Run only the tests that do not require MariaDB:

```powershell
.\composer.bat test:unit
```

Artwork endpoint tests use a separate MariaDB Compose file, a separate `127.0.0.1:3307`
port, a dedicated test account, and a MariaDB `tmpfs` data directory. It does not use or
mount the application database volume. The canonical schema is loaded into that
disposable database on container creation. Start it, run the integration tests, and
remove the container with:

```powershell
docker compose -f docker-compose.test.yml up -d --wait test-db
.\composer.bat test:integration
docker compose -f docker-compose.test.yml down
```

The database-backed integration tests create uniquely marked records and delete only the
rows they created. They verify the test-database marker before writing; if the service is
unavailable, the tests skip with setup guidance, and if the marker is missing they stop
instead of touching an unverified database. They also exercise persisted authentication
behavior and database constraints, including duplicate keys and foreign-key rejection.
The Art Institute client unit tests and Slim composite-route tests use Guzzle's
`MockHandler` with controlled fixture responses; they do not contact the real upstream
service. A passing mocked test is not evidence that the live Art Institute API is
available.

## How Do I Use/Deploy this Template?

### Option 1: Using Composer (Recommended)

1. Open a terminal in your web server's **document root** (i.e., `htdocs`).
2. Run the following command:
   ```bash
   composer create-project frostybee/slim-template [project-name]-api
   ```
   Replace `[project-name]` with your project name (e.g., `worldcup-api`).
3. Open your `[project-name]-api` folder in VS Code.
4. Adjust your database credentials in `config/env.php` (**see below**).

### Option 2: Using Docker (macOS/Linux/Windows)

Docker allows you to run the application in containers without installing PHP, Apache, or MariaDB locally. This works on **macOS**, **Linux**, and **Windows**.

**Prerequisites:**
- Install [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Install [Git](https://git-scm.com/downloads)

**Quick Start:**

1. Clone the repository (since Composer requires PHP, which you may not have installed):
   ```bash
   git clone https://github.com/frostybee/slim-template.git [project-name]-api
   ```
   Replace `[project-name]` with your project name (e.g., `worldcup-api`).

2. Navigate to the project folder:
   ```bash
   cd [project-name]-api
   ```

3. Remove the `.git` folder to start fresh with your own repository:
   ```bash
   rm -rf .git
   ```

4. Follow [ArtGateway Docker Setup](#artgateway-docker-setup) to create `.env` and start
   the application:
   ```bash
   docker compose up -d --build
   ```
   > **Note:** Use `docker-compose up -d --build` to rebuild images after modifying the Dockerfile or related configurations.

5. Access the application:
   - **App:** http://localhost:8080
   - **phpMyAdmin:** http://localhost:8081

**Configuring the Database:**

ArtGateway uses `artgateway_db`. Configure the database user and passwords in the private
`.env` file as described in [ArtGateway Docker Setup](#artgateway-docker-setup); do not
change the database name independently in Compose and PHP settings.

**Importing Database Schema:**

ArtGateway mounts `data/sql/artgateway_db.sql` as a MariaDB initialization script. MariaDB
imports it only when creating a new data directory.

Do not delete the database volume to rerun initialization. Existing volumes are preserved
and require a separately reviewed, backed-up migration or an import into a confirmed empty
database.

phpMyAdmin uses the root password from `.env`; no default database passwords are provided.

**Common Commands:**

| Action             | Command                        |
| ------------------ | ------------------------------ |
| Start containers   | `docker-compose up -d`         |
| Stop containers    | `docker-compose down`          |
| View app logs      | `docker-compose logs -f app`   |
| Rebuild containers | `docker-compose up -d --build` |

**Working with Multiple Projects:**

If you run **one project at a time**, no configuration changes are needed. Simply stop one project before starting another:

```bash
# Stop current project
docker-compose down

# Switch to another project
cd ../other-project
docker-compose up -d
```

If you need to run **multiple projects simultaneously**, change the port numbers in `docker-compose.yml` to avoid conflicts:

```yaml
services:
  app:
    ports:
      - "8082:80"      # Change 8080 to 8082, 8083, etc.
  db:
    ports:
      - "3307:3306"    # Change 3306 to 3307, 3308, etc.
  phpmyadmin:
    ports:
      - "8083:80"      # Change 8081 to 8083, 8084, etc.
```

### Option 3: Manual Installation

1. Download this repository as a `.zip` file.
2. Extract the downloaded `slim-template-main.zip` file locally.
3. Copy the `slim-template-main` folder into your web server's **document root** (i.e., `htdocs`).
4. Rename the `slim-template-main` folder to `[project_name]-api` (for example, `worldcup-api`).
5. Open your `[project_name]-api` folder in VS Code.
6. Install the project dependencies by running composer. If you are using Wampoon, open a terminal window in VS Code (hit ``` Ctrl+` ```) then run `.\composer.bat update`
   - If you are not using Wampoon to develop your app, just run composer from the command line.
7. In the `config` folder, make a copy of `env.example.php` and rename it to `env.php`.
8. Adjust your database credentials (**see below**).

**```NOTE:```** You can always clone this repository. However, if you do, you need to remove the ```.git``` ***hidden*** directory before you copy this template over to ```htdocs```

### Option 4: Using Nginx (Native Server)

If you're running Nginx directly on your server (not through Docker), configuration files are provided in the `nginx/` directory.

**Quick Start:**

1. Deploy your application to your server
2. Copy `nginx/slim-template.conf` to `/etc/nginx/sites-available/your-project-name`
3. Edit the configuration file to match your setup (domain, paths, PHP-FPM socket)
4. Enable the site:
   ```bash
   sudo ln -s /etc/nginx/sites-available/your-project-name /etc/nginx/sites-enabled/
   ```
5. Test and reload:
   ```bash
   sudo nginx -t && sudo systemctl reload nginx
   ```

**Important Notes:**

- Set document root to the `public/` directory (not the project root)
- Nginx doesn't use `.htaccess` files - all configuration is in the server config
- You need PHP-FPM installed and running

For detailed instructions, see [nginx/README.md](nginx/README.md).

## How Do I Configure My Database Connection?

Follow the outlined instructions in [config/env.example.php](config/env.example.php)

* Change the value of the `database` variable to reflect the name of the database to be used by your slim app.
* You may also want to change the connection credentials in that file.

## How do I Use Composer with Wampoon?

To install or update your project dependencies deployed on Wampoon, use the `composer.bat` script as follows:

| Action                | Command                            | Description                                                                     |
| --------------------- | ---------------------------------- | ------------------------------------------------------------------------------- |
| Install dependencies  | `.\composer.bat install`           | Installs packages listed in `composer.json` and creates the `vendor` directory. |
| Update dependencies   | `.\composer.bat update`            | Refreshes all packages to the latest versions allowed by `composer.json`.       |
| Add a package         | `.\composer.bat require [package]` | Installs a new package and adds it to `composer.json`.                          |
| Regenerate autoloader | `.\composer.bat dump-autoload -o`  | Rebuilds the optimized autoloader after adding or removing classes.             |

## On Using Environment Variables

Sensitive information used in app such as your database credentials, API key, etc. MUST not be pushed into your Git repo.

Do not use `.env` files for storing environment specific application settings/configurations. Dotenv [is not meant to be used in production](https://github.com/vlucas/phpdotenv/issues/76#issuecomment-87252126)

Just Google: "DB_PASSWORD" filetype:env
Alternatively, you can visit the following link: [Google env search](https://www.google.ch/search?q=%22DB_PASSWORD%22+filetype:env)

Instead, follow the instructions that are detailed in [config/env.example.php](config/env.example.php)

## Useful VS Code Keybindings

Below are keybindings that speeds up the insertion of special characters and keywords while editing a `.php` file. These bindings need to be added to your VS Code's keybindings.json

```json
  {
    "key": "alt+shift+p",
    "command": "type",
    "args": {
      "text": "=>"
    },
    "when": "textInputFocus && editorLangId == php"
  },
  {
    "key": "ctrl+shift+j",
    "command": "type",
    "args": {
      "text": "$this->"
    },
    "when": "textInputFocus && editorLangId == php"
  },
  {
    "key": "ctrl+shift+k",
    "command": "type",
    "args": {
      "text": "->"
    },
    "when": "textInputFocus && editorLangId == php"
  },
  {
    "key": "ctrl+shift+l",
    "command": "type",
    "args": {
      "text": "<?php"
    },
    "when": "textInputFocus && editorLangId == php"
  },
  {
    "key": "shift+enter",
    "command": "type",
    "args": {
      "text": "$"
    },
    "when": "textInputFocus && editorLangId == php"
  },
  {
    "key": "shift+space",
    "command": "type",
    "args": {
      "text": "_"
    },
    "when": "textInputFocus && editorLangId == php"
  }
  ```
