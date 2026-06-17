/* ============================================================
   CONGRESSIS — interactions v2
   ============================================================ */
(function(){
  "use strict";

  /* ---------- Sticky header ---------- */
  var hd = document.getElementById('hd');
  var backTop = document.getElementById('backTop');
  function onScroll(){
    hd.classList.toggle('scrolled', window.scrollY > 40);
    if(backTop) backTop.classList.toggle('visible', window.scrollY > 400);
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
  if(backTop) backTop.addEventListener('click', function(){ window.scrollTo({top:0,behavior:'smooth'}); });

  /* ---------- Mobile menu ---------- */
  var burger  = document.getElementById('burger');
  var mmenu   = document.getElementById('mmenu');
  var mmClose = document.getElementById('mmClose');
  function openMenu(){ mmenu.classList.add('open'); document.body.classList.add('lock'); }
  function closeMenu(){ mmenu.classList.remove('open'); document.body.classList.remove('lock'); }
  burger.addEventListener('click', openMenu);
  mmClose.addEventListener('click', closeMenu);
  document.querySelectorAll('[data-mm]').forEach(function(el){ el.addEventListener('click', closeMenu); });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('.faq-item').forEach(function(item){
    item.querySelector('.faq-q').addEventListener('click', function(){
      var isOpen = item.classList.contains('open');
      var faqA = item.querySelector('.faq-a');
      var targetH = isOpen ? 0 : faqA.scrollHeight; // read before DOM change to avoid forced reflow
      document.querySelectorAll('.faq-item.open').forEach(function(o){
        o.classList.remove('open');
        o.querySelector('.faq-a').style.maxHeight = null;
      });
      if(!isOpen){
        item.classList.add('open');
        faqA.style.maxHeight = targetH + 'px';
      }
    });
  });

  /* ---------- Modal ---------- */
  var modal        = document.getElementById('modal');
  var modalForm    = document.getElementById('modalForm');
  var modalSuccess = document.getElementById('modalSuccess');
  var lastFocus    = null;

  function openModal(){
    lastFocus = document.activeElement;
    modalForm.style.display   = '';
    modalSuccess.style.display = 'none';
    modal.classList.add('open');
    document.body.classList.add('lock');
    if(mmenu.classList.contains('open')) closeMenu();
    setTimeout(function(){ var n = document.getElementById('nome'); if(n) n.focus(); }, 320);
  }
  function closeModal(){
    modal.classList.remove('open');
    document.body.classList.remove('lock');
    if(lastFocus && lastFocus.focus) lastFocus.focus();
  }
  document.querySelectorAll('[data-modal-open]').forEach(function(b){ b.addEventListener('click', openModal); });
  document.querySelectorAll('[data-modal-close]').forEach(function(b){ b.addEventListener('click', closeModal); });
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){
      if(modal.classList.contains('open')) closeModal();
      else if(mmenu.classList.contains('open')) closeMenu();
    }
  });

  /* ============================================================
     MÁSCARAS
     ============================================================ */

  /* Celular: (00) 00000-0000 */
  var celularInput = document.getElementById('celular');
  if(celularInput){
    celularInput.addEventListener('input', function(){
      var d = this.value.replace(/\D/g,'').slice(0,11);
      var out = '';
      if(d.length > 0) out = '(' + d.slice(0,2);
      if(d.length >= 2) out += ') ';
      if(d.length > 2) out += d.slice(2, d.length > 10 ? 7 : 6);
      if(d.length > (d.length > 10 ? 7 : 6)) out += '-' + d.slice(d.length > 10 ? 7 : 6, 11);
      this.value = out;
    });
  }

  /* CPF: 000.000.000-00 */
  var cpfInput = document.getElementById('cpf');
  if(cpfInput){
    cpfInput.addEventListener('input', function(){
      var d = this.value.replace(/\D/g,'').slice(0,11);
      var out = d;
      if(d.length > 9)      out = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
      else if(d.length > 6) out = d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
      else if(d.length > 3) out = d.slice(0,3)+'.'+d.slice(3);
      this.value = out;
    });
  }

  /* CEP: 00000-000 */
  var cepInput = document.getElementById('cep');
  var cepTimeout = null;
  if(cepInput){
    cepInput.addEventListener('input', function(){
      var d = this.value.replace(/\D/g,'').slice(0,8);
      this.value = d.length > 5 ? d.slice(0,5)+'-'+d.slice(5) : d;
      clearTimeout(cepTimeout);
      if(d.length === 8){
        cepTimeout = setTimeout(function(){ fetchCep(d); }, 400);
      } else {
        setErr('f-cep', false);
      }
    });
  }

  function fetchCep(cep){
    fetch('https://viacep.com.br/ws/'+cep+'/json/')
      .then(function(r){ return r.json(); })
      .then(function(data){
        if(data.erro){
          setErr('f-cep', true);
          var msg = document.querySelector('#f-cep .msg');
          if(msg) msg.textContent = 'CEP não encontrado.';
        } else {
          setErr('f-cep', false);
        }
      })
      .catch(function(){
        /* Sem internet — não bloquear */
        setErr('f-cep', false);
      });
  }

  /* CNPJ: 00.000.000/0000-00 */
  var cnpjInput = document.getElementById('cnpj');
  if(cnpjInput){
    cnpjInput.addEventListener('input', function(){
      var d = this.value.replace(/\D/g,'').slice(0,14);
      var out = d;
      if(d.length > 12)      out = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
      else if(d.length > 8)  out = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
      else if(d.length > 5)  out = d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
      else if(d.length > 2)  out = d.slice(0,2)+'.'+d.slice(2);
      this.value = out;
    });
  }

  /* ============================================================
     VALIDAÇÕES
     ============================================================ */

  function setErr(id, on){ var el = document.getElementById(id); if(el) el.classList.toggle('err', on); }
  function emailOk(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }
  function phoneOk(v){ return v.replace(/\D/g,'').length >= 10; }
  function cepOk(v){ return v.replace(/\D/g,'').length === 8; }

  function cpfOk(v){
    var n = v.replace(/\D/g,'');
    if(n.length !== 11 || /^(\d)\1{10}$/.test(n)) return false;
    var s = 0;
    for(var i=0;i<9;i++) s += +n[i]*(10-i);
    var r = (s*10)%11; if(r===10||r===11) r=0;
    if(r !== +n[9]) return false;
    s = 0;
    for(var i=0;i<10;i++) s += +n[i]*(11-i);
    r = (s*10)%11; if(r===10||r===11) r=0;
    return r === +n[10];
  }

  function cnpjOk(v){
    var n = v.replace(/\D/g,'');
    if(n.length !== 14 || /^(\d)\1{13}$/.test(n)) return false;
    function calc(n, len){
      var weights = len === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2];
      var s = 0;
      for(var i=0;i<len;i++) s += +n[i]*weights[i];
      var r = s%11; return r < 2 ? 0 : 11-r;
    }
    return calc(n,12) === +n[12] && calc(n,13) === +n[13];
  }

  /* ============================================================
     UTM + CHECKOUT URL
     ============================================================ */
  var KIWIFY_URL = 'https://pay.kiwify.com.br/89muAYk';

  function getUtms(){
    var keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
    var p = new URLSearchParams(window.location.search);
    var out = {};
    keys.forEach(function(k){ var v = p.get(k); if(v) out[k] = v; });
    return out;
  }

  function buildCheckoutUrl(nome, email, tel, cpf){
    var params = new URLSearchParams();
    if(nome)  params.set('name',  nome);
    if(email) params.set('email', email);
    var phone = tel.replace(/\D/g,'');
    if(phone) params.set('phone', phone);
    var cpfClean = cpf.replace(/\D/g,'');
    if(cpfClean) params.set('cpf', cpfClean);
    var utms = getUtms();
    Object.keys(utms).forEach(function(k){ params.set(k, utms[k]); });
    var qs = params.toString();
    return KIWIFY_URL + (qs ? '?' + qs : '');
  }

  /* ============================================================
     SUBMIT
     ============================================================ */
  document.getElementById('regForm').addEventListener('submit', function(e){
    e.preventDefault();

    /* Honeypot */
    var hp = document.getElementById('website');
    if(hp && hp.value) return;

    var nome    = document.getElementById('nome').value.trim();
    var cpf     = cpfInput ? cpfInput.value.trim() : '';
    var celular = celularInput ? celularInput.value.trim() : '';
    var email   = document.getElementById('email').value.trim();
    var cep     = cepInput ? cepInput.value.trim() : '';
    var cnpj    = cnpjInput ? cnpjInput.value.trim() : '';
    var consent = document.getElementById('consent').checked;
    var ok = true;

    if(nome.length < 3)             { setErr('f-nome', true);   ok = false; } else setErr('f-nome', false);
    if(!cpfOk(cpf))                 { setErr('f-cpf', true);    ok = false; } else setErr('f-cpf', false);
    if(!phoneOk(celular))           { setErr('f-celular', true); ok = false; } else setErr('f-celular', false);
    if(!emailOk(email))             { setErr('f-email', true);  ok = false; } else setErr('f-email', false);
    if(cep && !cepOk(cep))         { setErr('f-cep', true);    ok = false; } else setErr('f-cep', false);
    if(cnpj && !cnpjOk(cnpj))      { setErr('f-cnpj', true);   ok = false; } else setErr('f-cnpj', false);

    document.getElementById('f-consent').classList.toggle('err', !consent);
    if(!consent) ok = false;
    if(!ok) return;

    var submitBtn = document.querySelector('#regForm [type="submit"]');
    if(submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Aguarde…'; }

    var utms = getUtms();
    var payload = {
      nome:     nome,
      cpf:      cpf.replace(/\D/g,''),
      telefone: celular,
      email:    email,
      cep:      cep.replace(/\D/g,''),
      cnpj:     cnpj.replace(/\D/g,''),
      website:  '',
      utm_source:   utms.utm_source   || '',
      utm_medium:   utms.utm_medium   || '',
      utm_campaign: utms.utm_campaign || '',
      utm_content:  utms.utm_content  || '',
      utm_term:     utms.utm_term     || ''
    };

    var checkoutUrl = buildCheckoutUrl(nome, email, celular, cpf);

    fetch('/api/submit-lead.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
    .catch(function(){ /* Falha silenciosa */ })
    .finally(function(){
      modalForm.style.display    = 'none';
      modalSuccess.style.display = '';
      setTimeout(function(){ window.location.href = checkoutUrl; }, 1400);
    });
  });

  /* ---------- Animations: deferred to idle so they don't block LCP/TTI ---------- */
  function setupAnimations(){
    var reducedMotion = window.matchMedia('(prefers-reduced-motion:reduce)').matches;
    var staggerSelectors = [
      {parent:'.pilares', children:'.pilar'},
      {parent:'.speakers', children:'.speaker'},
      {parent:'.dif-grid', children:'.dif'},
      {parent:'.viver-grid', children:'.viver-card'},
    ];
    staggerSelectors.forEach(function(sel){
      var parent = document.querySelector(sel.parent);
      if(!parent) return;
      var children = parent.querySelectorAll(sel.children);
      children.forEach(function(el, i){
        el.classList.remove('reveal');
        el.classList.add('stagger-child');
        if(!reducedMotion) el.style.transitionDelay = (i * 80) + 'ms';
      });
      var sio = new IntersectionObserver(function(entries){
        entries.forEach(function(en){
          if(en.isIntersecting){ en.target.classList.add('in'); sio.unobserve(en.target); }
        });
      }, {threshold:0.08, rootMargin:'0px 0px -20px 0px'});
      children.forEach(function(el){ sio.observe(el); });
    });

    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, {threshold:0.1, rootMargin:'0px 0px -32px 0px'});
    document.querySelectorAll('.reveal').forEach(function(el){ io.observe(el); });
  }

  if('requestIdleCallback' in window){
    requestIdleCallback(setupAnimations, {timeout:1500});
  } else {
    setTimeout(setupAnimations, 200);
  }

})();
