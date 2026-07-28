(function(){
  var modal=document.getElementById('pdfModal'), frame=document.getElementById('pdfFrame'), title=document.getElementById('pdfTitle');
  if(!modal)return;
  function close(){modal.classList.remove('open');modal.setAttribute('aria-hidden','true');frame.src='about:blank';document.body.style.overflow='';}
  document.querySelectorAll('.js-pdf-open').forEach(function(a){a.addEventListener('click',function(e){e.preventDefault();title.textContent=a.dataset.title||'Dokumen';frame.src=a.href+'#toolbar=0&navpanes=0&scrollbar=1';modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';});});
  document.getElementById('pdfClose').addEventListener('click',close); modal.addEventListener('click',function(e){if(e.target===modal)close();}); document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
})();
