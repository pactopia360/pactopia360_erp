(function(){
    const panel = document.getElementById('novaPanel');
    const fab = document.getElementById('novaToggle');
    const close = document.getElementById('novaClose');
    const log = document.getElementById('novaLog');
    const input = document.getElementById('novaInput');
    const send = document.getElementById('novaSend');
  
    function open() { panel.classList.add('open'); }
    function hide() { panel.classList.remove('open'); }
  
    fab?.addEventListener('click', open);
    close?.addEventListener('click', hide);
  
    document.querySelectorAll('.nova-btn').forEach(b=>{
      b.addEventListener('click', ()=>{
        input.value = b.dataset.q || '';
        input.focus();
      });
    });
  
    function push(role, text){
      const div = document.createElement('div');
      div.className = 'nova-msg ' + (role === 'user' ? 'user' : 'bot');
      div.textContent = text;
      log?.appendChild(div);
      log?.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });
    }
  
    function fakeAnswer(q){
      // Aquí luego conectamos a tu backend. Por ahora una respuesta de prueba.
      return 'Anotado: ' + q + '. Próximamente te respondo con pasos y accesos relacionados.';
    }
  
    function sendMsg(){
      const q = (input?.value || '').trim();
      if(!q) return;
      push('user', q);
      input.value = '';
      setTimeout(()=>push('bot', fakeAnswer(q)), 400);
    }
  
    send?.addEventListener('click', sendMsg);
    input?.addEventListener('keydown', (e)=>{ if(e.key==='Enter') sendMsg(); });
  })();
  