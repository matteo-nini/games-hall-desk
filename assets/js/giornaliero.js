var ICO_OK='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>';
var ICO_WARN='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>';
var ACTIVE=parseInt(localStorage.getItem('gp_tab'))||2, RES={};
function eur(v){v=Math.round(v*100)/100; if(Object.is(v,-0))v=0; return v.toLocaleString('it-IT',{minimumFractionDigits:2,maximumFractionDigits:2});}
function num(el){var v=parseFloat((el.value||'').toString().replace(',','.'));return isNaN(v)?0:v;}
function recalcTurno(sec){
  var cont=0;
  sec.querySelectorAll('.pezzi').forEach(function(p){var t=(+p.dataset.taglio)*(parseInt(p.value)||0);cont+=t;var rr=p.closest('.field').querySelector('.rr');if(rr)rr.textContent=eur(t);});
  var scass=0,forn={};
  sec.querySelectorAll('.scass').forEach(function(s){var v=num(s);scass+=v;forn[s.dataset.forn]=(forn[s.dataset.forn]||0)+v;});
  sec.querySelectorAll('.st').forEach(function(st){st.textContent=eur(forn[st.dataset.forn]||0);});
  var ticket=0;sec.querySelectorAll('.ticket').forEach(function(t){ticket+=num(t);});
  var refill=parseFloat(sec.dataset.refill)||0;
  function f(k){var e=sec.querySelector('.f-'+k);return e?num(e):0;}
  var cassetto=cont+refill+f('differenze')-f('ii_cassa')-f('rientri');
  var versvlt=scass-f('bancomat')-ticket;
  var incassato=cassetto+f('monete')+f('bancomat')+ticket;
  var totale=cassetto+f('monete')-versvlt;
  var fondo=f('fondo_cassa');
  var scost=totale-fondo;
  function set(c,v){var e=sec.querySelector(c);if(e)e.textContent=eur(v);}
  set('.o-cont',cont);set('.o-scass',scass);set('.o-ticket',ticket);
  var vers_cassa=cassetto+f('monete')-fondo;
  return {bancomat:f('bancomat'),versamento:versvlt,vers_cassa:vers_cassa,ticket:ticket,incasso:scass,forn:forn,
          cassetto:cassetto,incassato:incassato,totale:totale,fondo:fondo,scost:scost,
          cont:cont,tol:(parseFloat(sec.dataset.tol)||0)};
}
function updateActive(){
  var r=RES[ACTIVE]; if(!r)return;
  var abs=Math.abs(r.scost), sera=(ACTIVE===2);
  var isGood=abs<4, isMid=abs>=4&&abs<=5, isBad=abs>5;
  var vd=document.getElementById('hm-scost');
  vd.classList.toggle('ok',isGood); vd.classList.toggle('warn',isMid); vd.classList.toggle('bad',isBad);
  document.getElementById('v-ico').innerHTML=isGood?ICO_OK:ICO_WARN;
  document.getElementById('v-big').textContent=sera?(isGood?'I conti tornano':'Scostamento da verificare'):(isGood?'Controllo: torna':'Controllo: scostamento');
  document.getElementById('m-scost').textContent=(r.scost>=0?'+':'')+eur(r.scost);
  document.getElementById('v-tot').textContent='€ '+eur(r.totale);
  document.getElementById('v-fondo').textContent='€ '+eur(r.fondo);
  document.getElementById('v-sign').textContent=isGood?'=':'≠';
  var el;
  el=document.getElementById('m-cassetto'); if(el) el.textContent=eur(r.cassetto);
  el=document.getElementById('m-vers-reale'); if(el) el.textContent='€ '+eur(r.cont-r.fondo);
  el=document.getElementById('m-versamento'); if(el) el.textContent='€ '+eur(r.vers_cassa);
}
function recalcAll(){
  var g={bancomat:0,versamento:0,ticket:0,incasso:0,NOVO:0,INSPIRED:0,SPIELO:0};
  document.querySelectorAll('.turno').forEach(function(sec){
    var n=+sec.dataset.turno, r=recalcTurno(sec); RES[n]=r;
    g.bancomat+=r.bancomat;g.versamento+=r.versamento;g.ticket+=r.ticket;g.incasso+=r.incasso;
    for(var k in r.forn){if(g[k]!==undefined)g[k]+=r.forn[k];}
  });
  for(var k in g){var e=document.getElementById('g-'+k);if(e)e.textContent=eur(g[k]);}
  updateActive();
}
function showTab(n){
  ACTIVE=n;
  localStorage.setItem('gp_tab',n);
  var sf=document.getElementById('salva_turno');if(sf)sf.value=n;
  document.querySelectorAll('.turno').forEach(function(s){var a=+s.dataset.turno===n;s.dataset.hidden=a?'0':'1';s.setAttribute('aria-hidden',a?'false':'true');});
  document.querySelectorAll('.tab[role="tab"]').forEach(function(t){var a=+t.dataset.tab===n;t.classList.toggle('active',a);t.setAttribute('aria-selected',a?'true':'false');});
  updateActive();
}
document.addEventListener('input',function(e){if(e.target.closest('#frm'))recalcAll();});
document.getElementById('frm')?.addEventListener('submit',function(){
  var btn=document.querySelector('.save-btn');
  if(btn){btn.disabled=true;btn.textContent='Salvataggio…';}
});
recalcAll();
showTab(ACTIVE);
var tt=document.getElementById('toast');
if(tt){setTimeout(function(){tt.classList.add('hide');},2600);}
