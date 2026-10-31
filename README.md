# Caligrafy - Build Once, Experience Everywhere
![Caligrafy: Build Once, Experience Everywhere](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/build-once-banner.png)

## What is Caligrafy

**Caligrafy is the "Build Once, Experience Everywhere" framework.** You write a feature of your application once, as a **capability**: its rules, its permissions, its work. Caligrafy then serves that same capability to every way people and machines reach your application:

| Experience | How it reaches the capability |
| --- | --- |
| **Web** pages (Pug) | a controller calls it: `$this->call('create_book')` |
| **Tablet and mobile** (Vue apps, the Vite SPA, installed to the home screen) | a generated JS client: `await createBook({...})` |
| **APIs** for other applications | a REST route: `Route::capability('POST', '/books', 'create_book')` |
| **AI agents** such as Claude | MCP tools: `Route::mcp('/mcp')` |

A rule written once behaves the same whether a person, a phone or an agent invokes it: the same validation messages, the same permissions, the same audit log. You never write the same feature twice for two channels, and the channels never drift apart.

Caligrafy is built on a PHP MVC framework designed from the start for developers who are new to building web applications: one folder to start from, a readable syntax, and a foundation of security, payments, analytics, AI and machine learning already in place, so that you focus your genius on your ideas.

<br/>

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Introduction to Caligrafy](https://youtu.be/zTqbWlTPrNQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

<br/>

## 🎉 What's new (October 2026)

+ **Build once, experience everywhere (2.1):** write a feature once as a **capability** and serve it to your Pug pages, your Vue apps, a REST API and AI agents over MCP. Validation, permissions and the audit log apply the same way on every channel; programs and agents use scoped, revocable personal access tokens ([learn more](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)).
+ **One look for every page (2.1.3):** the page components of `public/css/tokens.css` (`.cq-page`, `.cq-card`, `.cq-form`, `.cq-tabs`, `.cq-list`) give every new page the look of the sign-in and face login screens, and the Claude plugin builds every new page with them ([learn more](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents)).
+ **Current libraries and services:** PHP 8.3+ (Docker on PHP 8.5 and MySQL 8.4), Bootstrap 5 without jQuery (the Caligrafy look is kept), Vue 3.5 and a Vite 8 + Pinia SPA template, OpenAI GPT-6 and gpt-image-2, Assistants on the Responses API, the Stripe Payment Element, Coinbase Business, ml5 1.4 and @vladmandic/face-api.
+ **Versioned releases:** v2.0.0 is the first tagged release. Every release is listed in the [changelog](CHANGELOG.md), and `php caligrafer.php version` prints the version of a project.
+ **New tools:** `php caligrafer.php upgrade` brings an existing project up to date, `php caligrafer.php doctor` checks `.env`, the database and every configured service.
+ **New features:** real HTTP status codes and JSON errors, namespaced controllers, install to home screen, face login with liveness checks and passkeys, open models (Llama) on Groq, Together, Fireworks, OpenRouter or Ollama.
+ **Claude + Caligrafy Quill:** the framework ships with its own [Claude Code](https://code.claude.com) plugin: the complete documentation, a source-verified API reference, scaffolding commands and a code reviewer ([learn more](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)).

**New projects** get everything automatically. **Existing projects:** follow the upgrade checklist of the **[Release notes: October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026)**, which also lists every change and what can break.

<br/>

## Build once, experience everywhere

```php
#[Capability(name: 'create_book', description: 'Adds a book to the catalog and returns it.', scope: 'books:write')]
class CreateBook extends Service
{
    public $rules = array('title' => 'required|max_len,255', 'author' => 'required|max_len,255');

    public function handle(array $input, Context $context)
    {
        return (new Book('books'))->create($input);
    }
}
```
One class, every channel: `$this->call('create_book')` in a Pug page controller, `Route::capability('POST', '/books', 'create_book')` for REST, `createBook()` from the generated JS client (`php caligrafer.php client`) in a Vue app, and a `create_book` tool for AI agents with `Route::mcp('/mcp')`. Start with `php caligrafer.php platform install` and `php caligrafer.php service Books/CreateBook`; the [guide](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP) explains tokens, the JS client, MCP and the audit log, and [ARCHITECTURE.md](ARCHITECTURE.md) the design.

<br/>

## Claude Code plugin

Open a Quill project in [Claude Code](https://code.claude.com) and accept the "trust this folder" prompt: the project's `.claude/settings.json` registers the plugin's marketplace and enables the `caligrafy-quill` plugin. To install it by hand:

```bash
claude plugin marketplace add caligrafy/caligrafy-quill
claude plugin install caligrafy-quill@caligrafy
```

The plugin lives in [`claude-plugin/`](claude-plugin/); its [README](claude-plugin/README.md) lists the commands, the reviewer agent and the changelog.

<br/>

## Requirements
+ PHP 8.3 or higher (8.5 recommended)
+ MySQL 8.4 (or another database supported by PDO)
+ A Apache/MySql/PHP server already installed locally OR Docker
+ Git and Composer
+ curl, mbstring, openssl, sodium, gd, bcmath, intl, pdo_mysql, and the Apache rewrite and headers modules must be enabled in your servers
+ Node.js 20.19+ or 22.12+ for the Vue Single Page Application template (`caligrafer create`)

<br/>

## Installation

### Quick Installation (MAMP/LAMP/XAMP)

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Installing Caligrafy](https://youtu.be/21Lazd38FfQ)
>
> In this video we show you how to install Caligrafy on a local Apache/MySQL/PHP server.

<br/>

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

    <br />

    >
    > Note that the Caligrafer Initialize command breaks all `git` links. 
    > - If you would like to create a `git` repo for your application, it is recommended that you do so after you initialize it. 
    > - If you are pulling an application built in Caligrafy from Github and you would like to preserve the `git` connection in order to pull updates regularly, it is recommended that you proceed with the manual installation instead.
    >

    <br />

+ Add/Modify the following to the .env file if not accurate: `APP_ROOT=<caligrafy root folder. default: caligrafy-quill>`

+ You are good to go! If the quick installation does not complete successfully, proceed with the manual installation

**Testing the Installation**
+ You can test if the framework is working by visiting `http://localhost:<server port, default 80>/<caligrafy root folder. default: caligrafy-quill>` in the browser. 

<br />

### Using Docker

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Installing Caligrafy using Docker](https://youtu.be/33TKktyxto4)
>
> In this video we show you how Caligrafy can be installed and run without setting up any development environment.

<br/>

This method requires Docker to be installed on your local machine. With Docker, you no longer need to go through the hassle of having an Apache server installed locally to run PHP and MySQL.

+ If you have PHP and Composer installed on your machine, check the `Quick installation`.

+ If you don't have Composer then pull the code from github (You can either clone the repo or download the zip file)

+ Make sure Docker is installed and is running on your machine

+ Run the following code from the Caligrafy root folder to start the Caligrafy server

    ```bash
    .bin/caligrafer server start
    ``` 

    + **With PHP and Composer on your machine**, `.bin/caligrafer` installs the dependencies and initializes the project when needed, then starts the `dev-box` container with your folder mounted into it. Check that `.env` has `APP_ROOT=` (blank). The site is at `http://localhost:8080`.
    + **Without PHP**, it starts the `prod-box` container: the code is copied into the image and `.env` comes from `framework/settings/.env.docker`. The site is at `http://localhost:8080/caligrafy-quill`.

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
+ Initialize the application folder by running the following `cp -r framework/settings/application ./application`
+ Make the folder `/public/uploads/` writable if you intend to allow uploads in your application. You will need to run the command `chmod -R 777 public/uploads` from the Caligrafy root
+ You are good to go!

### Testing the Installation
+ You can test if the framework is working by visiting `http://localhost:<server port, default 80>/<caligrafy root folder. default: caligrafy-quill>` in the browser. 

<br />

> For more advanced installation, check the documentation [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)

<br />


## Testing the framework

Caligrafy comes with a QA suite that runs framework, HTTP and tooling tests in an isolated Docker stack (ports 8090 and 3316, with stripe-mock and local mocks of the OpenAI, Coinbase, Groq and Watson APIs):

```bash
composer install
./qa/run.sh            # add --keep to leave the stack running for debugging
```

It needs Docker, PHP with curl and Node.js, and refuses to run when an `application` folder or a `.env` file that it did not create is present (use a clean checkout of the framework). It also runs the PHPUnit unit tests of `qa/unit` (`vendor/bin/phpunit` runs them alone, without Docker), and `QA_NO_BUILD=1 ./qa/run.sh` reuses an existing image when the Docker image cannot be built on your network. Learn more in [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy).

<br />

## Why Caligrafy

### 1. Build once, experience everywhere
A capability is written once and served to web pages, Vue apps on tablets and phones, REST clients and AI agents. Validation, permissions and the audit trail live in one place, so every experience of your application behaves the same. [Learn how](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP).

### 2. Every experience included
Server-rendered pages with a built-in template engine (Pug), Vue 3 as a library or a Vite single page application, installable mobile web apps, a REST API, and an MCP server for AI agents: they come with the framework and share one look through its design tokens.

### 3. Ready for AI agents
Every capability can be a tool for agents such as Claude, over MCP, with a description, an input schema and hints that tell the agent when to ask before acting. And Caligrafy ships with its own Claude Code plugin, so Claude builds Caligrafy applications the way the framework intends.

### 4. Secure by design
Personal access tokens with scopes, expiry and revocation for programs and agents; the session and CSRF tokens for browsers; permission levels for users; an audit log of who did what through which channel; server files that are never served; bound SQL everywhere.

### 5. The MVC you already know
Routes, controllers, models and views, as in the frameworks you may have read about. Capabilities sit beside them, so you can start with plain MVC and move a feature to a capability when a second experience needs it.

### 6. Batteries included
In a few lines, accept card, ACH and cryptocurrency payments, add face login with passkeys, build chatbots and assistants with OpenAI or open models, use machine learning in the browser, and make your pages ready for search and social sharing.

### 7. Lightweight syntax
Caligrafy is closer to bare-bone programming than other frameworks. It delivers equivalent features while staying readable for someone who is learning.

### 8. Documentation, course and support
An extensive [wiki](https://github.com/caligrafy/caligrafy-quill/wiki), a step-by-step [course](https://github.com/caligrafy/caligrafy-quill-course) that builds a complete application from an empty folder to an AI agent, video tutorials on YouTube, and help on Slack, Facebook and GitHub.


## Dependencies

This framework uses several third-party librairies that are included in the distribution
+ Phug 2 / Pug-php 3 (PHP Pug) is used for creating powerful HTML views and templates
+ GUMP Validation is used to provide an easy and painless data validation and filtering
+ dotEnv is used to provide the capability of setting up environment variables for both local and production servers
+ claviska/SimpleImage is used to provide the ability to do server-side manipulations on images
+ stripe/stripe-php 22 is used for payment features (Stripe.js Payment Element on the client)
+ PHPMailer 7 sends emails, lucianotonet/groq-php connects to Groq
+ lbuchs/webauthn 2 verifies passkeys (WebAuthn); PHPUnit 12 runs the unit tests of the framework (development only)
+ Bootstrap 5.3, Vue 3.5 and axios 1.20 (pinned CDN versions) power the default views and client apps; jQuery is no longer required
+ Cryptocurrency payments use the Coinbase Business Checkouts API (no SDK needed)


## Documentation

### Build once, experience everywhere

Start here: how one capability serves every experience.

+ [11. Build Once: Services, Capabilities and MCP](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP): write a capability, serve it to pages, Vue apps, REST and AI agents, tokens, the JS client, MCP, the audit log
+ [Release notes: 2.1.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0): what 2.1 adds and how to upgrade a project
+ [The Caligrafy course](https://github.com/caligrafy/caligrafy-quill-course): build Bookshelf step by step, from a web page to an AI agent

### Learning Caligrafy

We have created a rigorous documentation to help you understand the basics of the framework and to get you started as quickly as possible

1. [Getting Started](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)
    > + [Installation](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)
    > + [Understanding MVC architecture](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#architecture)
    > + [Framework fundamentals](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#fundamentals)
    > + [File Structure](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#filestructure)

2. [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing)
    > + [Defining Routes](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#definingroutes)
    > + ["Hello World" Route](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#basicroute)
    > + [Routing with parameters](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#parameterroute)
    > + [Controller Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#controllerroute)
    > + [HTML Form Methods](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing#htmlformmethods)
    
3. [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request)
    > + [Accessing the Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request#accessrequest)
    > + [Request Properties](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request#requestproperties)
    > + [Request Methods](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request#requestmethods)

4. [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
    > + [Relational Databases](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
    > + [Model Fundamentals](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models#modelfundamentals)
    > + [Interfacing with the Model](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models#modelinterface)
    > + [Model Methods](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models#modelmethods)
5. [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
    > + [One-to-One Relationship](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
    > + [One-to-Many Relationship](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships#onetomany)
    > + [Many-to-Many Relationship](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships#manytomany)
    > + [Overriding Naming Convention](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships#override)
6. [Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)
    > + [Data Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#validation)
    > + [Data Filtering](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#filter)
    > + [Validation & Filtering](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#validationandfiltering)
    > + [File Validation](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation#filevalidation)
7. [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
    > + [Introduction to Phug](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#introduction)
    > + [Pug Templates](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#templates)
    > + [Structure and Format](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#structure)
    > + [Getting Started with Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#viewsstartup)
    > + [Simple View](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#simpleview)
    > + [View with parameters](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#parameterview)
8. [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)
    > + [Getting Started with Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers)
    > + [Creating a Controller](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#createcontroller)
    > + [Routing to the Controller](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#controllerrouting)
    > + [Controller Context](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#controllercontext)
    > + [Controller Methods](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#controllermethods)
9. [Helpers, Forms & REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
    > + [Helpers](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#helpers)
    > + [HTML Forms](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#forms)
    > + [REST API](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi)
10. [Authentication](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
11. [Stripe & Cryptocurrency Payment](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment)
12. [Metadata & Rich Cards](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards)
13. [Search Referencing and Analytics](https://github.com/caligrafy/caligrafy-quill/wiki/9.4-Search-Referencing-and-Analytics)
14. [Security and Upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)

### Release notes and contributing

+ [Release notes: 2.1.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0): build once, experience everywhere
+ [Release notes: October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026): every change of October 2026, with an upgrade checklist for existing projects
+ [Testing Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy): the QA suite, the unit tests and the release process, for contributors

### Caligrafy and Vue.js

Caligrafy is a modern MVC framework that leverages the powerful technologies that fuel each of the M (Model), the V (View) and the C (Controller). Vue.js is a modern and progressive Javascript framework that has been built from the  ground up just like Caligrafy. Vue.js empowers you to create powerful and sophisticated Views. 
This framework integrates seamlessly with Vue.js to combine the best of PHP with the best of JS.

<br/>

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Integrating Caligrafy with VueJS](https://youtu.be/XpCpWezS4Pk)
>
> In this video, we show how VueJS can be used with Caligrafy in 2 different ways: 1) as a library to build simple web applications and 2) as a fully integrated front-end framework to build large-scale applications.

<br/>

#### Basics - Vue.js as a library

In this section, we cover the basics of Vue.js and we illustrate how it could be used quickly as a library.

1. [Setting up Vue.js as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library)
2. [Understanding the flow of information](https://github.com/caligrafy/caligrafy-quill/wiki/9.7-Understanding-the-flow-of-information)
3. [Understanding structure](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.1-Understanding-the-structure)
4. [Routes](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.2-Routes)
5. [Requests](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests)
6. [Forms](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.4-Forms)
7. [Validations](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.5-Validations)
8. [Components](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.6-Components)


#### For large scale application purposes

In order to build large scale applications using Vue, we need the ability to leverage the powerful capabilities of Vue such as Single Page Application (SPA) and Single File Components (SFC). 

1. [Setting up the Vue application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application)
2. [Routing for Single Page Applications](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.2-Routing-for-Single-Page-Applications)
3. [Single File Components](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.3-Single-File-Components)
4. [State Management](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.4-State-Management)

### AI in Caligrafy

Caligrafy works with AI in both directions: AI agents use your application through MCP ([learn more](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#mcp)), and your application uses AI to offer features such as bots, assistants, face detection and recognition, and machine learning.

1. [Creating Bots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson)
2. [Face Detection and Recognition](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition)
3. [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning)
4. [OpenAI: ChatGPT, GPTs, DALL.E](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.4-OpenAI:-ChatGPT,-GPTs,-DALL.E)
5. [OpenAI: Assistants](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.5-Open-AI:-Assistants)
6. [Open Source Agents (llama, Mistral, etc.)](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.6-Open-Source-Agents-(llama,-Mistral-etc.))
    
## Learn Caligrafy

+ Caligrafy is an outstanding framework for educational purposes. We constantly develop instructional video materials to illustrate the main features of the framework. Stay on the look for more video tutorials on our youtube channel.

    [Caligrafy Channel](https://www.youtube.com/playlist?list=PLsJG81fLk3_VWmXl9DnEldYY9ei4jTCjA)

+ Caligrafy offers online courses that give you all the basics that you need to create powerful applications with Caligrafy.

    [Caligrafy Academy](https://www.udemy.com/courses/search/?src=ukw&q=caligrafy)

## Connecting with the Caligrafy Community

There are several ways for the Caligrafy community to connect:

+ **github:** You can always use github to stay up to date with the roadmap of Caligrafy, to post issues and to track when feature requests and issues are done

+ **slack:** Joining our slack group is a great way to exchange with other members of the community, to get help on using the framework and to discuss any issues or features.

    [Join our slack community](https://join.slack.com/t/caligrafy/shared_invite/enQtNzI4MDY2OTA4MTgzLTI2NDc2NTVmMDNlMWQ5YWYxN2RjZTkwZjdiNjM5ZTg3NjQ2YWYyMzRmZDgzNWE0Nzc4YjQyODM2NDNkNjQ2OTU)

+ **facebook Caligrafy Group:** Joining our Caligrafy group on facebook gives you more ways to interact with the community and to share success stories. 

    [Join our facebook group](https://www.facebook.com/groups/caligrafy/)


## Need help getting started?
We are always here to help when you need us. If you need assistance getting started or if you need help understanding how Caligrafy can be useful to you, we can help. Reach out to us by joining our slack channel.
[Reach out to us](https://join.slack.com/t/caligrafy/shared_invite/enQtNzI4MDY2OTA4MTgzLTI2NDc2NTVmMDNlMWQ5YWYxN2RjZTkwZjdiNjM5ZTg3NjQ2YWYyMzRmZDgzNWE0Nzc4YjQyODM2NDNkNjQ2OTU)

## Keep Caligrafy going...
Your support keeps us going. If you like Caligrafy, there are several ways you could contribute:
+ **Promote us:** On our website, you can share our page with your friends and fans [caligrafy.com](https://caligrafy.com)
+ **Sponsor us:** You sponsor our work in multiple different ways on Github. Your contributions can help us keep our commitments to the developer community. [Github Sponsorship](https://github.com/sponsors/DoryAzar)