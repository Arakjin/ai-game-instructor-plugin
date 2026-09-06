# AI Game Guide — WordPress Plugin Instructions

## Purpose

Build a WordPress plugin that turns the game companion into a drop-in page element. The plugin must be installable by copying the plugin folder into `wp-content/plugins` and activating it in WordPress.

The plugin should be embeddable on any page or post with a shortcode such as:

```shortcode
[ai_game_guide]
```

or with options:

```shortcode
[ai_game_guide game_id="12" playthrough_id="3" title="Game Guide"]
```

This is not a standalone PHP app. It is a WordPress plugin that renders a chat-like assistant UI inside the page content, without requiring a custom app route or theme redesign.

The assistant helps the user with game walkthroughs, mechanics, quests, items, NPCs, locations, and playthrough questions. It supports multiple games and multiple playthroughs per game, with shared game knowledge and isolated playthrough memory.

The plugin must keep most behavior configurable from the WordPress admin instead of hard-coding personality rules in PHP.

---

# Core Requirements

## Main user experience

The main interface is a chat panel rendered by shortcode on a normal WordPress page.

The user can:

- pick a game
- pick a playthrough
- ask a question
- submit new game events or discoveries
- receive guided help based on game knowledge and playthrough memory
- review proposed memory after an AI response
- save, edit, or discard proposed memory
- start a new conversation
- review current playthrough memory and objectives if needed

Typical interaction:

```text
User:
I reached the old mine and found a brass key.
The iron door near the entrance is still locked.
Where should I go next?

AI:
<answer based on game knowledge + current playthrough>

Proposed memory:
- Reached the old mine.
- Obtained brass key.
- Iron door near mine entrance remains locked.

[Save] [Edit] [Discard]
```

Do not automatically save every user message to permanent memory.

Recent chat history may remain temporary so follow-up questions still work naturally.

The plugin must be usable on:

- desktop
- tablet
- smartphone

A mobile layout is important because the shortcode may be placed on a front-end page used on phone screens.

---

# Tech Stack

Use the simplest possible implementation consistent with WordPress.

## Frontend

- HTML
- CSS
- Vanilla JavaScript
- WordPress enqueue system

Do not introduce React, Vue, Svelte, Node build tooling, or custom JS frameworks unless explicitly requested later.

The shortcode should render a small UI container and load the necessary frontend assets through WordPress.

## Backend

- WordPress plugin PHP
- WordPress admin screens
- AJAX requests via `admin-ajax.php` or a REST route
- no custom PHP framework unless explicitly requested later

The host environment may be typical shared hosting and must not assume:

- Docker
- root access
- system services
- Node.js
- Redis
- background queues

## Database

- MariaDB / MySQL tables managed by WordPress plugin
- InnoDB tables
- FULLTEXT indexes for game knowledge search
- JSON columns only where flexible structure is useful

Do not require:

- MongoDB
- PostgreSQL
- Elasticsearch
- Pinecone
- vector database

## AI Provider

Initial provider:

- Groq API

Initial intended model:

- `openai/gpt-oss-120b`

The provider must remain replaceable without rewriting the application logic.

Future providers may include:

- OpenAI
- Gemini
- OpenRouter
- other OpenAI-compatible services

API keys must remain server-side and must never be exposed to browser JavaScript.

---

# Plugin Architecture

```text
WordPress page
   |
   v
[ai_game_guide] shortcode
   |
   v
Plugin frontend JS + HTML
   |
   v
WordPress AJAX / REST endpoint
   |
   v
Plugin PHP controller
   |
   +-- MariaDB / MySQL
   |     +-- wp_ai_gg_games
   |     +-- wp_ai_gg_playthroughs
   |     +-- wp_ai_gg_conversations
   |     +-- wp_ai_gg_messages
   |     +-- wp_ai_gg_memory
   |     +-- wp_ai_gg_objectives
   |     +-- wp_ai_gg_game_documents
   |     +-- wp_ai_gg_game_chunks
   |     +-- wp_ai_gg_settings
   |
   +-- AI provider API
```

The plugin is responsible for:

