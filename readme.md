# Простой PHP сервер для RestApi

## Настройка

### Создайте базу

Установите SQL базу данных на своей сервер

Через консоль зайдите в управления

```
sudo mysql -u root -p
```

Пример создания базы, напишите имя базы 'tasks_app'

```
CREATE DATABASE tasks_app;
```

Откройте базу

```
USE tasks_app;
```

Создайте не обходимые таблицы например users и tasks

```
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    login VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    friends TEXT,
    hash VARCHAR(255) NOT NULL,
    referal VARCHAR(255)
);

CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    text TEXT NOT NULL,
    completed BOOLEAN NOT NULL DEFAULT 0,
    executor VARCHAR(255),
    founder VARCHAR(255)
);
```

### Подключите базу к апи серверу

Просто знполните настройки базы в includes\settings.php
