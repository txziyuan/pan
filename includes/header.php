<?php
@header('Content-Type: text/html; charset=UTF-8');
//当前外观：<head> 里要用它输出自定义渐变，<body> 上要用它挂主题类名，算一次两处共用
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
//enterprise 是本项目新增的企业门户风外观，site_theme_keys()（上游 functions.php）尚未收录，
//这里单独放行，保证数据库里选了 enterprise 后前台不会悄悄回退成默认外观
if(!in_array($site_theme, site_theme_keys(), true) && $site_theme !== 'enterprise'){
  $site_theme = default_site_theme();
}
?><!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="renderer" content="webkit">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?php echo $title?></title>
  <meta name="keywords" content="<?php echo $conf['keywords']?>">
  <meta name="description" content="<?php echo $conf['description']?>">
  <meta name="viewport" content="width=device-width,height=device-height,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="format-detection" content="telephone=no">
  <meta name="google-adsense-account" content="ca-pub-6112564004010114">
  <link href="https://s4.zstatic.net/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/bootstrap-material-design.min.css" rel="stylesheet">
  <link href="https://s4.zstatic.net/ajax/libs/bootstrap-material-design/0.5.10/css/ripples.min.css" rel="stylesheet">
  <?php if($is_file){?><link rel="stylesheet" href="https://s4.zstatic.net/ajax/libs/aplayer/1.10.1/APlayer.min.css"><link href="assets/css/ckplayer.css" rel="stylesheet"><?php }?>
  <link href="assets/css/style.css?v=<?php echo asset_ver('assets/css/style.css')?>" rel="stylesheet">
  <?php //企业门户风：独立样式表在 style.css 之后加载，用它覆盖全部组件，其余外观不引入 ?>
  <?php if($site_theme === 'enterprise'){?><link href="assets/css/enterprise.css?v=<?php echo asset_ver('assets/css/enterprise.css')?>" rel="stylesheet"><?php }?>
  <?php //外观设置里给当前外观单独配过颜色才有输出，没配就都是空的：
  //先是整套换色（把这套外观用到的所有颜色全部按新主色重算），再是渐变角度等细节覆盖
  echo theme_recolor_tag($site_theme);
  echo theme_gradient_style($site_theme);?>
  <!--[if lt IE 9]>
    <script src="https://s4.zstatic.net/ajax/libs/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://s4.zstatic.net/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
  <script type="text/javascript" src="https://s4.zstatic.net/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
</head>
<?php
//布局型外观（侧栏/门户/工作台/macOS 窗口/渐变仪表盘）共用一套结构样式，统一挂 layout-theme
$layout_themes = layout_theme_keys();
$body_class = 'theme-' . $site_theme;
//「工作台家族」的五套外观共用同一套结构样式（侧栏 + 顶栏 + 横幅 + 右侧数据列），
//区别只在配色和横幅表现，所以再挂一个公共类，结构 CSS 只写一份
if(in_array($site_theme, studio_family_keys(), true))$body_class .= ' studio-layout';
if(in_array($site_theme, $layout_themes, true)){
  $body_class .= ' layout-theme';
  include_once SYSTEM_ROOT.'layout_blocks.php';
}
?>
<body class="<?php echo $body_class?>">

