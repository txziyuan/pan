<?php
/**
 * 滑块拼图验证码 - 拼图生成接口
 * 输出 JSON：{code, token, bg(base64 PNG), piece(base64 PNG), piece_y}
 * 缺口横向位置只存服务端 session，不下发前端，拖动位移由 captcha-check.php 比对
 * 登录成功后 puzzle_passed 一次性销毁，与旧图形验证码（code.php）互斥，可随时删除 code.php
**/
define('IN_ADMIN', true);
include("../includes/common.php");

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if(!function_exists('imagecreate')){
	echo json_encode(array('code'=>1,'msg'=>'服务器不支持图形处理，无法进行滑块验证'));
	exit;
}

//失败锁定：10 次错误锁 10 分钟，防止脚本逐个位移值爆破
if(isset($_SESSION['puzzle_locked_until']) && time() < $_SESSION['puzzle_locked_until']){
	echo json_encode(array('code'=>1,'msg'=>'尝试次数过多，请'.ceil(($_SESSION['puzzle_locked_until']-time())/60).'分钟后再试'));
	exit;
}

$W = 320; $H = 160; $S = 52; $R = 8;
$tx = rand(70, $W - $S - 12);
$ty = rand(30, $H - $S - 14);

//---- 背景：天空渐变 + 云 + 山（GD 动态绘制，零外部图片依赖） ----
$bg = imagecreatetruecolor($W, $H);
for($y = 0; $y < $H; $y++){
	$t = $y / $H;
	$r = (int)(142 + (224 - 142) * $t);
	$g = (int)(197 + (246 - 197) * $t);
	$b = (int)(252 + (255 - 252) * $t);
	imageline($bg, 0, $y, $W, $y, imagecolorallocate($bg, $r, $g, $b));
}
$cloud = imagecolorallocatealpha($bg, 255, 255, 255, 45);
imagefilledellipse($bg, 88, 44, 72, 26, $cloud);
imagefilledellipse($bg, 126, 36, 52, 22, $cloud);
imagefilledellipse($bg, 60, 56, 46, 18, $cloud);
imagefilledellipse($bg, 250, 30, 56, 20, $cloud);
$far = imagecolorallocate($bg, 126, 164, 200);
imagefilledpolygon($bg, array(0,142, 58,94, 132,142), 3, $far);
imagefilledpolygon($bg, array(96,142, 188,86, 272,142), 3, $far);
imagefilledpolygon($bg, array(228,142, 302,100, 320,126, 320,142), 4, $far);
$near = imagecolorallocate($bg, 96, 139, 181);
imagefilledpolygon($bg, array(0,160, 42,118, 112,160), 3, $near);
imagefilledpolygon($bg, array(68,160, 172,106, 262,160), 3, $near);
imagefilledpolygon($bg, array(206,160, 298,120, 320,142, 320,160), 4, $near);

//干净背景快照（用于缺口圆角外恢复原像素）
$bgClean = imagecreatetruecolor($W, $H);
imagecopy($bgClean, $bg, 0, 0, 0, 0, $W, $H);

