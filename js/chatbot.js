const BKEY = 'chat_browser_id_v1';
let browserId = localStorage.getItem(BKEY);
if(!browserId){
  browserId = (Math.random().toString(36).substr(2,9) + Date.now());
  localStorage.setItem(BKEY, browserId);
}

const assistantBtn = document.getElementById('assistant-btn');
const chatBox = document.getElementById('chat-box');
const menuTab = document.getElementById('menuTab');
const contactTab = document.getElementById('contactTab');
const menuContent = document.getElementById('menuContent');
const contactContent = document.getElementById('contactContent');
const closeChat = document.getElementById('closeChat');
const menuItems = document.querySelectorAll('.menu-item');
const menuNumber = document.getElementById('menuNumber');
const contactChat = document.getElementById('contact-chat');
const userMessage = document.getElementById('userMessage');
const sendBtn = document.getElementById('sendBtn');

const API = 'server.php';
let last_message_id = 0, pollInterval = null;

assistantBtn.addEventListener('click',()=>{
  chatBox.style.display = (chatBox.style.display === 'flex') ? 'none' : 'flex';
  chatBox.style.flexDirection = 'column';
});
closeChat.addEventListener('click',()=>{ chatBox.style.display = 'none'; stopPolling(); });

menuTab.addEventListener('click',()=>{
  menuTab.classList.add('active'); contactTab.classList.remove('active');
  menuContent.style.display = 'flex'; contactContent.style.display = 'none'; stopPolling();
});
contactTab.addEventListener('click',()=>{
  contactTab.classList.add('active'); menuTab.classList.remove('active');
  menuContent.style.display = 'none'; contactContent.style.display = 'flex'; startPolling();
});

menuItems.forEach(it => it.addEventListener('click',()=>{
  const val = it.dataset.value;
  appendUserToMenu(val);
  handleChoice(val);
}));
menuNumber.addEventListener('keypress', e=>{
  if(e.key === 'Enter'){
    const v = menuNumber.value.trim();
    if(!v) return;
    appendUserToMenu(v);
    handleChoice(v);
    menuNumber.value = '';
  }
});

function appendUserToMenu(text){
  const d = document.createElement('div');
  d.className = 'msg user';
  d.textContent = text;
  const content = document.querySelector('#menuContent #chat-content');
  content.appendChild(d);
  content.scrollTop = content.scrollHeight;
}
function appendBotToMenu(text){
  const d = document.createElement('div');
  d.className = 'msg bot';
  d.textContent = text;
  const content = document.querySelector('#menuContent #chat-content');
  content.appendChild(d);
  content.scrollTop = content.scrollHeight;
}

function handleChoice(value){
  let reply = '';
  if(value === '1') reply = 'International Conference on Technology and Innovation in the Health Sector - Muscat 2026';
  else if(value === '2') reply = 'January 19–20, 2026';
  else if(value === '3') reply = 'Oman Convention & Exhibition Centre';
  else if(value === '4'){ contactTab.click(); return; }
  else reply = '❓ Please select a valid number from the list.';
  setTimeout(()=>appendBotToMenu(reply), 350);
}

// ===== التواصل مع الموظف =====
sendBtn.addEventListener('click',sendUserMessage);
userMessage.addEventListener('keypress',e=>{if(e.key==='Enter') sendUserMessage();});

async function sendUserMessage(){
  const txt = userMessage.value.trim();
  if(!txt) return;

  // ✅ أضف الرسالة في الشات فورًا
  const userDiv = document.createElement('div');
  userDiv.className = 'msg user';
  userDiv.textContent = txt;
  contactChat.appendChild(userDiv);
  contactChat.scrollTop = contactChat.scrollHeight;

  // ✅ أرسلها للسيرفر
  const res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'send_message',
      sender: 'user',
      browser_id: browserId,
      message: txt
    })
  });

  const j = await res.json();
  if (j.ok && j.message_id)
    last_message_id = Math.max(last_message_id, j.message_id);

  userMessage.value = '';
}


async function pollMessages(){
  try{
    const res=await fetch(`${API}?action=fetch_messages&browser_id=${encodeURIComponent(browserId)}&since_id=${last_message_id}`);
    const j=await res.json();
    if(j.messages && j.messages.length){
      j.messages.forEach(m=>{
        if(m.id<=last_message_id) return;
        last_message_id=Math.max(last_message_id,m.id);
        const el=document.createElement('div');
        el.className=(m.sender==='admin')?'msg admin':(m.sender==='user')?'msg user':'msg bot';
        el.textContent=m.message;
        contactChat.appendChild(el);
        contactChat.scrollTop=contactChat.scrollHeight;
      });
    }
  }catch(e){console.error('poll error',e);}
}

function startPolling(){if(pollInterval)return; pollMessages(); pollInterval=setInterval(pollMessages,2500);}
function stopPolling(){if(!pollInterval)return; clearInterval(pollInterval); pollInterval=null;}