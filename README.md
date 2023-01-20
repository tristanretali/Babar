# S3.01 - E2 - Babar

## Setup

### Deploy local database

To deploy the database, you will need to have docker installed on your machine. You can find the installation guide [here](https://docs.docker.com/get-docker/).

Once docker is installed, you can run the following command to deploy the database in a docker container, including phpmyadmin.

```shell
cd database
docker-compose up -d
```

Once the databae is deployed, you can access it at localhost:3306 with the user root and password s3web.

Once in the administration interface, you need to create a database named `shows` and import the .sql.zip file in it.

> note: you have to disable ONLY_FULL_GROUP_BY, this can be done in mysql with the following:
> https://stackoverflow.com/questions/36829911/how-to-resolve-order-by-clause-is-not-in-select-list-caused-mysql-5-7-with-sel

- go to the Variables tabs

- search label "sql mode"

- edit the content and delete the mode : "ONLY_FULL_GROUP_BY"

- save

NB: don't forget to verify the comma separator

### Webpack stuff (for tailwind, even though it's overkill ik)

Run the following commands in the root folder of the project to install the required modules and build the project. Note that you will need to have nodejs installed on your machine. You can find the installation guide [here](https://nodejs.org/en/download/).

```
# install modules
npm i

# build
npm run build
```

### Setup and start symfony server

Run the following command to setup the project. You will need composer wich can be found here [here](https://getcomposer.org/download/).

```shell
# install dependencies
composer install
```

When you're ready, you can launch the symfony development server with the following command

```shell
symfony serve
```

### Create admin user

For creating a new admin user, use the following command in the project folder:

```shell
php bin/console app:add-admin
```

It will prompt you for a username, an email and a password, and will create an admin account accordingly.
You can then login with this newly created account.

### Create test users

You can create test accounts with this command.

```shell
php bin/console app:gen-users --users_num=4
```

This command will generate 4 users.

### Create test users with ratings

For creating ratings while creating users, you need to add --ratings_num with the specified amount.

```shell
php bin/console app:gen-users --users_num=4 --ratings_num=4
```

For example, this command will generate 4 test users with 4 ratings each, for a total of 16 ratings.

### Reset an user's password
An admin user is able to reset the password of any user, this is available on the website.
Note that by default, this will change its password to `sonic_the_hedgehog`

### Add a new series via OMDb API

For adding a new series with the OMDb API, enter this command:

```shell
php bin/console app:add-series --series_name="Series Name"
```

Where "Series Name" is the name of the series you want to search for.

### SGBD used

- mySQL

### Dev Setup

Here are the requirements for setting up the development enironment:

- Symfony
- Symfony VSCode extension (v1.0.2)
- Prettier VSCode extension (v9.10.3)
- Intelephense VSCode extension (v1.9.3)
- Conventional Commits VSCode extension (v1.25.0)
- Twig VSCode extension (v1.0.2)
- Tailwind CSS IntelliSense VSCode extension (v0.9.4)

## Dev Standards

- Class name use PascalCase rules
- Function name use camelCase rules
- Variable use snake_case rules
- Use W3C to validate the HTML and CSS
- PHP linter : PHP coding standards
- Twig linter : https://symfony.com/doc/current/templates.html#linting-twig-templates
- Tools to verify norms : Intelephense, Prettier
- Pipeline
