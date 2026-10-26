# Caligrafy Quill overview (wiki Home / README)

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

# Caligrafy - A Web Application Framework For Novice Developers
![Caligrafy](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/caligrafy-vue2.png)

## What is Caligrafy

Caligrafy is a new and modern MVC framework for PHP that was built from the ground up to provide easy and elegant ways for novice developers to build sophisticated and modern web applications. We've laid a solid foundation of security, social, e-commerce, analytics and machine learning features so that you focus your genius on your ideas. 

Caligrafy bridges the power of server-side languages like PHP with the sophistication of client-side languages like Javascript to expose you to how the most advanced Web capabilities are built.

> #### (video) [Introduction to Caligrafy](https://youtu.be/zTqbWlTPrNQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

## 🎉 New release: Claude + Caligrafy Quill

Caligrafy Quill now ships with its own **[Claude Code](https://code.claude.com) plugin**. Open a Quill project in Claude Code and Claude knows the framework inside out: the complete documentation, a source-verified API reference, known gotchas, scaffolding commands (`/caligrafy-quill:new-resource`, `/caligrafy-quill:new-app`, `/caligrafy-quill:server`, `/caligrafy-quill:db`) and a `caligrafy-reviewer` agent.

> [Learn how to install and use the Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)

## 🔒 Security release

This release hardens Caligrafy Quill: server files such as `.env` are no longer reachable from a browser, uploads cannot run scripts, client apps are protected against path traversal and script injection, queries are bound, and the authentication module hashes passwords. It also fixes many-to-many pivots with `UNSIGNED` ids, `DB_PORT`, Stripe checkout options, the OpenAI `COMPLETE` command and Assistant runs.

It also moves Caligrafy to **current libraries and services**: PHP 8.3+ (Docker on PHP 8.5 and MySQL 8.4), Bootstrap 5 without jQuery, Vue 3.5 and a Vite 8 + Pinia SPA template, the current OpenAI models and image API, Assistants on the Responses API, the Stripe Payment Element and Checkout URLs, Coinbase Business for crypto payments, current Groq models, ml5 1.4 and p5 2.3, and @vladmandic/face-api.

**New projects** created or cloned with this release get all the fixes automatically. **Existing projects:** updating the framework does not update the copies in your `application` folder. Follow [Upgrading an existing project](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#upgrading), [Upgrading to the current libraries](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#libraries) and review the [behavior changes](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading#changes).

## Requirements
+ PHP 8.3 or higher (8.5 recommended)
+ MySQL 8.4 (or another database supported by PDO)
+ A Apache/MySql/PHP server already installed locally OR Docker
+ Git and Composer
+ curl, mbstring, openssl, sodium, gd, bcmath, intl, pdo_mysql, and the Apache rewrite and headers modules must be enabled in your servers
+ Node.js 20.19+ or 22.12+ for the Vue Single Page Application template (`caligrafer create`)

## Installation

### Quick Installation (MAMP/LAMP/XAMP)

> #### (video) [Installing Caligrafy](https://youtu.be/21Lazd38FfQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

+ Run the following code from the terminal to get the latest version of Caligrafy

    ```bash

    composer create-project -s dev caligrafy/caligrafy-quill <optionally specify a folder name. default: caligrafy-quill>

    ```

+ Start the local Apache server. [Set up a local server](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started#build-your-own-local-apachemysqlphp-server-to-run-caligrafy) Or use the [Docker installation](https://github.com/caligrafy/caligrafy-quill?tab=readme-ov-file#using-docker) 

+ Run the following code in from the Caligrafy root folder to initialize the framework

    ```bash

    php caligrafer.php server start

    #or

    .bin/caligrafer server start

    ``` 

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

This method requires Docker to be installed on your local machine. With Docker, you no longer need to go through the hassle of having an Apache server installed locally to run PHP and MySQL.

+ If you have PHP and Composer installed on your machine, check the `Quick installation`.

+ If you don't have Composer then pull the code from github (You can either clone the repo or download the zip file)

+ Make sure Docker is installed and is running on your machine

+ Run the following code from the Caligrafy root folder to start the Caligrafy server

    ```bash

    #if you have php
    php caligrafer.php server start

    #in all cases
    .bin/caligrafer server start

    ``` 

+ Add/Modify the following to the .env file if not accurate: `APP_ROOT=''` (leave blank)

+ Run `server stop` instead of `server start` to stop the server at all time

**Testing the Installation**
+ Once started, you can access the website locally via `http://localhost:8080`

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
+ Make the folder `/public/uploads/` writable if you intend to allow uploads in your application. You will need to run the command `sudo chmod -R 777 /public/uploads`
+ You are good to go!

### Testing the Installation
+ You can test if the framework is working by visiting `http://localhost:<server port, default 80>/<caligrafy root folder. default: caligrafy-quill>` in the browser. 

> For more advanced installation, check the documentation [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)

## Testing the framework

Caligrafy comes with a QA suite that runs framework, HTTP and tooling tests in an isolated Docker stack (ports 8090 and 3316, with stripe-mock and local mocks of the OpenAI, Coinbase, Groq and Watson APIs):

```bash
composer install
./qa/run.sh            # add --keep to leave the stack running for debugging
```

It needs Docker, PHP with curl and Node.js, and refuses to run when an `application` folder or a `.env` file that it did not create is present (use a clean checkout of the framework).

## Why Caligrafy

### 1. Full-Stack Framework
Caligrafy is a full-stack framework that leverages the power of server-side languages such as PHP with the power of client-side languages such as Javascript to help you build powerful and sophisticated web applications.

### 2. MVC Architecture
Caligrafy is built with an MVC architecture pattern that separates the business logic from the presentation layers. The Caligrafy MVC established an arsenal of methods and features that ensure that the separation of concerns is maintained between business and presentation layers.

### 3. Modern Modular Librairies
Caligrafy comes pre-packaged with ready-to-use modern features. In few lines, you can start accepting credit card payments, ach payments, cryptocurrency payments, building chatbots and assistants. In one line of code, your application can have rich structured data and can be ready for social sharing. In no time, your application can have a REST-API exposed for third-party applications. And many others...

### 4. VueJS
Caligrafy supports VueJS as an alternative to using its template engine. VueJS complements Caligrafy in its ability to create powerful and nimble user experiences in the "View" layer of an MVC architecture. If you are a PHP developer, Caligrafy makes it easy for you to use VueJS. If you are a Javascript developer, Caligrafy makes it easy for you to not be overwhelmed by PHP.

### 5. AI and Machine Learning
Caligrafy integrates machine learning capabilities at its core to help you build artificial intelligence seemlessly into your application. 

### 6. Template Engine
Caligrafy has a built-in and powerful template engine that can be used to create sophisticated user experiences easily.

### 7. Lightweight Syntax
Caligrafy is closer to bare-bone programming than other frameworks out there. While it delivers equivalent - if not more powerful - features, it is built from the ground up with developers in mind.

### 8. Documentation & Support
Caligrafy is committed to providing continuous support to its community of developers through an extensive online Documentation, Online video tutorials on its youtube channel, training courses on Udemy and live help through Slack, Facebook and Github.

## Dependencies

This framework uses several third-party librairies that are included in the distribution
+ Phug 2 / Pug-php 3 (PHP Pug) is used for creating powerful HTML views and templates
+ GUMP Validation is used to provide an easy and painless data validation and filtering
+ dotEnv is used to provide the capability of setting up environment variables for both local and production servers
+ claviska/SimpleImage is used to provide the ability to do server-side manipulations on images
+ stripe/stripe-php 22 is used for payment features (Stripe.js Payment Element on the client)
+ PHPMailer 7 sends emails, lucianotonet/groq-php connects to Groq
+ Bootstrap 5.3, Vue 3.5 and axios 1.20 (pinned CDN versions) power the default views and client apps; jQuery is no longer required
+ Cryptocurrency payments use the Coinbase Business Checkouts API (no SDK needed)

## Documentation

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
    > + [Interfacing with the Model](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models#modelfundamentals)
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

### Caligrafy and Vue.js

Caligrafy is a modern MVC framework that leverages the powerful technologies that fuel each of the M (Model), the V (View) and the C (Controller). Vue.js is a modern and progressive Javascript framework that has been built from the  ground up just like Caligrafy. Vue.js empowers you to create powerful and sophisticated Views. 
This framework integrates seamlessly with Vue.js to combine the best of PHP with the best of JS.

> #### (video) [Integrating Caligrafy with VueJS](https://youtu.be/XpCpWezS4Pk)
>
> In this video, we show how VueJS can be used with Caligrafy in 2 different ways: 1) as a library to build simple web applications and 2) as a fully integrated front-end framework to build large-scale applications.

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

Caligrafy provides easy ways to include Artificial Intelligence and Machine Learning to offer features such as Bots, Face Detection and Recognition. 

1. [Creating Bots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson)
2. [Face Detection and Recognition](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition)
3. [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning)
4. [OpenAI: ChatGPT, GPTs, DALL.E](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.4-OpenAI:-ChatGPT,-GPTs,-DALL.E)
5. [OpenAI: Assistants](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.5-Open-AI:-Assistants)
6. [Open Source Agents (llama, Mistral, etc.)](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.6-Open-Source-Agents-(llama,-Mistral-etc.))

### Caligrafy and Claude

Caligrafy Quill ships with a Claude Code plugin that gives Claude complete knowledge of the framework, along with scaffolding, server, database and code review tools.

1. [Claude Code Plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)

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
