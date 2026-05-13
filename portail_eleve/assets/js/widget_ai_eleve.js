// assets/js/widget_ai_eleve.js - les fonctions pour le chatbox

if (window.speechSynthesis) {
  window.speechSynthesis.onvoiceschanged = () => {
    window.speechSynthesis.getVoices();
  };
}

document.addEventListener('DOMContentLoaded', () => {
  const chatLog = document.getElementById('chat-log');
  const userInput = document.getElementById('user-input');
  const sendBtn = document.getElementById('send-btn');
  const micBtn = document.getElementById('mic-btn');

  const selectedMode = document.getElementById('selected-mode');
  const selectedFocus = document.getElementById('selected-focus');
  const endSessionBtn = document.getElementById('end-session-btn');

  const sessionHistory = [];

  let currentMode = '';
  let currentTheme = '';
  let currentGrammar = '';
  let waitingForFocus = false;
  let trainingStarted = false;

  if (!chatLog) {
    console.error('❌ chat-log introuvable');
    return;
  }

  function getBestEnglishVoice() {
    if (!window.speechSynthesis) return null;

    const voices = window.speechSynthesis.getVoices();

    if (!voices || voices.length === 0) {
      return null;
    }

    const preferredNames = [
      'Microsoft Jenny Online',
      'Microsoft Aria Online',
      'Microsoft Guy Online',
      'Microsoft Libby Online',
      'Microsoft Sonia Online',
      'Google US English',
      'Google UK English Female',
      'Google UK English Male',
      'Samantha',
      'Daniel'
    ];

    for (const name of preferredNames) {
      const voice = voices.find(v =>
        v.name.toLowerCase().includes(name.toLowerCase())
      );

      if (voice) {
        return voice;
      }
    }

    return voices.find(v =>
      v.lang &&
      (
        v.lang.toLowerCase().startsWith('en-us') ||
        v.lang.toLowerCase().startsWith('en-gb') ||
        v.lang.toLowerCase().startsWith('en')
      )
    ) || null;
  }

  function speakText(text) {
    if (!window.speechSynthesis) return;

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    const bestVoice = getBestEnglishVoice();

    if (bestVoice) {
      utterance.voice = bestVoice;
      utterance.lang = bestVoice.lang;
      console.log('Voix utilisée :', bestVoice.name, bestVoice.lang);
    } else {
      utterance.lang = 'en-US';
      console.log('Aucune voix anglaise améliorée trouvée. Voix par défaut utilisée.');
    }

	utterance.rate = 0.82;
	utterance.pitch = 1.0;
	utterance.volume = 1;

    window.speechSynthesis.speak(utterance);
  }

  function addMessage(text, sender) {
    const msg = document.createElement('div');
    msg.className = `msg ${sender}`;
    msg.textContent = text;
    chatLog.appendChild(msg);
    chatLog.scrollTop = chatLog.scrollHeight;

    sessionHistory.push({
      sender: sender,
      text: text
    });
  }

  function addChoiceButtons() {
    const row = document.createElement('div');
    row.className = 'chat-choice-row';
    row.innerHTML = `
      <button type="button" class="chat-choice-btn" data-mode="conversation">Conversation practice</button>
      <button type="button" class="chat-choice-btn" data-mode="grammar">Grammar practice</button>
      <button type="button" class="chat-choice-btn" data-mode="both">Both</button>
    `;
    chatLog.appendChild(row);

    row.querySelectorAll('.chat-choice-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        currentMode = btn.dataset.mode || '';
        waitingForFocus = true;

        if (selectedMode) {
          selectedMode.textContent =
            currentMode === 'conversation' ? 'Conversation practice'
            : currentMode === 'grammar' ? 'Grammar practice'
            : currentMode === 'both' ? 'Both'
            : 'Aucun';
        }

        row.remove();

        let prompt = 'Great!';

        if (currentMode === 'conversation') {
          prompt = 'Great! What would you like to talk about today?';
        } else if (currentMode === 'grammar') {
          prompt = 'Great! What grammar point would you like to practise today?';
        } else if (currentMode === 'both') {
          prompt = 'Great! What would you like to talk about, and what grammar point would you like to practise?';
        }

        addMessage(prompt, 'bot');
        speakText(prompt);

        if (userInput) userInput.focus();
      });
    });

    chatLog.scrollTop = chatLog.scrollHeight;
  }

  async function getAIResponse(userMessage, theme = '', grammar = '') {
    try {
      const response = await fetch('./assets/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: userMessage,
          theme: theme,
          grammar: grammar
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

      if (data.reply) return data.reply;
      if (data.error) return "Sorry, can you repeat that?";

      return "I don't understand. Can you rephrase the sentence?";
    } catch (error) {
      console.error('Network Error:', error);
      return 'Problème de connexion. Réessaie dans un instant.';
    }
  }

  async function handleFocusMessage(userText) {
    if (userText.length < 2) {
      addMessage("Could you give a bit more detail? For example, 'food' or 'present perfect'", 'bot');
      speakText("Could you give a bit more detail? For example, food or present perfect.");
      waitingForFocus = true;
      return;
    }

    if (currentMode === 'conversation') {
      currentTheme = userText;
      currentGrammar = '';
      if (selectedFocus) selectedFocus.textContent = currentTheme;
      trainingStarted = true;

      const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
      addMessage(botReply, 'bot');
      speakText(botReply);
      return;
    }

    if (currentMode === 'grammar') {
      currentTheme = '';
      currentGrammar = userText;
      if (selectedFocus) selectedFocus.textContent = currentGrammar;
      trainingStarted = true;

      const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
      addMessage(botReply, 'bot');
      speakText(botReply);
      return;
    }

    if (currentMode === 'both') {
      const parts = userText.split(/ and |, /i);

      if (parts.length >= 2) {
        currentTheme = parts[0].trim();
        currentGrammar = parts[1].trim();

        if (selectedFocus) {
          selectedFocus.textContent = `${currentTheme} / ${currentGrammar}`;
        }

        trainingStarted = true;

        const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
        addMessage(botReply, 'bot');
        speakText(botReply);
        return;
      }

      const botReply = "Can you tell me the topic and grammar point separately? Example: 'food and present perfect'";
      addMessage(botReply, 'bot');
      speakText(botReply);
      waitingForFocus = true;
    }
  }

  async function sendCurrentMessage() {
    if (!userInput) return;

    const userText = userInput.value.trim();
    if (!userText) return;

    addMessage(userText, 'user');
    userInput.value = '';

    if (!currentMode) {
      const botReply = "Please choose what you would like to do first.";
      addMessage(botReply, 'bot');
      speakText(botReply);
      addChoiceButtons();
      return;
    }

    if (waitingForFocus) {
      waitingForFocus = false;
      await handleFocusMessage(userText);
      return;
    }

    if (!trainingStarted) {
      const botReply = "Please tell me first what topic or grammar point you would like to practise.";
      addMessage(botReply, 'bot');
      speakText(botReply);
      return;
    }

    const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
    addMessage(botReply, 'bot');
    speakText(botReply);
  }

  function startVoice() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

    if (!SpeechRecognition) {
      alert("La saisie vocale n'est pas disponible sur ce navigateur. Merci d'utiliser le clavier :)");
      return;
    }

    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;

    recognition.onresult = async (event) => {
      const voiceText = event.results[0][0].transcript;
      if (!userInput) return;

      userInput.value = voiceText;

      setTimeout(async () => {
        await sendCurrentMessage();
      }, 800);
    };

    recognition.onerror = (event) => {
      console.error('Voice error:', event.error);
    };

    recognition.start();
  }

  if (sendBtn) {
    sendBtn.addEventListener('click', sendCurrentMessage);
  }

  if (userInput) {
    userInput.addEventListener('keydown', async (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        await sendCurrentMessage();
      }
    });
  }

  if (micBtn) {
    micBtn.addEventListener('click', () => {
      startVoice();
    });
  }

  addChoiceButtons();

  if (endSessionBtn) {
    endSessionBtn.addEventListener('click', async () => {
      if (!trainingStarted) {
        alert("Aucune séance à terminer.");
        return;
      }

      try {
        const response = await fetch('./assets/api/chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'end_session',
            theme: currentTheme,
            grammar: currentGrammar,
            history: sessionHistory
          })
        });

        const rawText = await response.text();
        console.log('Raw end_session response:', rawText);

        if (!rawText) {
          alert("Aucune réponse du serveur.");
          return;
        }

        let data;
        try {
          data = JSON.parse(rawText);
        } catch (e) {
          console.error('JSON parse error on end_session:', e);
          alert("Réponse invalide du serveur.");
          return;
        }

        if (data.error) {
          console.error('Backend end_session error:', data.error);
          alert("Erreur lors de la fin de séance.");
          return;
        }

        alert("Séance terminée. L'observation a été enregistrée.");

        trainingStarted = false;
        currentMode = '';
        currentTheme = '';
        currentGrammar = '';
        waitingForFocus = true;

        sessionHistory.length = 0;

        if (selectedMode) selectedMode.textContent = 'Aucun';
        if (selectedFocus) selectedFocus.textContent = 'Aucun';

        if (chatLog) {
          chatLog.innerHTML = '<div class="msg bot">Hello! What would you like to do today?</div>';
        }

        addChoiceButtons();

        if (userInput) userInput.value = '';

      } catch (error) {
        console.error('Network error on end_session:', error);
        alert("Problème réseau lors de la fin de séance.");
      }
    });
  }
});