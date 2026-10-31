# 1. Getting Started

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Under Docker, `.env` overrides docker-compose environment (Dotenv `createUnsafeMutable`): set `DB_HOST=mysql` and `APP_ROOT=` in `.env`. Ports 8080/8077/3306 may clash with other projects (gotchas G11/G12).
> - `caligrafer initialize` keeps the keys of a signed project but still deletes `.git` (G25). `app` = Vue as a library, `create` = Vite SPA.

> ## Requirements
> + PHP 8.3 or higher (8.5 recommended)
> + MySQL 8.4 (or another database supported by PDO)
> + A Apache/MySql/PHP server already installed locally OR Docker
> + Git and Composer
> + curl, mbstring, openssl, sodium, gd, bcmath, intl, pdo_mysql, and the Apache rewrite and headers modules must be enabled in your servers
> + Node.js 20.19+ or 22.12+ for the Vue Single Page Application template

> #### (video) [Introduction to Caligrafy](https://youtu.be/zTqbWlTPrNQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

> ## Join the Caligrafy Community
> There are several ways for you to interact with the Caligrafy community
> + **github:** You can always use github to stay up to date with the roadmap of Caligrafy, to post issues and to track when feature requests and issues are done
> + **slack:** Joining our slack group is a great way to exchange with other members of the community, to get help on using the framework and to discuss any issues or features.
[Join our slack community](https://join.slack.com/t/caligrafy/shared_invite/enQtNzI4MDY2OTA4MTgzLTI2NDc2NTVmMDNlMWQ5YWYxN2RjZTkwZjdiNjM5ZTg3NjQ2YWYyMzRmZDgzNWE0Nzc4YjQyODM2NDNkNjQ2OTU)
> + **facebook Caligrafy Group:** Joining our Caligrafy group on facebook gives you more ways to interact with the community and to share success stories. [Join our facebook group](https://www.facebook.com/groups/caligrafy/)

## Installation

### Quick Installation (MAMP/LAMP/XAMP)

> #### (video) [Installing Caligrafy](https://youtu.be/21Lazd38FfQ)
>
> In this video we show you how to install Caligrafy on a local Apache/MySQL/PHP server.

+ Run the following code from the terminal to get the latest version of Caligrafy

    ```bash

    composer create-project caligrafy/caligrafy-quill <optionally specify a folder name. default: caligrafy-quill>

    ```

    This installs the latest release (see the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md)). Add `-s dev` to get the unreleased `master` branch instead.

+ Start the local Apache server. [Set up a local server](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#build-your-own-local-apachemysqlphp-server-to-run-caligrafy) Or use the [Docker installation](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#using-docker) 

+ Run the following code from the Caligrafy root folder to initialize the framework

    ```bash

    php caligrafer.php initialize

    #or

    .bin/caligrafer initialize

    ``` 

    It signs the project with an `APP_KEY` and an `API_KEY`, creates the `application` folder, sets `APP_ROOT` to the name of the folder and deletes the `.git` folder.

    >
    > Note that the Caligrafer Initialize command breaks all `git` links. 
    > - If you would like to create a `git` repo for your application, it is recommended that you do so after you initialize it. 
    > - If you are pulling an application built in Caligrafy from Github and you would like to preserve the `git` connection in order to pull updates regularly, it is recommended that you proceed with the manual installation instead.
    >

+ Add/Modify the following to the .env file if not accurate: `APP_ROOT=<caligrafy root folder. default: caligrafy-quill>`

+ You are good to go! If the quick installation does not complete successfully, proceed with the manual installation

**Testing the Installation**
+ You can test if the framework is working by visiting `http://localhost:<server port, default 80>/<caligrafy root folder. default: caligrafy-quill>` in the browser. 

### Using Docker

> #### (video) [Installing Caligrafy using Docker](https://youtu.be/33TKktyxto4)
>
> In this video we show you how Caligrafy can be installed and run without setting up any development environment.

This method requires Docker to be installed on your local machine. With Docker, you no longer need to go through the hassle of having an Apache server installed locally to run PHP and MySQL. The stack runs PHP 8.5 with Apache (`php:8.5-apache`), MySQL 8.4 LTS and phpMyAdmin 5.2.3.

+ If you have PHP and Composer installed on your machine, check the `Quick installation`.

+ If you don't have Composer then pull the code from github (You can either clone the repo or download the zip file)

+ Make sure Docker is installed and is running on your machine

+ Run the following code from the Caligrafy root folder to start the Caligrafy server

    ```bash
    .bin/caligrafer server start
    ``` 

    + **With PHP and Composer on your machine**, `.bin/caligrafer` installs the dependencies and initializes the project when needed, then starts the `dev-box` container with your folder mounted into it: your changes are live. Check that `.env` has `APP_ROOT=` (blank). (`php caligrafer.php server start` alone does nothing: the command is meant to be called through `.bin/caligrafer`. Without it, run `docker compose up -d dev-box`.)
    + **Without PHP**, `.bin/caligrafer` runs `.bin/server`, which starts the `prod-box` container: the code is copied into the image, `.env` is created from `framework/settings/.env.docker` (`APP_ROOT=caligrafy-quill`) and the image is rebuilt when files change. The site is then at `http://localhost:8080/caligrafy-quill`.

+ **How long it takes.** The first start builds the server image: it downloads a few system libraries and compiles the PHP extensions Caligrafy needs (gd, intl, zip, pdo_mysql, bcmath), which takes about 5 minutes. Every later start reuses the image and takes a few seconds; a new project on the same machine reuses Docker's build cache.
+ Run `server rebuild` to rebuild the image on purpose (after a Dockerfile change, or to update its system packages); `server start` never rebuilds an image that already exists.
+ Run `server stop` instead of `server start` to stop the server at all time

**Testing the Installation**
+ Once started, you can access the website locally via `http://localhost:8080` (`http://localhost:8080/caligrafy-quill` with `prod-box`)

**Testing the Database provided in Docker**
+ In both commands, a `phpmyadmin` instance  has been added and can be accessible at `http://localhost:8077/`
+ This is a web client for MySQL. It will allow you easy access to your Database.
     - Username: `root`
     - Password: `root`

### Manual Installation
+ Pull the code from github (You can either clone the repo or download the zip file)
+ It is recommended to place the repo at the Server Document Root level
+ Go to the downloaded repo and create a .env file by copying the example `cp .env.example .env`
+ Create an `APP_KEY` and an `API_KEY` in the .env file. You can use Caligrafer to generate API keys for you by running `php caligrafer.php generatekeys` and adding the generated keys to the .env file
+ Add the following to the .env file if not present: `APP_ROOT=<caligrafy root folder. default: caligrafy-quill>`. If caligrafy is not installed at the Server Document Root level, refer to the [Different Root Folder instructions](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#different-root-folder).
+ Change the other values in .env file to match your local or production server settings
+ Run `composer install` to get all the dependencies needed
+ Initialize the application folder by running the following `cp -r framework/settings/application ./application`. Unlike `initialize`, this copy writes no `application/.caligrafy-manifest.json`: `php caligrafer.php upgrade` then recognizes your untouched files from the template history instead
+ Make the folder `/public/uploads/` writable if you intend to allow uploads in your application. You will need to run the command `chmod -R 777 public/uploads` from the Caligrafy root
+ You are good to go!

### Testing the Installation
+ You can test if the framework is working by visiting `http://localhost:<server port, default 80>/<caligrafy root folder. default: caligrafy-quill>` in the browser. 

### Different Root Folder

#### The default root folder is `caligrafy` or `caligrafy-quill` (depending on what version of caligrafy you got). To change the name of this root folder:
+ Rename the folder `caligrafy` to `your-app-name` OR `git clone <caligrafy git repository> your-app-name`
+ Change the `APP_ROOT` entry in the `.env` file to `your-app-name`

#### Placing the Root Folder in subfolders within the Server Document Root
If you decide to place the Caligrafy repo in subfolders within the Server Document Root, you will need to modify the .env file accordingly regardless whether you picked the automatic or manual installation:
+ Change/Add `APP_ROOT` entry in the `.env` file to the `full path leading to the caligrafy root folder from server document root`

### Caligrafer
Caligrafer is a command line tool that comes prepackaged with several tools and utilities that help accelerate your work, here are some of the commands:

+ **Caligrafer help:** At all time you can know what commands are available to you by running ```php caligrafer.php``` from the Caligrafy root.

+ **Framework Initialization:** `.bin/caligrafer` initializes the project the first time you use it.
You can also run ```php caligrafer.php initialize``` from the Caligrafy root. It signs the project with an `APP_KEY` and an `API_KEY` (an already signed project keeps its keys), creates the `application` folder and **deletes the `.git` folder**.
After updating the framework of an existing project, run ```php caligrafer.php upgrade``` (dry run) then ```php caligrafer.php upgrade --apply``` to bring the template files of `application` up to date: untouched files are replaced after a backup in `backups/caligrafy-upgrade-<timestamp>/`, customized files are kept and get a `<file>.caligrafy-new` version next to them, and `php caligrafer.php upgrade --force <application/path>` replaces one customized file (see [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading)).

+ **Version:** run ```php caligrafer.php version``` (or `.bin/caligrafer version`) from the Caligrafy root to print the version of Caligrafy Quill that the project runs, for example `Caligrafy Quill 2.0.0`. It works before `composer install` and `initialize`, and creates nothing. In code, the version is `Caligrafy\Framework::VERSION`, and `doctor` shows it on its first line. Caligrafy follows [semantic versioning](https://semver.org): every release is a git tag `v<version>`, listed with its changes in the [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md).

+ **Build once commands** (learn more in [11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)):
    + ```php caligrafer.php platform install```: creates the framework tables (personal access tokens, audit log)
    + ```php caligrafer.php service Books/CreateBook --scope books:write```: creates a capability in `application/services/`
    + ```php caligrafer.php capabilities```: lists the capabilities (`--json`, `--markdown`)
    + ```php caligrafer.php client```: writes the JS client of the capabilities for the Vue apps (`public/js/capabilities.js` by default)
    + ```php caligrafer.php token create <name> --scopes books:read```, `token list`, `token revoke <id>`: personal access tokens
    + ```php caligrafer.php mcp```: an MCP server over stdio for AI agents on your machine
    + ```php caligrafer.php audit```: the latest capability calls

+ **Project health check:** run ```php caligrafer.php doctor``` (or `.bin/caligrafer doctor`) from the Caligrafy root. It is read-only: it checks PHP and its extensions, `vendor/`, `.env`, the `APP_KEY`/`API_KEY`, `public/uploads`, the web app manifest and its icons (and the service worker files when `PWA_SERVICE_WORKER=true`), whether `application` is up to date with the framework templates, the database connection (when `DB_ACTIVATE=true`) and every service configured in `.env`: OpenAI and Groq (the key is accepted and the configured models are available for it), the provider of the open source `Agent` (`AGENT_PROVIDER`: Together AI, Fireworks AI, OpenRouter, a custom endpoint, or Ollama: the server answers and the models are pulled, with the `host.docker.internal` hint when PHP runs in Docker), Stripe (key accepted, test or live mode), Coinbase Business, Watson and the SMTP server (connect and `EHLO` only, no mail is sent). Each line is `OK`, `WARN`, `FAIL` or `SKIP` (not configured) with a one-line fix. Secrets are never printed (at most `sk-proj-…EZ8A`), and a key pasted with spaces or a missing `-`/`_` after its prefix is reported. Network errors are explained: a `self-signed certificate in certificate chain` error means a corporate TLS inspection proxy (see the Claude plugin `server` skill for the Docker fix).
    + `--only openai,groq` limits the checks (`project`, `database`, `openai`, `groq`, `agent`, `stripe`, `coinbase`, `watson`, `mail`), `--json` prints machine-readable results, `--env .env.production` checks another environment file. The exit code is `1` when a check fails.
    + With Docker, run it in the container so that `DB_HOST=mysql` resolves and the network is the one of the app: `docker-compose exec dev-box php caligrafer.php doctor`.
    + Billing and credits cannot be checked without spending: an OpenAI `429 insufficient_quota` on the first request means the account has no credits.
    + Optional overrides (tests, proxies, regional endpoints): `OPENAI_API_BASE`, `GROQ_API_BASE`, `AGENT_API_BASE`, `STRIPE_API_BASE`, `COINBASE_API_BASE`; `WATSON_ASSISTANT_ID` lets doctor check the assistant id.

+ **Create a Vue app (Vue as a library):** Scaffolds a simple Vue 3 app in the `public` folder for you to use immediately.
You can run ```php caligrafer.php app <app_name>``` from the Caligrafy root. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library).

+ **Create a Vue project (Single Page Application):** Scaffolds a Vite 8 + Vue 3 project with Vue Router 5 and Pinia in the `public` folder.
You can run ```php caligrafer.php create <app_name>``` from the Caligrafy root. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application).

+ **Create a Bot app:** Scaffolds a Bot app for you to use immediately.
You can run ```php caligrafer.php bot <app_name>``` from the Caligrafy root.

+ **Create a Face Detection and Recognition app:** Scaffolds a ready to use face detection and recognition app.
You can run ```php caligrafer.php facedetect <app_name>``` from the Caligrafy root.

+ **Create a Machine Learning app:** Scaffolds a ready-to-use machine learning app for you to explore.
You can run ```php caligrafer.php ml <app_name>``` from the Caligrafy root.

+ **Generate Keys:** Generate keys will generate an APP_KEY and an API_KEY that you can add to the ```.env``` file you created before.
You can run ```php caligrafer.php generatekeys``` from the Caligrafy root. 

+ **Generate API key:** Generate a new unique API key every single time that you can distribute to third-parties.
You can run ```php caligrafer.php generateapikey``` from the Caligrafy root.

### Virtual Hosts
Creating a virtual host is optional. Caligrafy does not need the creation of virtual hosts to operate. However, any virtual host used will need to ensure to `AllowOverride All`, otherwise Caligrafy may not function properly.
To create a virtual host to point to the framework:
+ In your local machine, locate the `httpd-vhosts.conf` of your apache server. Usually it is located in `/apache2/extra`
+ In a production server, locate the `000-default.conf` that is typically in `/etc/apache2/sites-available`
+ Add a virtual host:
```
<VirtualHost *:80>
    ServerName <example.loc>
    DocumentRoot "<path-to-server-root>/caligrafy"
    <Directory "<path-to-server-root>/caligrafy">
       Options FollowSymLinks
       AllowOverride All
       Require all granted
    </Directory>
</VirtualHost>
```
+ Restart your apache server
+ Add a virtual host in your local machine by opening `/etc/hosts` file and adding the following:
```
127.0.0.1       example.loc

```

### Protecting your server files
Only the `index.php` front controller and the `public` folder are meant to be reached from a browser. The `.htaccess` file at the root of Caligrafy denies direct access to:
+ dotfiles such as `.env`, `.git` and `.claude`
+ the server-side folders `application`, `framework`, `vendor`, `tests`, `qa`, `docker` and `claude-plugin`
+ tooling files such as `caligrafer.php`, `composer.json`, `composer.lock` and `docker-compose.yaml` and `package.json`, the `archives` and `node_modules` folders, and any `.sql`, `.log`, `.bak`, `.ini`, `.sh` or `.env` file

A second `.htaccess` in `public/uploads` prevents any script that ends up in the uploads folder from being executed or downloaded.

Keep these files when you deploy. They use `mod_rewrite`, `mod_headers` (CORS, cookies, the service worker scope) and `mod_mime` (the web app manifest type), so your server needs these modules enabled and `AllowOverride FileInfo` (or `All`): without `mod_headers`, Apache answers `500`. The rules are case-insensitive, which matters on macOS and Windows file systems. You can check your server with `curl -I http://<your-host>/.env`: the answer must be `403` or `404`.

If you serve Caligrafy with **nginx**, `.htaccess` files are ignored and you need equivalent rules, for example:
```
location ~ /\.(?!well-known) { deny all; }
location ~ ^/(application|framework|vendor|tests|qa|docker|claude-plugin|archives|node_modules|backups)/ { deny all; }
location ~ ^/(caligrafer\.php|composer\.(json|lock)|docker-compose\.ya?ml|package(-lock)?\.json)$ { deny all; }
location ~* \.(sql|log|bak|ini|sh|env)$ { deny all; }
location = /public/sw.js { add_header Service-Worker-Allowed "/"; }
types { application/manifest+json webmanifest; }
location ~* ^/public/uploads/.*\.(php\d*|phtml|phar|cgi|pl|py|sh)$ { deny all; }
location / { try_files $uri $uri/ /index.php?uri=$uri&$args; }
```

### Caligrafy Namespace
The Caligrafy framework is all in the namespace `Caligrafy`. Every class that you create will need to call the appropriate classes from the namespace. 

- If you are creating a Controller:
```php
use Caligrafy\Controller;

class MyController extends Controller {
// your code goes here
}
```
- If you are creating a Model:
```php
use Caligrafy\Model;

class MyModel extends Model {
// your code goes here
}
```

### Build your own local Apache/MySQL/PHP server to run Caligrafy

If you would like to build your own server, you will need to go through the following setup items:

1. [Prepare your machine terminal](https://github.com/caligrafy/caligrafy-course/wiki/Lecture-1.2:-Command-Line,-Git)

2. [Prepare your development environment](https://github.com/caligrafy/caligrafy-course/wiki/Lecture-1.3:-Environment-Setup,-Github)

3. Download a code editor (for example [Visual Studio Code](https://code.visualstudio.com/) or [Sublime Text](https://www.sublimetext.com/))

## MVC Framework

This framework is based on a Model-View-Controller architecture that separates the concerns between back-end and front-end.
The framework relies on the following structures:
+ **Database**: The framework supports all the SQL databases that PDO supports. We recommend using MySql given its tight integration with PHP
+ **Model**: The model is the back-end that contains all the data logic. It is the layer that interfaces with the storage and manages the data, whether it be a database, a JSON object. Caligrafy uses databases for data storage and therefore the model becomes the object representation of the main objects/entities of an application. In other words, every table in your database that represents an entity should have an equivalent model created in the framework
+ **Controller**: The controller in an MVC architecture is the brain of the application that controls how the information is displayed. It is the logic layer that is responsible for interfacing with the model and rendering the data that will be displayed in the View
+ **View**: The view is the front-end part that is responsible for displaying a user interface with the data that the controller passes on to it

## Framework Fundamentals

This framework relies on several foundational concepts that are worth detailing. A good understanding of those fundamental concepts gets the developers up and running more efficiently. We will use an online catalogue of books (like Amazon) as an example throughout this documentation.

+ **Routes**: Routing is a key element to any framework. It uses the attributes of a user request to inform the application on what action needs to be taken on what object. That process of deconstructing requests and translating them into a call to engage controllers is called Routing. For example, if you are accessing `http://example.com/books` from your browser, this is sending a GET request on a BOOK object. In other words, this is requesting to view a list of books. Routing is responsible for redirecting such a URL to the controller in the application that is/will be responsible for viewing the list of all books.

+ **Controllers**: Controllers are a logical grouping of actions that can be taken on an object. For every object or entity, there should be one controller consisting of a group of actions that can be performed on that object (Create, Read, Update, Delete, etc.). Routing allows redirecting to a specific action in the controller.

+ **Views**: Caligrafy has two different ways of building views. One for rapid development that uses a server-side template engine to render HTML pages. The other one uses Vue.js which is a more sophisticated client-side framework that is suitable for building more delightful user experiences.You could learn more about Pug (or Phug the PHP equivalent) [here](https://www.phug-lang.com/)

+ **API & JSON**: This framework relies on REST API fundamentals. Every route that is created could either be accessed from a browser or from any application capable of making requests. The Caligrafy View is intelligent to know whether to render a graphical user interface or a response in a JSON format.

+ **Models**: In this framework, models are meant to be an object representation of all objects/entities of an application. For example, in an online bookstore example, books need to be represented by a Book model. Every model has a table associated with it in the database. Unlike other frameworks, this framework does not implicitly associate a model with a table (like Laravel for example). Every Model needs to be explicitly associated with a table. That gives the flexibility of separating terminology concerns between database tables and their object representations in the framework. However, all the required table columns that have no default values need to be represented in the model. Pivot tables (or many-to-many database tables) don't need to be created in the database nor represented in the Model. This framework creates these tables from the moment a many-to-many relationship is defined between Models (more on this later).

+ **Relationships**: This framework supports one-to-one, one-to-many and many-to-many relationships between models in both directions. Each relationship is defined as part of the Model. For example, if books and publishers are two models in our example and one book has one publisher then the one-to-one relationship needs to be defined in the Book Model (hasOne relationship). Similarly, the database needs to have a foreign key in the Book table that references the id in the Publisher table. For this to happen smoothly, a naming convention needs to be followed.

+ **Naming conventions**: This framework does not put constraints on the naming conventions as much as other frameworks. The only naming convention is around the table keys in the database.
    1. id: Every table must have `id` as its primary key no matter what the table name is. It is therefore recommended to avoid naming primary keys `book_id` or `bookId`. The primary key must always be `id` and cannot be overridden.
    2. Foreign keys: the foreign keys in the database should be of the format `modelname_id` (model name in lower case). Notice that the foreign key takes the name of the Model and NOT the name of the table in the database. The foreign keys name could be overridden if need be. A foreign key column must have the same type as the `id` it references (for example `INT UNSIGNED` for an `INT UNSIGNED` id), and every foreign key constraint needs a name that is unique in the database (for example `fk_books_publisher`).
    3. Pivot table naming: the framework takes care of creating the pivot tables for your convenience. The pivot table is named `<table1>_<table2>` after the tables of the model that calls the many-to-many relationship (belongsToMany) first and of the joined model. If a `<table2>_<table1>` table already exists, it is reused.

+ **Validation**: Input validation is key to any application. This framework integrates a third-party GUMP - a very solid library - to validate all types of inputs. The validation module is capable to do 2 things: 1) Validating inputs of all types based on easy-to-define  rules 2) Provide easy ways to filter data and format it based easy-to-define rules. You could learn more about GUMP [here](https://github.com/Wixel/GUMP)

## File Structure

Understanding the framework file structure is key to understanding how to plan your development project.
There are 5 main folders in the structure that you should be aware of:
1. **`/application`**: 
    This is where most of your application logic should go. This folder is where you should create the routes, models, views and controllers. You can create any classes and organize them freely in this folder without worrying about requiring them or including them: every PHP file of `application` (up to 5 folder levels deep) is loaded on each request, so two classes cannot have the same fully qualified name. 

    This folder has 4 subfolders (models, views, controllers and config). The config folder is where the routes are defined. 

    > When your logic becomes too complex to hold in a `Controller`, you can always create new classes to organize them without having to require or include them in the classes where you intend to use them.
2. **`/framework`**: This is the underlying code for the framework. For novice programmers, you will very rarely need to change anything in this folder. However, if you need to change the way the framework operates, then this is where it can be done.
3. **`/public`**: This is where your application client-side code should go. All your scripts, stylesheets and any other resource that needs to be publicly accessible will reside in this folder. For example, any javascript or css that you need to reference from your Views (residing in application) will need to be in the public folder. Similarly, if your application supports uploading files, all uploaded files will live in the `/public/uploads` folder.
4. **`/tests`**: every PHP file of this folder is loaded on each request, like `application`: use it for helpers that your application needs, not for PHPUnit tests (the framework's own unit tests live in `qa/unit`, see [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy)).
5. **`/vendor`**: The vendor folder contains all the third-party libraries that this framework depends on.

> [Next Section: Learn about Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)
