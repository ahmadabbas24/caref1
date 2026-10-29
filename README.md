# CareFirst Home Nursing Website (with accounts, blog database and comments)

Website for CareFirst Healthcare Services (Pvt) Limited (Karachi, Lahore and Islamabad / Rawalpindi).

This version needs **PHP 8 + MySQL** (XAMPP on your computer, or any cPanel hosting).
It no longer runs on GitHub Pages, because GitHub Pages cannot run PHP or a database.

## What's new
- **Sign up with email.** A new user gets an email with a "Confirm my email" link. Only after clicking it is the account active.
- **Log in / log out.** Only confirmed users can log in.
- **Comments.** Logged-in users can comment on every blog article. Users can delete their own comments; admins can delete any comment.
- **Admin blog form** (`admin.php`). Admins write a title, category, description and upload an image, then press Enter or **Publish**. Posts are saved in MySQL and appear first under *Latest blogs*. Admins can also edit and delete posts.
- **Everything is set up from the website (v5).** The first signup becomes admin, email (Gmail) is set up on
  Admin > Email settings, and users are managed on Admin > Users. No editing of `config.php` needed.

## Set up on XAMPP (step by step)
1. Copy this whole folder into `C:\xampp\htdocs\` (for example `C:\xampp\htdocs\carefirst-website`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **http://localhost/phpmyadmin** > **Import** tab > choose `database.sql` > **Go**.
   This creates the `carefirst` database with tables `users`, `blog_posts`, `blog_comments`, `settings`,
   plus the 3 existing articles.
4. Visit **http://localhost/carefirst-website/signup.php** and create your account.
   **The first account on a new website becomes the admin straight away** (no email needed).
5. You land on **Admin > Email settings**. Choose **Send real emails**, enter your Gmail address and
   Gmail **App Password**, and press **Save and send test email**. Check your inbox.

That's it: you never need to edit `config.php` unless your MySQL username or password is not the XAMPP default.

## Ready-made admin account (optional)
Instead of signing up, you can import `make-admin.sql` in phpMyAdmin (after `database.sql`).
It creates this admin login:
- Email: ahmadabbastuts@gmail.com
- Password: pakistan123

Change this password before the website goes live.

## Admin pages
- **Blog posts** (`admin.php`): write, edit and delete articles.
- **Users** (`admin-users.php`): see everyone who signed up, make or remove admins, confirm an account
  by hand, resend the confirmation email, delete users.
- **Email settings** (`admin-settings.php`): the email account the website sends from. The App Password is
  stored encrypted (the key is created automatically in `storage/app.key`). The page also checks that
  PHP OpenSSL is on and shows whether the last test email worked.

## Getting a Gmail App Password
1. Go to myaccount.google.com/security, sign in, and turn on **2-Step Verification**.
2. Open myaccount.google.com/apppasswords, type **CareFirst**, press **Create**, and copy the 16-letter code.
3. Paste it into Admin > Email settings.

On localhost the link inside the email starts with `http://localhost`, so open it on the same computer.

## Upgrading from v4
Copy the new files over the old folder. You don't need to import anything again: the `settings` table is
created automatically. If your site has no admin yet, just log in with your existing account and it becomes
the admin.

## Writing a post
In the Description box:
- Leave an empty line between paragraphs.
- Start a line with `## ` for a heading.
- Start a line with `- ` for a green tick list.
- Wrap words in `**double stars**` for bold.

Images: JPG, PNG, WEBP or GIF up to 4 MB, saved in `uploads/blog/`.

## Pages
- `index.html`, `services.html`, `equipment.html`, `why-us.html`, `hiring.html`, `appointment.html`, `contact.html`: same as before
- `blog.php`: Latest blogs, read from the database (`blog.html` now just forwards here)
- `post.php?id=…`: one article with its comments
- `signup.php`, `login.php`, `logout.php`, `verify.php` (email link), `resend.php` (send the link again)
- `admin.php`, `admin-users.php`, `admin-settings.php`: admin pages (admins only)

## Folders
- `css/style.css`: all styles and animations
- `js/script.js`: menu, scroll effects and forms
- `images/`: logo and photos
- `includes/`: shared PHP (database, login, email, page header and footer)
- `lib/PHPMailer/`: email library (included, no Composer needed)
- `uploads/blog/`: images uploaded by admins (must be writable on live hosting)

## Security built in
Passwords are hashed (`password_hash`), all database queries use prepared statements, every form has a
CSRF token, confirmation links are random, stored hashed and expire after 24 hours, uploads are checked to be
real images and given random names, and the `.htaccess` files block access to `config.php`, `includes/`,
`lib/`, `storage/` and scripts inside `uploads/`. Only admins can open the admin pages,
and an admin cannot delete or demote their own account.

## Going live (cPanel hosting)
Upload the folder, create a MySQL database and user in cPanel, import `database.sql` in phpMyAdmin
(remove the first two lines, `CREATE DATABASE` and `USE`, if your host gives the database a different name),
then put the database name, user and password into `config.php`.
