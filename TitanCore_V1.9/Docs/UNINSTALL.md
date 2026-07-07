# Uninstall notes

- `php artisan titancore:uninstall` removes **menu entries** and **permissions** but **keeps data tables**.
- To drop TitanCore data tables, write and run dedicated down migrations or a safe cleanup command. Avoid destructive ops in production.
