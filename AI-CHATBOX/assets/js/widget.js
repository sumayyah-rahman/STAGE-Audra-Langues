function startVoice() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        alert("Browser does not support voice input. Use Chrome/Edge.");
        return;
    }
    
    const recognition = new SpeechRecognition();
    recognition.lang = 'en-US';
    recognition.interimResults = false;
    
    recognition.onresult = (event) => {
        const voiceText = event.results[0][0].transcript;
        document.getElementById('user-input').value = voiceText;
        sendMessage();
    };
    
    recognition.onerror = (event) => {
        console.error("Voice error:", event.error);
    };
    
    recognition.start();
}

function speakText(text) {
    if (!window.speechSynthesis) return;
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    utterance.rate = 0.95;
    window.speechSynthesis.speak(utterance);
}

function addMessage(text, sender) {
    const log = document.getElementById('chat-log');
    if (!log) {
        console.error("❌ chat-log NOT FOUND!");
        return;
    }
    const msg = document.createElement('div');
    msg.className = `msg ${sender}`;
    msg.textContent = text;
    log.appendChild(msg);
    log.scrollTop = log.scrollHeight;
}

function sendMessage() {
    const input = document.getElementById('user-input');
    if (!input) {
        console.error("❌ user-input NOT FOUND!");
        return;
    }
    
    const userText = input.value.trim();
    if (!userText) return;
    
    addMessage(userText, 'user');
    input.value = '';
    
    const botReply = getAIResponse(userText);
    addMessage(botReply, 'bot');
    speakText(botReply);
}

function getAIResponse(userMessage) {
    const msg = userMessage.toLowerCase();
    
    if (msg.includes('hello') || msg.includes('hi')) {
        return "Hello there! How are you doing today?";
    } else if (msg.includes('how are you')) {
        return "I'm doing great! What about you?";
    } else if (msg.includes('name')) {
        return "I'm your English conversation partner. Call me ChatBot!";
    } else {
        return "That's interesting! Tell me more.";
    }
}

// Setup event listeners bila page dah load
document.addEventListener('DOMContentLoaded', () => {
    console.log("✅ Widget initialized!");
    
    document.getElementById('send-btn')?.addEventListener('click', sendMessage);
    document.getElementById('mic-btn')?.addEventListener('click', startVoice);
    
    const input = document.getElementById('user-input');
    if (input) {
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    }
});
