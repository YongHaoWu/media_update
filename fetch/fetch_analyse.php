<?php
include '../includes/init.php';
$appid="wx1adc5c4db7b764f2";
$appsecret="7491ebf3e1beb6c9439a419ad52e37e6";
$fetch_id=12;
$fetch=$db->getRow("select latest_ep_url,url from av_fetch where fetch_id=$fetch_id");
$url = $fetch['url'];
$ever_latest_ep_url = $fetch['latest_ep_url'];
if( !$url )
   exit("url is empty ");
$fetch_start = time();
$http = new http();
$re=$http->get($url);
$contents=$re['body'];
$contents = iconv("gb2312", "UTF-8", $contents);
if( !$contents )  exit("contents is empty ");
preg_match_all('/<div class="title1">(.+?)<\/div>/is',$contents, $core );

$clear_code = preg_replace('/<span class="xinfan">.+?<\/span>/is', '', $core[1]);
print_r( $clear_code );
foreach( $clear_code as  $k =>$single )
{
  preg_match_all('/<a href="([^"]+?)".+?title="([^"]+?)"/is', $single, $media[$k] );//取出视频名称（含集数）*title="([^"]+?)"
  preg_match_all( '/<span class="write">([^>]+)</' , $single, $times[$k] );//取出更新时间
}

$skip=0;
$video_url = $media[$skip][1][0];
echo $video_url.'url </br>';
if( $video_url == $ever_latest_ep_url )
   exit("No update video ");
