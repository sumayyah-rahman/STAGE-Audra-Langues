document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('open-widget');
  const closeBtn = document.getElementById('close-widget');
  const widget = document.getElementById('ai-widget');
  const widgetBody = document.querySelector('.ai-widget-body');

  if (!widgetBody) {
    console.error('❌ .ai-widget-body introuvable');
    return;
  }

  // ---------- OUVERTURE / FERMETURE WIDGET ----------
  if (openBtn && widget) {
    openBtn.addEventListener('click', () => {
      widget.classList.toggle('hidden');
    });
  }

  if (closeBtn && widget) {
    closeBtn.addEventListener('click', () => {
      widget.classList.add('hidden');
    });
  }

  // ---------- TEXT TO SPEECH ----------
  function speakText(text) {
    if (!window.speechSynthesis) return;

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    utterance.rate = 0.95;
    window.speechSynthesis.speak(utterance);
  }

  // ---------- AJOUT MESSAGE ----------
  function addMessage(text, sender) {
    const log = document.getElementById('chat-log');
    if (!log) {
      console.error('❌ chat-log introuvable');
      return;
    }

    const msg = document.createElement('div');
    msg.className = `msg ${sender}`;
    msg.textContent = text;
    log.appendChild(msg);
    log.scrollTop = log.scrollHeight;
  }

  // ---------- APPEL BACKEND ----------
  async function getAIResponse(userMessage, theme = '') {
    try {
      const response = await fetch('./assets/api/chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message: userMessage,
          theme: theme
        })
      });

      const rawText = await response.text();
      console.log('Raw backend response:', rawText);

      if (!rawText) {
        return "Sorry, I didn't receive any response from the server.";
      }

      let data;
      try {
        data = JSON.parse(rawText);
      } catch (e) {
        console.error('JSON parse error:', e);
        return "Sorry, the server response is invalid.";
      }

      if (data.reply) {
        return data.reply;
      }

      if (data.error) {
        console.error('Backend Error:', data.error);
        return "Sorry, can you repeat that?";
      }

      return "I don't understand. Can you rephrase the sentence?";
    } catch (error) {
      console.error('Network Error:', error);
      return 'Problème de connexion. Réessaie dans un instant.';
    }
  }

  // ---------- SPEECH TO TEXT ----------
  function startVoice(theme) {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      alert('Browser does not support voice input. Use Chrome/Edge.');
      return;
    }

    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    recognition.onresult = async (event) => {
      const voiceText = event.results[0][0].transcript;
      const input = document.getElementById('user-input');

      if (!input) return;

      input.value = voiceText;

      setTimeout(async () => {
        const userText = input.value.trim();
        if (!userText) return;

        addMessage(userText, 'user');
        input.value = '';

        const botReply = await getAIResponse(userText, theme);
        addMessage(botReply, 'bot');
        speakText(botReply);
      }, 2000);
    };

    recognition.onerror = (event) => {
      console.error('Voice error:', event.error);
    };

    recognition.start();
  }

  // ---------- BIND CHAT CONTROLS ----------
  function bindChatControls(theme) {
    const sendBtn = document.getElementById('send-btn');
    const input = document.getElementById('user-input');
    const micBtn = document.getElementById('mic-btn');

    async function sendCurrentMessage() {
      const userText = input.value.trim();
      if (!userText) return;

      addMessage(userText, 'user');
      input.value = '';

      const botReply = await getAIResponse(userText, theme);
      addMessage(botReply, 'bot');
      speakText(botReply);
    }

    if (sendBtn && input) {
      sendBtn.addEventListener('click', sendCurrentMessage);

      input.addEventListener('keydown', async (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          await sendCurrentMessage();
        }
      });
    }

    if (micBtn) {
      micBtn.addEventListener('click', () => {
        startVoice(theme);
      });
    }
  }

  // ---------- THEMES + MESSAGES DE DEPART ----------
  const starters = {
    Travel: "Great! Let’s talk about travel. Where would you like to go on holiday?",
    Shopping: "Great! Let’s talk about shopping. Do you enjoy shopping, or do you only shop when necessary?",
    Work: "Great! Let’s talk about work. What kind of job would you like to do in the future?",
    School: "Great! Let’s talk about school. What is your favourite subject, and why?",
    Food: "Great! Let’s talk about food. What kind of food do you enjoy the most?",
    Family: "Great! Let’s talk about family. Who are you closest to in your family?",
    Hobbies: "Great! Let’s talk about hobbies. What do you like doing in your free time?",
    "Daily Life": "Great! Let’s talk about daily life. What do you usually do after waking up?",
    Other: "Okay. So what do you want to talk about?"
  };

  // ---------- AFFICHAGE THEMES ----------
  function renderThemeSelection() {
    widgetBody.innerHTML = `
      <p>Hello! What would you like to talk about today?</p>
      <div class="theme-btns">
        <button type="button" class="theme-btn">Travel</button>
        <button type="button" class="theme-btn">Shopping</button>
        <button type="button" class="theme-btn">Work</button>
        <button type="button" class="theme-btn">School</button>
        <button type="button" class="theme-btn">Food</button>
        <button type="button" class="theme-btn">Family</button>
        <button type="button" class="theme-btn">Hobbies</button>
        <button type="button" class="theme-btn">Daily Life</button>
        <button type="button" class="theme-btn">Other</button>
      </div>
    `;

    bindThemeButtons();
  }

  function bindThemeButtons() {
    const currentThemeButtons = document.querySelectorAll('.theme-btn');

    currentThemeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const theme = btn.textContent.trim();
        const starter = starters[theme] || "Great! Let’s start speaking together.";

        widgetBody.innerHTML = `
          <div class="widget-topbar">
            <p class="topic-selected"><strong>Topic selected:</strong> ${theme}</p>
            <button type="button" id="back-to-themes" class="back-btn">← Retour</button>
          </div>

          <div id="chat-log" class="chat-log">
            <div class="msg bot">${starter}</div>
          </div>

          <div class="chat-controls">
            <input type="text" id="user-input" placeholder="Type or use the mic..." />
            <button type="button" id="send-btn">➜</button>
            <button type="button" id="mic-btn" class="mic">🎤</button>
          </div>
        `;

        const backBtn = document.getElementById('back-to-themes');
        if (backBtn) {
          backBtn.addEventListener('click', () => {
            renderThemeSelection();
          });
        }

        bindChatControls(theme);
        speakText(starter);
      });
    });
  }

  // ---------- INITIALISATION ----------
  bindThemeButtons();
});