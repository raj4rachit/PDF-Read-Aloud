<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## 🐙 Installation

First, Clone this Repo into your local machine

```php
git clone https://github.com/raj4rachit/PDF-Read-Aloud.git
```

Rename to .env.example file to .env file and add related information like Database, App URl, etc..

After run this command

```php
composer install
```

Run Database migration

Next, please add these keys to your `.env` file:

```env
GOOGLE_APPLICATION_CREDENTIALS="Path to your google cloud application credential json file"
```

Authentication Please see our <a href="https://github.com/googleapis/google-cloud-php/blob/main/AUTHENTICATION.md">Authentication guide</a> for more information on authenticating your client.

## 📄 License

The MIT License (MIT). Please see <a href="https://opensource.org/license/MIT">LICENSE</a> for more information.