//---- 拼图块：从背景抠出圆角方块 ----
$piece = imagecreatetruecolor($S, $S);
imagealphablending($piece, false);
$alpha = imagecolorallocatealpha($piece, 0, 0, 0, 127);
imagefilledrectangle($piece, 0, 0, $S, $S, $alpha);
imagealphablending($piece, true);
imagesavealpha($piece, true);
imagecopy($piece, $bg, 0, 0, $tx, $ty, $S, $S);
//圆角：圆角矩形外的像素置透明
for($px = 0; $px < $S; $px++){
	for($py = 0; $py < $S; $py++){
		$cx = $px < $R ? $R : ($px >= $S - $R ? $S - $R - 1 : $px);
		$cy = $py < $R ? $R : ($py >= $S - $R ? $S - $R - 1 : $py);
		$dx = $px - $cx; $dy = $py - $cy;
		if($dx * $dx + $dy * $dy > $R * $R){
			imagesetpixel($piece, $px, $py, $alpha);
		}
	}
}
//白色圆角描边
$white = imagecolorallocate($piece, 255, 255, 255);
for($px = 0; $px < $S; $px++){
	for($py = 0; $py < $S; $py++){
		$cx = $px < $R ? $R : ($px >= $S - $R ? $S - $R - 1 : $px);
		$cy = $py < $R ? $R : ($py >= $S - $R ? $S - $R - 1 : $py);
		$dx = $px - $cx; $dy = $py - $cy;
		if($dx * $dx + $dy * $dy > $R * $R) continue;
		$out = false;
		foreach(array(array(1,0),array(-1,0),array(0,1),array(0,-1)) as $d){
			$qx = $px + $d[0]; $qy = $py + $d[1];
			if($qx < 0 || $qy < 0 || $qx >= $S || $qy >= $S){ $out = true; break; }
			$qcx = $qx < $R ? $R : ($qx >= $S - $R ? $S - $R - 1 : $qx);
			$qcy = $qy < $R ? $R : ($qy >= $S - $R ? $S - $R - 1 : $qy);
			$qdx = $qx - $qcx; $qdy = $qy - $qcy;
			if($qdx * $qdx + $qdy * $qdy > $R * $R){ $out = true; break; }
		}
		if($out) imagesetpixel($piece, $px, $py, $white);
	}
}

//---- 缺口：半透明遮罩 + 圆角白描边 ----
$mask = imagecolorallocatealpha($bg, 15, 40, 90, 52);
imagefilledrectangle($bg, $tx, $ty, $tx + $S - 1, $ty + $S - 1, $mask);
$gapWhite = imagecolorallocate($bg, 255, 255, 255);
for($px = 0; $px < $S; $px++){
	for($py = 0; $py < $S; $py++){
		$cx = $px < $R ? $R : ($px >= $S - $R ? $S - $R - 1 : $px);
		$cy = $py < $R ? $R : ($py >= $S - $R ? $S - $R - 1 : $py);
		$dx = $px - $cx; $dy = $py - $cy;
		if($dx * $dx + $dy * $dy > $R * $R){
			//圆角外恢复原背景
			imagesetpixel($bg, $tx + $px, $ty + $py, imagecolorat($bgClean, $tx + $px, $ty + $py));
		}else{
			$out = false;
			foreach(array(array(1,0),array(-1,0),array(0,1),array(0,-1)) as $d){
				$qx = $px + $d[0]; $qy = $py + $d[1];
				if($qx < 0 || $qy < 0 || $qx >= $S || $qy >= $S){ $out = true; break; }
				$qcx = $qx < $R ? $R : ($qx >= $S - $R ? $S - $R - 1 : $qx);
				$qcy = $qy < $R ? $R : ($qy >= $S - $R ? $S - $R - 1 : $qy);
				$qdx = $qx - $qcx; $qdy = $qy - $qcy;
				if($qdx * $qdx + $qdy * $qdy > $R * $R){ $out = true; break; }
			}
			if($out) imagesetpixel($bg, $tx + $px, $ty + $py, $gapWhite);
		}
	}
}

//---- 输出 ----
ob_start();
imagepng($bg);
$bgData = base64_encode(ob_get_clean());
ob_start();
imagepng($piece);
$pieceData = base64_encode(ob_get_clean());
imagedestroy($bg);
imagedestroy($bgClean);
imagedestroy($piece);

$token = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : md5(uniqid(mt_rand(), true));
$_SESSION['puzzle'] = array('token' => $token, 'tx' => $tx, 'ts' => time());
echo json_encode(array(
	'code' => 0,
	'token' => $token,
	'bg' => 'data:image/png;base64,' . $bgData,
	'piece' => 'data:image/png;base64,' . $pieceData,
	'piece_y' => $ty
));