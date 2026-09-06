document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('.ai-game-instructor-plugin');

    containers.forEach(function (container) {
        const sendButton = container.querySelector('.ai-game-instructor-send');
        const input = container.querySelector('.ai-game-instructor-input');
        const messages = container.querySelector('.ai-game-instructor-messages');
        const memoryPanel = container.querySelector('.ai-game-instructor-memory-panel');
        const memoryList = container.querySelector('.ai-game-instructor-memory-list');
        const gameSelect = container.querySelector('.ai-game-instructor-game-select');
        const playthroughSelect = container.querySelector('.ai-game-instructor-playthrough-select');

        try {
            container._state = JSON.parse(container.dataset.state || '{}');
        } catch (error) {
            container._state = { games: [], playthroughs: [] };
        }

        const buildGameOptions = function () {
            if (!gameSelect) {
                return;
            }

            const games = container._state.games || [];
            gameSelect.innerHTML = '';

            if (!games.length) {
                const option = document.createElement('option');
                option.value = '0';
                option.textContent = 'No games yet';
                gameSelect.appendChild(option);
                container.dataset.gameId = '0';
                return;
            }

            games.forEach(function (game) {
                const option = document.createElement('option');
                option.value = String(game.id);
                option.textContent = game.title || 'Game';
                gameSelect.appendChild(option);
            });

            const selectedGame = games.some(function (game) {
                return Number(game.id) === Number(container.dataset.gameId);
            }) ? String(container.dataset.gameId) : String(games[0].id);

            gameSelect.value = selectedGame;
            container.dataset.gameId = selectedGame;
        };

        const updatePlaythroughOptions = function (gameId) {
            if (!playthroughSelect) {
                return;
            }

            const playthroughs = (container._state.playthroughs || []).filter(function (item) {
                return Number(item.game_id) === Number(gameId);
            });

            playthroughSelect.innerHTML = '';
            if (!playthroughs.length) {
                const option = document.createElement('option');
                option.value = '0';
                option.textContent = 'No playthroughs yet';
                playthroughSelect.appendChild(option);
                playthroughSelect.value = '0';
                container.dataset.playthroughId = '0';
                return;
            }

            playthroughs.forEach(function (item) {
                const option = document.createElement('option');
                option.value = String(item.id);
                option.textContent = item.name || 'Playthrough';
                playthroughSelect.appendChild(option);
            });

            const selectedPlaythrough = container.dataset.playthroughId && playthroughs.some(function (item) {
                return Number(item.id) === Number(container.dataset.playthroughId);
            }) ? container.dataset.playthroughId : String(playthroughs[0].id);

            playthroughSelect.value = selectedPlaythrough;
            container.dataset.playthroughId = selectedPlaythrough;
        };

        const refreshWidgetState = function (selectedGameId) {
            const formData = new FormData();
            formData.append('action', 'ai_game_instructor_get_state');
            formData.append('nonce', aiGameInstructorData.nonce);
            formData.append('game_id', selectedGameId || container.dataset.gameId || '0');

            fetch(aiGameInstructorData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload.success) {
                        return;
                    }

                    container._state = {
                        games: payload.data.games || [],
                        playthroughs: payload.data.playthroughs || []
                    };

                    if (payload.data.game_id) {
                        container.dataset.gameId = String(payload.data.game_id);
                    }

                    if (payload.data.playthrough_id) {
                        container.dataset.playthroughId = String(payload.data.playthrough_id);
                    }

                    buildGameOptions();
                    updatePlaythroughOptions(container.dataset.gameId || '0');
                    syncWidgetAvailability();
                })
                .catch(function () {
                    // Ignore refresh errors silently; the widget keeps its current state.
                });
        };

        const syncWidgetAvailability = function () {
            const hasGame = !!(container.dataset.gameId && Number(container.dataset.gameId) > 0);
            const hasPlaythrough = !!(container.dataset.playthroughId && Number(container.dataset.playthroughId) > 0);
            const canChat = hasGame && hasPlaythrough;

            if (sendButton) {
                sendButton.disabled = !canChat;
            }

            if (input) {
                input.disabled = !canChat;
                input.placeholder = canChat ? 'Describe what happened and ask for help...' : 'Create a game and playthrough first...';
            }

            if (memoryPanel && !canChat) {
                memoryPanel.style.display = 'none';
            }
        };

        container._lastProposedMemory = [];
        container._lastProposedObjectives = [];

        if (gameSelect) {
            gameSelect.addEventListener('change', function () {
                const selectedGameId = this.value;
                container.dataset.gameId = selectedGameId;
                refreshWidgetState(selectedGameId);
            });
        }

        if (playthroughSelect) {
            playthroughSelect.addEventListener('change', function () {
                container.dataset.playthroughId = this.value;
                syncWidgetAvailability();
            });
        }

        if (gameSelect) {
            buildGameOptions();
            updatePlaythroughOptions(container.dataset.gameId || '0');
            syncWidgetAvailability();
            refreshWidgetState(container.dataset.gameId || '0');
        }

        syncWidgetAvailability();

        const addMessage = function (text, type) {
            const item = document.createElement('div');
            item.className = 'ai-game-instructor-message ai-game-instructor-message--' + type;

            const p = document.createElement('p');
            p.textContent = text;
            item.appendChild(p);
            messages.appendChild(item);
            messages.scrollTop = messages.scrollHeight;
        };

        const showMemory = function (items) {
            if (!items || !items.length) {
                container._lastProposedMemory = [];
                memoryPanel.style.display = 'none';
                return;
            }

            container._lastProposedMemory = items;
            memoryList.innerHTML = '';
            items.forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item.summary || item.type || 'Memory item';
                memoryList.appendChild(li);
            });

            memoryPanel.style.display = 'block';
        };

        const sendMessage = function () {
            if (!container.dataset.gameId || Number(container.dataset.gameId) <= 0 || !container.dataset.playthroughId || Number(container.dataset.playthroughId) <= 0) {
                addMessage('Create a game and playthrough before sending a message.', 'assistant');
                return;
            }

            const value = input.value.trim();
            if (!value) {
                return;
            }

            addMessage(value, 'user');
            input.value = '';

            const formData = new FormData();
            formData.append('action', 'ai_game_instructor_send_message');
            formData.append('nonce', aiGameInstructorData.nonce);
            formData.append('game_id', container.dataset.gameId || '0');
            formData.append('playthrough_id', container.dataset.playthroughId || '0');
            formData.append('message', value);

            fetch(aiGameInstructorData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload.success) {
                        addMessage(payload.data && payload.data.message ? payload.data.message : 'An error occurred.', 'assistant');
                        return;
                    }

                    const answer = payload.data.answer || 'No answer returned.';
                    addMessage(answer, 'assistant');
                    container._lastProposedObjectives = payload.data.proposed_objectives || [];
                    showMemory(payload.data.proposed_memory || []);
                })
                .catch(function () {
                    addMessage('Unable to reach the AI service right now.', 'assistant');
                });
        };

        sendButton.addEventListener('click', sendMessage);

        input.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                sendMessage();
            }
        });

        const saveButton = container.querySelector('.ai-game-instructor-save-memory');
        if (saveButton) {
            saveButton.addEventListener('click', function () {
                const formData = new FormData();
                formData.append('action', 'ai_game_instructor_save_memory');
                formData.append('nonce', aiGameInstructorData.nonce);
                formData.append('game_id', container.dataset.gameId || '0');
                formData.append('playthrough_id', container.dataset.playthroughId || '0');
                formData.append('memory', JSON.stringify(container._lastProposedMemory || []));
                formData.append('objectives', JSON.stringify(container._lastProposedObjectives || []));

                fetch(aiGameInstructorData.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function () {
                    memoryPanel.style.display = 'none';
                    container._lastProposedMemory = [];
                    container._lastProposedObjectives = [];
                });
            });
        }
    });
});
