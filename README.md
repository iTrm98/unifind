# UniFind

UniFind is a campus lost-and-found website for Umm Al-Qura University. Students use it to report items they have lost and to check what has been found on campus. The Lost & Found Office admin uses it to register found items, match them to students' requests and update each case. Every case is tracked in one place instead of in scattered group chats.

## What each user can do

| Student | Admin (Lost & Found Office) |
|---|---|
| Browse found items | Everything the student can do |
| Report a lost item | Register found items |
| Follow my requests and their status | Mark requests found, returned or closed, with a note and a matched item |
| Edit my request while it is open | Remove and restore items |
| | Dashboard |
| | Database page |
| | Activity log |

## Requirements

- PHP 8.4 with the extensions pdo_mysql, mbstring, openssl, fileinfo, curl, zip and gd
- Composer
- Node.js 22 or newer
- MySQL on port 3306

## First-time setup

1. Create the database in MySQL:

   ```sql
   CREATE DATABASE unifind1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Install the backend and create its settings file:

   ```powershell
   cd backend
   composer install
   copy .env.example .env
   ```

3. Open `backend\.env` and set `DB_PASSWORD` (your MySQL root password), `ADMIN_PASSWORD` and `STUDENT_PASSWORD`, then save the file.

4. Prepare the backend:

   ```powershell
   php artisan key:generate
   php artisan migrate --seed
   php artisan storage:link
   ```

5. Install the frontend:

   ```powershell
   cd ..\frontend
   npm install
   ```

## Run the app

Open two terminals.

Terminal 1:

```powershell
cd backend
php artisan serve
```

Terminal 2:

```powershell
cd frontend
npm run dev
```

Open http://localhost:5173 (use `localhost`, not `127.0.0.1`).

## Login accounts

| Role | Email | Password |
|---|---|---|
| Admin | admin@uqu.edu.sa | `asd` |
| Student | student@uqu.edu.sa | `asd` |

These are the passwords set in `backend\.env`. If you change them there, run `php artisan migrate:fresh --seed` to apply them (this resets all data).

To create more users, sign in as admin and use the Database page.

## Database

The database is `unifind1` on 127.0.0.1:3306, user `root`.

To look at it, open it in MySQL Workbench, or sign in as admin and use the Database page in the app.

`php artisan migrate:fresh --seed` resets everything to just the two accounts, the categories and the locations.

## Deploy

The free setup is: frontend on GitHub Pages, API on Render, database on Aiven MySQL, photos on Cloudflare R2. Local development does not change.

1. **GitHub.** Push the project to https://github.com/iTrm98/unifind. GitHub Pages on a private repository needs GitHub Pro (free with the GitHub Student Developer Pack); otherwise make the repository public.

2. **Aiven (database).** Create the free MySQL service. From its overview page copy the host, port, user, password and database name. The CA certificate is already in the project at `backend/storage/certs/ca.pem`, so there is nothing to download.

3. **Cloudflare R2 (photos).** Create a bucket and turn on public access for it (the `r2.dev` public URL). Then create an R2 API token with read and write access to that bucket. Copy the Access Key ID, the Secret Access Key, the S3 endpoint (`https://<account-id>.r2.cloudflarestorage.com`) and the bucket's public URL (`https://pub-....r2.dev`).

4. **Render (API).** Create a New Web Service from the repository with root directory `backend`, language Docker and the Free instance type. Under Environment, add every variable from `backend/.env.production.example`:
   - `APP_KEY`: run `php artisan key:generate --show` in `backend` and paste the output.
   - `ADMIN_PASSWORD` and `STUDENT_PASSWORD`: strong new passwords, not the local ones.
   - `DB_*`: the Aiven values from step 2. `MYSQL_ATTR_SSL_CA`: `/var/www/html/storage/certs/ca.pem`.
   - `AWS_*`: the R2 values from step 3. `FRONTEND_URL`: `https://itrm98.github.io`. `SANCTUM_EXPIRATION`: `720`.

   Deploy. Each start runs the migrations and the seeder, so the tables and the two accounts are created automatically. When the first deploy is done, set `APP_URL` to the Render URL (for example `https://unifind-api.onrender.com`) and deploy again. Check that `https://<render-url>/up` shows the green "Application up" page.

5. **GitHub Pages (frontend).** In the repository open Settings > Pages and set Source to GitHub Actions. In Settings > Secrets and variables > Actions > Variables, add `VITE_API_URL` = `https://<render-url>/api`. Open the Actions tab, run "Deploy frontend to GitHub Pages" (it also runs on every push to `main` that changes `frontend/`), then open https://itrm98.github.io/unifind/.

6. **Notes.** The free Render service sleeps when nobody uses it, so the first visit after a while can take about a minute. The free Aiven service can be powered off after a period of inactivity; turn it back on in the Aiven console.