1. reading the selected game and playthrough
2. loading approved playthrough memory
3. searching relevant game knowledge
4. loading recent temporary conversation context
5. assembling the final prompt
6. calling the configured AI provider
7. returning the answer
8. returning proposed memory separately
9. saving memory only after the user explicitly confirms it

The page itself should not run a custom standalone backend. It should rely on WordPress plugin hooks and AJAX calls.

---

# WordPress Integration Requirements

## Shortcode API

The plugin must register at least one shortcode:

```php
add_shortcode('ai_game_guide', 'ai_game_guide_shortcode_handler');
```

Supported attributes:

- `game_id`
- `playthrough_id`
- `title`
- `class`
- `theme`
- `default_open`

Example:

```shortcode
[ai_game_guide game_id="12" playthrough_id="3" title="Fallout Guide"]
```

If no attributes are passed, the shortcode should fall back to the currently active game and playthrough from plugin settings or the latest relevant record.

## Frontend rendering

The shortcode should render a minimal container like:

```html
<div class="ai-game-guide" data-game-id="12" data-playthrough-id="3"></div>
```

The plugin then attaches JavaScript to render:

- message list
- input field
- send button
- memory proposal panel
- save/edit/discard buttons
- loading/error states

Do not require a separate page for the app.

## AJAX endpoint

The plugin must use a WordPress-safe AJAX flow:

- nonce validation
- sanitization of request input
- permission checks
- JSON response

Example action names:

- `ai_game_guide_get_state`
- `ai_game_guide_send_message`
- `ai_game_guide_save_memory`
- `ai_game_guide_reset_playthrough`

Use `wp_send_json_success()` / `wp_send_json_error()` for responses.

## Admin page

Provide a WordPress admin page such as:

- Settings → AI Game Guide
- or Tools → AI Game Guide

This admin area is a core requirement, not an afterthought.

---

# Data Model

The coding agent will not have direct database access outside of the plugin code.

Do not assume WordPress migrations can run automatically.

Whenever schema changes are required:

1. provide the exact SQL
2. explain where it should be executed
3. explain whether it is safe on an existing database
4. clearly identify destructive statements
5. do not assume the schema already exists

The user may create or adjust tables manually through phpMyAdmin or hosting tools.

Use table names prefixed with the plugin name to avoid collisions.

Recommended prefix:

```text
wp_ai_gg_
```

Examples:

- `wp_ai_gg_games`
- `wp_ai_gg_playthroughs`
- `wp_ai_gg_memory`
- `wp_ai_gg_game_chunks`

---

# Tables

## `wp_ai_gg_games`

```sql
CREATE TABLE wp_ai_gg_games (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_playthroughs`

```sql
CREATE TABLE wp_ai_gg_playthroughs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_playthroughs_game
        FOREIGN KEY (game_id)
        REFERENCES wp_ai_gg_games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_game_documents`

```sql
CREATE TABLE wp_ai_gg_game_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    source_type VARCHAR(50) NULL,
    original_text LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_game_documents_game
        FOREIGN KEY (game_id)
        REFERENCES wp_ai_gg_games(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_game_chunks`

```sql
CREATE TABLE wp_ai_gg_game_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    game_id INT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NULL,
    heading VARCHAR(500) NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FULLTEXT KEY ft_wp_ai_gg_game_chunks (heading, content),

    CONSTRAINT fk_wp_ai_gg_game_chunks_game
        FOREIGN KEY (game_id)
        REFERENCES wp_ai_gg_games(id)
        ON DELETE CASCADE,

    CONSTRAINT fk_wp_ai_gg_game_chunks_document
        FOREIGN KEY (document_id)
        REFERENCES wp_ai_gg_game_documents(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Use MariaDB FULLTEXT search for knowledge retrieval. Do not add embeddings or vector search unless retrieval quality is demonstrably poor.

## `wp_ai_gg_memory`

```sql
CREATE TABLE wp_ai_gg_memory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playthrough_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    summary TEXT NOT NULL,
    data JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_memory_playthrough
        FOREIGN KEY (playthrough_id)
        REFERENCES wp_ai_gg_playthroughs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Possible values for `type` include:

```text
event
fact
npc
item
location
choice
note
```

## `wp_ai_gg_objectives`