else
{
	$latest_ep_url = $video_url;
	$localtime =  date( "y-m-d ", time());

	for( $i=0; $times[$i][1][0]; $i++ )
	{
		for( $j=3, $k=0 ;  $times[$i][1][0][$j]!=']'; $j++, $k++ )//判斷是否今天更新
		{
			if( $times[$i][1][0][$j]!=$localtime[$k] )
				break;
		}
		if( $times[$i][1][0][$j]==']' )//在今天更新
		{
			echo "update today! skip is ".$skip.'<br>';
			if( !preg_match('/[0-9)]+$/',$media[$skip][2][0] ) )
			   {
					$rawtext .= $media[$skip][2][0];
					++$skip;
					echo "in!skip is ".$skip.'<br>';
					continue;
				}
			$name = preg_replace('/[0-9]*[(0-9]*[)]*$/', '', $media[$i+$skip][2][0]);
			require_once('pinyin_table.php');
			$title= get_pinyin_array($name);
			$pic_name = preg_replace('/\W*/', '', $title[0]); //去不了_
			$list_url = 'http://qingkong.net/anime/'.$pic_name.'/';
			$my_prefix = 'http://pic.qingkong.net/pic/'.$pic_name[0].'/';
			$my_suffix = '.jpg';
			$title_img_url = $my_prefix.$pic_name.$my_suffix ;
			$ep = preg_replace('/[^()0-9]+/', '', $media[$i+$skip][2][0]);
			$video_url = $media[$i+$skip][1][0];
			if($ever_latest_ep_url == $video_url)
				break;
            echo 'name:  '.$name.'<br> url:'.$video_url.'<br><br>';
			$sql = "SELECT * FROM av_title WHERE title_name = '$name' ";  
			$title_result = $db->getRow($sql);
			echo "title result";
			print_r($title_result);
			if( !$title_result ) //空的 
			{
				$field_values = array( "title_name" => $name, "ep_qty" =>intval($ep, 10), "latest_ep_title" =>$ep, "title_img_url" =>$title_img_url, "list_url" =>$list_url);  
				$db->autoExecute("av_title", $field_values);  
			    $sql = "SELECT * FROM av_title WHERE title_name = '$name' ";  
			    $title_result = $db->getRow($sql);
			}

			$title_id = $title_result['title_id'];
			$field_values = array( "title_id" =>$title_id, "ep"=>intval($ep, 10), "ep_title" => $ep, "video_url" =>$video_url , "rawtext" =>$media[$i+$skip][2][0], "fetch_id" => $fetch_id, "fetch_time" =>time() ); 
			$db->autoExecute("av_video", $field_values);

			$latest_ep_title = $ep.'更新了！ 观看地址： '.$video_url;
			echo '******'.$latest_ep_title.'***********';
			$field_values = array( "ep_qty" =>intval($ep, 10), "latest_ep_title"=>$latest_ep_title);  
			$db->autoExecute("av_title", $field_values, "UPDATE", "title_id=$title_id");


			$sql ="insert into av_msg_queue (user_id, msg) 
				select user_id,CONCAT(t.title_name,t.latest_ep_title)
				from av_subs s
				inner join av_title t on s.title_id = t.title_id
				where s.title_id = $title_result[title_id]";
			$db->query($sql);
			$field_values = array( "last_fetch_started" =>$fetch_start, "last_fetch_ended" =>time(), "latest_ep_url" =>$latest_ep_url);  
			$db->autoExecute("av_fetch", $field_values, "UPDATE", "fetch_id=$fetch_id");

			$sql = "select  * from av_msg_queue where done=0";
			$msg_queue = $db->getAll($sql);
			print_r($msg_queue);
			foreach( $msg_queue as $single_msg )
			{
				if( $single_msg['msg'] )
				{
					$user_id = $single_msg['user_id'];
					if( send_message($single_msg['user_id'], $single_msg['msg'])==1 || $single_msg['count']>5)
					{
						$single_msg['done']=1;
						$field_values = array("done"=>1);
						$db->autoExecute("av_msg_queue", $field_values, "UPDATE", "user_id=$user_id");
					}
					else
					{
						$single_msg['count']++;
						$single_msg['try_time']=time();
						$field_values = array("count"=>$single_msg['count'],"try_time"=>time());  
						$db->autoExecute("av_msg_queue", $field_values, "UPDATE", "user_id=$user_id"); 
					}
				}

			}
 
	/*	$sql = "select  user_id, t.title_name,av_video.latest_ep_title   from av_subs s
				inner join av_title t on s.title_id = t.title_id
				where s.title_id = $title_result[title_id] ";  
			$msg_queue_result = $db->getAll($sql);  */
		/*	foreach( $msg_queue_result as $value )
			{
				$field_values = array( "user_id" =>$value[$i][$user_id], "msg" => '你关注的视频： ' .$value[$i][$title_name]. ' 已经更新至 ' .$value[$i][$latest_ep_title]);
			$db->autoExecute("av_msg_queue", $field_values);  
			}
*/

			/*insert into av_msg_queue (user_id,msg) 
				select  user_id, '你关注的视频： ' + t.title_name + ' 已经更新至 ' + av_video.latest_ep_title   from av_subs s
				inner join av_title t on s.title_id = t.title_id
				where s.title_id = $title_result['title_id'];
				CONCAT('你关注的视频： ' ,t.title_name,' 已经更新至 ',t.latest_ep_title)
				*/
		}
	}
}


function send_message($user_id, $message)
{
global $db,$appid,$appsecret;
$http=new http();
$acctoken="";
if( file_exists("access_token.txt")){
	if(file_exists("token_expires.txt")){
		if( time()<file_get_contents("token_expires")) $acctoken=file_get_contents("access_token.txt");
	}
}
if( $acctoken==''){
	$m=file_get_contents("https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret");
	$m=json_decode( $m, true);
//	print_r($m);
	if( $m['access_token']) {
		
		//echo 'save acc token';
		$acctoken=$m['access_token'];
		file_put_contents("access_token.txt", $acctoken);
		file_put_contents("token_expires.txt", time()+intval($m['expires_in'])-10);
	}else{
		die('wx error');
	}
	//echo 'token created<br>';
	unset( $m);
}
$openid=$db->getOne("select openid from av_wx_visitor where visitor_id=$user_id"); 

$url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=$acctoken";
$data='{"touser":"'.$openid.'","msgtype":"text","text":{
														"content":"'.$message.'"
														}	
		}';
//echo $data;
        $ch = curl_init();  
  
        curl_setopt($ch, CURLOPT_URL, $url); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);  
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);  
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);  
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
        $tmpInfo = curl_exec($ch);  
        if (curl_errno($ch)) {  
            echo 'Error'.curl_error($ch);  
        }       
        curl_close($ch);
		return 1;
}
?>


