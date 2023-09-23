# About

Shehryar personal sites & projects.

## System requirements
You’ll want to ensure you have the following already installed on your local machine before getting started:
* [Docker](https://docs.docker.com/get-docker/)
* [ddev](https://ddev.com/get-started/)

## Setup Instructions
```shell
ddev start
```

### Setting up database
```shell
ddev import-db --file=mysql-backup/rn_chatapp.sql
```
### To use phpMyAdmin
```shell
ddev get ddev/ddev-phpmyadmin
ddev start
```

### Useful information
- Local DB Credentials are stored in [.ddev/.env](.ddev/.env)
- [Advance kubernates setup locally](ADVANCE.MD)
