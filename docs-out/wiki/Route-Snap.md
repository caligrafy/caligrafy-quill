# Snap Cards and the assistant: `Route::snap`

`Route::snap($options)` declares, in one line of `web.php`, every capability of the app as a card and an assistant that
answers with them. Since 2.23.0. This page is about the declaration; what people get, the card types, card views and
hints are in [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant).

```php
Auth::authentication('User', 'users');
Route::snap();
```

<br/>

## With Claude

```text
"Add Snap Cards and the assistant to the app."
```

**Check** before you say OK:
+ `Route::snap();` is below `Auth::authentication(...)`;
+ `php caligrafer.php snap install` was run (the `caligrafy_snap_*` tables);
+ `/assistant` opens for a signed-in person and `/snap` lists the cards.

<br/>

## By hand

1. **One line** in `application/config/routing/web.php`, after `Auth::authentication(...)`:

   ```php
   Route::snap();
   ```

2. **Its tables, and a starter hints file:**

   ```bash
   php caligrafer.php snap install
   ```

   It creates the `caligrafy_snap_*` tables (they also come with `php caligrafer.php platform install`, and with every
   deploy) and `application/config/snap.php`, then prints what is ready.

3. **Open `/assistant`.** The rest is optional: the `queue_work` job for background tasks, the `snap_routines_due` job
   for routines, and `ANTHROPIC_API_KEY` in `.env` for the assistant's model (without it, the assistant answers from the
   cards' names).

<br/>

## <a name="routes"></a>What it declares

| Address | What it is |
|---|---|
| `GET /assistant` | Home (what needs you, insights, things to browse, shortcuts) and the task space; signed-in people only (`comeBack`) |
| `POST /assistant/ask` | what the person typed, said, attached or clicked (`snap_ask`, a `Route::action`) |
| `GET /assistant/noticed` | what the assistant noticed, and the person's routines (a settings page); signed-in people only |
| `GET /snap` | every card the person may use (a gallery); visitors see the public capabilities |
| `GET /snap/c/{capability}` | one card at its own address |
| `POST /snap/run` | a card sent (`snap_run`, a `Route::action`) |
| `POST /snap/call/{name}` | the `snap_*` capabilities the pages call, and only them, as the signed-in person |

Changes only ever run from a Confirm the person clicks.

<br/>

## <a name="options"></a>Options

| Option | Default | What it does |
|---|---|---|
| `assistant` | `'/assistant'` | the path of the assistant and its pages |
| `cards` | `'/snap'` | the path of the cards and their routes |

```php
Route::snap(['assistant' => '/help', 'cards' => '/cards']);
```

<br/>

## Reference

+ [`Route::snap()`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Route#route-snap)
+ [Snap in the API reference](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap):
  [`Snap\Cards`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap-Cards#snap-cards),
  [`Snap\Consent`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-consent),
  [`Snap\Setup`](https://github.com/caligrafy/caligrafy-quill/wiki/API-Snap#snap-setup)

<br/>

## Common mistakes

+ **"Snap is almost ready" (503)** on every Snap page: its `caligrafy_snap_*` tables are missing. Run
  `php caligrafer.php snap install` or `platform install` (after an `update` to 2.23.0, run one of them locally).
+ **`Route::snap()` without `Auth::authentication(...)` above it**: `/assistant` and `/assistant/noticed` are signed-in
  pages, and opening them stops with "'signedIn', 'level' and 'visitorsOnly' need Auth::authentication(...) in web.php".
+ **Expecting `async: true` to change REST or MCP**: `#[Capability(..., async: true)]` only changes Snap, where the
  card runs in the background as a Track: Task card.

## Related pages

+ [Snap Cards and the assistant](https://github.com/caligrafy/caligrafy-quill/wiki/Snap-Cards-and-the-Assistant)
+ [Background jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Background-Jobs)
+ [Scheduled jobs](https://github.com/caligrafy/caligrafy-quill/wiki/Scheduled-Jobs)
+ [Release notes 2.23.0](https://github.com/caligrafy/caligrafy-quill/wiki/Release-notes:-2.23.0)
