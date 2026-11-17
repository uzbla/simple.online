document.addEventListener('click',function(e){
  var t=e.target.closest('[data-confirm]'); if(t&&!confirm(t.getAttribute('data-confirm'))) e.preventDefault();
  if(e.target.closest('.menu-btn')) document.body.classList.toggle('nav-open');
});
document.addEventListener('change',function(e){ if(e.target.matches('[data-autosubmit]')) e.target.form.submit(); });
