/**
 * BeauteBot - AI Concierge Logic (Recreated)
 * Focus: Premium Interactions, Smooth Transitions, Robust State
 */

document.addEventListener('DOMContentLoaded', () => {
    window.beauteBotInstance = new BeauteBot();
});

class BeauteBot {
    constructor() {
        this.isLoggedIn = window.BEAUTEBOT_LOGGED_IN === true;
        this.apiPath = this.getApiPath();
        this.lastAction = null;
        this.isTyping = false;

        this.init();
    }

    init() {
        this.createWidget();
        this.bindEvents();
        this.checkInitialState();
    }

    createWidget() {
        // Remove existing container if it exists
        const existing = document.getElementById('beautebot-container');
        if (existing) existing.remove();

        const container = document.createElement('div');
        container.id = 'beautebot-container';
        container.innerHTML = `
            <div id="beautebot-trigger" class="beautebot-trigger" title="Chat with BeauteBot">
                <i class="fas fa-comment-dots"></i>
                <span class="notification-badge" id="beautebot-badge">1</span>
            </div>
            <div id="beautebot-window" class="beautebot-window">
                <div class="beautebot-header">
                    <div class="header-info">
                        <div class="bot-avatar"><i class="fas fa-spa"></i></div>
                        <div class="header-text">
                            <h4>BeauteBot</h4>
                            <div class="status-text">
                                <span class="status-dot"></span>
                                AI Concierge • Online
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button id="beautebot-reset" title="Reset Conversation" onclick="window.beauteBotInstance.resetChat()"><i class="fas fa-undo-alt"></i></button>
                        <button id="beautebot-close" title="Close Chat" onclick="window.beauteBotInstance.toggleWindow(false)"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div id="beautebot-messages" class="beautebot-messages">
                    <!-- Welcome Screen -->
                    <div class="welcome-screen" id="welcome-screen">
                        <div class="welcome-logo"><i class="fas fa-spa"></i></div>
                        <h3>Welcome to Beaute Studio</h3>
                        <p>How can I assist you in your beauty journey today?</p>
                        <div class="beautebot-options" id="initial-options"></div>
                    </div>
                </div>
                <div class="beautebot-input-area">
                    <div id="beautebot-options" class="beautebot-options"></div>
                    <div class="input-wrapper">
                        <input type="text" id="beautebot-input" placeholder="Ask me anything…" autocomplete="off">
                        <button id="beautebot-send" title="Send Message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(container);

        this.elements = {
            trigger: document.getElementById('beautebot-trigger'),
            window: document.getElementById('beautebot-window'),
            messages: document.getElementById('beautebot-messages'),
            input: document.getElementById('beautebot-input'),
            send: document.getElementById('beautebot-send'),
            reset: document.getElementById('beautebot-reset'),
            close: document.getElementById('beautebot-close'),
            options: document.getElementById('beautebot-options'),
            initialOptions: document.getElementById('initial-options'),
            welcome: document.getElementById('welcome-screen'),
            badge: document.getElementById('beautebot-badge')
        };

        this.createInitialOptions();
    }

    bindEvents() {
        this.elements.trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = this.elements.window.classList.contains('active');
            this.toggleWindow(!isOpen);
        });

        const closeHandler = (e) => {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.toggleWindow(false);
        };

        this.elements.close.addEventListener('click', closeHandler);
        this.elements.close.addEventListener('touchstart', closeHandler, { passive: false });

        this.elements.reset.addEventListener('click', (e) => {
            e.preventDefault();
            this.resetChat();
        });

        this.elements.send.addEventListener('click', (e) => {
            e.preventDefault();
            this.handleUserMessage();
        });

        this.elements.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.handleUserMessage();
            }
        });

        // Ensure clicking the wrapper also focuses the input
        const wrapper = this.elements.input.closest('.input-wrapper');
        if (wrapper) {
            wrapper.addEventListener('click', () => this.elements.input.focus());
        }
    }

    checkInitialState() {
        // Global access for inline handlers
        window.beauteBotInstance = this;

        if (sessionStorage.getItem('beautebot_opened')) {
            this.elements.badge.style.display = 'none';
        }

        // Auto-resume pending booking after login/register
        if (window.BEAUTEBOT_PENDING_BOOKING === true) {
            setTimeout(() => {
                this.toggleWindow(true);
                this.hideWelcome();
                this.addMessage('Welcome back! Let me pull up your booking... ✨', 'bot');
                this.fetchResponse({ action: 'resume_booking', payload: '' });
            }, 500);
        }
    }

    createInitialOptions() {
        const options = [
            { label: '📅 Book Now', action: 'start_booking' },
            { label: '💆 Services', action: 'list_services' },
            { label: '👩‍⚕️ Specialists', action: 'list_staff' }
        ];

        if (this.isLoggedIn) {
            options.push({ label: '📋 My Bookings', action: 'view_appointments' });
        }

        options.forEach((opt, index) => {
            const btn = this.createOptionButton(opt);
            btn.style.animationDelay = (index * 0.15) + 's';
            this.elements.initialOptions.appendChild(btn);
        });
    }

    toggleWindow(open) {
        if (open) {
            this.elements.window.classList.add('active');
            this.elements.trigger.classList.add('active');
            this.elements.badge.style.display = 'none';
            sessionStorage.setItem('beautebot_opened', 'true');

            // Multiple focus attempts to ensure it works across all browsers and after transition
            this.elements.input.focus();
            setTimeout(() => this.elements.input.focus(), 300);
            setTimeout(() => this.elements.input.focus(), 600);
        } else {
            this.elements.window.classList.remove('active');
            this.elements.trigger.classList.remove('active');
        }
    }

    async handleUserMessage() {
        const text = this.elements.input.value.trim();
        if (!text || this.isTyping) return;

        this.elements.input.value = '';
        this.hideWelcome();
        this.addMessage(text, 'user');

        await this.fetchResponse({ message: text });
    }

    async handleAction(opt) {
        this.hideWelcome();
        if (opt.label) this.addMessage(opt.label, 'user');

        await this.fetchResponse({ action: opt.action, payload: opt.payload || '' });
    }

    async fetchResponse(body) {
        this.showTyping();
        this.clearOptions();
        this.lastAction = body;

        try {
            const response = await fetch(this.apiPath, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            });

            const data = await response.json();
            this.hideTyping();

            this.addMessage(data.text, 'bot');
            this.showOptions(data.options);

            // Handle automatic redirection (e.g. to login)
            if (data.redirect) {
                const prefix = this.getPrefix();
                let target = prefix + data.redirect;
                const currentUrl = window.location.href;

                // Add redirect parameter to the target URL
                if (target.indexOf('?') === -1) {
                    target += '?redirect=' + encodeURIComponent(currentUrl);
                } else {
                    target += '&redirect=' + encodeURIComponent(currentUrl);
                }

                // Small delay so user sees the message
                setTimeout(() => {
                    window.location.href = target;
                }, 1500);
            }
        } catch (error) {
            this.hideTyping();
            this.addMessage("I'm having trouble connecting to my beauty brain. 💫", 'bot');
            this.showRetry();
        }
    }

    addMessage(text, sender) {
        const msg = document.createElement('div');
        msg.className = `message ${sender}-message`;
        msg.innerHTML = this.formatText(text);

        this.elements.messages.appendChild(msg);
        this.scrollToBottom();
    }

    formatText(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>')
            .replace(/• (.*?)(<br>|$)/g, '<div class="list-item"><i class="fas fa-sparkles"></i> $1</div>')
            .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="chat-link">$1</a>');
    }

    showOptions(options) {
        this.clearOptions();
        if (!options || options.length === 0) return;

        options.forEach(opt => {
            this.elements.options.appendChild(this.createOptionButton(opt));
        });
        this.elements.options.style.display = 'flex';
        this.scrollToBottom();
    }

    createOptionButton(opt) {
        const btn = document.createElement('button');
        btn.className = 'option-btn' + (opt.danger ? ' danger' : '');
        btn.innerText = opt.label;
        btn.onclick = () => this.handleAction(opt);
        return btn;
    }

    clearOptions() {
        this.elements.options.innerHTML = '';
        this.elements.options.style.display = 'none';
    }

    showRetry() {
        const btn = this.createOptionButton({ label: '🔄 Try Again', action: 'retry' });
        btn.onclick = () => this.fetchResponse(this.lastAction);
        this.elements.options.appendChild(btn);
        this.elements.options.style.display = 'flex';
    }

    showTyping() {
        if (this.isTyping) return;
        this.isTyping = true;

        const loader = document.createElement('div');
        loader.id = 'bot-typing';
        loader.className = 'message bot-message typing';
        loader.innerHTML = '<span></span><span></span><span></span>';

        this.elements.messages.appendChild(loader);
        this.scrollToBottom();
    }

    hideTyping() {
        this.isTyping = false;
        const loader = document.getElementById('bot-typing');
        if (loader) loader.remove();
    }

    hideWelcome() {
        if (this.elements.welcome) {
            this.elements.welcome.style.display = 'none';
        }
    }

    resetChat() {
        this.elements.messages.innerHTML = '';
        this.elements.messages.appendChild(this.elements.welcome);
        this.elements.welcome.style.display = 'block';
        this.handleAction({ action: 'reset_chat' });
    }

    scrollToBottom() {
        setTimeout(() => {
            this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
        }, 50);
    }

    getPrefix() {
        if (typeof BASE_URL !== 'undefined') return BASE_URL;
        const path = window.location.pathname;
        const appName = '/Capstone/';
        const index = path.indexOf(appName);
        if (index !== -1) {
            const subPath = path.substring(index + appName.length);
            const depth = (subPath.match(/\//g) || []).length;
            return '../'.repeat(depth);
        }

        // Final fallback
        const depth = (path.match(/\//g) || []).length - 1;
        return depth > 1 ? '../'.repeat(depth - 1) : '';
    }

    getApiPath() {
        return this.getPrefix() + 'api/chatbot.php';
    }
}
