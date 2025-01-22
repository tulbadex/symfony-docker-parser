
# Initial setup

## Create proect directory
```bash
mkdir news-parser
cd news-parser
```
## Install Symfony
```bash 
composer create-project symfony/website-skeleton:5.4.* .
```

## Install Additional Dependencies If Not Yet Installed
```bash 
composer require symfony/security-bundle
composer require symfony/form
composer require symfony/validator
composer require symfony/messenger
composer require symfony/amqp-messenger
composer require php-amqplib/php-amqplib
composer require symfony/twig-bundle
composer require symfony/asset
composer require symfony/dom-crawler
composer require symfony/http-client
composer require --dev symfony/profiler-pack
composer require symfony/rate-limiter
composer require sensio/framework-extra-bundle
```

## Project Structure

news-parser/
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   └── php/
│       └── Dockerfile
├── src/
│   ├── Command/
│   ├── Controller/
│   ├── Entity/
│   ├── Repository/
│   ├── Security/
│   └── Service/
├── templates/
├── .env
├── docker-compose.yml
└── symfony.lock

## Configuration Environment
Create .env.local

## Database Setup
Create migration:
```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## to install amq
Check if your php version is thread safe or non thread safe
```bash
php -i|findstr "Thread"
```
Next, proceed:

1. Download the right version of the extension https://pecl.php.net/package/amqp
2. After download, copy rabbitmq.4.dll and rabbitmq.4.pdb files to PHP root folder and copy php_amqp.dll and php_amqp.pdb files to PHP\ext folder
3. Add extension=amqp to the php.ini file
4. Check if is everything OK with php -m

## Configure Cron Job
Create config/packages/messenger.yaml

## Now for the Docker commands to start and run the project:
1. First, make sure you're in the project root directory
2. Build and start the containers:
```bash
# First time build
docker-compose build

# Start the containers
docker-compose up -d

# Check the status
docker-compose ps

# View logs
docker-compose logs -f
```
3. Set up the database and load fixtures
```bash
# Enter PHP container
docker-compose exec php bash

# Inside the container:
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

Make sure all files have Unix line endings (LF instead of CRLF). If you're using Windows, you might need to configure Git to handle this:

```bash
git config --global core.autocrlf false
```

## Then, in your project directory, run:
```bash
# Remove old containers and images
docker-compose down -v
docker-compose down --volumes --remove-orphans
docker-compose rm -f
docker-compose build --no-cache
docker-compose up -d
```

## Show required extensions
```bash
docker-compose exec php php -m
```