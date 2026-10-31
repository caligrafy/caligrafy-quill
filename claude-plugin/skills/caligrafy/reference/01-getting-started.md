# 1. Getting Started

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

> **Verified against framework source (see gotchas.md):**
> - Under Docker, `.env` overrides docker-compose environment (Dotenv `createUnsafeMutable`): set `DB_HOST=mysql` and `APP_ROOT=` in `.env`. Ports 8080/8077/3306 may clash with other projects (gotchas G11/G12).
> - `caligrafer initialize` keeps the keys of a signed project but still deletes `.git` (G25). `app` = Vue as a library, `create` = Vite SPA.

> **New to Caligrafy?** Start with the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): with Git and Docker, one command (`.bin/caligrafer quickstart --example`) sets everything up, and you use one feature from a web page, over REST and from an AI agent in 5 minutes. This page is the reference for every other way to install, for the configuration and for the Caligrafer commands.

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

### Quickstart (recommended)

With [Git](https://git-scm.com/) and [Docker Desktop](https://www.docker.com/products/docker-desktop/):
```bash
git clone https://github.com/caligrafy/caligrafy-quill.git myapp
cd myapp
.bin/caligrafer quickstart          # add --example for the Tasks example
```
It installs the libraries (Composer in Docker), starts the server, initializes the project, points `.env` at the Docker database, creates the database and the framework tables, and adds the `/capabilities` and `/mcp` routes. Every step can be run again. Details and troubleshooting: [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart). The options below are for your own Apache, MySQL and PHP, or for full control.

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
Caligrafer is the command line tool of Caligrafy: `php caligrafer.php <command>` (with Docker: `docker compose exec dev-box php caligrafer.php <command>`). **Every command is described on one page: [Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer).** The ones you use most:

| Command | What it does |
| --- | --- |
| `quickstart [--example]` | sets up a project in one step ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#quickstart)) |
| `initialize` | prepares a fresh copy of the framework; deletes `.git` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#initialize)) |
| `service <Name>` | creates a capability ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#service)) |
| `client` | writes the JavaScript client of the capabilities ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#client)) |
| `token create <name> --scopes ...` | a token for a program or an AI agent ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#token)) |
| `app`, `create`, `facedetect`, `ml`, `bot` | creates a client app in `public/` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#apps)) |
| `sql <file.sql>` | runs a SQL script on the database ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#sql)) |
| `doctor` | checks the project and its services ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#doctor)) |
| `upgrade` | updates a project's templates after a framework update ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#upgrade)) |

+ The version of a project: `php caligrafer.php version` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#version)).
+ Capabilities, tokens, MCP and the audit log: `service`, `capabilities`, `client`, `platform install`, `token`, `mcp`, `audit` ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#service)).
+ Something does not work: `php caligrafer.php doctor` checks the project, the database and every service in `.env`, with a fix for each problem ([details](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer#doctor)).

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

### Build once, experience everywhere

Caligrafy adds two layers on top of MVC so that a feature is built once and experienced everywhere:

| Layer | What it holds | Where |
| --- | --- | --- |
| **Capabilities** | the features of your application, written once: inputs and their rules, who may use them, the work, the result | `application/services/` (a `Service` with `#[Capability]`) |
| **Experiences** | every way to reach a capability, each shaped for its audience | Pug pages, Vue apps and the SPA on desktop, tablet and phone, REST for other applications, MCP for AI agents |

Models stay the data layer under the capabilities; controllers and views become one experience among others. You can start with plain MVC and move a feature to a capability as soon as a second experience needs it. Learn more in [11. Build Once](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

## Framework Fundamentals

This framework relies on several foundational concepts that are worth detailing. A good understanding of those fundamental concepts gets the developers up and running more efficiently. We will use an online catalogue of books (like Amazon) as an example throughout this documentation.

+ **Routes**: Routing is a key element to any framework. It uses the attributes of a user request to inform the application on what action needs to be taken on what object. That process of deconstructing requests and translating them into a call to engage controllers is called Routing. For example, if you are accessing `http://example.com/books` from your browser, this is sending a GET request on a BOOK object. In other words, this is requesting to view a list of books. Routing is responsible for redirecting such a URL to the controller in the application that is/will be responsible for viewing the list of all books.

+ **Controllers**: Controllers are a logical grouping of actions that can be taken on an object. For every object or entity, there should be one controller consisting of a group of actions that can be performed on that object (Create, Read, Update, Delete, etc.). Routing allows redirecting to a specific action in the controller.

+ **Views**: Caligrafy has two different ways of building views. One for rapid development that uses a server-side template engine to render HTML pages. The other one uses Vue.js which is a more sophisticated client-side framework that is suitable for building more delightful user experiences.You could learn more about Pug (or Phug the PHP equivalent) [here](https://www.phug-lang.com/)

+ **API & JSON**: This framework relies on REST API fundamentals. Every route that is created could either be accessed from a browser or from any application capable of making requests. The Caligrafy View is intelligent to know whether to render a graphical user interface or a response in a JSON format.

+ **Capabilities**: a capability is one feature of the application (add a book, list the books) written once as a service: it declares its inputs with validation rules, the scope and permission level it needs, does the work and returns data. Every experience calls the same capability, so the rules never drift between the web, the apps, the API and the agents. Learn more [here](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service).

+ **Experiences**: web pages, Vue apps and the single page application on any device, REST clients and AI agents (MCP) are all experiences of the same capabilities. Each one is shaped for its audience (a dense dashboard, a touch-first tablet view, a tool for an agent) without re-implementing the logic.

+ **Identity**: people sign in (sessions, passkeys, face login); programs and AI agents use personal access tokens with scopes, which can expire and be revoked. Every capability call, and every refusal, goes to the audit log.

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

    This folder has 5 subfolders: models, views, controllers, config (where the routes are defined) and services, where your capabilities live: features written once and served to every experience. 

    > When your logic becomes too complex to hold in a `Controller`, you can always create new classes to organize them without having to require or include them in the classes where you intend to use them.
2. **`/framework`**: This is the underlying code for the framework. For novice programmers, you will very rarely need to change anything in this folder. However, if you need to change the way the framework operates, then this is where it can be done. Inside it, `core/` holds the framework and what a capability is (`Service`, `Capability`, `Context`...), and `interfaces/` how the world reaches a capability: REST, MCP for AI agents and the JS client of the Vue apps.
3. **`/public`**: This is where your application client-side code should go. All your scripts, stylesheets and any other resource that needs to be publicly accessible will reside in this folder. For example, any javascript or css that you need to reference from your Views (residing in application) will need to be in the public folder. Similarly, if your application supports uploading files, all uploaded files will live in the `/public/uploads` folder.
4. **`/tests`**: every PHP file of this folder is loaded on each request, like `application`: use it for helpers that your application needs, not for PHPUnit tests (the framework's own unit tests live in `qa/unit`, see [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy)).
5. **`/vendor`**: The vendor folder contains all the third-party libraries that this framework depends on.

> [Next Section: Learn about Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)