<?php if($site_theme === 'enterprise'){ ?>
<?php // ==================== 企业门户风：顶部导航 ====================
//导航菜单与默认 navbar 同一套业务逻辑：入口显隐、高亮、登录态完全一致，
//只是视觉换成深蓝渐变企业级顶栏（enterprise.css 提供样式）
$ep_menu = [];
$ep_menu[] = ['index,', './', 'fa-list', '文件列表'];
$ep_menu[] = ['upload', './upload.php', 'fa-upload', '上传文件'];
if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1){
  //enterprise 非布局型外观，与默认 navbar 的非 layout 分支一致走独立赞助页目录；
  //该 URL 无文件名，checkIfActive 取不到高亮，与原版一致不挂 active
  $ep_menu[] = ['', './includes/sponsor/', 'fa-money', '赞助名单'];
}
if(function_exists('is_buy_open') && is_buy_open()){
  $ep_menu[] = ['buy', './buy.php', 'fa-shopping-cart', '购买权限'];
}
if(!isset($conf['violation_open']) || $conf['violation_open'] == 1){
  $ep_menu[] = ['violation', './violation.php', 'fa-gavel', '违规公示'];
}
if(!empty($is_file)){
  $ep_menu[] = ['file', '', 'fa-file', '文件查看'];
}
?>
<div class="ep-nav">
  <div class="container">
    <a class="ep-nav-brand" href="./"><i class="fa fa-cloud" aria-hidden="true"></i> <?php echo $conf['title']?></a>
    <button type="button" class="ep-nav-toggle" id="epNavToggle" aria-label="展开菜单"><i class="fa fa-bars" aria-hidden="true"></i></button>
    <nav class="ep-nav-menu" id="epNavMenu">
      <?php foreach($ep_menu as $m){?>
      <a href="<?php echo $m[1]?>" class="<?php echo checkIfActive($m[0])?>"><i class="fa <?php echo $m[2]?>" aria-hidden="true"></i> <?php echo $m[3]?></a>
      <?php }?>
      <span class="ep-nav-user" id="epNavUser">
        <?php if($islogin2){?>
        <a class="ep-user-btn" href="./user.php?tab=files"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a>
        <span class="ep-dropdown">
          <a class="ep-user-btn" href="./user.php" data-ep-drop="1"><i class="fa fa-<?php echo $userrow['type']=='qq'?'qq':($userrow['type']=='mail'?'envelope':'wechat');?>" aria-hidden="true"></i> <?php echo $userrow['nickname']?><i class="fa fa-angle-down" aria-hidden="true"></i></a>
          <ul class="ep-dropdown-menu">
            <li><a href="./user.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 个人中心</a></li>
            <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out" aria-hidden="true"></i> 退出登录</a></li>
          </ul>
        </span>
        <?php }else{?>
        <a class="ep-user-btn" href="./?m=mine"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a>
        <?php if($conf['userlogin']){?>
        <a class="ep-login-btn" href="./login.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 登录 / 注册</a>
        <?php }?>
        <?php }?>
      </span>
    </nav>
  </div>
</div>
<?php // ==================== 企业门户风：首页 Hero ====================
//只在文件列表首页显示（搜索/我的文件等场景保持纯列表，避免把结果顶出视口），
//统计走一次全表 count + sum，与默认首页的 numrows 查询同级开销
//$kw 只在 index.php 定义，其它页面 include header.php 时用 $_GET['kw'] 判断，避免未定义变量
$ep_is_home = (basename($_SERVER['SCRIPT_NAME']) === 'index.php' || basename($_SERVER['SCRIPT_NAME']) === 'index')
  && empty($_GET['kw']) && (!isset($_GET['m']) || $_GET['m'] !== 'mine');
