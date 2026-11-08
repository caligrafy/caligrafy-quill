---
name: marketplace
description: Package a Caligrafy app hosted on Caligrafy Cloud for the marketplace of caligrafy.com - read the whole code, run the app and walk the whole experience end to end to screenshot its key features (5 at most, all different), and write, in one private file (.caligrafy/marketplace.json, never in git), its title, its description for buyers, those screenshots, the prompt that recreates the whole app with Caligrafy and an estimated price window from a short market study; then send it to Caligrafy (php caligrafer.php cloud submit) and redeploy the app. Caligrafy reviews it, sets the final price and publishes it; the maker earns 25% of every sale.
when_to_use: The user offered their app on Caligrafy Cloud and its page asks them to run /caligrafy-quill:marketplace, or they ask to put, sell, list or submit their app on the Caligrafy marketplace, to package it for caligrafy.com, or to resubmit its listing after a change.
argument-hint: "[app-name]"
---

# Marketplace: one file that lets Caligrafy sell a recipe of this app

App: `$ARGUMENTS` (its name on Caligrafy Cloud; empty: the one `cloud apps` lists for this repository, or the project
folder's name). The maker offered it on its page on Caligrafy Cloud (Marketplace section, which asks them to accept the
terms); Caligrafy will sell the prompt that recreates it and the maker earns 25% of every sale. Offering it removed its
password if it had one (G114): a listed app is public.

**Do it yourself, in one go.** Read, walk the app, write the file, show the user a short summary, submit. The user
does nothing: everything the listing needs is rendered by this skill. Say first, in one line, that it takes a while
(often 15 to 30 minutes: the app is read, run and walked through) and that its page on Caligrafy Cloud shows the
listing once it is done. Never print or read
`.caligrafy/cloud.json` (the CLI carries the sign-in), never put a secret, a `.env` value, a key, personal data or user
data in the file. Caligrafy commands: `php caligrafer.php ...` when PHP 8.3+ is here, otherwise
`docker compose exec -T dev-box php caligrafer.php ...`; below, `caligrafer`.

## 1. Ready

```bash
caligrafer cloud account            # signed in? If not: caligrafer cloud login (a browser approval)
caligrafer cloud status <app>       # the app exists on Caligrafy Cloud
git check-ignore -q .caligrafy/marketplace.json && echo private   # must print "private" (.caligrafy/ is git-ignored)
```
An app that is not offered yet is refused at the submission: say so and open its page on Caligrafy Cloud
(`<cloud url>/app/<app>`, Marketplace section, "Offer it to the marketplace").

## 2. Read the whole app

Everything that makes it, not only the README: `application/config/routing/web.php` (pages, routes), every capability
in `application/services/**` (name, inputs and rules, scope, public or signed in, what it does), every
`application/models/**/create_db.sql` and `application/migrations/**` (tables, fields, relationships: the final schema
is the scripts plus the migrations), `application/config/schedule.php` (jobs on a timer, the background jobs' worker),
`application/config/container.php`, `tests/`, the controllers, `application/views/**` and
`public/<app>/` (screens, client apps: Vue, camera, chat), `.env.example` (the services it needs: names only),
`composer.json`, `README.md`. Note what is truly built, not what a comment promises.

## 3. Walk the whole experience and screenshot its key features

The screenshots sell the app: they show what it does, not its sign-in page. Walk it end to end as its user would,
signed in, with realistic sample data, and keep the **5 most telling screens, all different** (no two of the same
screen, no empty states, no error, no sign-in form unless signing in is the feature, like a face sign-in).

1. **Plan the journey** from what you read: the 3 to 5 key features in the order a newcomer meets them (for a catalog:
   the catalog, an item, adding one, the AI assistant answering). One screenshot per feature, each with a caption of a
   few words saying what it shows ("Learning the letter B with live hand tracking").
2. **Run the app on this computer**, never the live app (the walk creates data), as a separate Docker project with its
   own free ports and its own empty database, so it neither collides with other projects on this computer nor
   touches the user's local data:
   ```bash
   lsof -nP -iTCP:18080 -sTCP:LISTEN     # nothing: 18080 is free (else take 18081, 18082...)
   cat > .caligrafy/marketplace/compose.yaml <<'YAML'
   services:
     dev-box: { ports: !override ["18080:80"] }
     phpmyadmin: { ports: !reset [] }
     mysql: { ports: !reset [] }
   YAML
   docker compose -p <app>-marketplace -f docker-compose.yaml -f .caligrafy/marketplace/compose.yaml up -d dev-box
   ```
   (the first start builds the image, about 5 minutes: run it in the background.) The app is then at
   http://localhost:18080 once `curl -s -o /dev/null -w '%{http_code}' http://localhost:18080/` answers 200 (its
   tables are created on the first request). Create a test account through the app's own sign-up page and sample
   content through its own pages or capabilities: realistic, invented, never a real person's data, never the user's
   own accounts or keys. A feature that needs a service key that is not set here (OpenAI, Stripe): skip it rather
   than fake it, and say so in the summary.
