# AI Game Instructor Plugin

A WordPress plugin that turns a game guide into a playable in-page assistant. It supports game and playthrough management, imported knowledge chunks, and a chat widget you can embed with a shortcode.

## Features

- WordPress admin page for managing games and playthroughs
- Shortcode embed for a chat widget on any page or post
- Knowledge import from uploaded text files or documents
- Basic retrieval over imported game knowledge
- AI chat flow using Groq or OpenAI-compatible APIs
- Memory and objective saving for each playthrough

## Install

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate the plugin in WordPress admin.
3. Go to the admin menu item "AI Game Instructor".
4. Create a game, then create at least one playthrough.
5. Upload a game knowledge file such as `.txt`, `.md`, or `.docx`.
6. Add the shortcode to a page or post.

## Shortcode

Simple embed:

```shortcode
[ai_game_guide]
```

With explicit game and playthrough:

```shortcode
[ai_game_guide game_id="12" playthrough_id="4" title="Fallout Guide"]
```

You can also add a custom class or theme:

```shortcode
[ai_game_guide game_id="12" playthrough_id="4" title="Fallout Guide" class="my-guide" theme="default"]
```

## AI setup

The plugin looks for an API key in either:

- `AI_GAME_INSTRUCTOR_API_KEY`
- `AI_GAME_INSTRUCTOR_GROQ_API_KEY`
- `GROQ_API_KEY`
- `OPENAI_API_KEY`

You can define these in `wp-config.php` or in your environment before WordPress boots. Example:

```php
define('AI_GAME_INSTRUCTOR_API_KEY', 'your-key-here');
```

Then set the provider and model in the plugin admin under "Agent settings".

## Admin flow

Use the plugin admin page to:

- create a game
- create playthroughs
- upload game knowledge files
- save AI agent settings
- delete or reset playthroughs and games

## Notes

This version is intended to be a lightweight WordPress plugin drop-in with low setup friction. It is designed for a practical game companion flow, not a polished SaaS-style interface.
