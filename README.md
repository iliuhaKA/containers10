# Лабораторная работа №10. Управление секретами в контейнерах

## Цель работы

Познакомиться с методами управления секретами в контейнерах Docker и научиться безопасно передавать конфиденциальную информацию (пароли, имена пользователей) между сервисами многосервисного приложения.

## Задание

Создать многосервисное приложение на базе проекта `containers08`, состоящее из трёх сервисов (`frontend`, `backend`, `database`), и настроить передачу учётных данных к базе данных через механизм Docker Secrets вместо открытых переменных окружения.

## Описание выполнения работы

### 1. Подготовка проекта

В новый каталог `containers10` было скопировано содержимое проекта `containers08` (Web-приложение на PHP с тестами). Так как в лабораторной работе №10 вместо SQLite используется MariaDB, из старого `Dockerfile` были удалены шаги, связанные с подготовкой SQLite-файла внутри образа (установка `sqlite3`, `libsqlite3-dev`, расширения `pdo_sqlite`, `VOLUME`, `COPY sql/schema.sql` и `RUN` с инициализацией `db.sqlite`).

### 2. Переписывание класса `Database`

Файл [site/modules/database.php](site/modules/database.php) был обновлён — конструктор теперь принимает три параметра вместо пути к SQLite-файлу:

```php
public function __construct(string $dsn, string $username, string $password) {
    $this->pdo = new PDO($dsn, $username, $password);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
```

### 3. Обновление `index.php`

В файле [site/index.php](site/index.php) строка создания объекта `Database` была заменена на формирование DSN-строки и передачу учётных данных:

```php
$dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['database']};charset=utf8";
$db  = new Database($dsn, $config['db']['username'], $config['db']['password']);
```

### 4. Конфигурационный файл `config.php`

Файл [site/config.php](site/config.php) теперь читает параметры подключения из переменных окружения, а имя пользователя и пароль — из файлов секретов, смонтированных в `/run/secrets/`:

```php
$config['db']['host']     = getenv('MYSQL_HOST');
$config['db']['database'] = getenv('MYSQL_DATABASE');
$config['db']['username'] = trim(file_get_contents('/run/secrets/user'));
$config['db']['password'] = trim(file_get_contents('/run/secrets/secret'));
```

> В методичке указана функция `get_file_contents`, но такой функции в PHP нет — правильное имя `file_get_contents`. Также добавлен `trim()`, чтобы отсечь возможный символ перевода строки, попадающий в пароль при сохранении файла.

### 5. Dockerfile

Расширение `pdo_sqlite` заменено на `pdo_mysql`:

```dockerfile
FROM php:7.4-fpm AS base

RUN apt-get update && \
    apt-get install -y libzip-dev && \
    docker-php-ext-install pdo_mysql

COPY site /var/www/html
```

### 6. Конфигурация nginx

Конфигурационный файл взят из `containers07` и помещён в `./nginx.conf`. В `docker-compose.yaml` он монтируется в контейнер `frontend` по пути `/etc/nginx/conf.d/default.conf`.

### 7. Защита секретов

В корне проекта создана папка `secrets/` с тремя файлами:

- `secrets/root_secret` — пароль суперпользователя MariaDB;
- `secrets/user` — имя пользователя базы данных;
- `secrets/secret` — пароль пользователя базы данных.

Файлы сохранены **без перевода строки в конце**, чтобы при чтении в пароль не попал символ `\n`.

### 8. Итоговый `docker-compose.yaml`

Объявлены секреты на верхнем уровне и подключены к сервисам `backend` и `database`. Открытых паролей в `environment` больше нет:

```yaml
services:
  frontend:
    image: nginx:latest
    ports:
      - "80:80"
    volumes:
      - ./site:/var/www/html
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - frontend
  backend:
    build:
      context: .
      dockerfile: Dockerfile
    environment:
      MYSQL_HOST: database
      MYSQL_DATABASE: my_database
      MYSQL_USER_FILE: /run/secrets/user
      MYSQL_PASSWORD_FILE: /run/secrets/secret
    secrets:
      - user
      - secret
    networks:
      - backend
      - frontend
  database:
    image: mariadb:latest
    volumes:
      - ./sql:/docker-entrypoint-initdb.d
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/root_secret
      MYSQL_DATABASE: my_database
      MYSQL_USER_FILE: /run/secrets/user
      MYSQL_PASSWORD_FILE: /run/secrets/secret
    secrets:
      - root_secret
      - user
      - secret
    networks:
      - backend

secrets:
  root_secret:
    file: ./secrets/root_secret
  user:
    file: ./secrets/user
  secret:
    file: ./secrets/secret

networks:
  frontend: {}
  backend: {}
```

