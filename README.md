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

You can connect the plugin to multiple AI providers from the admin settings page under "Agent settings".

### Supported providers

- Groq
  - Good for cheap and fast usage
  - API key: from https://console.groq.com/keys
  - Example model: `openai/gpt-oss-120b` or `llama-3.3-70b-versatile`

- OpenAI
  - API key: from https://platform.openai.com/api-keys
  - Example model: `gpt-4o-mini`, `gpt-4.1-mini`, `gpt-4o`

- Google Gemini
  - API key: from https://aistudio.google.com/app/apikey
  - Example model: `gemini-2.5-flash`, `gemini-2.5-pro`, `gemini-3.6-flash`

- Azure OpenAI
  - API key and endpoint: from Azure Portal → your Azure OpenAI resource → Keys and Endpoint
  - Use the deployment name as the model value
  - Example URL pattern: `https://<resource>.openai.azure.com/openai/deployments/<deployment>/chat/completions?api-version=2024-02-01`

- Custom OpenAI-compatible endpoint
  - Use a custom endpoint that accepts the same OpenAI-style chat data format
  - Put the full endpoint URL in the AI API URL field

### Environment fallback

The plugin still checks for these environment variables if you prefer server-level config:

- `AI_GAME_INSTRUCTOR_API_KEY`
- `AI_GAME_INSTRUCTOR_GROQ_API_KEY`
- `GROQ_API_KEY`
- `OPENAI_API_KEY`
- `GEMINI_API_KEY`

Example:

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
