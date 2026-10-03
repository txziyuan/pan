<style>
/* 页脚固定：始终固定在视口底部，不随内容滚动（布局型外观有自己的布局结构，不套用） */
html{min-height:100%}
body:not(.layout-theme):not(.studio-layout){min-height:100vh;padding-bottom:76px}
body:not(.layout-theme):not(.studio-layout)>footer.footer{position:fixed;left:0;right:0;bottom:0;z-index:1000;margin:0;background:rgba(255,255,255,.94);border-top:1px solid rgba(0,0,0,.06);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);padding-bottom:calc(28px + env(safe-area-inset-bottom))}
</style>
<footer class="footer text-center">
      <div class="container">
        <p class="text-muted">Copyright &copy; <?php echo date('Y')?> <a href="/"><?php echo $conf['title']?></a> <?php echo $conf['tongji']?> </p>
      </div>
    </footer>
<script>
//把当前外观存一份到浏览器，静态的 404.html 读不到后台配置，靠这个跟随外观
try{localStorage.setItem('site_theme','<?php echo isset($site_theme)?$site_theme:default_site_theme()?>');}catch(e){}
</script>
<?php //蓝白工作台风：顶栏的 Ctrl K 快捷键每个页面都要能用，排序和视图切换只有列表页有元素，脚本里各自判断
if(isset($site_theme) && in_array($site_theme, studio_family_keys(), true)){?>
<script src="assets/js/layout-studio.js?v=<?php echo VERSION?>"></script>
<?php }?>
<script src="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/material.min.js"></script>
<script src="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/js/ripples.min.js"></script>
<script>
  $.material.init();
</script>
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-6112564004010114"crossorigin="anonymous"></script>