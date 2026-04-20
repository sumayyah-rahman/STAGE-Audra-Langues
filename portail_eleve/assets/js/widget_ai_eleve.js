document.addEventListener('DOMContentLoaded', () => {
  const themeSelect = document.getElementById('theme-select');
  const grammarSelect = document.getElementById('grammar-select');
  const startBtn = document.getElementById('start-training-btn');

  const selectedTheme = document.getElementById('selected-theme');
  const selectedGrammar = document.getElementById('selected-grammar');

  const chatLog = document.getElementById('chat-log');
  const userInput = document.getElementById('user-input');
  const sendBtn = document.getElementById('send-btn');
  const micBtn = document.getElementById('mic-btn');

  let currentTheme = '';
  let currentGrammar = '';
  let trainingStarted = false;

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

  function speakText(text) {
    if (!window.speechSynthesis) return;

    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    utterance.rate = 0.95;
    window.speechSynthesis.speak(utterance);
  }

  function addMessage(text, sender) {
    if (!chatLog) {
      console.error('❌ chat-log introuvable');
      return;
    }

    const msg = document.createElement('div');
    msg.className = `msg ${sender}`;
    msg.textContent = text;
    chatLog.appendChild(msg);
    chatLog.scrollTop = chatLog.scrollHeight;
  }

  function clearChat() {
    if (!chatLog) return;
    chatLog.innerHTML = '';
  }

  async function getAIResponse(userMessage, theme = '', grammar = '') {
    try {
      const response = await fetch('./assets/api/chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
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

  function startVoice() {
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

      if (!userInput) return;

      userInput.value = voiceText;

      setTimeout(async () => {
        const userText = userInput.value.trim();
        if (!userText) return;

        addMessage(userText, 'user');
        userInput.value = '';

        const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
        addMessage(botReply, 'bot');
        speakText(botReply);
      }, 2000);
    };

    recognition.onerror = (event) => {
      console.error('Voice error:', event.error);
    };

    recognition.start();
  }

  async function sendCurrentMessage() {
    if (!trainingStarted) {
      alert("Please choose a theme and click 'Commencer l'entraînement' first.");
      return;
    }

    const userText = userInput.value.trim();
    if (!userText) return;

    addMessage(userText, 'user');
    userInput.value = '';

    const botReply = await getAIResponse(userText, currentTheme, currentGrammar);
    addMessage(botReply, 'bot');
    speakText(botReply);
  }

  if (startBtn) {
    startBtn.addEventListener('click', () => {
      currentTheme = themeSelect ? themeSelect.value.trim() : '';
      currentGrammar = grammarSelect ? grammarSelect.value.trim() : '';

      if (!currentTheme) {
        alert("Please choose a theme before starting.");
        return;
      }

      trainingStarted = true;

      if (selectedTheme) {
        selectedTheme.textContent = currentTheme || 'Aucun';
      }

      if (selectedGrammar) {
        selectedGrammar.textContent = currentGrammar || 'Aucun';
      }

      clearChat();

      let starter = starters[currentTheme] || "Great! Let’s start speaking together.";

      if (currentGrammar) {
        starter += ` We will also pay attention to ${currentGrammar}.`;
      }

      addMessage(starter, 'bot');
      speakText(starter);

      if (userInput) {
        userInput.focus();
      }
    });
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
      if (!trainingStarted) {
        alert("Please choose a theme and click 'Commencer l'entraînement' first.");
        return;
      }
      startVoice();
    });
  }
});
