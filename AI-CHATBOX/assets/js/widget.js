// INPUT VOICE
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

// OUTPUT VOICE
function speakText(text) {
    if (!window.speechSynthesis) return;
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.lang = 'en-US';
    utterance.rate = 0.95;
    window.speechSynthesis.speak(utterance);
}

// MESSAGE BUBBLES
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

// SEND MESSAGE
async function sendMessage() {
    const input = document.getElementById('user-input');
    const userText = input.value.trim();
    if (!userText) return;
    
    addMessage(userText, 'user');
    input.value = '';
    
    const botReply = await getAIResponse(userText);

    addMessage(botReply, 'bot');
    speakText(botReply);
}

// BOT'S BRAIN
async function getAIResponse(userMessage) {    
    try {
        const response = await fetch("../../api/chat.php",
            {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: userMessage
                })
            }
        );


        const rawText = await response.text();
        console.log("Raw backend response:", rawText);

        const data = JSON.parse(rawText);
        console.log("Backend Response:", data);
        
        if (data.reply) {
            return data.reply;
        }
        
        if (data.error) {
            console.error("Gemini API Error:", data.error);
            return "Sorry, can you repeat that?";
        }
        
        return "I don't understand. Can you rephrase the sentence?";
        
    } catch (error) {
        console.error("Network Error:", error);
        return "Connection problem. Try again.";
    }
}




// EVENT LISTENERS
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
