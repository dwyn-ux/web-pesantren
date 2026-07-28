(function(){
  'use strict';
  document.querySelectorAll('[data-article-editor]').forEach(function(wrapper){
    var source=wrapper.parentElement.querySelector('textarea[name="isi"]'),surface=wrapper.querySelector('.editor-surface');
    if(!source||!surface)return;surface.innerHTML=source.value||'<p><br></p>';
    function sync(){source.value=surface.innerHTML;} function focus(){surface.focus();}
    wrapper.querySelectorAll('[data-command]').forEach(function(button){
      button.addEventListener('mousedown',function(e){e.preventDefault();});
      button.addEventListener('click',function(){var command=button.dataset.command;focus();if(command==='createLink'){var url=window.prompt('Masukkan alamat tautan (https://...)');if(!url)return;if(!/^https?:\/\//i.test(url))url='https://'+url;document.execCommand(command,false,url);}else document.execCommand(command,false,null);sync();});
    });
    wrapper.querySelector('.editor-format').addEventListener('change',function(){focus();document.execCommand('formatBlock',false,this.value);sync();});
    surface.addEventListener('input',sync);surface.addEventListener('blur',sync);source.addEventListener('input',function(){if(surface.innerHTML!==source.value)surface.innerHTML=source.value||'<p><br></p>';});var form=source.closest('form');if(form)form.addEventListener('submit',sync);
  });
})();