3. **Take the screenshots at 1280x800**, in `.caligrafy/marketplace/` (git-ignored), PNG or JPEG of 1 MB at most:
   + with a browser you can drive in this session (the desktop app's browser, Claude in Chrome, a Playwright tool):
     walk it there and save each screenshot to that folder;
   + otherwise with the runner of this skill (Node 18+; it installs Playwright's Chromium once, outside the project,
     with a fake camera and microphone for camera apps): write the journey as steps, then run it
     ```bash
     bash <this skill's folder>/scripts/journey.sh .caligrafy/marketplace/journey.json .caligrafy/marketplace
     ```
     `journey.json`: `{"base": "http://localhost:18080", "steps": [{"goto": "/register"}, {"fill": "#email", "value":
     "maya@example.test"}, {"click": "text=Create account"}, {"wait": "#catalog"}, {"shot": "catalog", "caption": "The
     catalog"}]}` (steps: `goto`, `fill` + `value`, `click`, `press`, `wait` (milliseconds or a selector), `shot` +
     `caption`). It keeps 5 at most, drops a screen identical to another, writes `shots.json` and prints `SHOTS`;
     `FAIL at <step>` leaves `failed.png`: look at it, fix the step, run it again.
   + **A camera app** (getUserMedia: face sign-in, hand or object recognition, a scanner): its screens must show a
     realistic camera scene, never Chromium's test pattern. Choose a picture or a short clip (a few seconds, MP4 or
     WebM) that matches what the app expects to see: a hand signing a letter for a sign language app, a face for a
     face sign-in, a book cover for a scanner. Take it from the project when it has one (its samples, its tests),
     otherwise download one under a license that allows this use (Unsplash, Pexels, or public domain or CC0 on
     Wikimedia Commons; a face only from a photo with a model release, such as those licenses cover), into
     `.caligrafy/marketplace/`. Walk such an app with the runner and `"camera": "<file>"` in `journey.json`: it plays
     the file as the page's camera. Say in the summary where the picture comes from.
4. **Look at every screenshot** (read the image files). Retake any that is blank, half loaded, an error, a sign-in
   wall or too like another one. Then remove the app and its test data:
   `docker compose -p <app>-marketplace -f docker-compose.yaml -f .caligrafy/marketplace/compose.yaml down -v`.

## 4. Write `.caligrafy/marketplace.json`

```json
{
  "app": "<app>",
  "title": "A few words, its own name when it has one",
  "description": "2 to 4 sentences for a buyer: what it does, for whom, why it is worth having. No internals.",
  "images": [{"file": "marketplace/01-catalog.png", "caption": "The catalog"}],
  "prompt": "...",
  "price": {"low": 19, "high": 39, "rationale": "..."},
  "written_at": "<ISO date>"
}
```
- **images** (1 to 5, the order buyers see them, the first is the hero): the screenshots of step 3, paths relative to
  `.caligrafy/`.
- **prompt** (what is sold): one request that rebuilds the whole app with Caligrafy from an empty project, ready for
  `/caligrafy-quill:idea`. In this order: the idea in one sentence; every capability (name, inputs and rules, who may
  use it, what it does); the data (tables, fields, relationships, as they are after the migrations); the jobs on a timer and the work done
in the background; every screen and client app (what it shows and
  does, its look); how the web reaches it when the app uses them (2.19.0+: the forms that post with `Route::form`,
  sign-in with `Route::auth`, a webhook, a CSV export, file fields), said as features, not code; the services and settings it needs (names, never values); how it goes online (Caligrafy Cloud,
  `/caligrafy-quill:ship`). Complete enough to rebuild it, faithful to the code (no feature it does not have), no code
  pasted, no repository address, no secret, no personal data. As long as the app needs, usually 400 to 1,500 words.
- **price** (US dollars, for the prompt): a short market study. Search the web for what comparable things sell for:
  app templates and starter kits of the same kind, prompt and template marketplaces, the SaaS it imitates. Weigh
  what it saves a buyer (how many capabilities, screens, integrations: AI, payments, camera). Give a window (`low` and
  `high`, whole dollars, high at most three times low) and a `rationale` of 2 to 4 sentences naming the comparables
  and their prices. Caligrafy sets the final price.

## 5. Submit

Show the user, in a few lines: the title, the description, the screenshots and their captions, the prompt's length, the price window. Then:

```bash
caligrafer cloud submit <app>               # sends the file privately, then redeploys and waits until it is online
```
Run it in the background and read its output. `--no-deploy` when the app has no time left (`cloud status` says so). A refusal says why (not offered, already published, a field):
fix the file, or tell the user what only they can do, and run it again. Running it again replaces the submission
until Caligrafy publishes it.

## 6. Report

The app's page on Caligrafy Cloud now shows the submission as **Processing**: the title, the description, the
screenshots and the estimated price. Caligrafy reviews it, may polish it, sets the
final price and publishes it; the page then shows it on sale, with every purchase and the maker's 25%.
On caligrafy.com (signed in with the same account), **Listings** follows it, **Tasks** says when Caligrafy asks for
a change, and **Earnings** shows its sales and the maker's payouts.
