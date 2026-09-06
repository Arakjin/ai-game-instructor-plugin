document.addEventListener('DOMContentLoaded', function () {
    const containers = document.querySelectorAll('.ai-game-instructor-plugin');

    containers.forEach(function (container) {
        const sendButton = container.querySelector('.ai-game-instructor-send');
        const input = container.querySelector('.ai-game-instructor-input');
        const messages = container.querySelector('.ai-game-instructor-messages');
        const memoryPanel = container.querySelector('.ai-game-instructor-memory-panel');
        const memoryList = container.querySelector('.ai-game-instructor-memory-list');

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
                memoryPanel.style.display = 'none';
                return;
            }

            memoryList.innerHTML = '';
            items.forEach(function (item) {
                const li = document.createElement('li');
                li.textContent = item.summary || item.type || 'Memory item';
                memoryList.appendChild(li);
            });

            memoryPanel.style.display = 'block';
        };

        const sendMessage = function () {
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

                fetch(aiGameInstructorData.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function () {
                    memoryPanel.style.display = 'none';
                });
            });
        }
    });
});
