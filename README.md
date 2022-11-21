# Test app
##### Работоспособность проверена на Fedora 36, docker v20.10.21 / docker-compose v1.29.2
Для запуска склонируйте репозиторий и запустите следующие команды:
- docker-compose up -d --build
- docker exec -it ovikus_testsymfonyapp_app composer require symfony/runtime
- docker exec -it ovikus_testsymfonyapp_app bin/console doctrine:migrations:migrate
- Все настройки докера кроме volume базы данных можно менять из .env файла

### Эндпоинты
- POST /api/user - сохранение пользователя, принимает параметры: username, email, password. Все параметры обязательны, о чем скрипт сообщит
- PATCH /api/user/{id} - изменение пользователя, принимает параметры: username, email, password. Обязательен хоть один параметр, о чем скрипт сообщит
- DELETE /api/user/{id} - удаление пользователя
- GET /api/user/{id} - выборка пользователя по id
- GET /api/users/ - выбрать всех существующих пользователей
- GET /api/users/query - поиск пользователей по параметрам. Принимает след. параметры (обязателен хоть один): username, email. Для выборки необходимо полное совпадение (не LIKE)