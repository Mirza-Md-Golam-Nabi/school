## About this Project



## ✅ Project Setup Instructions

1. Clone the repository

For Local Machine:

```sh
git clone https://github.com/Mirza-Md-Golam-Nabi/school.git
```

For Live Server or cPanel:

```sh
git clone https://github.com/Mirza-Md-Golam-Nabi/school.git .
```

2. Goto project folder

```sh
cd school
```

3. Install dependencies using Composer

```sh
composer install
```

If you want to install it in cPanel, first check **composer** is install or not. For checking:

```sh
composer -v
```

If you see "**Composer Not Found**", you need to install Composer on your system.
Follow this guide [Composer install in cPanel](https://github.com/Mirza-Md-Golam-Nabi/tips/blob/master/laravel/composer/README.md#composer-install-in-cpanel-%EF%B8%8F)

4. Create the **.env** file

Copy the example environment file:

```sh
cp .env.example .env
```

5. Run this command:

```sh
php artisan key:generate
```

```sh
php artisan webpush:vapid
```

6. Create the database

Create a database named:

```sh
school
```

7. Run migrations and seeders

Run the following command to migrate and seed the database:

```sh
php artisan migrate --seed
```

8. Run the application

```sh
npm install
```

and

```sh
npm run build
```

and

```sh
php artisan serve
```

## 🔑 Default Login Credentials

When the seeder file is executed, default users are created in the `users` table for all three panels — **Admin**, **Teacher**, and **Student**.

| Panel   | Email                                             | Password   |
| ------- | -------------------------------------------------- | ---------- |
| Admin   | `admin@example.com`   | `password` |
| Teacher | `teacher@example.com` | `password` |
| Student | `student@example.com` | `password` |

> ⚠️ These are default seeder credentials meant for local/testing environments only. Make sure to change them before deploying to production.

