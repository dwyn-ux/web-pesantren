    </div><!-- /.admin-content -->
</div><!-- /.admin-main -->

<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= ASSET_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js?v=<?= ASSET_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/article-editor.js?v=<?= ASSET_VERSION ?>"></script>
<?php if (!empty($adminExtraScript)) echo $adminExtraScript; ?>
<script>
(function(){
  var sidebar=document.getElementById('adminSidebar'),toggle=document.getElementById('sidebarToggle'),backdrop=document.getElementById('adminSidebarBackdrop');
  if(!sidebar||!toggle||!backdrop)return;
  function setOpen(open){sidebar.classList.toggle('open',open);document.body.classList.toggle('sidebar-open',open);toggle.setAttribute('aria-expanded',open?'true':'false');}
  toggle.addEventListener('click',function(){setOpen(!sidebar.classList.contains('open'));});
  backdrop.addEventListener('click',function(){setOpen(false);});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')setOpen(false);});
  sidebar.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){if(window.innerWidth<=768)setOpen(false);});});
})();
</script>
</body>
</html>
