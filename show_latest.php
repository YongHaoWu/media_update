<?php 
include 'includes/init.php';
$number = 10;
$title_id = intval( $_GET['title_id']);
$media=$db->getAll("select * from av_video order by fetch_time desc LIMIT $number");
 if($media){
		$smarty->assign('media', $media);
		$smarty->display('show_latest.html');
		   }
else{
		$smarty->assign('msg','�������·������ȷ������Ƶ�Ѿ���ɾ����');
		$smarty->display('error.html');
	}
	die();
 ?>