if($ep_is_home){
  //全表统计走 120 秒会话缓存，避免每次打开首页都扫三遍 pre_file
  $ep_stat_key = 'ep_home_stat_'.date('YmdH');
  $ep_stat = isset($_SESSION[$ep_stat_key]) ? $_SESSION[$ep_stat_key] : null;
  if(!is_array($ep_stat) || empty($ep_stat['t']) || $ep_stat['t'] < time() - 120){
    $ep_stat = [
      't' => time(),
      'files' => intval($DB->getColumn("SELECT count(*) from pre_file")),
      'today' => intval($DB->getColumn("SELECT count(*) from pre_file WHERE addtime>='".date('Y-m-d 00:00:00')."'")),
      'size' => floatval($DB->getColumn("SELECT sum(size) from pre_file")),
    ];
    $_SESSION[$ep_stat_key] = $ep_stat;
  }
?>
<div class="ep-hero">
  <div class="container">
    <span class="ep-hero-kicker"><i class="fa fa-shield" aria-hidden="true"></i> 系统运行中</span>
    <h1>安全 · 稳定 · 高效</h1>
    <p>企业级文件外链分享平台：上传即得链接，随时随地分发下载。数据加密存储，7×24 小时稳定运行。</p>
    <div class="ep-hero-actions">
      <a class="ep-btn ep-btn-light" href="./upload.php"><i class="fa fa-cloud-upload" aria-hidden="true"></i> 立即上传</a>
      <a class="ep-btn ep-btn-ghost" href="./?m=mine"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a>
    </div>
    <div class="ep-hero-stats">
      <div class="ep-hero-stat"><i class="fa fa-database" aria-hidden="true"></i><div><strong><?php echo number_format($ep_stat['files'])?></strong><span>文件总数</span></div></div>
      <div class="ep-hero-stat"><i class="fa fa-cloud-upload" aria-hidden="true"></i><div><strong><?php echo number_format($ep_stat['today'])?></strong><span>今日上传</span></div></div>
      <div class="ep-hero-stat"><i class="fa fa-hdd-o" aria-hidden="true"></i><div><strong><?php echo function_exists('size_format') ? size_format($ep_stat['size']) : '-'?></strong><span>存储用量</span></div></div>
    </div>
  </div>
</div>
<?php } ?>
<script>
//企业导航移动端展开/收起
(function(){
  var b = document.getElementById('epNavToggle'), m = document.getElementById('epNavMenu'), u = document.getElementById('epNavUser');
  if(!b || !m) return;
  b.addEventListener('click', function(){
    m.classList.toggle('open'); if(u)u.classList.toggle('open');
  });
})();
</script>
<?php }else{ ?>

  <div class="navbar navbar-default">
    <div class="container">
      <div class="navbar-header">
        <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
          <span class="icon-bar"></span>
        </button>
        <a class="navbar-brand" href="./"><?php echo $conf['title']?></a>
      </div>
      <div class="navbar-collapse collapse navbar-responsive-collapse">
        <ul class="nav navbar-nav">
          <li class="<?php echo checkIfActive('index,')?>"><a href="./"><i class="fa fa-list" aria-hidden="true"></i> 文件列表</a></li>
          <li class="<?php echo checkIfActive('upload')?>"><a href="./upload.php"><i class="fa fa-upload" aria-hidden="true"></i> 上传文件</a></li>
          <?php //赞助名单可以在「网站信息设置」里整个关掉，关了两种外观的入口都不出现
          if(!isset($conf['sponsor_open']) || $conf['sponsor_open'] == 1){
            //布局型外观用站内的赞助页，保持自己的导航布局；其它主题仍跳转原来的独立赞助页
            if(in_array($site_theme, $layout_themes, true)){?>
          <li class="<?php echo checkIfActive('sponsor')?>"><a href="./sponsor.php"><i class="fa fa-money" aria-hidden="true"></i> 赞助名单</a></li>
          <?php }else{?>
          <li><a href="./includes/sponsor/"><i class="fa fa-money" aria-hidden="true"></i> 赞助名单</a></li>
          <?php }
          }?>
          <?php //开启购买功能且配置完整时才显示入口
          if(function_exists('is_buy_open') && is_buy_open()){?>
          <li class="<?php echo checkIfActive('buy')?>"><a href="./buy.php"><i class="fa fa-shopping-cart" aria-hidden="true"></i> 购买权限</a></li>
          <?php }?>
          <?php if(!isset($conf['violation_open']) || $conf['violation_open'] == 1){?>
          <li class="<?php echo checkIfActive('violation')?>"><a href="./violation.php"><i class="fa fa-gavel" aria-hidden="true"></i> 违规公示</a></li>
          <?php }?>
          <?php if($is_file){?>
          <li class="<?php echo checkIfActive('file')?>"><a href=""><i class="fa fa-file" aria-hidden="true"></i> 文件查看</a></li>
          <?php }?>
        </ul>
        <ul class="nav navbar-nav navbar-right">
          <?php //登录用户的「我的文件」直接进个人中心的文件页，那里才有重命名/删除等管理操作；
          //游客没有账号，只能看 $_SESSION['fileids'] 那套浏览器缓存记录，仍然走首页的 ?m=mine
          if($islogin2){?>
          <li class="<?php echo checkIfActive('user')?>"><a href="./user.php?tab=files"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a></li>
          <?php }else{?>
          <li class="<?php echo checkIfActive('mine')?>"><a href="./?m=mine"><i class="fa fa-folder-open" aria-hidden="true"></i> 我的文件</a></li>
          <?php }?>
          <?php if($conf['userlogin']){?>
            <?php if($islogin2){?>
            <li class="dropdown">
              <a data-target="#" class="dropdown-toggle" data-toggle="dropdown"><i class="fa fa-<?php echo $userrow['type']=='qq'?'qq':($userrow['type']=='mail'?'envelope':'wechat');?>" aria-hidden="true"></i> <?php echo $userrow['nickname']?><b class="caret"></b></a>
              <ul class="dropdown-menu">
                <li><a href="./user.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 个人中心</a></li>
                <li><a href="./login.php?logout=1" onclick="return confirm('是否确定退出登录？')"><i class="fa fa-sign-out" aria-hidden="true"></i> 退出登录</a></li>
              </ul>
            </li>
            <?php }else{?>
            <li class="<?php echo checkIfActive('login')?>"><a href="./login.php"><i class="fa fa-user-circle" aria-hidden="true"></i> 未登录</a></li>
            <?php }?>
          <?php }?>
        </ul>
        <?php
        //侧栏型外观（数据控制台风/深色工作台风）在侧栏底部补一张今日上传统计卡，对应原型里的存储条
        //这三套是固定侧栏布局，底部放得下卡片；上传门户风和配色型外观是顶部导航，
        //改在文件列表页和上传页顶部显示一条权限条（见 render_permission_bar）
        //蓝白工作台风的侧栏底部放的是升级卡，不是统计卡
        if(in_array($site_theme, studio_family_keys(), true)){
          echo layout_render_studio_upsell();
        }
        if($site_theme === 'console' || $site_theme === 'workspace' || $site_theme === 'dashboard'){
          $side_limit = function_exists('get_effective_upload_count_limit') ? get_effective_upload_count_limit() : 0;
          //统计走会话缓存，pre_file 上没有 ip/addtime 索引，不能每次打开页面都扫一遍
          $side_today = function_exists('layout_today_upload_count') ? layout_today_upload_count($DB) : 0;
          $side_percent = $side_limit > 0 ? min(100, round($side_today / $side_limit * 100)) : min(100, $side_today * 10);
        ?>
        <div class="layout-side-card">
          <div class="layout-side-row"><strong>今日上传</strong><span><?php echo $side_limit > 0 ? $side_today.' / '.$side_limit : $side_today.' 个'?></span></div>
          <div class="layout-side-bar"><i style="width:<?php echo intval($side_percent)?>%"></i></div>
          <small><?php echo $side_limit > 0 ? ('今日还可上传 '.max(0, $side_limit - $side_today).' 个文件') : '当前账号不限每日上传数量'?></small>
        </div>
        <?php
        //登录用户再补一张权限卡：当前额度、到期时间、买过的套餐，方便随时看还剩多久
        if(!empty($islogin2)){
          $side_size = function_exists('get_effective_upload_size_limit') ? get_effective_upload_size_limit() : 0;
          $side_expire = isset($userrow['expiretime']) ? $userrow['expiretime'] : '';
          $side_plan = function_exists('layout_user_plan') ? layout_user_plan($DB) : null;
          if(empty($side_expire)){
            $side_state = '永久有效'; $side_state_cls = 'ok';
          }elseif(function_exists('is_user_permission_active') && !is_user_permission_active()){
            $side_state = '已过期'; $side_state_cls = 'expired';
          }else{
            $side_left = max(1, ceil((strtotime($side_expire) - time()) / 86400));
            $side_state = '剩 '.$side_left.' 天'; $side_state_cls = $side_left <= 7 ? 'warn' : 'ok';
          }
        ?>
        <div class="layout-side-card">
          <div class="layout-side-row"><strong>我的权限</strong><span class="layout-side-tag layout-side-tag-<?php echo $side_state_cls?>"><?php echo $side_state?></span></div>
          <div class="layout-side-kv"><span>每日上传</span><b><?php echo $side_limit > 0 ? $side_limit.' 个' : '不限制'?></b></div>
          <div class="layout-side-kv"><span>单文件</span><b><?php echo $side_size > 0 ? $side_size.' MB' : '不限制'?></b></div>
          <?php if(!empty($userrow['bonus_limit']) && $side_limit > 0){?>
          <div class="layout-side-kv"><span>其中加量包</span><b>+<?php echo intval($userrow['bonus_limit'])?> 个/天</b></div>
          <?php }?>
          <?php if(!empty($side_expire)){?>
          <div class="layout-side-kv"><span>到期时间</span><b><?php echo htmlspecialchars(date('Y-m-d', strtotime($side_expire)))?></b></div>
          <?php }?>
          <?php if($side_plan && $side_plan['bought']){?>
          <div class="layout-side-kv"><span>已购套餐</span><b title="<?php echo htmlspecialchars($side_plan['plan_name'], ENT_QUOTES, 'UTF-8')?>"><?php echo htmlspecialchars($side_plan['plan_name'], ENT_QUOTES, 'UTF-8')?></b></div>
          <?php }?>
          <?php if(function_exists('is_buy_open') && is_buy_open()){?>
          <a class="layout-side-buy" href="./buy.php"><?php echo ($side_plan && $side_plan['bought']) ? '续费 / 升级权限' : '购买权限'?> <i class="fa fa-angle-right" aria-hidden="true"></i></a>
          <?php }?>
        </div>
        <?php }?>
        <?php }?>
      </div>
    </div>
  </div>
<?php //蓝白工作台风的顶部搜索条：每个页面都有，所以放在这里而不是各页面自己输出
if(in_array($site_theme, studio_family_keys(), true)){echo layout_render_studio_topbar();}?>
<?php }?>

  <script src="includes/ads.php?v=<?php echo VERSION?>"></script>
