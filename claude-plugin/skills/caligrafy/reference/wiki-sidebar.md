# Wiki sidebar (table of contents)

> Source: Caligrafy Quill wiki - https://github.com/caligrafy/caligrafy-quill/wiki/_Sidebar
> Bundled with the caligrafy-quill Claude plugin. Content preserved from the wiki; formatting cleaned.

**Caligrafy: Describe it. Approve it. Ship it.** Your idea turned into a complete app: every feature built once, for web pages, Vue apps, a REST API and AI agents, and online.

## Start here

[Why Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Why-Caligrafy): the benefits, the measurements, and what it is not yet

1. [Quickstart with Claude](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart-with-Claude): describe it, approve it, ship it
    > or the [Quickstart](https://github.com/caligrafy/caligrafy-quill/wiki/Quickstart): the same three steps by hand, in 5 minutes
2. [How Caligrafy works](https://github.com/caligrafy/caligrafy-quill/wiki/How-Caligrafy-Works): the ideas in 5 minutes
3. [Your first capability](https://github.com/caligrafy/caligrafy-quill/wiki/Your-First-Capability): build a feature, page included

## Capabilities

+ [Capabilities in depth](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP)
    > + [Write a capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#service)
    > + [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#tokens)
    > + [The audit log](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#audit)
    > + [Testing a capability](https://github.com/caligrafy/caligrafy-quill/wiki/11.-Build-Once:-Services,-Capabilities-and-MCP#test)
+ [`#[Capability]`: every option](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Attribute)
    > + [Inputs: rules, filters, descriptions](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Inputs) · [file uploads](https://github.com/caligrafy/caligrafy-quill/wiki/File-Uploads)
    > + [Who may call: scope, level, public](https://github.com/caligrafy/caligrafy-quill/wiki/Capability-Scopes-and-Access)
    > + [Read-only and destructive](https://github.com/caligrafy/caligrafy-quill/wiki/Read-Only-and-Destructive-Capabilities) · [web-only](https://github.com/caligrafy/caligrafy-quill/wiki/Web-Only-Capabilities) · [async](https://github.com/caligrafy/caligrafy-quill/wiki/Async-Capabilities)
    > + [Refusing a call: `ServiceError`](https://github.com/caligrafy/caligrafy-quill/wiki/Refusing-a-Call-ServiceError) · [the context: who calls](https://github.com/caligrafy/caligrafy-quill/wiki/The-Context)
    > + [Personal access tokens](https://github.com/caligrafy/caligrafy-quill/wiki/Personal-Access-Tokens) · [the audit log](https://github.com/caligrafy/caligrafy-quill/wiki/The-Audit-Log)
    > + [How to call a capability from code](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Call-a-Capability-from-Code)
+ [Scheduled jobs: capabilities on a timer](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Notifications: web push and email](https://github.com/caligrafy/caligrafy-quill/wiki/Notifications)
+ [Background jobs: capabilities run later](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
+ [Migrations: database changes, in order](https://github.com/caligrafy/caligrafy-quill/wiki/Migrations)
+ [The cache](https://github.com/caligrafy/caligrafy-quill/wiki/Caching)
+ [Testing your app](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Your-App)
    > + [Testing capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Capabilities) · [pages, forms and actions](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Pages-Forms-and-Actions) · [the test database](https://github.com/caligrafy/caligrafy-quill/wiki/The-Test-Database)
+ [Dependency injection](https://github.com/caligrafy/caligrafy-quill/wiki/Dependency-Injection)
+ [Validation rules](https://github.com/caligrafy/caligrafy-quill/wiki/6.-Validation)

## Experiences

+ **Web pages**
    > + [Views and page components](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#pagecomponents)
    > + [Design styles: another look, one setting](https://github.com/caligrafy/caligrafy-quill/wiki/Design-Styles)
    > + [Declarative routes: every request, in one line](https://github.com/caligrafy/caligrafy-quill/wiki/Declarative-Routes)
    > + [`Route::page`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Page) · [`Route::form`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Form) · [`Route::auth`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Auth) · [`Route::webhook`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Webhook) · [`Route::download`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Download) · [`Route::redirect`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Redirect) · [`Route::action`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Action) · [`Route::snap`](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Snap) · [capability routes](https://github.com/caligrafy/caligrafy-quill/wiki/Route-Capability-and-REST)
    > + [How to export a list as CSV](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Export-a-List-as-CSV)
    > + [Controllers: moving one to a declared route](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers#controllerstartup) · [how to, step by step](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Move-a-Controller-Route-to-a-Declared-Route)
    > + [Forms and helpers](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API)
+ **Vue apps**
    > + [Vue as a library](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.0-Setting-up-Vue.js-as-a-library) · [calling capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.3-Requests#capabilities) · [structure](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.1-Understanding-the-structure) · [forms](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.4-Forms) · [components](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.6-Components)
    > + [Single page application](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.1---Setting-up-the-Vue-application) · [routing](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.2-Routing-for-Single-Page-Applications) · [single file components](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.3-Single-File-Components) · [state](https://github.com/caligrafy/caligrafy-quill/wiki/9.8.4-State-Management)
+ **Mobile and tablet**
    > + [One look on every device](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#designtokens) · [install to home screen](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views#install)
+ **REST API**
    > + [The REST API](https://github.com/caligrafy/caligrafy-quill/wiki/REST-API) · [the JS client](https://github.com/caligrafy/caligrafy-quill/wiki/The-JS-Client) · [controller APIs](https://github.com/caligrafy/caligrafy-quill/wiki/9.-Helpers-,-Forms-&-REST-API#restapi)
+ **AI agents**
    > + [MCP: Claude, Cursor and other agents](https://github.com/caligrafy/caligrafy-quill/wiki/MCP-for-AI-Agents)
+ **Snap Cards and the assistant**
    > + [Every capability as a card, an assistant that answers with them](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant)
    > + Cards: [Ask](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Ask-Cards) · [Show](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Show-Cards) · [Go and Track](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Go-and-Track-Cards) · [Offer](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Offer-Cards)
    > + [Card views](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Card-Views) · [hints](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Hints) · [the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/The-Snap-Assistant)

## Data and people

1. [Models](https://github.com/caligrafy/caligrafy-quill/wiki/4.-Models)
2. [Relationships](https://github.com/caligrafy/caligrafy-quill/wiki/5.-Relationships)
3. [Queries: filters, paging, related rows](https://github.com/caligrafy/caligrafy-quill/wiki/Queries)
    > + [Filters and order](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Filters-and-Order) · [getting the rows](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Getting-Rows) · [pagination](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Pagination) · [eager loading](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Eager-Loading) · [writing and transactions](https://github.com/caligrafy/caligrafy-quill/wiki/Query-Builder-Writing-and-Transactions)
4. [Sign-in, users and tokens](https://github.com/caligrafy/caligrafy-quill/wiki/9.1-Authentication)
    > + How to: [sign in with an email](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-with-an-Email) · [reset a password](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Reset-a-Password) · [build a sign-up page](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Build-a-Sign-Up-Page) · [sign in through another site](https://github.com/caligrafy/caligrafy-quill/wiki/How-To-Sign-In-Through-Another-Site)
    > + [Passkeys](https://github.com/caligrafy/caligrafy-quill/wiki/Passkeys)

## Built-in features

+ [Payments: Stripe and crypto](https://github.com/caligrafy/caligrafy-quill/wiki/9.2-Stripe-&-Cryptocurrency-Payment)
+ [Face detection and face login](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.2-Face-Detection-and-Face-Recognition)
+ [OpenAI: chat and images](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.4-OpenAI:-ChatGPT,-GPTs,-DALL.E) · [Assistants](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.5-Open-AI:-Assistants)
+ [Open models: Llama, Mistral...](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.6-Open-Source-Agents-(llama,-Mistral-etc.))
+ [Chatbots with Watson](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.1-Creating-Bots-with-Watson)
+ [Machine learning in the browser](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.3-Machine-Learning)
+ [Phone and tablet apps: iOS and Android](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.7-Phone-and-Tablet-Apps)
+ [Languages: translations, right to left](https://github.com/caligrafy/caligrafy-quill/wiki/9.9.8-Languages)
+ [Metadata and social cards](https://github.com/caligrafy/caligrafy-quill/wiki/9.3-Metadata-&-Social-Rich-Cards) · [search and analytics](https://github.com/caligrafy/caligrafy-quill/wiki/9.4-Search-Referencing-and-Analytics)

## Put it online

+ [Putting it online](https://github.com/caligrafy/caligrafy-quill/wiki/Putting-it-Online): the production image, the database at every start
    > + [Caligrafy Cloud](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Caligrafy-Cloud) · [your own server](https://github.com/caligrafy/caligrafy-quill/wiki/Deploying-to-Your-Own-Server)
    > + [The production image](https://github.com/caligrafy/caligrafy-quill/wiki/The-Production-Image) · [the deploy step](https://github.com/caligrafy/caligrafy-quill/wiki/The-Deploy-Step) · [updating Caligrafy](https://github.com/caligrafy/caligrafy-quill/wiki/Updating-Caligrafy)
    > + [Performance and deployment](https://github.com/caligrafy/caligrafy-quill/wiki/Performance-and-Deployment) · [security defaults](https://github.com/caligrafy/caligrafy-quill/wiki/Security-Defaults) · [support and release policy](https://github.com/caligrafy/caligrafy-quill/wiki/Support-and-Release-Policy)

## Build with AI

+ [Claude Code plugin](https://github.com/caligrafy/caligrafy-quill/wiki/10.-Claude-Code-Plugin)

## Reference

+ [API reference: every class and method](https://github.com/caligrafy/caligrafy-quill/wiki/API-Reference)
    > + [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/API-Routing) · [`Route`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route) · [`AuthRoutes`](https://github.com/caligrafy/caligrafy-quill/wiki/API-AuthRoutes) · [pages](https://github.com/caligrafy/caligrafy-quill/wiki/API-Pages) · [`Controller`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Controller) · [capabilities](https://github.com/caligrafy/caligrafy-quill/wiki/API-Capabilities)
    > + [Data](https://github.com/caligrafy/caligrafy-quill/wiki/API-Data) · [`Model`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Model) · [`Query`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Query) · [sign-in](https://github.com/caligrafy/caligrafy-quill/wiki/API-Auth) · [jobs](https://github.com/caligrafy/caligrafy-quill/wiki/API-Jobs) · [`Notify`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Notify) · [payments](https://github.com/caligrafy/caligrafy-quill/wiki/API-Payments) · [AI](https://github.com/caligrafy/caligrafy-quill/wiki/API-AI)
    > + [Interfaces](https://github.com/caligrafy/caligrafy-quill/wiki/API-Interfaces) · [Snap](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap) · [`Snap\Cards`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards) · [`Snap\Conversation`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Conversation) · [testing](https://github.com/caligrafy/caligrafy-quill/wiki/API-Testing) · [tooling](https://github.com/caligrafy/caligrafy-quill/wiki/API-Tooling) · [helper functions](https://github.com/caligrafy/caligrafy-quill/wiki/API-Helpers)
+ [Caligrafer: every command](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer)
    > + [Project](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Project-Commands) · [build](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Build-Commands) · [data](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Data-Commands) · [agents](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Agent-Commands) · [jobs and tests](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Job-Commands) · [maintenance](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Maintenance-Commands) · [deploy](https://github.com/caligrafy/caligrafy-quill/wiki/Caligrafer-Deploy-Commands)
+ [Installation, configuration and Caligrafer](https://github.com/caligrafy/caligrafy-quill/wiki/1.-Getting-Started)
+ [Routing](https://github.com/caligrafy/caligrafy-quill/wiki/2.-Routing) · [Request](https://github.com/caligrafy/caligrafy-quill/wiki/3.-Request) · [Controllers](https://github.com/caligrafy/caligrafy-quill/wiki/8.-Controllers) · [Views](https://github.com/caligrafy/caligrafy-quill/wiki/7.-Views)
+ [Vue: flow of information](https://github.com/caligrafy/caligrafy-quill/wiki/9.7-Understanding-the-flow-of-information) · [routes](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.2-Routes) · [validations](https://github.com/caligrafy/caligrafy-quill/wiki/9.7.5-Validations)
+ [Security and upgrading](https://github.com/caligrafy/caligrafy-quill/wiki/9.5-Security-and-Upgrading)
+ Release notes: [2.34](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.34.0) · [2.33](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.33.0) · [2.32](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.32.0) · [2.31](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.31.0) · [2.30](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.30.0) · [2.29](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.29.0) · [2.28](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.28.0) · [2.27](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.27.0) · [2.26](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.26.0) · [2.25](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.25.0) · [2.24](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.24.0) · [2.23](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.23.0) · [2.22](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.22.0) · [2.21](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.21.0) · [2.20](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.20.0) · [2.19](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.19.0) · [2.18](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.18.0) · [2.17](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.17.0) · [2.16](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.16.0) · [2.15](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.15.0) · [2.14](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.14.0) · [2.13](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.13.0) · [2.12](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.12.0) · [2.11](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.11.0) · [2.10](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.10.0) · [2.9](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.9.0) · [2.8](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.8.0) · [2.7](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.7.0) · [2.6](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.6.0) · [2.5](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.5.0) · [2.4](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.4.0) · [2.3](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.3.0) · [2.1](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.1.0) · [October 2026](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-October-2026) · [changelog](https://github.com/caligrafy/caligrafy-quill/blob/master/CHANGELOG.md)
+ [Testing Caligrafy (contributors)](https://github.com/caligrafy/caligrafy-quill/wiki/Testing-Caligrafy)