### 9. Запуск и тестирование

Приложение запускалось командой:

```bash
docker compose up --build -d
docker compose ps
```

Все три контейнера (`frontend`, `backend`, `database`) успешно поднялись, страницы `http://localhost/?page=1`, `?page=2`, `?page=3` корректно отображают данные, прочитанные из MariaDB, — значит backend смог прочитать секреты из `/run/secrets/` и подключиться к базе.

### 10. Анализ безопасности образа через Docker Scout

После сборки выполнена команда:

```bash
docker scout quickview containers10-backend
```

Получен следующий отчёт:

```text
  Target             │  containers10-backend:latest  │    8C    46H    72M   181L     3?
  Base image         │  php:7-fpm                    │    8C    46H    73M   181L     3?
  Updated base image │  php:8-fpm                    │    0C     1H     1M    98L
                     │                               │    -8    -45    -72    -83     -3
```

В текущем базовом образе `php:7-fpm` обнаружены **8 Critical**, **46 High**, **72 Medium** и **181 Low** уязвимостей. Docker Scout рекомендует обновить базовый образ до `php:8-fpm` — тогда количество критических уязвимостей упадёт до нуля, а High — до 1. Основная масса уязвимостей обусловлена устаревшим PHP 7.4, снятым с поддержки.

## Ответы на вопросы

### Почему плохо передавать секреты в образ при сборке?

Секреты, «зашитые» в образ на этапе сборки (через `ENV`, `ARG` или `COPY`), сохраняются навсегда в слоях образа. Любой, кто получит доступ к образу — из публичного или приватного реестра, из кэша сборки, из файла, экспортированного через `docker save`, — сможет извлечь эти секреты командой `docker history` или распаковкой слоёв. Даже удаление секрета в последующем слое не помогает: предыдущий слой с ним остаётся в образе. Кроме того, образ может попасть в CI-артефакты, резервные копии, логи сборки — и секрет утечёт вместе с ним. Поэтому все пароли, ключи API и токены должны попадать в контейнер только во время его запуска, а не во время сборки образа.

### Как можно безопасно управлять секретами в контейнерах?

- Хранить секреты вне репозитория и вне образа (локальные файлы в `secrets/`, занесённые в `.gitignore`; внешние хранилища — HashiCorp Vault, AWS Secrets Manager, Azure Key Vault, Kubernetes Secrets).
- Передавать секреты в контейнер только в момент запуска — через файлы-секреты (`Docker Secrets`, `docker run --secret`, `BuildKit --mount=type=secret` для сборки), а не через `ENV`.
- Ограничивать доступ к секретам: монтировать только тем сервисам, которым они реально нужны, давать минимальные права чтения, использовать RBAC в оркестраторах.
- Вести ротацию паролей и ключей; не логировать содержимое секретов; не печатать их в отладочный вывод.
- Защищать канал связи между сервисами TLS, чтобы секреты не утекали по сети.

### Как использовать Docker Secrets для управления конфиденциальной информацией?

В `docker-compose.yaml` секреты объявляются на верхнем уровне и привязываются к сервисам, которым они нужны. В собственной работе это выглядит так:

1. Поместить чувствительные данные в отдельные файлы внутри каталога `secrets/` (каждый файл — один секрет).
2. Объявить их в верхнеуровневой секции `secrets:` с указанием пути к файлу (`file:`).
3. В каждом сервисе, которому нужен секрет, добавить список `secrets: [имя]`.
4. Внутри контейнера секрет автоматически появляется как файл в `/run/secrets/<имя>`, доступный только процессам этого контейнера.
5. Приложение читает его из файла (`file_get_contents`) или пользуется готовыми `*_FILE`-переменными MariaDB/MySQL/Postgres — образ сам прочитает файл по указанному пути.

Такой подход не оставляет секретов ни в слоях образа, ни в переменных окружения хоста, ни в `docker inspect` контейнера (значение секрета там не отображается), что значительно повышает безопасность.

## Выводы

В ходе работы многосервисное приложение (`nginx` + `PHP-FPM` + `MariaDB`) было переведено со встраивания SQLite в образ на полноценное внешнее хранение данных в отдельном контейнере БД. Учётные данные для подключения вынесены из открытых переменных окружения в Docker Secrets — их значения не попадают в образ, не видны в `docker inspect` и не сохраняются в истории слоёв. Анализ образа через `docker scout quickview` показал, как инструменты статического анализа помогают выявлять уязвимости и рекомендуют обновить базовый образ. Работа закрепила навыки безопасной передачи конфиденциальных данных между контейнерами и показала практический сценарий применения механизма Docker Secrets.