```sql
CREATE TABLE wp_ai_gg_objectives (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playthrough_id INT UNSIGNED NOT NULL,
    text TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_objectives_playthrough
        FOREIGN KEY (playthrough_id)
        REFERENCES wp_ai_gg_playthroughs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_conversations`

```sql
CREATE TABLE wp_ai_gg_conversations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playthrough_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_conversations_playthrough
        FOREIGN KEY (playthrough_id)
        REFERENCES wp_ai_gg_playthroughs(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_messages`

```sql
CREATE TABLE wp_ai_gg_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(20) NOT NULL,
    content LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_wp_ai_gg_messages_conversation
        FOREIGN KEY (conversation_id)
        REFERENCES wp_ai_gg_conversations(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `wp_ai_gg_settings`

```sql
CREATE TABLE wp_ai_gg_settings (
    name VARCHAR(100) PRIMARY KEY,
    value LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Store application-level config here, and keep sensitive keys in `wp-config.php` or environment variables instead of directly in the database.

---

# Game Isolation Rules

Every game must have independent game knowledge.

Every playthrough must have independent playthrough memory.

Never retrieve memory from another playthrough.

Never retrieve knowledge from another game.

Conceptually:

```text
Game
├── shared game knowledge
└── Playthroughs
    ├── memory
    ├── objectives
    └── conversations
```

All queries involving playthrough state must be scoped by `playthrough_id`.

All knowledge queries must be scoped by `game_id`.

---

# Knowledge Import

The admin panel must allow game knowledge to be imported manually.

Initial supported formats:

- plain text paste
- `.txt` upload
- `.md` upload

Import process:

```text
source text
   |
   v
normalize text
   |
   v
split into chunks
   |
   v
insert into wp_ai_gg_game_chunks
   |
   v
FULLTEXT index updates automatically
```

Use a simple chunking strategy at first. Aim for roughly 500–1500 words per chunk and preserve headings where practical.

Do not overengineer semantic chunking in the initial release.

---

# Knowledge Retrieval

When the user asks a question:

1. inspect the question
2. include relevant playthrough facts and objectives
3. search `wp_ai_gg_game_chunks`
4. retrieve only the most relevant chunks
5. pass those chunks to the AI

Example query pattern:

```sql
SELECT
    id,
    heading,
    content,
    MATCH(heading, content)
        AGAINST (? IN NATURAL LANGUAGE MODE) AS score
FROM wp_ai_gg_game_chunks
WHERE game_id = ?
  AND MATCH(heading, content)
      AGAINST (? IN NATURAL LANGUAGE MODE)
ORDER BY score DESC
LIMIT 8;
```

Keep retrieval simple and predictable. Improve query generation and chunking before introducing vector search.

---

# Prompt Construction

The AI should receive several distinct context layers.

Example:

```text
SYSTEM / BASE BEHAVIOUR

<base_prompt>

AGENT PERSONALITY

<personality_prompt>

GAME

Title:
<game title>

Description:
<game description>

GAME-SPECIFIC INSTRUCTIONS

<optional game instructions>

SPOILER POLICY

<spoiler_prompt>

PLAYTHROUGH STATE

<approved memory>
<active objectives>

RELEVANT GAME KNOWLEDGE

<retrieved chunks>

RECENT CONVERSATION

<recent messages>

USER

<question>
```

Do not hard-code the personality in PHP. Most behavior should be editable from the plugin settings panel.

---

# Admin / Modding Panel

The plugin must include an admin screen for configuration and management.

## Agent settings

Allow editing:

- agent name
- base behavior
- personality / tone
- spoiler policy
- response style
- game-specific instructions
- AI provider
- AI model

Tone and personality must be freeform text.

Example:

```text
You are sarcastic, eccentric, opinionated, and concise.
Point out ridiculous game design when appropriate.
Do not sound like a customer-service chatbot.
Do not invent facts when the provided reference material is insufficient.
```

The plugin should not enforce a narrow built-in personality.

---

# Game Management

The admin area must support:

- create game
- edit game title
- edit game description
- select active game
- delete game
- import knowledge
- inspect imported documents
- delete individual knowledge documents
- replace all knowledge for a game

Switching games must not wipe the database or clear unrelated data.

---

# Playthrough Management

Support:

- create playthrough
- rename playthrough
- select playthrough
- reset playthrough
- delete playthrough
- inspect/edit memory
- inspect/edit objectives

A reset playthrough operation should delete:

- memory
- objectives
- conversations/messages

It should keep:

- game
- game knowledge
- agent settings

---

# Reset / Destructive Operations

Do not recreate the database for normal resets.

Use row deletes.

## Reset playthrough

Delete only rows related to that playthrough.

## Replace game knowledge

Delete:

- `wp_ai_gg_game_chunks`
- relevant `wp_ai_gg_game_documents`

for the selected game.

Keep playthrough memory unless the user explicitly chooses otherwise.

## Delete game

Deleting a game should remove:

- playthroughs
- memory
- objectives
- conversations
- messages
- game documents
- game chunks

Foreign keys with `ON DELETE CASCADE` should handle most of this.

## Factory reset

A factory reset may delete all plugin data while keeping the schema.

Never execute destructive SQL without clearly marking it as destructive.

---

# Memory Philosophy

Persistent memory must remain user-controlled.

The model may propose memory, but it should not automatically decide what belongs in long-term state.

After each question, the backend should return something like:

```json
{
  "answer": "...",
  "proposed_memory": [
    {
      "type": "event",
      "summary": "Reached the old mine."
    },
    {
      "type": "fact",
      "summary": "Obtained a brass key."
    }
  ],
  "proposed_objectives": [
    {
      "text": "Explore the eastern tunnels.",
      "status": "active"
    }
  ]
}
```

The frontend displays:

```text
[Save] [Edit] [Discard]
```

No permanent insert occurs until the user explicitly chooses Save.

---

# Memory Quality Rules

Prefer concise factual memory.

Good:

```text
Obtained the Moon Amulet.
Met Ardan in the capital.
Completed Water Temple.
```

Bad:

```text
The player probably plans to travel north because that seems like the most logical thing to do next.
```

Do not save guesses as facts.

Prefer updating existing state instead of storing redundant entries.

Do not let memory become a transcript dump.

---

# Conversation Memory

Conversation context and persistent memory are separate systems.

Recent conversation exists so follow-up messages still work naturally.

Example:

```text
User:
I found a strange statue.

User:
It has three blue gems. Does that matter?
```

The second message should see the first even if neither is saved to long-term memory.

Only a limited number of recent messages should be sent to the model.

Do not load the entire conversation archive into every request.

---

# AI Response Format

Prefer a predictable structured backend response.

The provider integration should ask the model for:

```json
{
  "answer": "string",
  "proposed_memory": [],
  "proposed_objectives": []
}
```

The plugin must tolerate a provider occasionally returning malformed data.

Do basic validation only.

Do not build an elaborate recovery system unless absolutely required.

---

# WordPress-Specific Implementation Notes

The plugin should follow WordPress conventions:

- main plugin bootstrap file with plugin header
- plugin prefix namespacing
- `register_activation_hook()` for setup tasks if needed
- `register_deactivation_hook()` only if necessary
- `wp_enqueue_style()` and `wp_enqueue_script()` for frontend assets
- `wp_localize_script()` for runtime configuration
- `check_ajax_referer()` for AJAX validation
- `sanitize_text_field()`, `absint()`, `wp_unslash()`, and `esc_html()` where appropriate
- proper escaping before output to avoid XSS

A user should be able to install this plugin, insert a shortcode on a page, and have it work without custom theme code.

The plugin should remain as self-contained as possible. It is not a full custom site; it is a simple drop-in WordPress feature.

---

# Final Goal

Build a WordPress plugin that gives the user a game companion assistant available anywhere on the site through a shortcode, while keeping all configuration and game data management in the WordPress admin panel.

The system should be simple, resilient, and easy to deploy on a typical WordPress hosting setup.

The user should be able to:

- install the plugin
- activate it
- add a shortcode to a page
- manage games and playthroughs in admin
- use the assistant in front-end chat without leaving the page


The user-visible answer must remain separate from proposed memory.

---

# AI Provider Layer

Keep provider-specific code isolated.

Suggested structure:

```text
/api/
    ask.php

/lib/
    ai.php
    groq.php
    db.php
    knowledge.php
    memory.php
    prompt.php
```

Avoid unnecessary classes or abstraction layers.

A simple provider switch is enough.

Conceptually:

```php
switch ($provider) {
    case 'groq':
        return askGroq($payload);

    case 'openai':
        return askOpenAI($payload);

    default:
        throw new RuntimeException('Unsupported AI provider');
}
```

Do not introduce dependency-injection containers, factories, plugin frameworks, or service locators.

---

# Security

Minimum requirements:

- API keys must be server-side only
- use prepared SQL statements
- escape user-controlled HTML output
- restrict admin/modding panel behind authentication
- validate uploads
- limit uploaded file size
- accept only intended text formats initially
- use CSRF protection for destructive admin operations
- require explicit confirmation for destructive actions

Do not expose database credentials to frontend JavaScript.

Do not expose Groq/OpenAI/etc. API keys to frontend JavaScript.

---

# Files and Configuration

Prefer a simple directory layout:

```text
public_html/
├── index.php
├── assets/
│   ├── app.js
│   └── style.css
├── api/
│   ├── ask.php
│   ├── memory-save.php
│   └── games.php
├── admin/
│   └── index.php
└── lib/
    ├── config.php
    ├── db.php
    ├── ai.php
    ├── groq.php
    ├── prompt.php
    ├── memory.php
    └── knowledge.php
```

If possible, keep secrets outside the public web root.

Example:

```text
/home/account/private/gameguide-config.php
```

Do not over-engineer the filesystem.

---

# Initial Implementation Order

Build in this order.

## Phase 1 — Database and basic configuration

Create the MariaDB schema manually.

The coding agent must provide SQL and wait for the user to execute it.

Implement PHP database connection.

## Phase 2 — Games and playthroughs

Implement:

- create game
- select game
- create playthrough
- select playthrough

## Phase 3 — Agent settings

Implement editable prompts:

- base prompt
- personality
- spoiler policy
- response style

## Phase 4 — Knowledge import

Implement:

- paste text
- upload text/Markdown
- chunking
- FULLTEXT storage/search

## Phase 5 — Chat

Implement:

- ask question
- retrieve knowledge
- retrieve playthrough state
- include recent messages
- call Groq
- display answer

## Phase 6 — Controlled memory

Implement:

- proposed memory
- Save
- Edit
- Discard
- objective updates

## Phase 7 — Admin cleanup/reset

Implement:

- reset playthrough
- replace knowledge
- delete playthrough
- delete game

Do not build optional features before this core works.

---

# Non-Goals for Version 1

Do not implement unless explicitly requested:

- automatic screen capture
- image understanding
- live game monitoring
- voice input
- speech synthesis
- native mobile application
- vector database
- embeddings
- multi-agent architecture
- autonomous tool use
- cron-based AI processing
- websockets
- streaming responses
- multiplayer accounts
- social features
- cloud file storage
- React/Vue/Svelte frontend
- Python web backend

Keep the first version small and understandable.

---

# Coding Style

Prefer the simplest working solution.

- small cohesive PHP files/functions
- linear control flow
- prepared statements
- vanilla browser APIs
- minimal dependencies
- no framework unless required
- no premature abstraction
- no speculative extensibility
- no unused wrappers
- no generic repository/service/controller layers unless they solve an actual current problem

If ten lines solve the problem cleanly, do not write fifty.

---

# Development Guidance

The coding agent does not have direct access to the production database or hosting control panel.

When database work is required:

1. explain what needs to change
2. provide exact SQL
3. state whether the SQL is destructive
4. ask the user to run it manually
5. continue from the resulting schema/output

Do not pretend database operations have been performed.

When deployment work is required:

- assume shared hosting
- provide exact file paths and shell commands
- avoid requiring root
- avoid requiring long-running daemons
- prefer PHP features supported by standard shared hosting

---

# Core Product Principle

The application owns:

- game knowledge
- playthrough memory
- objectives
- conversations
- prompt/personality settings

The AI provider only performs reasoning and response generation.

The application must remain usable if the AI provider changes later.

The user remains in control of what becomes persistent memory.
