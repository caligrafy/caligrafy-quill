# Caligrafy - A Web Application Framework For Novice Developers
![Caligrafy](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/caligrafy-vue2.png)

## What is Caligrafy

Caligrafy is a new and modern MVC framework for PHP that was built from the ground up to provide easy and elegant ways for novice developers to build sophisticated and modern web applications. We've laid a solid foundation of security, social, e-commerce, analytics and machine learning features so that you focus your genius on your ideas. 

Caligrafy bridges the power of server-side languages like PHP with the sophistication of client-side languages like Javascript to expose you to how the most advanced Web capabilities are built.

<br/>

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Introduction to Caligrafy](https://youtu.be/zTqbWlTPrNQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

<br/>


## Requirements
+ PHP > 7.2
+ MySql > 5.6
+ curl, mbstring, openssl, mcrypt, gd, headers and redirect modules must be enabled in your servers

## Installation

### Quick Installation

<br/>

> #### ![](https://github.com/caligrafy/caligrafy-quill/blob/master/public/images/resources/play.png) [Installing Caligrafy](https://youtu.be/21Lazd38FfQ)
>
> In this video we introduce you to the Caligrafy framework and to the different components that are used in it.

<br/>

+ Run the following code from the terminal to get the latest version of Caligrafy

    ```bash

    composer create-project -s dev caligrafy/caligrafy-quill <optionally specify a folder name. default: caligrafy-quill>

    ```

+ Run the following code in from the Caligrafy root folder to initialize the framework

    ```bash

    php caligrafer.php initialize

    #or

    .bin/caligrafer initialize

    ``` 

    <br />

    >
    > Note that the Caligrafer Initialize command breaks all `git` links. 
    > - If you would like to create a `git` repo for your application, it is recommended that you do so after you initialize it. 
    > - If you are pulling an application built in Caligrafy from Github and you would like to preserve the `git` connection in order to pull updates regularly, it is recommended that you proceed with the manual installation instead.
    >

    <br />

+ You are good to go! If the quick installation does not complete successfully, proceed with the manual installation

### Using Docker

**If you have a local PHP server with composer:**

+ Start with the `Quick installation`

+ Run `docker-compose up -d dev-box`
    * This command will map your current working files to the docker container, which means you need to have your files locally setup correctly, including the vendor folder.
    * This is useful for local development but through docker.


**If you don't have a local PHP server with composer:**

+ Pull the code from github (You can either clone the repo or download the zip file)

+ Go to the downloaded repo and create a .env file by copying the example `cp .env.example .env`. You should be working with the `.env` file for defining environment variables beyond this point

+ If the `application` folder does not exit. Initialize it by running the following in the terminal `cp -r framework/settings/application ./application`

+ Run `docker-compose up -d prod-box`
    * This command does not need any dependencies on your local system, all you need is docker and the files.

    * Once started, you can access the website locally via `http://localhost:8080`

+ **Note:** You need to run the above command everytime, you make change in your code


**Database provided in Docker**

+ In both commands, a `phpmyadmin` instance  has been added and can be accessible at `http://localhost:8077/`

+ This is a web client for MySQL. It will allow you easy access to your Database.
        * Username: `root`
        * Password: `root`
    

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

<br />

> For more advanced installation, check the documentation [here](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)

<br />


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
+ Phug (PHP Pug) is used for creating powerful HTML views and templates
+ GUMP Validation is used to provide an easy and painless data validation and filtering
+ dotEnv is used to provide the capability of setting up environment variables for both local and production servers
+ claviska/SimpleImage is used to provide the ability to do server-side manipulations on images
+ stripe/stripe-php is used for payment features
+ coinbase/coinbase-commerce is used for cryptopayment features


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

Caligrafy provides easy ways to include Artificial Intelligence and Machine Learning to offer features such as Bots, Face Detection and Recognition. 

1. [Creating Bots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson)
2. [Face Detection and Recognition](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition)
3. [Machine Learning](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning)
4. [OpenAI: ChatGPT, GPT-3, DALL.E](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.4-OpenAI:-ChatGPT,-GPT-3,-DALL.E)
    
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
+ **Fund our project:** You can fund our project on [Kickposter](https://kickposter.us/kickposter/project/index.php?email=bVVCOHhURXQ0RjhFd1hTUjBVeGR0Umh6LzVucm80NHNDckNQdGI3Rkp3bz0=&postcart_id=a3pQM0c3UlFoZVdUK00zL1RhSjU3Zz09). Kickposter is an application that was built using earlier versions of Caligrafy. 