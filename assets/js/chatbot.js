/**
 * CARPIBOT - ASISTENTE VIRTUAL INTERNO
 * High-contrast input + interactive stock flow + multi-step navigation
 */
document.addEventListener('DOMContentLoaded', () => {
    initCarpibot();
});

function initCarpibot() {
    if (document.getElementById('carpibotContainer')) return;

    const container = document.createElement('div');
    container.id = 'carpibotContainer';

    container.innerHTML = `
        <!-- FLOATING BUTTON TRIGGER -->
        <button class="bot-trigger-btn" id="botTrigger" onclick="toggleCarpibot()" title="Abrir Asistente Virtual Carpibot">
            <i class="fas fa-robot"></i>
            <span class="bot-trigger-badge"></span>
        </button>

        <!-- CHAT WINDOW -->
        <div class="bot-window" id="botWindow">
            <!-- HEADER -->
            <div class="bot-header">
                <div class="bot-header-info">
                    <div class="bot-avatar-wrapper">
                        <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                        <span class="bot-status-dot"></span>
                    </div>
                    <div class="bot-header-text">
                        <div class="bot-title">Carpibot <span class="bot-title-tag">IA</span></div>
                        <div class="bot-subtitle">Asistente en línea • Carpicenter</div>
                    </div>
                </div>
                <button class="bot-close-btn" onclick="toggleCarpibot()" title="Cerrar"><i class="fas fa-times"></i></button>
            </div>

            <!-- CHIPS / QUICK ACCESS -->
            <div class="bot-chips-bar">
                <button class="bot-chip" onclick="botTriggerAction({action:'stock_inicio'})"><i class="fas fa-box"></i> Consultar Stock</button>
                <button class="bot-chip" onclick="botSendText('Contratos pendientes')"><i class="fas fa-file-contract"></i> Contratos</button>
                <button class="bot-chip" onclick="botSendText('Ventas de hoy')"><i class="fas fa-coins"></i> Ventas</button>
                <button class="bot-chip" onclick="botSendText('Entregas esta semana')"><i class="fas fa-truck"></i> Entregas</button>
                <button class="bot-chip" onclick="botSendText('Ayuda del sistema')"><i class="fas fa-question-circle"></i> Ayuda</button>
            </div>

            <!-- MESSAGES BODY -->
            <div class="bot-body" id="botBody">
                <div class="bot-msg bot-msg-bot">
                    👋 ¡Hola! Soy <strong>Carpibot</strong>, tu asistente de Carpicenter.<br><br>
                    Escríbeme tu consulta (ej. <em>"¿Hay Banco Capri en stock?"</em>) o haz clic en <strong>📦 Consultar Stock</strong> para ver por catálogo.
                </div>
            </div>

            <!-- FOOTER INPUT CONTAINER WITH INLINE FAILSAFE HIGH-CONTRAST STYLES -->
            <div class="bot-footer" style="padding: 12px 14px; background: #ffffff !important; border-top: 1px solid #e2e8f0 !important; display: flex; flex-direction: column; gap: 5px;">
                <div class="bot-input-wrapper" id="botInputWrapper" style="display: flex; align-items: center; gap: 8px; background: #ffffff !important; border: 2px solid #cbd5e1 !important; border-radius: 24px; padding: 5px 8px 5px 14px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.03);">
                    <i class="far fa-comment-dots" style="color: #64748b !important; font-size: 1rem;"></i>
                    <input type="text" class="bot-input" id="botInput"
                        placeholder="Escribe tu consulta aquí..."
                        style="flex: 1; background: #ffffff !important; color: #0f172a !important; font-size: 0.95rem !important; font-weight: 600 !important; border: none !important; outline: none !important; box-shadow: none !important; padding: 6px 0 !important;"
                        onkeypress="onBotKeyPress(event)"
                        oninput="onBotInputChange(this)"
                        onfocus="this.parentElement.style.borderColor='#C62828'; this.parentElement.style.boxShadow='0 0 0 3px rgba(198, 40, 40, 0.18)';"
                        onblur="this.parentElement.style.borderColor='#cbd5e1'; this.parentElement.style.boxShadow='none';"
                        autocomplete="off">
                    <button class="bot-send-btn" id="botSendBtn" onclick="sendBotMessage()" title="Enviar mensaje" style="width: 38px; height: 38px; border-radius: 50%; background: #C62828; color: #ffffff !important; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0;">
                        <i class="fas fa-paper-plane" style="color: #ffffff !important;"></i>
                    </button>
                </div>
                <div class="bot-input-hint" style="font-size: 0.72rem; color: #64748b !important; text-align: center; margin-top: 3px; font-weight: 500;">💡 Presiona <strong>Enter ↵</strong> para enviar tu consulta</div>
            </div>
        </div>
    `;

    document.body.appendChild(container);
}

