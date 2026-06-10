/* ============================================================
   CONGRESSIS — interactions v2
   ============================================================ */
(function(){
  "use strict";

  /* ---------- Sticky header ---------- */
  var hd = document.getElementById('hd');
  function onScroll(){
    hd.classList.toggle('scrolled', window.scrollY > 40);
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();

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
      document.querySelectorAll('.faq-item.open').forEach(function(o){
        o.classList.remove('open');
        o.querySelector('.faq-a').style.maxHeight = null;
      });
      if(!isOpen){
        item.classList.add('open');
        item.querySelector('.faq-a').style.maxHeight = item.querySelector('.faq-a').scrollHeight + 'px';
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

  /* ---------- WhatsApp phone mask ---------- */
  var whatsInput = document.getElementById('whats');
  whatsInput.addEventListener('input', function(){
    var d = whatsInput.value.replace(/\D/g,'').slice(0,11);
    var out = '';
    if(d.length > 0) out = '(' + d.slice(0,2);
    if(d.length >= 2) out += ') ';
    if(d.length > 2) out += d.slice(2, d.length > 10 ? 7 : 6);
    if(d.length > (d.length > 10 ? 7 : 6)) out += '-' + d.slice(d.length > 10 ? 7 : 6, 11);
    whatsInput.value = out;
  });

  /* ---------- Form validation & Kiwify redirect ---------- */
  var KIWIFY_URL = 'https://pay.kiwify.com.br/89muAYk';

  function setErr(id, on){ document.getElementById(id).classList.toggle('err', on); }
  function emailOk(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); }
  function phoneOk(v){ return v.replace(/\D/g,'').length >= 10; }

  /* Collect UTM params from the landing page URL */
  function getUtms(){
    var utmKeys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
    var pageParams = new URLSearchParams(window.location.search);
    var out = {};
    utmKeys.forEach(function(k){ var v = pageParams.get(k); if(v) out[k] = v; });
    return out;
  }

  /* Build Kiwify checkout URL with prefill + UTMs */
  function buildCheckoutUrl(nome, email, tel){
    var params = new URLSearchParams();
    if(nome)  params.set('name',  nome);
    if(email) params.set('email', email);
    var phone = tel.replace(/\D/g,'');
    if(phone) params.set('phone', phone);
    var utms = getUtms();
    Object.keys(utms).forEach(function(k){ params.set(k, utms[k]); });
    var qs = params.toString();
    return KIWIFY_URL + (qs ? '?' + qs : '');
  }

  document.getElementById('regForm').addEventListener('submit', function(e){
    e.preventDefault();
    var nome    = document.getElementById('nome').value.trim();
    var email   = document.getElementById('email').value.trim();
    var tel     = document.getElementById('whats').value.trim();
    var consent = document.getElementById('consent').checked;
    var ok = true;

    if(nome.length < 3){ setErr('f-nome', true); ok = false; } else setErr('f-nome', false);
    if(!emailOk(email)){ setErr('f-email', true); ok = false; } else setErr('f-email', false);
    if(!phoneOk(tel)){   setErr('f-whats', true); ok = false; } else setErr('f-whats', false);
    document.getElementById('f-consent').classList.toggle('err', !consent);
    if(!consent) ok = false;
    if(!ok) return;

    /* Show success state, then redirect */
    modalForm.style.display    = 'none';
    modalSuccess.style.display = '';
    var url = buildCheckoutUrl(nome, email, tel);
    setTimeout(function(){ window.location.href = url; }, 1600);
  });

  /* ---------- Stagger animate grid children (must run before reveal setup) ---------- */
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
      el.classList.remove('reveal'); // remove before reveal observer runs
      el.classList.add('stagger-child');
      if(!reducedMotion) el.style.transitionDelay = (i * 80) + 'ms';
    });
    var sio = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){
          en.target.classList.add('in');
          sio.unobserve(en.target);
        }
      });
    }, {threshold:0.08, rootMargin:'0px 0px -20px 0px'});
    children.forEach(function(el){ sio.observe(el); });
  });

  /* ---------- Reveal on scroll ---------- */
  var io = new IntersectionObserver(function(entries){
    entries.forEach(function(en){
      if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); }
    });
  }, {threshold:0.1, rootMargin:'0px 0px -32px 0px'});
  document.querySelectorAll('.reveal').forEach(function(el){ io.observe(el); });

})();
