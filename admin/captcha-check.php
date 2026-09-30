<?php
/**
 * 滑块拼图验证码 - 位移校验接口
 * POST: x(拼图块像素位移), token
 * 通过：session 置 puzzle_passed=true（一次性，登录成功时由 login.php 销毁）
 * 防爆破：同一 session 10 次失败锁 10 分钟
**/
define('IN_ADMIN', true);
include("../includes/common.php");

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$ok = false;
$msg = '验证未通过';

if(isset($_SESSION['puzzle_locked_until']) && time() < $_SESSION['puzzle_locked_until']){
	$msg = '尝试次数过多，请'.ceil(($_SESSION['puzzle_locked_until']-time())/60).'分钟后再试';
}elseif($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['x']) && isset($_POST['token'])){
	$p = isset($_SESSION['puzzle']) ? $_SESSION['puzzle'] : null;
	if($p && hash_equals((string)$p['token'], (string)$_POST['token'])){
		$age = time() - (int)$p['ts'];
		if($age > 0 && $age <= 600){
			$x = (float)$_POST['x'];
			if(abs($x - (float)$p['tx']) <= 6){
				$_SESSION['puzzle_passed'] = true;
				$_SESSION['puzzle_fail'] = 0;
				unset($_SESSION['puzzle']);
				unset($_SESSION['puzzle_locked_until']);
				$ok = true;
				$msg = '验证通过';
			}else{
				$_SESSION['puzzle_fail'] = isset($_SESSION['puzzle_fail']) ? $_SESSION['puzzle_fail'] + 1 : 1;
				if($_SESSION['puzzle_fail'] >= 10){
					unset($_SESSION['puzzle']);
					$_SESSION['puzzle_locked_until'] = time() + 600;
					$msg = '尝试次数过多，请10分钟后再试';
				}else{
					$msg = '拖动滑块将拼图对准缺口';
				}
			}
		}else{
			unset($_SESSION['puzzle']);
			$msg = '验证已过期，请重新验证';
		}
	}else{
		$msg = '验证信息失效，请重新验证';
	}
}else{
	$msg = '参数错误';
}

echo json_encode(array('code' => $ok ? 0 : 1, 'msg' => $msg));