// ─── Toggle chat window ──────────────────────────────────────────────────────
function toggleCarpibot() {
    const win = document.getElementById('botWindow');
    if (!win) return;
    win.classList.toggle('active');
    if (win.classList.contains('active')) {
        setTimeout(() => {
            const input = document.getElementById('botInput');
            if (input) input.focus();
        }, 100);
    }
}

// ─── Real-time input animation ───────────────────────────────────────────────
function onBotInputChange(input) {
    const btn = document.getElementById('botSendBtn');
    if (!btn) return;
    if (input.value.trim().length > 0) {
        btn.style.background = '#2E7D32';
        btn.style.boxShadow = '0 4px 10px rgba(46, 125, 50, 0.4)';
    } else {
        btn.style.background = '#C62828';
        btn.style.boxShadow = 'none';
    }
}

// ─── Key press on input ──────────────────────────────────────────────────────
function onBotKeyPress(e) {
    if (e.key === 'Enter') sendBotMessage();
}

// ─── Send plain text message ─────────────────────────────────────────────────
function botSendText(text) {
    const input = document.getElementById('botInput');
    if (input) {
        input.value = text;
        onBotInputChange(input);
        sendBotMessage();
    }
}

// ─── Trigger action button (interactive flow) ────────────────────────────────
function botTriggerAction(data) {
    const action = data.action || '';
    const params = { ...data };
    delete params.action;

    // Show what the user "clicked" as a user bubble
    const labels = {
        'stock_inicio'    : '📦 Consultar Stock',
        'stock_categoria' : '🪑 ' + (data.cat_nombre || 'Categoría seleccionada'),
        'stock_producto'  : '📋 ' + (data.prod_nombre || 'Producto seleccionado'),
        'stock_resultado' : '🏬 ' + (data.local_nombre || 'Local seleccionado'),
    };
    const displayLabel = labels[action] || action;
    appendUserBubble(displayLabel);

    // Disable all action buttons in current bot messages (avoid double clicks)
    document.querySelectorAll('.bot-action-btn').forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.5';
        b.style.cursor = 'not-allowed';
    });

    callBotAPI('', action, params);
}

// ─── Send message from text input ────────────────────────────────────────────
function sendBotMessage() {
    const input = document.getElementById('botInput');
    if (!input) return;

    const text = input.value.trim();
    if (!text) return;

    appendUserBubble(text);
    input.value = '';
    onBotInputChange(input);

    callBotAPI(text, '', {});
}

// ─── Append a user bubble ────────────────────────────────────────────────────
function appendUserBubble(text) {
    const body = document.getElementById('botBody');
    if (!body) return;
    const bubble = document.createElement('div');
    bubble.className = 'bot-msg bot-msg-user';
    bubble.textContent = text;
    body.appendChild(bubble);
    scrollToBottom();
}

// ─── Central API call ────────────────────────────────────────────────────────
function callBotAPI(message, action, params) {
    const body = document.getElementById('botBody');
    if (!body) return;

    // Typing indicator
    const typingElem = document.createElement('div');
    typingElem.className = 'bot-msg bot-msg-bot bot-typing';
    typingElem.id = 'botTypingElem';
    typingElem.innerHTML = '<span></span><span></span><span></span>';
    body.appendChild(typingElem);
    scrollToBottom();

    fetch('/carpicenter_sys/modules/chatbot/chatbot_api.php', {
        method : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body   : JSON.stringify({ message, action, params })
    })
    .then(res => res.json())
    .then(data => {
        const typing = document.getElementById('botTypingElem');
        if (typing) typing.remove();

        const botBubble = document.createElement('div');
        botBubble.className = 'bot-msg bot-msg-bot';
        botBubble.innerHTML = data.reply || 'No pude procesar tu mensaje.';
        body.appendChild(botBubble);
        scrollToBottom();
    })
    .catch(err => {
        const typing = document.getElementById('botTypingElem');
        if (typing) typing.remove();

        const botBubble = document.createElement('div');
        botBubble.className = 'bot-msg bot-msg-bot';
        botBubble.innerHTML = '⚠️ Error de conexión con el asistente.';
        body.appendChild(botBubble);
        scrollToBottom();
        console.error('Carpibot error:', err);
    });
}

// ─── Scroll messages to bottom ───────────────────────────────────────────────
function scrollToBottom() {
    const body = document.getElementById('botBody');
    if (body) body.scrollTop = body.scrollHeight;
}
