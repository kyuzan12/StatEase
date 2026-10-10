# StatEase

**A Laravel MVC-based survey data analysis tool for students and researchers.**

Upload survey responses (CSV or Excel, e.g. from Google Forms) and StatEase computes thesis-ready results:
weighted mean with verbal interpretation and rank, profile of the respondents (frequency and percentage),
charts, Cronbach's alpha, chi-square, Pearson r and one-way ANOVA — each with an auto-written interpretation,
a printable layout and a Word report.

Final project for **System Integration and Architecture**.

## Requirements

- PHP 8.2 or newer with the `zip`, `fileinfo`, `mbstring`, `pdo_mysql` and `openssl` extensions (XAMPP includes them; turn on `extension=zip` in `php.ini` if needed)
- Composer 2
- MySQL or MariaDB (XAMPP)
- Internet connection for the Bootstrap, Chart.js and font files (loaded from a CDN)

## Setup

1. Start **MySQL** in the XAMPP Control Panel and create an empty database named `statease_db`.
2. In the project folder, install the PHP packages and create the settings file:

   ```bash
   composer install
   copy .env.example .env
   php artisan key:generate
   ```

3. Create the tables, using **one** of these:
   - **Fresh start** (built-in Likert scales only): `php artisan migrate`
   - **With the sample data:** import `database/statease_db.sql` into `statease_db` (phpMyAdmin → Import)
4. Run the app and open http://127.0.0.1:8000

   ```bash
   php artisan serve
   ```

5. Click **Create an account**. Each user sees only their own surveys. If you imported the sample data,
   the **first account you register** becomes the owner of the sample surveys.

## Sample files

- `public/samples/` — CSV files (also downloadable from the upload page)
- `test-files/library-satisfaction-40.xlsx` — an Excel file (use the "5-point Satisfaction" scale)

## Tests

```bash
php artisan test
```

48 automated tests check the formulas (interpretation ranges, Slovin, p-values against statistical tables),
the workflow (upload, validation, column types, results, tests, report), and accounts and privacy
(login, registration, and that users cannot open each other's surveys). They use a temporary
in-memory database, so your data is not changed.

## Where things are (MVC)

| Part | Folder |
| --- | --- |
| Models | `app/Models` — User, Survey, SurveyColumn, Respondent, LikertScale, LikertScaleLevel |
| Views | `resources/views` — Blade templates |
| Controllers | `app/Http/Controllers` |
| Validation | `app/Http/Requests` |
| Access rules | `app/Policies` — who may view, change or delete a survey or scale |
| Computations | `app/Services` — statistics, reliability, tests, interpretations, file import |
| Routes | `routes/web.php` |
| Database | `database/migrations`, `database/seeders`, `database/statease_db.sql` |
