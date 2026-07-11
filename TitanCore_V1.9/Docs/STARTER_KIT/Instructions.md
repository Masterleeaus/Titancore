# Instructions

1) Verify root signals (composer.json, artisan).
2) Run `composer install` (if applicable) and `php artisan config:cache`.
3) Place AI provider keys in `.env` (see `config/ai.php` stub).
4) Import routes from this module under `routes/` as needed.
5) Use the Meta-Prompt to run the next conversion/cleanup pass.
