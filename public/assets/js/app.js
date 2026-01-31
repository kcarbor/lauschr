/**
 * LauschR - Main JavaScript
 */

(function() {
    'use strict';

    // Auto-hide alerts after 5 seconds
    document.querySelectorAll('.alert').forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.3s ease';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // Confirm dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // File input preview
    document.querySelectorAll('.file-input-preview').forEach(function(input) {
        input.addEventListener('change', function(e) {
            const preview = document.getElementById(this.dataset.preview);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // Audio file info
    document.querySelectorAll('input[type="file"][accept*="audio"]').forEach(function(input) {
        input.addEventListener('change', function(e) {
            const file = this.files[0];
            if (!file) return;

            const infoContainer = document.getElementById(this.dataset.info);
            if (!infoContainer) return;

            // Show file info
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            infoContainer.innerHTML = `
                <div class="file-info">
                    <strong>${escapeHtml(file.name)}</strong>
                    <span class="text-muted">${sizeMB} MB</span>
                </div>
            `;

            // Try to get duration
            const audio = document.createElement('audio');
            audio.preload = 'metadata';
            audio.src = URL.createObjectURL(file);
            audio.onloadedmetadata = function() {
                URL.revokeObjectURL(audio.src);
                const duration = Math.round(audio.duration);
                const durationInput = document.getElementById('duration');
                if (durationInput) {
                    durationInput.value = duration;
                }

                const durationDisplay = formatDuration(duration);
                infoContainer.innerHTML += `<span class="text-muted"> • Dauer: ${durationDisplay}</span>`;
            };
        });
    });

    // Duration input formatting
    const durationInput = document.getElementById('duration-display');
    if (durationInput) {
        durationInput.addEventListener('blur', function() {
            const seconds = parseDuration(this.value);
            document.getElementById('duration').value = seconds;
            this.value = formatDuration(seconds);
        });
    }

    // Copy to clipboard
    document.querySelectorAll('[data-copy]').forEach(function(button) {
        button.addEventListener('click', function() {
            const text = this.dataset.copy;
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.textContent;
                button.textContent = 'Kopiert!';
                setTimeout(function() {
                    button.textContent = originalText;
                }, 2000);
            });
        });
    });

    // Toggle visibility
    document.querySelectorAll('[data-toggle]').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.getElementById(this.dataset.toggle);
            if (target) {
                target.classList.toggle('hidden');
            }
        });
    });

    // Search users (for collaborator invite)
    const userSearchInput = document.getElementById('user-search');
    if (userSearchInput) {
        let searchTimeout;
        userSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            const resultsContainer = document.getElementById('user-search-results');

            if (query.length < 2) {
                resultsContainer.innerHTML = '';
                return;
            }

            searchTimeout = setTimeout(function() {
                fetch('/api/users/search?q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.users && data.users.length > 0) {
                            resultsContainer.innerHTML = data.users.map(user => `
                                <div class="user-search-result" data-user-id="${user.id}" data-user-email="${escapeHtml(user.email)}">
                                    <div class="collaborator-avatar">${user.name.charAt(0).toUpperCase()}</div>
                                    <div class="collaborator-info">
                                        <div class="collaborator-name">${escapeHtml(user.name)}</div>
                                        <div class="collaborator-email">${escapeHtml(user.email)}</div>
                                    </div>
                                </div>
                            `).join('');

                            // Add click handlers
                            resultsContainer.querySelectorAll('.user-search-result').forEach(function(result) {
                                result.addEventListener('click', function() {
                                    document.getElementById('collaborator-email').value = this.dataset.userEmail;
                                    resultsContainer.innerHTML = '';
                                });
                            });
                        } else {
                            resultsContainer.innerHTML = '<p class="text-muted text-small">Keine Benutzer gefunden</p>';
                        }
                    });
            }, 300);
        });
    }

    // Helper functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
        return `${minutes}:${String(secs).padStart(2, '0')}`;
    }

    function parseDuration(str) {
        const parts = str.split(':').reverse();
        let seconds = 0;
        const multipliers = [1, 60, 3600];

        parts.forEach(function(part, i) {
            if (multipliers[i]) {
                seconds += parseInt(part, 10) * multipliers[i];
            }
        });

        return isNaN(seconds) ? 0 : seconds;
    }
})();
