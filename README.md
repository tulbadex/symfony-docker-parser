# News Parser Service

An automated news parsing service built with Symfony 5.4 that fetches and stores news articles from external sources. Features parallel processing via RabbitMQ and role-based access control.

## Features

- Automated news parsing with parallel processing
- Role-based authentication (Admin/Moderator)
- Article management with pagination
- Duplicate detection and update tracking
- Optimized database queries for high load
- CLI commands for manual parsing
- Automated parsing via cron

## Prerequisites

- PHP 7.4
- Docker & Docker Compose
- MySQL
- RabbitMQ
- Composer

## Tech Stack

- Symfony 5.4
- Bootstrap 5.1
- MySQL
- RabbitMQ
- Docker

## Installation

1. Clone the repository:
```bash
git clone https://github.com/tulbadex/symfony-docker-parser.git
cd news-parser
```

2. Build and start Docker containers:
```bash
docker-compose up -d --build
```

## Configuration

1. Copy `.env` to `example.env` and configure:
```bash
cp .env example.env
```

2. Update these variables in `example.env`:
```
DATABASE_URL=
RABBITMQ_URL=
APP_SECRET=
```

## Usage

### Running the Parser

Manual execution:
```bash
php bin/console app:parse-news
```

Setup cron job:
```bash
* * * * * php /path/to/project/bin/console app:parse-news
```

### Accessing the Web Interface

1. Create an admin user:
```bash
### create an admin account, you pass the email, password and the role
docker-compose exec php php bin/console app:create-user test@example.com password123 admin

### create a user account, you pass the email, password and the role
docker-compose exec php php bin/console app:create-user test@example.com password123 user
```

2. Visit `http://localhost` and login

## Docker Services

- Web: `localhost`
- MySQL: `localhost:3308`
- RabbitMQ Management: `localhost:15672`

## License

MIT License