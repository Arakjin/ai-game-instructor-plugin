<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AI_Game_Instructor_Plugin
{
    protected static $instance = null;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->register_hooks();
    }

    private function register_hooks()
    {
        add_action('init', array($this, 'register_shortcode'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_ai_game_instructor_get_state', array($this, 'ajax_get_state'));
        add_action('wp_ajax_nopriv_ai_game_instructor_get_state', array($this, 'ajax_get_state'));
        add_action('wp_ajax_ai_game_instructor_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_ai_game_instructor_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_ai_game_instructor_save_memory', array($this, 'ajax_save_memory'));
        add_action('wp_ajax_nopriv_ai_game_instructor_save_memory', array($this, 'ajax_save_memory'));
    }

    public static function activate()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $tables = array(
            $wpdb->prefix . 'ai_gg_games' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_games (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_playthroughs' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_playthroughs (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id INT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY game_id (game_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_conversations' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_conversations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                playthrough_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY playthrough_id (playthrough_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_messages' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_messages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT UNSIGNED NOT NULL,
                role VARCHAR(20) NOT NULL,
                content LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_memory' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_memory (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                playthrough_id INT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL,
                summary TEXT NOT NULL,
                data JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY playthrough_id (playthrough_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_objectives' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_objectives (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                playthrough_id INT UNSIGNED NOT NULL,
                text TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY playthrough_id (playthrough_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_game_documents' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_game_documents (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                source_type VARCHAR(50) NULL,
                original_text LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY game_id (game_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_game_chunks' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_game_chunks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                game_id INT UNSIGNED NOT NULL,
                document_id BIGINT UNSIGNED NULL,
                heading VARCHAR(500) NULL,
                content TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY game_id (game_id),
                KEY document_id (document_id)
            ) {$charset_collate};",

            $wpdb->prefix . 'ai_gg_settings' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_gg_settings (
                name VARCHAR(100) NOT NULL,
                value LONGTEXT NULL,
                PRIMARY KEY (name)
            ) {$charset_collate};",
        );

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        self::insert_default_settings();
    }

    public static function deactivate()
    {
        // Intentionally empty for now. We keep schema on deactivation.
    }

    public static function insert_default_settings()
    {
        global $wpdb;

        $defaults = array(
            'ai_provider' => 'groq',
            'ai_model' => 'openai/gpt-oss-120b',
            'agent_name' => 'AI Game Guide',
            'base_prompt' => 'You are an expert game guide helping a player with walkthroughs and strategy.',
            'personality_prompt' => 'Be helpful, concise, and factual.',
            'spoiler_prompt' => 'Avoid unnecessary spoilers unless the user asks directly.',
            'response_style_prompt' => 'Answer in a clear, structured way.',
        );

        foreach ($defaults as $name => $value) {
            $table = $wpdb->prefix . 'ai_gg_settings';
            $existing = $wpdb->get_var($wpdb->prepare("SELECT value FROM {$table} WHERE name = %s LIMIT 1", $name));

            if (null === $existing) {
                $wpdb->insert(
                    $table,
                    array('name' => $name, 'value' => $value),
                    array('%s', '%s')
                );
            }
        }
    }

    private function get_table_name($suffix)
    {
        global $wpdb;

        return $wpdb->prefix . 'ai_gg_' . $suffix;
    }

    public function handle_admin_actions()
    {
        if (!isset($_POST['ai_game_instructor_action'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        check_admin_referer('ai_game_instructor_admin_action');

        $action = sanitize_text_field(wp_unslash($_POST['ai_game_instructor_action']));

        if ('create_game' === $action) {
            $title = trim(sanitize_text_field(wp_unslash($_POST['game_title'] ?? '')));
            $description = trim(sanitize_textarea_field(wp_unslash($_POST['game_description'] ?? '')));

            if ($title) {
                global $wpdb;
                $wpdb->insert(
                    $this->get_table_name('games'),
                    array(
                        'title' => $title,
                        'description' => $description,
                    ),
                    array('%s', '%s')
                );
            }
        }

        if ('create_playthrough' === $action) {
            $game_id = absint($_POST['playthrough_game_id'] ?? 0);
            $name = trim(sanitize_text_field(wp_unslash($_POST['playthrough_name'] ?? '')));

            if ($game_id && $name) {
                global $wpdb;
                $wpdb->insert(
                    $this->get_table_name('playthroughs'),
                    array(
                        'game_id' => $game_id,
                        'name' => $name,
                    ),
                    array('%d', '%s')
                );
            }
        }

        if ('import_game_knowledge' === $action) {
            $game_id = absint($_POST['knowledge_game_id'] ?? 0);
            $title = trim(sanitize_text_field(wp_unslash($_POST['knowledge_title'] ?? 'Imported knowledge')));
            $content = trim(wp_unslash($_POST['knowledge_text'] ?? ''));
            $source_type = sanitize_text_field(wp_unslash($_POST['knowledge_source_type'] ?? 'manual'));

            if ($game_id && !empty($content)) {
                $this->import_game_knowledge($game_id, $title, $content, $source_type);
            }
        }

        if ('delete_game' === $action) {
            $game_id = absint($_POST['game_id'] ?? 0);
            if ($game_id) {
                global $wpdb;
                $wpdb->delete($this->get_table_name('games'), array('id' => $game_id), array('%d'));
            }
        }

        if ('delete_playthrough' === $action) {
            $playthrough_id = absint($_POST['playthrough_id'] ?? 0);
            if ($playthrough_id) {
                global $wpdb;
                $wpdb->delete($this->get_table_name('playthroughs'), array('id' => $playthrough_id), array('%d'));
            }
        }

        if ('reset_playthrough' === $action) {
            $playthrough_id = absint($_POST['playthrough_id'] ?? 0);
            if ($playthrough_id) {
                global $wpdb;
                $wpdb->delete($this->get_table_name('memory'), array('playthrough_id' => $playthrough_id), array('%d'));
                $wpdb->delete($this->get_table_name('objectives'), array('playthrough_id' => $playthrough_id), array('%d'));
                $wpdb->delete($this->get_table_name('messages'), array('conversation_id' => $playthrough_id), array('%d'));
            }
        }

        if ('save_settings' === $action) {
            $settings = array(
                'agent_name' => sanitize_text_field(wp_unslash($_POST['agent_name'] ?? 'AI Game Guide')),
                'ai_provider' => sanitize_text_field(wp_unslash($_POST['ai_provider'] ?? 'groq')),
                'ai_model' => sanitize_text_field(wp_unslash($_POST['ai_model'] ?? 'openai/gpt-oss-120b')),
                'base_prompt' => sanitize_textarea_field(wp_unslash($_POST['base_prompt'] ?? '')),
                'personality_prompt' => sanitize_textarea_field(wp_unslash($_POST['personality_prompt'] ?? '')),
                'spoiler_prompt' => sanitize_textarea_field(wp_unslash($_POST['spoiler_prompt'] ?? '')),
                'response_style_prompt' => sanitize_textarea_field(wp_unslash($_POST['response_style_prompt'] ?? '')),
            );

            $this->save_settings($settings);
        }

        wp_safe_redirect(add_query_arg('updated', 'true', admin_url('admin.php?page=ai-game-instructor')));
        exit;
    }

    public function save_settings($settings)
    {
        global $wpdb;

        foreach ($settings as $name => $value) {
            $table = $this->get_table_name('settings');
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (name, value) VALUES (%s, %s) ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    $name,
                    $value
                )
            );
        }
    }

    public function get_games()
    {
        global $wpdb;

        $table = $this->get_table_name('games');
        $rows = $wpdb->get_results(
            "SELECT id, title, description, created_at FROM {$table} ORDER BY created_at DESC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        return $rows;
    }

    public function save_objectives_for_playthrough($playthrough_id, $objectives)
    {
        if (!$playthrough_id || !is_array($objectives)) {
            return 0;
        }

        global $wpdb;
        $table = $this->get_table_name('objectives');
        $saved = 0;

        foreach ($objectives as $objective) {
            $text = isset($objective['text']) ? trim((string) $objective['text']) : '';
            if ('' === $text) {
                continue;
            }

            $wpdb->insert(
                $table,
                array(
                    'playthrough_id' => $playthrough_id,
                    'text' => $text,
                    'status' => isset($objective['status']) ? sanitize_text_field($objective['status']) : 'active',
                ),
                array('%d', '%s', '%s')
            );

            $saved++;
        }

        return $saved;
    }

    public function get_game_documents($game_id = 0)
    {
        global $wpdb;

        $table = $this->get_table_name('game_documents');
        $sql = "SELECT id, game_id, title, source_type, created_at FROM {$table}";
        $params = array();

        if ($game_id) {
            $sql .= ' WHERE game_id = %d';
            $params[] = $game_id;
        }

        $sql .= ' ORDER BY created_at DESC';

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function chunk_text_into_segments($content, $max_words = 1000)
    {
        $normalized = preg_replace('/\r\n|\r/', "\n", (string) $content);
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        if ('' === $normalized) {
            return array();
        }

        $segments = preg_split('/(?<=\.)\s+|\n\s*\n+/', $normalized);
        $chunks = array();
        $current = '';

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ('' === $segment) {
                continue;
            }

            $segment_words = preg_split('/\s+/', $segment);
            $current_words = preg_split('/\s+/', trim($current));
            $count_current = count(array_filter($current_words, static fn ($word) => '' !== trim((string) $word)));
            $count_segment = count(array_filter($segment_words, static fn ($word) => '' !== trim((string) $word)));

            if ('' !== $current && ($count_current + $count_segment) > $max_words) {
                $chunks[] = trim($current);
                $current = $segment;
                continue;
            }

            $current = ('' === $current) ? $segment : $current . ' ' . $segment;
        }

        if ('' !== trim($current)) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    public function import_game_knowledge($game_id, $title, $content, $source_type = 'manual')
    {
        global $wpdb;

        $game_id = absint($game_id);
        $title = trim((string) $title);
        $content = trim((string) $content);

        if (!$game_id || '' === $content) {
            return false;
        }

        if ('' === $title) {
            $title = 'Imported knowledge';
        }

        $document_id = $wpdb->insert(
            $this->get_table_name('game_documents'),
            array(
                'game_id' => $game_id,
                'title' => $title,
                'source_type' => sanitize_text_field($source_type),
                'original_text' => $content,
            ),
            array('%d', '%s', '%s', '%s')
        );

        if (false === $document_id) {
            return false;
        }

        $document_id = (int) $wpdb->insert_id;
        $chunks = $this->chunk_text_into_segments($content, 900);

        if (empty($chunks)) {
            $chunks = array($content);
        }

        foreach ($chunks as $index => $chunk) {
            $heading = $title;
            if (count($chunks) > 1) {
                $heading .= ' (Part ' . ((int) $index + 1) . ')';
            }

            $wpdb->insert(
                $this->get_table_name('game_chunks'),
                array(
                    'game_id' => $game_id,
                    'document_id' => $document_id,
                    'heading' => $heading,
                    'content' => $chunk,
                ),
                array('%d', '%d', '%s', '%s')
            );
        }

        return true;
    }

    public function get_playthroughs($game_id = 0)
    {
        global $wpdb;

        $table = $this->get_table_name('playthroughs');
        $where = '';
        $params = array();

        if ($game_id) {
            $where = ' WHERE game_id = %d';
            $params[] = $game_id;
        }

        $sql = "SELECT id, game_id, name, created_at FROM {$table}{$where} ORDER BY created_at DESC";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        return $rows;
    }

    public function get_settings_map()
    {
        global $wpdb;

        $table = $this->get_table_name('settings');
        $rows = $wpdb->get_results("SELECT name, value FROM {$table}", ARRAY_A);

        $map = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(string) $row['name']] = (string) $row['value'];
            }
        }

        return $map + array(
            'agent_name' => 'AI Game Guide',
            'ai_provider' => 'groq',
            'ai_model' => 'openai/gpt-oss-120b',
            'base_prompt' => 'You are an expert game guide helping a player with walkthroughs and strategy.',
            'personality_prompt' => 'Be helpful, concise, and factual.',
            'spoiler_prompt' => 'Avoid unnecessary spoilers unless the user asks directly.',
            'response_style_prompt' => 'Answer in a clear, structured way.',
        );
    }

    public function register_shortcode()
    {
        add_shortcode('ai_game_guide', array($this, 'render_shortcode'));
    }

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'ai-game-instructor-plugin-style',
            AI_GAME_INSTRUCTOR_PLUGIN_URL . 'assets/css/ai-game-instructor-plugin.css',
            array(),
            '0.1.0'
        );

        wp_enqueue_script(
            'ai-game-instructor-plugin-script',
            AI_GAME_INSTRUCTOR_PLUGIN_URL . 'assets/js/ai-game-instructor-plugin.js',
            array(),
            '0.1.0',
            true
        );

        wp_localize_script(
            'ai-game-instructor-plugin-script',
            'aiGameInstructorData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ai_game_instructor_nonce'),
                'defaultGameId' => 0,
                'defaultPlaythroughId' => 0,
            )
        );
    }

    public function enqueue_admin_assets($hook)
    {
        if ('toplevel_page_ai-game-instructor' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'ai-game-instructor-plugin-admin-style',
            AI_GAME_INSTRUCTOR_PLUGIN_URL . 'assets/css/ai-game-instructor-plugin.css',
            array(),
            '0.1.0'
        );
    }

    public function render_shortcode($atts = array())
    {
        $atts = shortcode_atts(
            array(
                'game_id' => 0,
                'playthrough_id' => 0,
                'title' => 'Game Guide',
                'class' => '',
                'theme' => 'default',
            ),
            $atts,
            'ai_game_guide'
        );

        $games = $this->get_games();
        $selected_game_id = absint($atts['game_id']);
        if (!$selected_game_id && !empty($games)) {
            $selected_game_id = (int) $games[0]['id'];
        }

        $playthroughs = $selected_game_id ? $this->get_playthroughs($selected_game_id) : array();
        $selected_playthrough_id = absint($atts['playthrough_id']);
        if (!$selected_playthrough_id && !empty($playthroughs)) {
            $selected_playthrough_id = (int) $playthroughs[0]['id'];
        }

        $all_playthroughs = array();
        foreach ($games as $game) {
            $all_playthroughs = array_merge($all_playthroughs, $this->get_playthroughs((int) $game['id']));
        }

        $state_payload = wp_json_encode(array(
            'games' => $games,
            'playthroughs' => $all_playthroughs,
        ));

        $wrapper_classes = 'ai-game-instructor-plugin';
        if (!empty($atts['class'])) {
            $wrapper_classes .= ' ' . esc_attr($atts['class']);
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr($wrapper_classes); ?>" data-game-id="<?php echo esc_attr($selected_game_id); ?>" data-playthrough-id="<?php echo esc_attr($selected_playthrough_id); ?>" data-title="<?php echo esc_attr($atts['title']); ?>" data-theme="<?php echo esc_attr($atts['theme']); ?>" data-state='<?php echo esc_attr($state_payload); ?>'>
            <div class="ai-game-instructor-chat">
                <div class="ai-game-instructor-header">
                    <h3><?php echo esc_html($atts['title']); ?></h3>
                </div>

                <?php if (!empty($games)) : ?>
                    <div class="ai-game-instructor-selector">
                        <label>
                            <span><?php esc_html_e('Game', 'ai-game-instructor'); ?></span>
                            <select class="ai-game-instructor-game-select">
                                <?php foreach ($games as $game) : ?>
                                    <option value="<?php echo esc_attr((int) $game['id']); ?>" <?php selected((int) $game['id'], $selected_game_id); ?>>
                                        <?php echo esc_html($game['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            <span><?php esc_html_e('Playthrough', 'ai-game-instructor'); ?></span>
                            <select class="ai-game-instructor-playthrough-select">
                                <?php foreach ($playthroughs as $playthrough) : ?>
                                    <option value="<?php echo esc_attr((int) $playthrough['id']); ?>" <?php selected((int) $playthrough['id'], $selected_playthrough_id); ?>>
                                        <?php echo esc_html($playthrough['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="ai-game-instructor-messages" aria-live="polite">
                    <div class="ai-game-instructor-message ai-game-instructor-message--assistant">
                        <p><?php esc_html_e('Welcome to your game companion. Ask about quests, items, mechanics, or the next step in your playthrough.', 'ai-game-instructor'); ?></p>
                    </div>
                </div>

                <div class="ai-game-instructor-input-wrap">
                    <textarea class="ai-game-instructor-input" rows="3" placeholder="Describe what happened and ask for help..."></textarea>
                    <button type="button" class="ai-game-instructor-send">
                        <?php esc_html_e('Send', 'ai-game-instructor'); ?>
                    </button>
                </div>

                <div class="ai-game-instructor-memory-panel" style="display:none;">
                    <h4><?php esc_html_e('Proposed memory', 'ai-game-instructor'); ?></h4>
                    <ul class="ai-game-instructor-memory-list"></ul>
                    <div class="ai-game-instructor-memory-actions">
                        <button type="button" class="ai-game-instructor-save-memory"><?php esc_html_e('Save', 'ai-game-instructor'); ?></button>
                        <button type="button" class="ai-game-instructor-edit-memory"><?php esc_html_e('Edit', 'ai-game-instructor'); ?></button>
                        <button type="button" class="ai-game-instructor-discard-memory"><?php esc_html_e('Discard', 'ai-game-instructor'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function register_admin_menu()
    {
        add_menu_page(
            __('AI Game Instructor', 'ai-game-instructor'),
            __('AI Game Instructor', 'ai-game-instructor'),
            'manage_options',
            'ai-game-instructor',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            25
        );
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $games = $this->get_games();
        $settings = $this->get_settings_map();
        $playthroughs = array();

        if (!empty($games)) {
            $playthroughs = $this->get_playthroughs((int) $games[0]['id']);
        }

        $documents = !empty($games) ? $this->get_game_documents((int) $games[0]['id']) : array();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AI Game Instructor', 'ai-game-instructor'); ?></h1>

            <?php if (isset($_GET['updated'])) : ?>
                <div class="notice notice-success"><p><?php echo esc_html__('Settings or data updated.', 'ai-game-instructor'); ?></p></div>
            <?php endif; ?>

            <div class="card" style="padding:1rem; margin-top:1rem; margin-bottom:1rem; max-width:900px;">
                <h2><?php echo esc_html__('Create game', 'ai-game-instructor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                    <input type="hidden" name="ai_game_instructor_action" value="create_game" />
                    <p>
                        <label for="game_title"><?php echo esc_html__('Game title', 'ai-game-instructor'); ?></label><br />
                        <input id="game_title" type="text" name="game_title" class="regular-text" required />
                    </p>
                    <p>
                        <label for="game_description"><?php echo esc_html__('Game description', 'ai-game-instructor'); ?></label><br />
                        <textarea id="game_description" name="game_description" rows="4" class="large-text"></textarea>
                    </p>
                    <?php submit_button(__('Create game', 'ai-game-instructor')); ?>
                </form>
            </div>

            <div class="card" style="padding:1rem; margin-top:1rem; margin-bottom:1rem; max-width:900px;">
                <h2><?php echo esc_html__('Create playthrough', 'ai-game-instructor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                    <input type="hidden" name="ai_game_instructor_action" value="create_playthrough" />
                    <p>
                        <label for="playthrough_game_id"><?php echo esc_html__('Game', 'ai-game-instructor'); ?></label><br />
                        <select id="playthrough_game_id" name="playthrough_game_id" required>
                            <?php foreach ($games as $game) : ?>
                                <option value="<?php echo esc_attr((int) $game['id']); ?>"><?php echo esc_html($game['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="playthrough_name"><?php echo esc_html__('Playthrough name', 'ai-game-instructor'); ?></label><br />
                        <input id="playthrough_name" type="text" name="playthrough_name" class="regular-text" required />
                    </p>
                    <?php submit_button(__('Create playthrough', 'ai-game-instructor')); ?>
                </form>
            </div>

            <div class="card" style="padding:1rem; margin-top:1rem; margin-bottom:1rem; max-width:900px;">
                <h2><?php echo esc_html__('Import game knowledge', 'ai-game-instructor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                    <input type="hidden" name="ai_game_instructor_action" value="import_game_knowledge" />
                    <p>
                        <label for="knowledge_game_id"><?php echo esc_html__('Game', 'ai-game-instructor'); ?></label><br />
                        <select id="knowledge_game_id" name="knowledge_game_id" required>
                            <?php foreach ($games as $game) : ?>
                                <option value="<?php echo esc_attr((int) $game['id']); ?>"><?php echo esc_html($game['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                    <p>
                        <label for="knowledge_title"><?php echo esc_html__('Knowledge title', 'ai-game-instructor'); ?></label><br />
                        <input id="knowledge_title" type="text" name="knowledge_title" class="regular-text" value="Imported knowledge" />
                    </p>
                    <p>
                        <label for="knowledge_source_type"><?php echo esc_html__('Source type', 'ai-game-instructor'); ?></label><br />
                        <select id="knowledge_source_type" name="knowledge_source_type">
                            <option value="manual"><?php echo esc_html__('Manual', 'ai-game-instructor'); ?></option>
                            <option value="walkthrough"><?php echo esc_html__('Walkthrough', 'ai-game-instructor'); ?></option>
                            <option value="faq"><?php echo esc_html__('FAQ', 'ai-game-instructor'); ?></option>
                            <option value="notes"><?php echo esc_html__('Notes', 'ai-game-instructor'); ?></option>
                        </select>
                    </p>
                    <p>
                        <label for="knowledge_text"><?php echo esc_html__('Knowledge text', 'ai-game-instructor'); ?></label><br />
                        <textarea id="knowledge_text" name="knowledge_text" rows="12" class="large-text" placeholder="Paste a walkthrough, quest guide, or notes here..."></textarea>
                    </p>
                    <?php submit_button(__('Import knowledge', 'ai-game-instructor')); ?>
                </form>
            </div>

            <div class="card" style="padding:1rem; margin-top:1rem; margin-bottom:1rem; max-width:900px;">
                <h2><?php echo esc_html__('Agent settings', 'ai-game-instructor'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                    <input type="hidden" name="ai_game_instructor_action" value="save_settings" />
                    <p>
                        <label for="agent_name"><?php echo esc_html__('Agent name', 'ai-game-instructor'); ?></label><br />
                        <input id="agent_name" type="text" name="agent_name" class="regular-text" value="<?php echo esc_attr($settings['agent_name']); ?>" />
                    </p>
                    <p>
                        <label for="ai_provider"><?php echo esc_html__('AI provider', 'ai-game-instructor'); ?></label><br />
                        <input id="ai_provider" type="text" name="ai_provider" class="regular-text" value="<?php echo esc_attr($settings['ai_provider']); ?>" />
                    </p>
                    <p>
                        <label for="ai_model"><?php echo esc_html__('AI model', 'ai-game-instructor'); ?></label><br />
                        <input id="ai_model" type="text" name="ai_model" class="regular-text" value="<?php echo esc_attr($settings['ai_model']); ?>" />
                    </p>
                    <p>
                        <label for="base_prompt"><?php echo esc_html__('Base prompt', 'ai-game-instructor'); ?></label><br />
                        <textarea id="base_prompt" name="base_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['base_prompt']); ?></textarea>
                    </p>
                    <p>
                        <label for="personality_prompt"><?php echo esc_html__('Personality prompt', 'ai-game-instructor'); ?></label><br />
                        <textarea id="personality_prompt" name="personality_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['personality_prompt']); ?></textarea>
                    </p>
                    <p>
                        <label for="spoiler_prompt"><?php echo esc_html__('Spoiler prompt', 'ai-game-instructor'); ?></label><br />
                        <textarea id="spoiler_prompt" name="spoiler_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['spoiler_prompt']); ?></textarea>
                    </p>
                    <p>
                        <label for="response_style_prompt"><?php echo esc_html__('Response style prompt', 'ai-game-instructor'); ?></label><br />
                        <textarea id="response_style_prompt" name="response_style_prompt" rows="4" class="large-text"><?php echo esc_textarea($settings['response_style_prompt']); ?></textarea>
                    </p>
                    <?php submit_button(__('Save settings', 'ai-game-instructor')); ?>
                </form>
            </div>

            <div class="card" style="padding:1rem; margin-top:1rem; max-width:900px;">
                <h2><?php echo esc_html__('Games', 'ai-game-instructor'); ?></h2>
                <?php if (empty($games)) : ?>
                    <p><?php echo esc_html__('No games yet.', 'ai-game-instructor'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($games as $game) : ?>
                            <li>
                                <strong><?php echo esc_html($game['title']); ?></strong>
                                <?php if (!empty($game['description'])) : ?>
                                    <div><?php echo esc_html($game['description']); ?></div>
                                <?php endif; ?>
                                <form method="post" style="display:inline-block; margin-top:0.5rem;">
                                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                                    <input type="hidden" name="ai_game_instructor_action" value="delete_game" />
                                    <input type="hidden" name="game_id" value="<?php echo esc_attr((int) $game['id']); ?>" />
                                    <?php submit_button(__('Delete game', 'ai-game-instructor'), 'secondary small', 'submit', false); ?>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h2><?php echo esc_html__('Playthroughs', 'ai-game-instructor'); ?></h2>
                <?php if (empty($playthroughs)) : ?>
                    <p><?php echo esc_html__('No playthroughs yet.', 'ai-game-instructor'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($playthroughs as $playthrough) : ?>
                            <li>
                                <?php echo esc_html($playthrough['name']); ?>
                                <form method="post" style="display:inline-block; margin-left:0.5rem;">
                                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                                    <input type="hidden" name="ai_game_instructor_action" value="delete_playthrough" />
                                    <input type="hidden" name="playthrough_id" value="<?php echo esc_attr((int) $playthrough['id']); ?>" />
                                    <?php submit_button(__('Delete playthrough', 'ai-game-instructor'), 'secondary small', 'submit', false); ?>
                                </form>
                                <form method="post" style="display:inline-block; margin-left:0.5rem;">
                                    <?php wp_nonce_field('ai_game_instructor_admin_action'); ?>
                                    <input type="hidden" name="ai_game_instructor_action" value="reset_playthrough" />
                                    <input type="hidden" name="playthrough_id" value="<?php echo esc_attr((int) $playthrough['id']); ?>" />
                                    <?php submit_button(__('Reset playthrough', 'ai-game-instructor'), 'secondary small', 'submit', false); ?>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h2><?php echo esc_html__('Imported knowledge', 'ai-game-instructor'); ?></h2>
                <?php if (empty($documents)) : ?>
                    <p><?php echo esc_html__('No documents imported yet.', 'ai-game-instructor'); ?></p>
                <?php else : ?>
                    <ul>
                        <?php foreach ($documents as $document) : ?>
                            <li><?php echo esc_html($document['title']); ?> (<?php echo esc_html($document['source_type'] ?: 'manual'); ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function ajax_get_state()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'ai_game_instructor_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
        }

        $games = $this->get_games();
        $game_id = isset($_POST['game_id']) ? absint($_POST['game_id']) : 0;
        if (!$game_id && !empty($games)) {
            $game_id = (int) $games[0]['id'];
        }

        $playthroughs = $game_id ? $this->get_playthroughs($game_id) : array();

        wp_send_json_success(
            array(
                'game_id' => $game_id,
                'playthrough_id' => !empty($playthroughs) ? (int) $playthroughs[0]['id'] : 0,
                'games' => $games,
                'playthroughs' => $playthroughs,
            )
        );
    }

    public function get_provider_api_key()
    {
        $candidates = array(
            'AI_GAME_INSTRUCTOR_API_KEY',
            'AI_GAME_INSTRUCTOR_GROQ_API_KEY',
            'GROQ_API_KEY',
            'OPENAI_API_KEY',
        );

        foreach ($candidates as $candidate) {
            if (defined($candidate) && constant($candidate)) {
                return (string) constant($candidate);
            }

            $env_value = getenv($candidate);
            if ($env_value) {
                return (string) $env_value;
            }
        }

        return '';
    }

    public function get_setting_value($key, $default = '')
    {
        $settings = $this->get_settings_map();

        if (isset($settings[$key]) && '' !== $settings[$key]) {
            return (string) $settings[$key];
        }

        return $default;
    }

    public function get_game_title($game_id)
    {
        if (!$game_id) {
            return 'Unknown game';
        }

        global $wpdb;
        $game = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT title FROM {$this->get_table_name('games')} WHERE id = %d LIMIT 1",
                $game_id
            ),
            ARRAY_A
        );

        return !empty($game['title']) ? $game['title'] : 'Unknown game';
    }

    public function get_playthrough_name($playthrough_id)
    {
        if (!$playthrough_id) {
            return 'Current playthrough';
        }

        global $wpdb;
        $playthrough = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT name FROM {$this->get_table_name('playthroughs')} WHERE id = %d LIMIT 1",
                $playthrough_id
            ),
            ARRAY_A
        );

        return !empty($playthrough['name']) ? $playthrough['name'] : 'Current playthrough';
    }

    public function get_relevant_knowledge($game_id, $question)
    {
        if (!$game_id || empty($question)) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name('game_chunks');

        $sql = "SELECT id, heading, content,
                MATCH(heading, content) AGAINST (%s IN NATURAL LANGUAGE MODE) AS score
                FROM {$table}
                WHERE game_id = %d
                  AND MATCH(heading, content) AGAINST (%s IN NATURAL LANGUAGE MODE)
                ORDER BY score DESC
                LIMIT 6";

        $query = $wpdb->prepare($sql, $question, $game_id, $question);
        $rows = $wpdb->get_results($query, ARRAY_A);

        if (!is_array($rows)) {
            return array();
        }

        return $rows;
    }

    public function get_recent_memory($playthrough_id)
    {
        if (!$playthrough_id) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name('memory');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT type, summary FROM {$table} WHERE playthrough_id = %d ORDER BY created_at DESC LIMIT 8",
                $playthrough_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    public function get_recent_objectives($playthrough_id)
    {
        if (!$playthrough_id) {
            return array();
        }

        global $wpdb;
        $table = $this->get_table_name('objectives');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT text, status FROM {$table} WHERE playthrough_id = %d ORDER BY updated_at DESC LIMIT 8",
                $playthrough_id
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    public function get_recent_messages($playthrough_id)
    {
        if (!$playthrough_id) {
            return array();
        }

        global $wpdb;
        $conversations_table = $this->get_table_name('conversations');
        $messages_table = $this->get_table_name('messages');

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT m.role, m.content
                 FROM {$messages_table} m
                 INNER JOIN {$conversations_table} c ON c.id = m.conversation_id
                 WHERE c.playthrough_id = %d
                 ORDER BY m.created_at DESC
                 LIMIT 8",
                $playthrough_id
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        return array_reverse($rows);
    }

    public function ensure_conversation($playthrough_id)
    {
        if (!$playthrough_id) {
            return 0;
        }

        global $wpdb;
        $table = $this->get_table_name('conversations');

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE playthrough_id = %d ORDER BY created_at DESC LIMIT 1",
                $playthrough_id
            )
        );

        if ($existing) {
            return (int) $existing;
        }

        $wpdb->insert(
            $table,
            array('playthrough_id' => $playthrough_id),
            array('%d')
        );

        return (int) $wpdb->insert_id;
    }

    public function save_message($conversation_id, $role, $content)
    {
        if (!$conversation_id || empty($content)) {
            return;
        }

        global $wpdb;
        $table = $this->get_table_name('messages');

        $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id,
                'role' => $role,
                'content' => $content,
            ),
            array('%d', '%s', '%s')
        );
    }

    public function build_prompt($game_id, $playthrough_id, $message)
    {
        $game_title = $this->get_game_title($game_id);
        $playthrough_name = $this->get_playthrough_name($playthrough_id);
        $settings = $this->get_settings_map();

        $memory_rows = $this->get_recent_memory($playthrough_id);
        $objective_rows = $this->get_recent_objectives($playthrough_id);
        $knowledge_rows = $this->get_relevant_knowledge($game_id, $message);
        $recent_messages = $this->get_recent_messages($playthrough_id);

        $memory_block = array();
        foreach ($memory_rows as $memory) {
            $memory_block[] = '- ' . $memory['summary'];
        }
        $memory_text = !empty($memory_block) ? implode("\n", $memory_block) : 'None';

        $objective_block = array();
        foreach ($objective_rows as $objective) {
            $objective_block[] = '- ' . $objective['text'] . ' [' . $objective['status'] . ']';
        }
        $objective_text = !empty($objective_block) ? implode("\n", $objective_block) : 'None';

        $knowledge_block = array();
        foreach ($knowledge_rows as $row) {
            $heading = trim((string) $row['heading']);
            $content = trim((string) $row['content']);
            $knowledge_block[] = ($heading ? "## {$heading}\n" : '') . $content;
        }
        $knowledge_text = !empty($knowledge_block) ? implode("\n\n", $knowledge_block) : 'No direct game knowledge matched this query.';

        $conversation_block = array();
        foreach ($recent_messages as $entry) {
            $conversation_block[] = strtoupper((string) $entry['role']) . ': ' . trim((string) $entry['content']);
        }
        $conversation_text = !empty($conversation_block) ? implode("\n", $conversation_block) : 'No recent conversation context.';

        $prompt = "SYSTEM / BASE BEHAVIOUR\n\n";
        $prompt .= !empty($settings['base_prompt']) ? $settings['base_prompt'] : 'You are a helpful game guide.';
        $prompt .= "\n\nAGENT PERSONALITY\n\n";
        $prompt .= !empty($settings['personality_prompt']) ? $settings['personality_prompt'] : 'Be concise and clear.';
        $prompt .= "\n\nGAME\nTitle: {$game_title}\nDescription: Game guide session\n\n";
        $prompt .= "PLAYTHROUGH STATE\n\nMemory:\n{$memory_text}\n\nObjectives:\n{$objective_text}\n\n";
        $prompt .= "RELEVANT GAME KNOWLEDGE\n\n{$knowledge_text}\n\n";
        $prompt .= "RECENT CONVERSATION\n\n{$conversation_text}\n\n";
        $prompt .= "SPOILER POLICY\n\n" . (!empty($settings['spoiler_prompt']) ? $settings['spoiler_prompt'] : 'Avoid unnecessary spoilers.') . "\n\n";
        $prompt .= "USER\n\n" . $message;

        return $prompt;
    }

    public function call_ai_provider($prompt, $provider = 'groq', $model = '')
    {
        $api_key = $this->get_provider_api_key();
        if (empty($api_key)) {
            return array(
                'error' => 'AI API key is not configured. Add AI_GAME_INSTRUCTOR_API_KEY or GROQ_API_KEY in wp-config.php or your environment.',
            );
        }

        $provider = strtolower($provider ?: 'groq');
        $model = $model ?: $this->get_setting_value('ai_model', 'openai/gpt-oss-120b');

        if ('openai' === $provider) {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
        } else {
            $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
        }

        $request_body = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt,
                ),
            ),
            'temperature' => 0.4,
            'response_format' => array('type' => 'json_object'),
        );

        $response = wp_remote_post(
            $endpoint,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($request_body),
            )
        );

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        if (200 !== $status) {
            $body = wp_remote_retrieve_body($response);
            return array('error' => 'AI provider returned status: ' . $status . ' - ' . $body);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($body['choices'][0]['message']['content'])) {
            return array('error' => 'Malformed response from AI provider.');
        }

        $content = trim((string) $body['choices'][0]['message']['content']);
        if (empty($content)) {
            return array('error' => 'Empty response from AI provider.');
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $cleaned = preg_replace('/^```json\s*/i', '', $content);
        $cleaned = preg_replace('/```$/', '', $cleaned);
        $cleaned = trim($cleaned);
        $decoded = json_decode($cleaned, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return array(
            'answer' => $content,
            'proposed_memory' => array(),
            'proposed_objectives' => array(),
        );
    }

    public function ajax_send_message()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'ai_game_instructor_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
        }

        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $game_id = absint($_POST['game_id'] ?? 0);
        $playthrough_id = absint($_POST['playthrough_id'] ?? 0);

        if (empty($message)) {
            wp_send_json_error(array('message' => 'Message is empty.'), 400);
        }

        $conversation_id = $this->ensure_conversation($playthrough_id);
        $this->save_message($conversation_id, 'user', $message);

        $prompt = $this->build_prompt($game_id, $playthrough_id, $message);
        $provider = $this->get_setting_value('ai_provider', 'groq');
        $model = $this->get_setting_value('ai_model', 'openai/gpt-oss-120b');
        $response = $this->call_ai_provider($prompt, $provider, $model);

        if (!empty($response['error'])) {
            $this->save_message($conversation_id, 'assistant', $response['error']);
            wp_send_json_error(array('message' => $response['error']), 500);
        }

        $answer = !empty($response['answer']) ? $response['answer'] : 'I could not form a valid answer from the AI response.';
        $proposed_memory = isset($response['proposed_memory']) && is_array($response['proposed_memory']) ? $response['proposed_memory'] : array();
        $proposed_objectives = isset($response['proposed_objectives']) && is_array($response['proposed_objectives']) ? $response['proposed_objectives'] : array();

        $this->save_message($conversation_id, 'assistant', $answer);

        wp_send_json_success(array(
            'answer' => $answer,
            'proposed_memory' => $proposed_memory,
            'proposed_objectives' => $proposed_objectives,
        ));
    }

    public function ajax_save_memory()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'ai_game_instructor_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce.'), 403);
        }

        $playthrough_id = absint($_POST['playthrough_id'] ?? 0);
        $memory_json = $_POST['memory'] ?? '[]';
        $objective_json = $_POST['objectives'] ?? '[]';
        $memory_items = array();

        if (!empty($memory_json)) {
            $memory_items = json_decode(stripslashes($memory_json), true);
        }

        $objective_items = array();
        if (!empty($objective_json)) {
            $objective_items = json_decode(stripslashes($objective_json), true);
        }

        if (!is_array($memory_items)) {
            $memory_items = array();
        }

        if (!is_array($objective_items)) {
            $objective_items = array();
        }

        if (!$playthrough_id) {
            wp_send_json_error(array('message' => 'No playthrough selected.'), 400);
        }

        global $wpdb;
        $saved_memory = 0;
        $table = $this->get_table_name('memory');

        foreach ($memory_items as $item) {
            $type = isset($item['type']) ? sanitize_text_field($item['type']) : 'event';
            $summary = isset($item['summary']) ? sanitize_text_field($item['summary']) : '';

            if (empty($summary)) {
                continue;
            }

            $wpdb->insert(
                $table,
                array(
                    'playthrough_id' => $playthrough_id,
                    'type' => $type,
                    'summary' => $summary,
                    'data' => isset($item['data']) ? wp_json_encode($item['data']) : null,
                ),
                array('%d', '%s', '%s', '%s')
            );

            $saved_memory++;
        }

        $saved_objectives = $this->save_objectives_for_playthrough($playthrough_id, $objective_items);

        wp_send_json_success(array(
            'saved_memory' => $saved_memory,
            'saved_objectives' => $saved_objectives,
        ));
    }
}
