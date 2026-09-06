# AI Game Instructor Plugin

A WordPress plugin scaffold for a game companion assistant that can be embedded on a page with a shortcode.

## Shortcode

```shortcode
[ai_game_guide]
```

Optional attributes:

```shortcode
[ai_game_guide game_id="12" playthrough_id="3" title="Fallout Guide"]
```

## Install

1. Copy the plugin folder into `wp-content/plugins/`.
2. Activate the plugin in WordPress admin.
3. Add the shortcode to a page or post.

## Notes

This is the initial scaffold. It includes:

- plugin bootstrap
- shortcode renderer
- WordPress admin menu
- frontend CSS/JS shell
- table creation on activation
- AJAX endpoints for messages and memory saving

The next steps are to connect it to a real AI provider, add CRUD management for games/playthroughs, and implement full prompt + retrieval logic.
