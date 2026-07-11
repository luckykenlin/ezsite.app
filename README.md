# Website Generator
This repository is a simple website generator built with the [Filament PHP](https://filamentphp.com/).
## Tech stack

- Laravel
- FilamentPHP
- Tailwind
- AlpineJs
- Livewire

## Local Installation

1. Clone the repository

```bash
git clone git@github.com:luckykenlin/ezsite.git
```

2. Run the following commands -

```bash
composer install #installing php dependencies

npm install # installing the JS dependencies

npm run build # to build the frontend assets

cp .env.example .env # copy env file
```

3. Now run the following command to create the required tables in database -

```bash
php artisan key:generate

php artisan migrate

php artisan storage:link
```

Optionally, you can create the dummy data by running the seeder as -

```bash
php artisan db:seed
```
