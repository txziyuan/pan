<?php
/**
 * 登录
**/
//滑块拼图验证：服务端不支持 GD 时自动跳过（与旧图形验证码的降级逻辑一致，由IP限速兜底）
$puzzle_enabled = function_exists('imagecreate');
define('IN_ADMIN', true);
include("../includes/common.php");
//登录失败按IP锁定：5次失败锁15分钟。计数记在服务端，清Cookie也绕不过。
//这里刻意用 REMOTE_ADDR 而不是 $clientip：$clientip 默认信任 X-Forwarded-For，
//可以被请求头随意伪造，拿它做限速等于没做。
$login_max_fail = 5;
$login_lock_time = 900;
$login_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
//登录提示直接渲染在登录页上：成功/失败都就地显示，不再用 alert 弹窗二次确认
$login_msg = '';
$login_msg_type = '';
$login_auto_jump = 0;

if(isset($_POST['user']) && isset($_POST['pass'])){
	if(!isset($_SESSION['pass_error']))$_SESSION['pass_error']=0;
	//密码是原样存进 pre_config 的（saveSetting 走绑定参数），这里不能再 addslashes，
	//否则密码里带引号或反斜杠时永远对不上
	$user=(string)$_POST['user'];
	$pass=(string)$_POST['pass'];
	$locked = login_throttle_locked($login_ip, $login_max_fail, $login_lock_time);
	if ($locked > 0) {
		$login_msg = '登录失败次数过多，请在'.ceil($locked/60).'分钟后重试！';
		$login_msg_type = 'error';
	}elseif($puzzle_enabled && (!isset($_SESSION['puzzle_passed']) || $_SESSION['puzzle_passed'] !== true)) {
		//滑块拼图未通过：就地提示，不计入IP失败次数（拼图自身带防爆破）
		$login_msg = '请先完成滑动拼图验证！';
		$login_msg_type = 'error';
	}elseif($_SESSION['pass_error']>$login_max_fail) {
		$login_msg = '用户名或密码不正确！';
		$login_msg_type = 'error';
	}elseif(hash_equals((string)$conf['admin_user'], $user) && hash_equals((string)$conf['admin_pwd'], $pass)) {
		//必须用 hash_equals 做二进制比较：== 会把两个纯数字串按数值比，'0123456' == '123456' 为真
		login_throttle_reset($login_ip);
		$_SESSION['pass_error']=0;
		//滑块验证一次性：登录成功即作废，下次登录需重新拖拼图
		unset($_SESSION['puzzle_passed']);
		//登录成功换一个会话ID，避免会话固定攻击
		session_regenerate_id(true);
		$session=md5($user.$pass.$password_hash);
		$expiretime=time()+2592000;
		$token=authcode("{$user}\t{$session}\t{$expiretime}", 'ENCODE', SYS_KEY);
		ob_clean();
		//$sitepath 就是浏览器原本给这个 cookie 算出来的默认 path（/admin 或 /子目录/admin），
		//显式传进去既不改变作用域，又能带上 HttpOnly/Secure/SameSite
		set_auth_cookie("admin_token", $token, time() + 2592000, $sitepath);
		$login_msg = '登录管理中心成功！';
		$login_msg_type = 'success';
		$login_auto_jump = 1;
	}else {
		login_throttle_fail($login_ip, $login_lock_time);
		$_SESSION['pass_error']++;
		$login_msg = '用户名或密码不正确！';
		$login_msg_type = 'error';
	}
}elseif(isset($_GET['logout'])){
	set_auth_cookie("admin_token", "", time() - 2592000, $sitepath);
	$login_msg = '您已成功注销本次登录！';
	$login_msg_type = 'success';
}elseif($islogin==1){
	@header('Location: ./');
	exit;
}
$site_theme = isset($conf['site_theme']) ? $conf['site_theme'] : default_site_theme();
if(!in_array($site_theme, site_theme_keys(), true)){
	$site_theme = default_site_theme();
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8') ?></title>
<meta name="renderer" content="webkit">
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0">
<style>
:root {
    --c-brand: #1677ff; --c-brand-hover: #4096ff; --c-brand-soft: #e6f4ff;
    --c-text: #1f1f1f; --c-text-2: #666; --c-text-3: #999;
    --c-border: #d9d9d9; --c-border-light: #f0f0f0;
    --radius: 12px;
    --shadow: 0 12px 40px rgba(22, 119, 255, .12), 0 4px 16px rgba(0, 0, 0, .06);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    min-height: 100vh;
    background: #eef3ff;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
    color: var(--c-text); -webkit-font-smoothing: antialiased; overflow-x: hidden;
}

.login-bg {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
    background:
        radial-gradient(circle at 15% 20%, rgba(22,119,255,.18) 0%, transparent 42%),
        radial-gradient(circle at 85% 75%, rgba(105,177,255,.16) 0%, transparent 40%),
        linear-gradient(160deg, #eef4ff 0%, #f0f2f5 50%, #fafcff 100%);
}
.login-bg::before,
.login-bg::after {
    content: ''; position: absolute; border-radius: 50%; filter: blur(60px); opacity: .55;
}
.login-bg::before {
    width: 420px; height: 420px; top: -120px; right: -80px;
    background: rgba(22,119,255,.22); animation: floatA 12s ease-in-out infinite;
}
.login-bg::after {
    width: 360px; height: 360px; bottom: -100px; left: -60px;
    background: rgba(114,46,209,.12); animation: floatB 14s ease-in-out infinite;
}
@keyframes floatA { 0%,100%{ transform: translate(0,0); } 50%{ transform: translate(-24px, 18px); } }
@keyframes floatB { 0%,100%{ transform: translate(0,0); } 50%{ transform: translate(20px, -16px); } }

.login-page {
    position: relative; z-index: 1;
    min-height: 100vh; display: flex; flex-direction: column;
    align-items: center; padding: 28px 16px;
}
.login-shell {
    width: 100%; max-width: 960px; margin: auto 0;
    display: grid; grid-template-columns: 1.08fr 1fr;
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    border-radius: 18px; overflow: visible;
    border: 1px solid rgba(255,255,255,.85);
    box-shadow: var(--shadow);
    animation: cardIn .55s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardIn {
    from { opacity: 0; transform: translateY(18px) scale(.985); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@media (max-width: 860px) {
    .login-shell { grid-template-columns: 1fr; max-width: 460px; }
    .login-banner { display: none; }
}

.login-banner {
    position: relative; min-height: 480px; overflow: hidden; border-radius: 18px 0 0 18px;
    background:
        radial-gradient(circle at 80% 16%, rgba(255,255,255,.13) 0%, transparent 34%),
        radial-gradient(circle at 12% 88%, rgba(0,20,60,.30) 0%, transparent 42%),
        linear-gradient(155deg, #003eb3 0%, #1677ff 48%, #4096ff 100%);
}
.login-banner__overlay {
    position: absolute; inset: 0;
    background: linear-gradient(180deg, rgba(0,30,80,.08) 0%, rgba(0,30,80,.42) 100%);
}
.login-banner__decor {
    position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,.16);
}
.login-banner__decor--1 { width: 220px; height: 220px; top: 12%; right: -40px; }
.login-banner__decor--2 { width: 140px; height: 140px; top: 42%; left: -30px; background: rgba(255,255,255,.06); }
.login-banner__mask {
    position: relative; z-index: 1; height: 100%; padding: 42px 36px;
    display: flex; flex-direction: column; justify-content: space-between; color: #fff;
}
.login-banner__badge {
    display: inline-flex; align-items: center; gap: 6px; align-self: flex-start;
    height: 28px; padding: 0 12px; border-radius: 999px;
    background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.22);
    font-size: 12px; font-weight: 500; backdrop-filter: blur(6px);
}
.login-banner__badge-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #95de64;
    box-shadow: 0 0 0 4px rgba(149,222,100,.25);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{ opacity:1; } 50%{ opacity:.55; } }
.login-banner__bottom { margin-top: auto; }
.login-banner__title { font-size: 32px; font-weight: 700; line-height: 1.25; margin-bottom: 12px; letter-spacing: .02em; }
.login-banner__desc { font-size: 14px; opacity: .9; letter-spacing: .18em; margin-bottom: 18px; }
.login-banner__tags { display: flex; flex-wrap: wrap; gap: 8px; }
.login-banner__tag {
    padding: 5px 10px; border-radius: 6px; font-size: 12px;
    background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.18);
}

.login-panel { padding: 40px 40px 32px; display: flex; flex-direction: column; justify-content: center; }
.login-panel__head { margin-bottom: 26px; }
.login-panel__welcome { font-size: 13px; color: var(--c-brand); font-weight: 600; margin-bottom: 6px; }
.login-panel__title { font-size: 24px; font-weight: 700; color: var(--c-text); line-height: 1.2; margin-bottom: 6px; }
.login-panel__sub { font-size: 13px; color: var(--c-text-3); }

.login-brand {
    display: flex; align-items: center; gap: 14px;
    padding: 12px 14px; margin-bottom: 22px;
    background: linear-gradient(180deg, #fafcff 0%, #fff 100%);
    border: 1px solid var(--c-border-light); border-radius: 12px;
}
.login-brand__logo-wrap {
    flex-shrink: 0; width: 52px; height: 52px; border-radius: 12px; padding: 3px;
    background: linear-gradient(135deg, #69b1ff, #1677ff);
    box-shadow: 0 4px 12px rgba(22,119,255,.25);
}
.login-brand__logo {
    width: 100%; height: 100%; padding: 13px; border-radius: 9px; display: block;
}
.login-brand__title { font-size: 16px; font-weight: 600; color: var(--c-text); line-height: 1.35; }
.login-brand__meta { font-size: 12px; color: var(--c-text-3); margin-top: 2px; }

.login-alert {
    margin-bottom: 16px; padding: 10px 14px; border-radius: 10px;
    font-size: 13px; line-height: 1.5; text-align: center; word-break: break-all;
}
.login-alert--error { background: #fff1f0; border: 1px solid #ffccc7; color: #cf1322; }
.login-alert--success { background: #f6ffed; border: 1px solid #b7eb8f; color: #389e0d; }

.login-form { display: flex; flex-direction: column; gap: 16px; }
.login-field { position: relative; }
.login-field__icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    width: 18px; height: 18px; color: #bfbfbf; pointer-events: none;
    transition: color .2s;
}
.login-field:focus-within .login-field__icon { color: var(--c-brand); }
.login-field__input {
    width: 100%; height: 44px; padding: 0 14px 0 42px;
    font-size: 14px; color: var(--c-text);
    border: 1px solid var(--c-border); border-radius: 10px; background: #fff;
    outline: none; transition: border-color .2s, box-shadow .2s, background .2s; font-family: inherit;
}
.login-field__input:hover { border-color: #91caff; }
.login-field__input:focus {
    border-color: var(--c-brand); background: #fcfdff;
    box-shadow: 0 0 0 3px rgba(22,119,255,.12);
}
.login-field__input::placeholder { color: #c0c4cc; }

.login-captcha { position: relative; }
.puzzle-trigger {
    display: flex; align-items: center; gap: 8px; width: 100%; height: 44px;
    padding: 0 14px; border: 1px solid var(--c-border); border-radius: 10px;
    background: #fff; cursor: pointer; font-size: 14px; color: var(--c-text-2);
    transition: border-color .2s, box-shadow .2s, background .2s; font-family: inherit;
}
.puzzle-trigger:hover { border-color: #91caff; }
.puzzle-trigger__icon { width: 18px; height: 18px; color: #bfbfbf; flex-shrink: 0; }
.puzzle-trigger__arrow { margin-left: auto; color: #c0c4cc; font-size: 16px; }
.puzzle-trigger.is-passed { border-color: #95de64; background: #f6ffed; color: #389e0d; cursor: default; }
.puzzle-trigger.is-passed .puzzle-trigger__icon { color: #52c41a; }
.puzzle-trigger.is-passed .puzzle-trigger__arrow { display: none; }
.puzzle-box {
    display: none; position: fixed; inset: 0; z-index: 60;
    align-items: center; justify-content: center;
    background: rgba(10,20,45,.45); padding: 16px;
}
.puzzle-box.is-open { display: flex; }
.puzzle-card {
    display: flex; flex-direction: column; gap: 10px; padding: 16px;
    background: #fff; border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0,20,60,.35);
}
@media (max-width: 420px) {
    .puzzle-box { padding: 10px; }
    .puzzle-card { padding: 10px; width: 100%; }
    .puzzle-stage { width: 100%; }
}
.puzzle-box__head { display: flex; align-items: center; font-size: 13px; color: var(--c-text-3); }
.puzzle-box__btns { margin-left: auto; display: flex; gap: 4px; }
.puzzle-box__btn { width: 24px; height: 24px; border: none; background: transparent; color: #999; cursor: pointer; font-size: 14px; border-radius: 6px; line-height: 1; }
.puzzle-box__btn:hover { background: #eef3ff; color: var(--c-brand); }
.puzzle-stage { position: relative; width: 320px; height: 160px; border-radius: 10px; overflow: hidden; background: #e6e6e6; user-select: none; -webkit-user-select: none; }
.puzzle-stage img { display: block; width: 100%; height: 100%; pointer-events: none; }
.puzzle-piece { position: absolute; top: 0; left: 0; width: 52px; height: 52px; box-shadow: 0 2px 8px rgba(0,0,0,.25); pointer-events: none; }
.puzzle-track { position: relative; height: 40px; border-radius: 10px; background: #eef1f6; border: 1px solid var(--c-border-light); overflow: hidden; }
.puzzle-track__fill { position: absolute; left: 0; top: 0; bottom: 0; width: 0; background: linear-gradient(90deg, rgba(22,119,255,.25), rgba(22,119,255,.45)); }
.puzzle-track__hint { position: absolute; left: 0; right: 0; top: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--c-text-3); pointer-events: none; }
.puzzle-track.is-failed .puzzle-track__hint { color: #cf1322; }
.puzzle-thumb {
    position: absolute; left: 0; top: 0; bottom: 0; width: 40px; z-index: 2;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #1677ff, #4096ff); color: #fff;
    border-radius: 9px; cursor: grab; box-shadow: 0 2px 6px rgba(22,119,255,.35);
}
.puzzle-thumb:active { cursor: grabbing; }
.puzzle-thumb svg { width: 14px; height: 14px; }

.login-submit {
    width: 100%; height: 44px; margin-top: 4px;
    border: none; border-radius: 10px; cursor: pointer;
    background: linear-gradient(135deg, #1677ff 0%, #4096ff 100%);
    color: #fff; font-size: 15px; font-weight: 600; letter-spacing: .08em; font-family: inherit;
    transition: transform .15s, box-shadow .2s, filter .2s;
    box-shadow: 0 6px 16px rgba(22,119,255,.28);
}
.login-submit:hover {
    filter: brightness(1.03);
    box-shadow: 0 8px 20px rgba(22,119,255,.34);
    transform: translateY(-1px);
}
.login-submit:active { transform: translateY(0); box-shadow: 0 4px 12px rgba(22,119,255,.24); }

.login-footer {
    margin-top: 22px; text-align: center; font-size: 12px; color: var(--c-text-3); line-height: 1.6;
}
.login-footer span { opacity: .85; }
</style>
</head>
<body>
<div class="login-bg"></div>
<div class="login-page">
    <div class="login-shell">
        <div class="login-banner">
            <div class="login-banner__overlay"></div>
            <div class="login-banner__decor login-banner__decor--1"></div>
            <div class="login-banner__decor login-banner__decor--2"></div>
            <div class="login-banner__mask">
                <div class="login-banner__badge"><span class="login-banner__badge-dot"></span> 系统运行中</div>
                <div class="login-banner__bottom">
                    <div class="login-banner__title">安全 · 稳定 · 高效</div>
                    <div class="login-banner__desc">安 全 高 效 的 网 盘 解 决 方 案</div>
                    <div class="login-banner__tags">
                        <span class="login-banner__tag">数据加密</span>
                        <span class="login-banner__tag">权限管控</span>
                        <span class="login-banner__tag">7×24 运维</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-panel">
            <div class="login-panel__head">
                <div class="login-panel__welcome">Welcome back</div>
                <div class="login-panel__title">欢迎登录后台</div>
                <div class="login-panel__sub">请输入账号信息以继续访问管理控制台</div>
            </div>

            <div class="login-brand">
                <div class="login-brand__logo-wrap">
                    <svg class="login-brand__logo" viewBox="0 0 24 24" fill="#fff"><path d="M19.35 10.04A7.49 7.49 0 0 0 12 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 0 0 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM19 18H6c-2.21 0-4-1.79-4-4s1.79-4 4-4h.71C7.37 7.69 9.48 6 12 6c3.04 0 5.5 2.46 5.5 5.5v.5H19c1.66 0 3 1.34 3 3s-1.34 3-3 3z"/></svg>
                </div>
                <div>
                    <div class="login-brand__title"><?php echo htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="login-brand__meta">管理控制台</div>
                </div>
            </div>

            <?php if($login_msg !== ''){?>
            <div class="login-alert login-alert--<?php echo $login_msg_type?>"><?php echo htmlspecialchars($login_msg, ENT_QUOTES, 'UTF-8')?></div>
            <?php if($login_auto_jump){?><script>setTimeout(function(){location.href='./';},1500);</script><?php }?>
            <?php }?>
            <form class="login-form" method="post">
                <div class="login-field">
                    <svg class="login-field__icon" viewBox="0 0 1024 1024" fill="currentColor"><path d="M288 320a224 224 0 1 0 448 0 224 224 0 1 0-448 0zm544 608H160a32 32 0 0 1-32-32v-96a160 160 0 0 1 160-160h448a160 160 0 0 1 160 160v96a32 32 0 0 1-32 32z"/></svg>
                    <input class="login-field__input" type="text" autocomplete="off" placeholder="请输入账号" name="user" value="<?php echo isset($_POST['user']) ? htmlspecialchars($_POST['user'], ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>
                <div class="login-field">
                    <svg class="login-field__icon" viewBox="0 0 1024 1024" fill="currentColor"><path d="M512 64a256 256 0 0 1 256 256v128H256V320A256 256 0 0 1 512 64zm192 160v-64a192 192 0 1 0-384 0v64h384zM224 448h576a96 96 0 0 1 96 96v384a96 96 0 0 1-96 96H224a96 96 0 0 1-96-96V544a96 96 0 0 1 96-96z"/></svg>
                    <input class="login-field__input" type="password" autocomplete="off" placeholder="请输入密码" name="pass">
                </div>
                <?php if($puzzle_enabled){?>
                <div class="login-captcha">
                    <button type="button" class="puzzle-trigger" id="puzzleTrigger">
                        <svg class="puzzle-trigger__icon" viewBox="0 0 1024 1024" fill="currentColor"><path d="M384 128a128 128 0 0 1 128 128v64H192v-64a128 128 0 0 1 192 0zm160 192v-64a160 160 0 0 0-320 0v64H128a32 32 0 0 0-32 32v544a32 32 0 0 0 32 32h640a32 32 0 0 0 32-32V352a32 32 0 0 0-32-32H544zm32 64v480H192V384h384z"/></svg>
                        <span id="puzzleTriggerText">点击进行安全验证</span>
                        <span class="puzzle-trigger__arrow">›</span>
                    </button>
                    <div class="puzzle-box" id="puzzleBox">
                        <div class="puzzle-card">
                        <div class="puzzle-box__head">
                            <span>拖动左边滑块完成上方拼图</span>
                            <div class="puzzle-box__btns">
                                <button type="button" class="puzzle-box__btn" id="puzzleRefresh" title="刷新">⟳</button>
                                <button type="button" class="puzzle-box__btn" id="puzzleClose" title="关闭">×</button>
                            </div>
                        </div>
                        <div class="puzzle-stage" id="puzzleStage">
                            <img id="puzzleBgImg" alt="拼图" draggable="false">
                            <div class="puzzle-piece" id="puzzlePiece"><img id="puzzlePieceImg" alt="" draggable="false"></div>
                        </div>
                        <div class="puzzle-track" id="puzzleTrack">
                            <div class="puzzle-track__fill" id="puzzleTrackFill"></div>
                            <span class="puzzle-track__hint" id="puzzleTrackHint">按住滑块，拖动完成拼图</span>
                            <div class="puzzle-thumb" id="puzzleThumb">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 6h14v2H5zm0 5h14v2H5zm0 5h14v2H5z"/></svg>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                <?php }?>
                <button type="submit" class="login-submit">登 录 系 统</button>
            </form>
        </div>
    </div>

    <div class="login-footer">
        <span>copyright © <?php echo date('Y') ?> <?php echo htmlspecialchars($conf['title'], ENT_QUOTES, 'UTF-8') ?> · All Rights Reserved</span>
    </div>
</div>
<?php if($puzzle_enabled){?>
<script>
(function(){
var box=document.getElementById('puzzleBox'),
    trig=document.getElementById('puzzleTrigger'),
    trigText=document.getElementById('puzzleTriggerText'),
    bgImg=document.getElementById('puzzleBgImg'),
    piece=document.getElementById('puzzlePiece'),
    pieceImg=document.getElementById('puzzlePieceImg'),
    track=document.getElementById('puzzleTrack'),
    trackFill=document.getElementById('puzzleTrackFill'),
    trackHint=document.getElementById('puzzleTrackHint'),
    thumb=document.getElementById('puzzleThumb'),
    refreshBtn=document.getElementById('puzzleRefresh'),
    closeBtn=document.getElementById('puzzleClose'),
    form=document.querySelector('.login-form'),
    alertBox=document.querySelector('.login-alert');
var S={token:'',ty:0,pieceW:52,passed:false,drag:false,loading:false,x:0,rangePiece:268,rangeThumb:220};
function showMsg(msg,type){
  if(alertBox){
    alertBox.className='login-alert login-alert--'+(type||'error');
    alertBox.textContent=msg;
    alertBox.style.display='block';
  }
}
function clearMsg(){ if(alertBox){ alertBox.style.display='none'; } }
function calcRange(){
  if(track.clientWidth>0){ S.rangeThumb=track.clientWidth-thumb.offsetWidth; }
}
function resetDrag(){
  S.x=0; thumb.style.left='0px'; trackFill.style.width='0px';
  piece.style.left='0px'; track.classList.remove('is-failed');
  trackHint.textContent='按住滑块，拖动完成拼图'; trackHint.style.opacity='';
}
function loadPuzzle(){
  if(S.loading||S.passed)return;
  S.loading=true; clearMsg(); resetDrag();
  var xhr=new XMLHttpRequest();
  xhr.open('GET','./captcha.php?_='+Date.now(),true);
  xhr.onreadystatechange=function(){
    if(xhr.readyState!==4)return;
    S.loading=false;
    if(xhr.status===200){
      var d=null; try{ d=JSON.parse(xhr.responseText); }catch(e){}
      if(d&&d.code===0){
        S.token=d.token;
        bgImg.src=d.bg; pieceImg.src=d.piece;
        piece.style.top=(d.piece_y||0)+'px';
        box.classList.add('is-open');
        calcRange();
      }else{
        box.classList.remove('is-open');
        showMsg(d&&d.msg?d.msg:'验证加载失败，请重试','error');
      }
    }else{
      box.classList.remove('is-open');
      showMsg('验证加载失败，请重试','error');
    }
  };
  xhr.send();
}
function verify(){
  if(S.x<=0)return;
  var fd=new FormData();
  fd.append('x',Math.round(S.x*S.rangePiece/S.rangeThumb));
  fd.append('token',S.token);
  var xhr=new XMLHttpRequest();
  xhr.open('POST','./captcha-check.php',true);
  xhr.onreadystatechange=function(){
    if(xhr.readyState!==4)return;
    var ok=false,msg='';
    if(xhr.status===200){
      try{ var d=JSON.parse(xhr.responseText); ok=d.code===0; msg=d.msg||''; }catch(e){}
    }
    if(ok){
      S.passed=true;
      box.classList.remove('is-open');
      trig.classList.add('is-passed');
      trigText.textContent='✓ 安全验证通过';
      clearMsg();
    }else{
      track.classList.add('is-failed');
      trackHint.textContent=msg||'验证失败，请重新拖动';
      setTimeout(resetDrag,700);
    }
  };
  xhr.send(fd);
}
trig.addEventListener('click',function(){ if(!S.passed)loadPuzzle(); });
refreshBtn.addEventListener('click',function(){ loadPuzzle(); });
closeBtn.addEventListener('click',function(){ box.classList.remove('is-open'); resetDrag(); });
box.addEventListener('click',function(e){ if(e.target===box){ box.classList.remove('is-open'); resetDrag(); } });
function clientX(e){ return e.touches?e.touches[0].clientX:e.clientX; }
var startX=0;
function startDrag(e){
  if(S.loading)return;
  e.preventDefault();
  S.drag=true; startX=clientX(e)-S.x;
  trackHint.style.opacity='0';
  document.addEventListener('mousemove',moveDrag);
  document.addEventListener('mouseup',endDrag);
  document.addEventListener('touchmove',moveDrag,{passive:false});
  document.addEventListener('touchend',endDrag);
}
function moveDrag(e){
  if(!S.drag)return;
  e.preventDefault();
  var nx=clientX(e)-startX;
  if(nx<0)nx=0; if(nx>S.rangeThumb)nx=S.rangeThumb;
  S.x=nx;
  thumb.style.left=nx+'px';
  trackFill.style.width=nx+'px';
  piece.style.left=Math.round(nx*S.rangePiece/S.rangeThumb)+'px';
}
function endDrag(){
  if(!S.drag)return;
  S.drag=false;
  document.removeEventListener('mousemove',moveDrag);
  document.removeEventListener('mouseup',endDrag);
  document.removeEventListener('touchmove',moveDrag);
  document.removeEventListener('touchend',endDrag);
  verify();
}
thumb.addEventListener('mousedown',startDrag);
thumb.addEventListener('touchstart',startDrag,{passive:false});
if(form){
  form.addEventListener('submit',function(e){
    if(!S.passed){ e.preventDefault(); showMsg('请先完成滑动拼图验证！','error'); }
  });
}
})();
</script>
<?php }?>
</body>
</html>