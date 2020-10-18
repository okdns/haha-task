<?php
/**
 * haha-task version 0.9
 *
 * 介绍：这是一个单文件的task、bug管理工具，部署方便，给小团队或个人使用的。
 * 作者：大牛哥 <478602@qq.com>
 * 项目托管：https://github.com/okdns/haha-task.git , https://gitee.com/okdns/haha-task
 * 使用说明：
 *   初始登录账户为：admin/123
 *   项目文件中的haha_task.db只是一个演示数据库，用来帮助体验系统的，完全可以删除它，程序会自动引导建立新的数据库。
 *   本软件基本功能和流程都是齐全的，因为没经过更多用户的使用测试，可能难免会存在一些问题，所以版本号暂定为0.9
 **/

/*----配置区 ---*/
	define('HAHA_TASK_VER', 1);			//版本号，可以通过变更版本号让静态文件在客户端更新
	define('HAHA_TITLE','Task/bug管理系统');
	define('IS_DEV', 1);				//当前环境，开发环境能输出调试信息和详细报错
	error_reporting(IS_DEV?E_ALL:0);

	$ui_img_dir		= './ui/img/';	//图片文件夹位置
	$up_file_dir	= './up_file/';	//上传文件保存位置
	$db_file		= './haha_task.db'; //sqlite3数据库文件位置

	$enable_up_file	= true;			//是否允许上传图片
	$save_url		= '/upload';	//图片文件的url
	$save_path		= __DIR__.'/upload'; //图片保存文件夹的路径
	$img_type_list		= ['jpg','png','jpeg','gif','webp'];//支持的图片类型
	$display_max_width	= 450;		//图片显示的最大宽度(上传图片长宽未做限制，这里是限制显示时的长宽)

	$field_list = array(
		'type'			=> '类型',
		'position'		=> '位置',
		'browser'		=> '浏览器环境',
		'urgent'		=> '优先级',
		'submiter_uid'	=> '发布者',
		'acceptor_uid'	=> '结果测验者',
		'worker_uid'	=> '处理者',
		'tester_uid'	=> '测试者',
		'note'			=> '工作内容',
		'remark'		=> '补充说明',
	);

	$position = array(1=>'前台',2=>'后台',3=>'手机站',0=>'其他');
	$task_type = array(
		'1'	=> array('ico' => 'crop', 'title'=>'细节调整', 'key'=>'fix'),
		'2'	=> array('ico' => 'wrench', 'title'=>'功能更改', 'key'=>'change'),
		'3'	=> array('ico' => 'thumb-tack', 'title'=>'新增需求', 'key'=>'task'),
		'5'	=> array('ico' => 'bug', 'title'=>'运行出错', 'key'=>'bug'),
		'0'	=> array('ico' => 'code', 'title'=>'其他工作', 'key'=>'work'),
	);
	$browser= array(1=>'IE',2=>'Chrome',3=>'360-兼容',4=>'Edge',5=>'Firefox',0=>'全部');
	$urgent = array(0=>'空闲',1=>'普通',2=>'优先',3=>'警急');
	$task_op_type = array(
		'add'	=> '添加',
		'get'	=> '领取',
		'edit'	=> '修改',
		'to'	=> '工作转给',
		'assign'=> '测验派给',
		'urgent'=> '优先级改为',
		'end'	=> '确认完工',
		'no'	=> '打回',
		'ok'	=> '完工提交',
		'open'	=> '重开',
		'cancel'=> '取消',
		'del'	=> '删除',
		'split'	=> '拆分',
	);
	$task_status = array(
		1 => array('ico'=>'bug', 'class'=>'work', 'title'=>'待领取'),	//wait #FF9966 橙 
		4 => array('ico'=>'eye2', 'class'=>'work', 'title'=>'待测验'),	//test #FFCC33 土黄
		7 => array('ico'=>'hourglass', 'class'=>'work', 'title'=>'处理中'),//work #99CCFF 蓝
		9 => array('ico'=>'flag', 'class'=>'work', 'title'=>'已完成'),	//end #99CC99 绿
		11 => array('ico'=>'delete', 'class'=>'work', 'title'=>'已取消'), //cancel #CCCCCC 灰
	);
	$ui_type=array('sticky'=>'便贴','flat'=>'扁平','table'=>'表格');
	$task_role = [1 => '普通员工',10=>'程序开发',20=>'美工设计',50=>'市场推广',60=>'客服',70=>'产品经理', 80=>'行政经理', 99=>'超级管理员'];
		//普通员工只能处置自己提交的任务，产品经理以上可以处置全部任务，超级管理员可以改别人账户 

/* ==== 主流程 =====*/

	$haha_db = new sqlite_db($db_file);
	if(!$haha_db){
		die($haha_db->lastErrorMsg());
	}

	if(!$haha_db->exists_table()){
		if(isset($_POST['tab']) && $post['tab']='init'){
			$haha_db->init_table();
			echo json_encode(['status'=>1, 'url'=>'?tab=login']);
			exit;
		}
		haha_html('boot_init');
		exit;
	}

	if(isset($_GET['haha_res'])) haha_res($_GET['haha_res']);

	session_start();
	$tab = v_get('tab','');

	$ui=val($_COOKIE['ui_type'],'sticky');

	if(isset($_SESSION['login_user'])){
		$login_user = $_SESSION['login_user'];
	}else{
		if(isset($_COOKIE['haha_login_token'])){
			$token_str	= url64_decode($_COOKIE['haha_login_token']);
			$token_arr	= explode(',',$token_str);
			$user		= $haha_db->get_user($token_arr[0]);
			$authcode	= md5($user['uid'].$user['password'].'asdDXs$%(@$');
			if($authcode==$token_arr[1]){
				$login_user = $user;
				if(!$tab){
					$tab = 'my';
				}
			}
		}
	}
	$task_id	= v_get('task_id',0);
	$users		= $haha_db->list_user(1);	//全部成员id、name
	$work_users	= $users;					//可处理任务的人员id、name
	$manager_uids	= $haha_db->get_uids_by_role([70,80]); //有管理权限的uid列表

	if($_FILES){

		if(!$login_user){
			niceditor_up_msg("<?=haha_lang('file_tip_power')?>");
		}

		if(!isset($_FILES['image']) || !isset($_FILES['image']['tmp_name']) || empty($_FILES['image']['tmp_name'])){
			niceditor_up_msg("<?=haha_lang('file_tip_empty')?>");
		}

		$the_img = $_FILES['image'];
		$img_ext = pathinfo($the_img['name'], PATHINFO_EXTENSION);
		if(!in_array($img_ext, $img_type_list)){
			niceditor_up_msg("<?=haha_lang('file_tip_type')?>");
		}
		if($the_img['size'] > 1*1024*1024) {
			niceditor_up_msg("<?=haha_lang('file_tip_size')?>");
		}

		$y = date('Y');
		$m = date('m');
		if(!file_exists($save_path)){
			mkdir($save_path);
		}
		$save_path.='/'.$y;
		if(!file_exists($save_path)){
			mkdir($save_path);
		}
		$save_path.='/'.$m;
		if(!file_exists($save_path)){
			mkdir($save_path);
		}

		$file_name = date('Ymd-His_').$login_user['uid'].'.'.$img_ext;
		move_uploaded_file($the_img['tmp_name'], $save_path.'/'.$file_name);

		$img_size		= getimagesize($save_path.'/'.$file_name);
		$data['link']	= $save_url.'/'.$y.'/'.$m.'/'.$file_name;
		$data['width']	= $img_size[0];
		$data['height']	= $img_size[1];
		if($display_max_width && $data['width']>$display_max_width){
			$data['height']	= intval($display_max_width*($data['height']/$data['width']));
			$data['width']	= $display_max_width;
		}
		niceditor_up_msg($data);
	}

	if($_POST){
		$tab = v_post('tab','');
		if($tab=='login'){
			$account	= v_post('account','');
			$password	= v_post('password','');
			$user		= $haha_db->get_user_by_account($account);
			if($account && md5($password)==$user['password']){
				$authcode = md5($user['uid'].$user['password'].'asdDXs$%(@$');
				setcookie('haha_login_token', url64_encode($user['uid'].','.$authcode.','.time()),time()+864000);
				$_SESSION['login_user'] = $user;
				echo json_encode(['status'=>1,'url'=>'?tab=my']); exit;
			}else{
				echo json_encode(['status'=>0,'msg'=>haha_lang('login_tip_pswd')]); exit;
			}
		}

		if(!$login_user){
			echo json_encode(['status'=>0,'msg'=>haha_lang('login_tip_power')]); exit;
			console.log('in');
		}

		if($tab=='user_del'){
			if(!in_array($login_user['role'],[80,99])){
				echo json_encode(['status'=>0,'msg'=>haha_lang('login_tip_power')]); exit;
			}
			$uid = v_post('uid',0);
			if($uid){
				if($uid==1){
					echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_admin')]); exit;
				}else{
					$haha_db->delete('haha_user', ['uid'=>$uid]);
					echo json_encode(['status'=>1,'msg'=>haha_lang('user_tip_ok')]); exit;
				}
			}else{
				echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_param')]); exit;
			}
		}
		if($tab=='user_add'){
			if(!in_array($login_user['role'],[80,99])){
				echo json_encode(['status'=>0,'msg'=>haha_lang('login_tip_power')]); exit;
			}
			$account	= v_post('account','');
			$password	= v_post('password','');
			$nickname	= v_post('nickname','');
			$role		= v_post('role',0);
			$status		= v_post('status',0);
			if(!$account||!$password||!$nickname){
				echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_need')]); exit;
			}
			if($haha_db->get_user_by_account($account)){
				echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_namefind')]); exit;
			}
			if($haha_db->get_user_by_nickname($nickname)){
				echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_nickfind')]); exit;
			}
			$uid = $haha_db->add_user($account,$password,$nickname,$role,$status);
			if($uid){
				echo json_encode(['status'=>1,'msg'=>haha_lang('tip_succeed')]); exit;
			}else{
				echo json_encode(['status'=>0,'msg'=>haha_lang('tip_failed')]); exit;
			}
		}
		if($tab=='user_edit'){
			if(!in_array($login_user['role'],[80,99])){
				echo json_encode(['status'=>0,'msg'=>haha_lang('login_tip_power')]); exit;
			}
			$uid = v_post('uid',0);
			if(!$uid){
				echo json_encode(['status'=>1,'msg'=>haha_lang('user_tip_param')]); exit;
			}
			$data = [];
			if(isset($_POST['account'])){
				$account =  v_post('account','');
				if($account){
					if($haha_db->get_user_by_account($account, $uid)){
						echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_namefind')]); exit;
					}
					$data['account'] = $account;
				}
			}
			if(isset($_POST['nickname'])){
				$nickname = v_post('nickname','');
				if($nickname){
					$find = $haha_db->get_user_by_nickname($nickname, $uid);
					if($find){
						echo json_encode(['status'=>0,'msg'=>haha_lang('user_tip_nickfind')]); exit;
					}
					$data['nickname'] = $nickname;
				}
			}
			if(isset($_POST['password'])){
				$password = v_post('password','');
				if($password){
					$data['password'] = md5($password);
				}
			}
			if(isset($_POST['role'])){
				$data['role'] = v_post('role',0);
			}
			if(isset($_POST['status'])){
				$data['status'] = v_post('status',0);
			}
			$res = $haha_db->update('haha_user', ['uid'=>$uid], $data);
			if($res){
				echo json_encode(['status'=>1,'msg'=>haha_lang('tip_succeed')]); exit;
			}else{
				echo json_encode(['status'=>0,'msg'=>haha_lang('tip_failed')]); exit;
			}
		}
		if($tab=='modify_pswd'){
			$password = v_post('password','');
			if($password){
				$haha_db->modify_password($login_user['uid'], $password);
				echo json_encode(['status'=>1,'msg'=>haha_lang('pswd_ok')]); exit;
			}else{
				echo json_encode(['status'=>0,'msg'=>haha_lang('pswd_not')]); exit;
			}
		}
		if($tab=="clear_msg"){
			$haha_db->clear_msg($login_user['uid']);
			echo json_encode(['status'=>1,'msg'=>haha_lang('msg_cleared')]); exit;
		}
		$task_id	= v_post('task_id',0);
		$back		= array();
		$msg_url	= '';
		$msg_target	= 'mainFrame';
		if($tab=='add'){//【添加】 权限:所有人；通知：处理者，审核者
			$post_note			= v_post('note','');
			$post_acceptor_uid	= v_post('acceptor_uid',0);
			$post_worker_uid	= v_post('worker_uid',0);
			$post_tester_uid	= v_post('tester_uid',0);
			$post_status		= v_post('status',0);
			if($post_status==0){
				$post_status = $post_worker_uid==0?1:7;
			}
			$data = array(
				'note'			=> $post_note,
				'position'		=> v_post('position',0),
				'type'			=> v_post('type',0),
				'browser'		=> v_post('browser',0),
				'urgent'		=> v_post('urgent',0),
				'submiter_uid'	=> $login_user['uid'],
				'acceptor_uid'	=> $post_acceptor_uid,
				'worker_uid'	=> $post_worker_uid,
				'tester_uid'	=> $post_tester_uid,
				'status'		=> $post_status,
				'ctime'			=> time(),
				'mtime'			=> time(),
			);
			$inst_id = $haha_db->add_task($data);
			if($inst_id){
				$haha_db->add_log($inst_id, 'add', $login_user['uid']);
			}
			$msg_type = v_post('msg_type',0);
			$msg_url = '';
			if('notice'==$msg_type && $post_status==9){//是从欢迎页添加完成公告方式提交的
				$msg_content = str_limit(strip_tags($post_note),30);
				$haha_db->add_notice($msg_content,$login_user['uid'],'');
			}else{
				$sended_uids = array();
				if($post_acceptor_uid && $post_acceptor_uid!=$login_user['uid'] && !in_array($post_acceptor_uid, $sended_uids)) {
					$haha_db->add_msg($post_acceptor_uid,$login_user['nickname'].haha_lang('msg_added',$inst_id).', '.haha_lang('msg_add_test'), 0,$msg_url, $msg_target);
					$sended_uids[]=$post_acceptor_uid;
				}
				if($post_worker_uid && $post_worker_uid!=$login_user['uid'] && !in_array($post_worker_uid, $sended_uids)){
					$haha_db->add_msg($post_worker_uid,$login_user['nickname'].haha_lang('msg_added',$inst_id).haha_lang('msg_add_to'), 0, $msg_url, $msg_target);
					$sended_uids[]=$post_worker_uid;
				}
			}
			$back = array('status'=>1,'msg'=>haha_lang('tip_add'));
		}else{
			$task = $haha_db->get_task($task_id);
			$log_arr = $haha_db->list_log($task_id);
			$follow_uids = $haha_db->get_follow_uids($task_id);
			$msg_content = '';
			if($tab=='view'){//【查看】 权限:所有人
				$view_list = $task['view']?json_decode($task['view'],true):array();
				if(!in_array($login_user['uid'],$view_list)){
					$view_list[]=$login_user['uid'];
					$view_json = json_encode($view_list);
					//$haha_db->update('haha_task',['id'=>$task_id],['view'=>$view_json]);
					$task['view'] = $view_json;
				}
				if(isset($users[$task['submiter_uid']])){
					$task['submiter_nickname'] = $users[$task['submiter_uid']];
				}else{
					$task['submiter_nickname'] = $task['submiter_uid'];
				}
				if(isset($task_status[$task['status']])){
					$task['status_title'] = $task_status[$task['status']]['title'];
				}else{
					$task['status_title'] = $task['status'];
				}
				$task['mtime_format'] = date('Y-m-d H:i',$task['mtime']);
				$back = array('status'=>1,'msg'=>haha_lang('tip_get_data'),'data'=>$task);
			}elseif($tab=='get'){//【领取】 权限:所有人; 通知：发布者、测验者
				if($task){
					$haha_db->get_work($task_id, $login_user['uid']);
					$msg_content = $login_user['nickname'].haha_lang('msg_get',$task_id);
					if($task['acceptor_uid'] && $task['acceptor_uid']!=$login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					if($task['submiter_uid']!=$task['acceptor_uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					$back = array('status'=>1,'msg'=>haha_lang('tip_get'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_noget'));
				}
			}elseif($tab=='end'){//【确认完工】 权限:发布者、管理员；通知：处理者、公告
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'], $task['acceptor_uid'])))) {
					$haha_db->end_work($task_id, $login_user['uid']);
					$msg_content = $login_user['nickname'].haha_lang('msg_end',$task_id);
					if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
						$follow_uids[]=$task['worker_uid'];
					}
					if($task['submiter_uid'] != $login_user['uid'] && $sended_uid!=$task['submiter_uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					if($task['acceptor_uid'] && $task['acceptor_uid']!=$task['submiter_uid'] && $task['submiter_uid'] !=$login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					$back = array('status'=>1,'msg'=>haha_lang('tip_end'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='no'){//【打回】 权限:发布者、管理员；通知：处理者
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'], $task['acceptor_uid'])))) {
					$haha_db->no_work($task_id, $login_user['uid']);
					if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid']){
						$haha_db->add_msg($task['worker_uid'],haha_lang('msg_my_no',$task_id,$login_user['nickname']),0, $msg_url, $msg_target);
						$follow_uids = array_diff($follow_uids, array($task['worker_uid']));
					}
					$msg_content = haha_lang('msg_no',$task_id,$login_user['nickname']);
					$back = array('status'=>1,'msg'=>haha_lang('tip_no'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='ok'){//【提交】 权限：处理者；通知：测验者、发布者
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'], $task['worker_uid'])))) {
					$haha_db->ok_work($task_id, $login_user['uid']);
					$msg_content = haha_lang('msg_ok',$login_user['nickname'],$task_id);
					if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					if(empty($task['acceptor_uid']) && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					$back = array('status'=>1,'msg'=>haha_lang('tip_success'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='open'){//【重开】 权限：处理者、发布者、管理员；通知：处理者、测验者
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))) {
					$haha_db->open_work($task_id, $login_user['uid']);
					if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid']){
						$haha_db->add_msg($task['worker_uid'],haha_lang('msg_my_open',$login_user['nickname'],$task_id),0, $msg_url, $msg_target);
						$follow_uids = array_diff($follow_uids, array($task['worker_uid']));
					}
					$msg_content = haha_lang('msg_to_open',$login_user['nickname'],$task_id);
					if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					if(empty($task['acceptor_uid']) && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					$back = array('status'=>1,'msg'=>haha_lang('tip_success'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='cancel'){//【取消】 权限：发布者、管理员；通知：处理者、测验者、发布者
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))) {
					$haha_db->cancel_work($task_id, $login_user['uid']);
					$msg_content = haha_lang('msg_cancel',$login_user['nickname'],$task_id);
					if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
						$follow_uids[]=$task['worker_uid'];
					}
					if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					if($task['submiter_uid'] && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					$back = array('status'=>1,'msg'=>haha_lang('tip_success'));
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='del'){//【删除】 权限：发布者、管理员；通知：发布者
				if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))) {
					$haha_db->del_work($task_id, $login_user['uid']);
					$msg_url = '';
					/* 已取消的工作包才能删除，删除已取消的工作包不用发提醒。
					$msg_content = haha_lang('msg_deled',$login_user['nickname'],$task_id);
					if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
						$follow_uids[]=$task['worker_uid'];
					}
					if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
						$follow_uids[]=$task['acceptor_uid'];
					}
					if($task['submiter_uid'] && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
						$follow_uids[]=$task['submiter_uid'];
					}
					*/
					$back = array('status'=>1,'msg'=>haha_lang('tip_deled'));
				}else{
					$back= array('status'=>0,'msg'=>haha_lang('tip_power'));
				}
			}elseif($tab=='edit'){//【修改】 权限:发布者、管理员；通知：处理者、测验者、发布者
				$field = v_post('field','');
				$value = v_post('value','');
				if($task_id && $field){
					if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'], $task['acceptor_uid'])))) {
						if(1){
							if($field == 'worker_uid') {
								$haha_db->edit_task($task_id, array($field=>$value,'status'=>($task['status']==1?7:$task['status'])));
								$haha_db->add_log($task_id, 'to', $login_user['uid'], $value);
								if($value!=$login_user['uid']){
									//if($value && !in_array($value,$sended_uid)){ //
									//	$haha_db->add_msg($value, haha_lang('msg_to_me',$login_user['nickname'],$task_id),0, $msg_url, $msg_target);
									//	$sended_uid[]=$value;
									//}
									$worker_name = isset($users[$value])?$users[$value]:$value;
									$msg_content = haha_lang('msg_to',$login_user['nickname'],$task_id,$worker_name);
									if($task['worker_uid'] && $task['worker_uid'] != $login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
										$follow_uids[]=$task['worker_uid'];
									}
									if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
										$follow_uids[]=$task['acceptor_uid'];
									}
									if($task['submiter_uid'] && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
										$follow_uids[]=$task['submiter_uid'];
									}
								}
							}elseif($field=='acceptor_uid'){
								$haha_db->edit_task($task_id, [$field=>$value]);
								$haha_db->add_log($task_id, 'assign', $login_user['uid'], $value);
								if($value!=$login_user['uid']){
									//if($value && !in_array($value,$sended_uid)){
									//	$haha_db->add_msg($value, haha_lang('msg_test_me',$login_user['nickname'],$task_id),0, $msg_url, $msg_target);
									//	$sended_uid[]=$value;
									//}
									$acceptor_name = isset($users[$value])?$users[$value]:$value;
									$msg_content   = haha_lang('msg_test',$login_user['nickname'],$acceptor_name,$task_id);
									if($task['worker_uid'] && $task['worker_uid'] != $login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
										$follow_uids[]=$task['worker_uid'];
									}
									if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
										$follow_uids[]=$task['acceptor_uid'];
									}
									if($task['submiter_uid'] && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
										$follow_uids[]=$task['submiter_uid'];
									}
								}
							}else{
								$update = array($field=>$value);
								if($field=='type'){
									$field_title = $field_list[$field];
									$field_data  = $task_type[$value]['title'];
								}elseif($field=='browser'){
									$field_title = $field_list[$field];
									$field_data  = $browser[$value];
								}elseif($field=='position'){
									$field_title = $field_list[$field];
									$field_data  = $position[$value];
								}elseif($field=='note'){
									$field_title = $field_list[$field];
									$field_data  = null;
								}elseif($field=='urgent'){
									$field_title = $field_list[$field];
									$field_data  = $urgent[$value];
									$log_arr[]	= array('time'=>time(),'event'=>'urgent','urgent'=>$value,'opid'=>$login_user['uid']);
									$update['log'] = json_encode($log_arr);
								}elseif(in_array($field, array('submiter_uid','acceptor_uid','worker_uid','tester_uid'))){
									$field_title = $field_list[$field];
									$field_data  = isset($users[$value])?$users[$value]:$value;
								}else{
									$field_title = $field;
									$field_data  = $value;
								}
								$haha_db->edit_task($task_id, $update);
								$msg_content = haha_lang('msg_edit',$login_user['nickname'],$task_id,$field_title,$field_data);
								if($task['worker_uid'] && $task['worker_uid'] != $login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
									$follow_uids[]=$task['worker_uid'];
								}
								if($task['acceptor_uid'] && $task['acceptor_uid'] != $login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
									$follow_uids[]=$task['acceptor_uid'];
								}
								if($task['submiter_uid'] && $task['submiter_uid'] != $login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
									$follow_uids[]=$task['submiter_uid'];
								}
							}
						}else{
							if($task['submiter_uid'] != $login_user['uid']){
								$haha_db->add_msg($task['submiter_uid'],haha_lang('tip_modify'),0, $msg_url, $msg_target);
							}
						}
						$back = array('status'=>1,'msg'=>haha_lang('tip_success'));
					}else{
						$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
					}
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_param'));
				}
			}elseif($tab=='remark'){
				$field = v_post('field','');
				$value = v_post('value','');
				if($task_id && $field=='remark'){
					if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'], $task['worker_uid'], $task['acceptor_uid'])))) {
						$msg_content = $login_user['nickname'].haha_lang('msg_remark',$task_id);
						$haha_db->add_remark($task_id, $login_user['uid'], $value);
						if($task['worker_uid'] && $task['worker_uid']!=$login_user['uid'] && !in_array($task['worker_uid'], $follow_uids)){
							$follow_uids[]=$task['worker_uid'];
						}
						if($task['acceptor_uid'] && $task['acceptor_uid']!=$login_user['uid'] && !in_array($task['acceptor_uid'], $follow_uids)){
							$follow_uids[]=$task['acceptor_uid'];
						}
						if($task['submiter_uid'] && $task['submiter_uid']!=$login_user['uid'] && !in_array($task['submiter_uid'], $follow_uids)){
							$follow_uids[]=$task['submiter_uid'];
						}
						$back = array('status'=>1,'msg'=>haha_lang('tip_success'));
					}else{
						$back = array('status'=>0,'msg'=>haha_lang('tip_power'));
					}
				}else{
					$back = array('status'=>0,'msg'=>haha_lang('tip_param'));
				}
			}elseif($tab=='follow'){
				$follow_status = v_post('follow_status',0);
				$follow_uids = $haha_db->follow_work($task_id, $login_user['uid'], $follow_status);
				$msg_content = $login_user['nickname'].haha_lang('msg_followed').'[#'.$task_id.']';
				$follow_uids[]= $task['submiter_uid'];
				$follow_uids[]= $task['acceptor_uid'];
				$follow_uids[]= $task['worker_uid'];
				$follow_uids[]= $task['tester_uid'];
				if($follow_uids){
					$follow_uids = array_diff($follow_uids, array($login_user['uid']));
				}
				$back = array('status'=>1,'msg'=>'ok');
			}
			if($follow_uids && $msg_content){
				$follow_uids = array_unique($follow_uids);
				foreach($follow_uids as $msg_to_uid){
					$haha_db->add_msg($msg_to_uid, $msg_content, 0, $msg_url, $msg_target);
				}
			}
		}
		if(empty($back)) {
			$back = array('status'=>0,'msg'=>haha_lang('tip_param'));
		}
		echo json_encode($back); exit;
	}

	if($tab=='logout'){
		setcookie('haha_login_token', NULL);
		session_unset();
		session_destroy();
		to_url('?tab=login');
	}

	if(!$tab || $tab=='login'){
		haha_html('login');
		exit;
	}

	if(!$login_user){
		to_url('?tab=login');
		exit;
	}

	if(in_array($tab,array('add','view','edit'))){
		$task = [];
		if($task_id){
			$task = $haha_db->get_task($task_id);
			$follow_uids = $haha_db->get_follow_uids($task_id);
		}
	}elseif(in_array($tab,array('my','wait','list','test','end','cancel','find'))){
		$select_field = 'id,title,status,submiter_uid,acceptor_uid,worker_uid,tester_uid,type,urgent,mtime';
		$where_arr=[];
		$order_arr =[];
		$limit = null;
		if($tab=='my'){
			$where_arr = " (`status`=7 AND worker_uid = {$login_user['uid']}) OR (`status`=1 AND submiter_uid={$login_user['uid']}) OR (`status`=4 AND acceptor_uid={$login_user['uid']})";
			$order_arr = ['urgent'=>'DESC','mtime'=>'DESC'];
		}elseif($tab=='wait'){
			$where_arr = ['status'=>[1]];
			$order_arr = ['urgent'=>'DESC','mtime'=>'DESC'];
		}elseif($tab=='list'){
			$where_arr = ['status'=>[7]];
			$order_arr = ['mtime'=>'desc'];
		}elseif($tab=='test'){
			$where_arr = ['status'=>[4]];
			$order_arr = ['urgent'=>'DESC','mtime'=>'DESC'];
		}elseif($tab=='end'){
			$where_arr = ['status'=>[9]];
			$limit = '50';
			$order_arr = ['mtime'=>'desc'];
		}elseif($tab=='cancel'){
			$where_arr = ['status'=>[11]];
			$limit = '50';
			$order_arr = ['mtime'=>'desc'];
		}elseif($tab=='find'){
			$q = v_get('q','');
			if($q){
				$where_arr = ['note%%'=>$q];
				$limit = '50';
				$order_arr = ['ctime'=>'desc'];
			}else{
				$where_arr = ['id <'=>0];
			}
		}
		$list_task = $haha_db->list_task($where_arr, $order_arr, $select_field, $limit);
	}
	$my_tab = $tab;
	haha_html('common_head');
?>
	<?php if(in_array($my_tab,array('add','edit','view'))):?>
		<?php
			$remark_list	= $haha_db->list_remark($task_id);
			$log_list		= $haha_db->list_log($task_id);
			$follow_list	= $haha_db->list_follow($task_id);
		?>

		<script src="?haha_res=nicEdit.js&ver=<?=HAHA_TASK_VER?>"></script>
		<script>
			bkLib.onDomLoaded(function() {
				new nicEditor({
					buttonList : ['fontFamily','fontFormat','forecolor','bgcolor','bold','italic','underline','strikethrough','strikeThrough','subscript','superscript','indent','outdent','ol','ul','left','center','right','justify','upload','link','unlink','removeformat','hr','xhtml'],
					iconsPath : '?haha_res=nicEditorIcons.gif',
					uploadURI : '<?=pathinfo(__FILE__, PATHINFO_BASENAME)?>'
				}).panelInstance('note')<?php if(in_array($my_tab,['edit','view'])):?>.panelInstance('remark')<?php endif;?>;
			});
		</script>

		<div class="main_top clearfix">
			<h3>
				<?php if($my_tab=='add'):?>
					<?=haha_lang('add_title')?>
				<?php else:?>
					#<?=$task['id']?> <!--处理<?=@$task_type[$task['type']]['title']?> --> <?=$task_status[$task['status']]['title']?>
					<span class="main_top_follow">
					<?php if(@$follow_uids && in_array($login_user['uid'], $follow_uids)):?>

						<i class='fa fa-eye-slash' title='<?=haha_lang('func_unfollow')?>' onclick='follow(<?=$task['id']?>,0)'> <?=haha_lang('func_unfollow')?></i>
					<?php elseif($login_user['uid']!=$task['submiter_uid'] && $login_user['uid']!=$task['acceptor_uid'] && $login_user['uid']!=$task['worker_uid']):?>
						<i class='fa fa-eye' title='<?=haha_lang('func_follow')?>' onclick='follow(<?=$task['id']?>,1)'> <?=haha_lang('func_follow')?></i>
					<?php endif;?>
					</span>
				<?php endif;?>
			</h3>
			<div class="func_bar">
				<?php if($my_tab!='add'):?>
					<?php if($task['status']==1):?>
						<!--/待处理-->
						<?php if(isset($users[@$login_user['uid']])):?>
							<input type="button" value="<?=haha_lang('func_get')?>" tab='get' class="btn_get">
						<?php endif;?>
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_cancel')?>" tab="cancel" class="btn_cancel">
						<?php endif;?>
					<?php elseif($task['status']==4):?>
						<!--/待测试-->
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['acceptor_uid'], $task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_end')?>" tab='end' class="btn_end">
							<input type="button" value="<?=haha_lang('func_no')?>" tab='no' class="btn_no">
						<?php endif;?>
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_cancel')?>" tab="cancel" class="btn_cancel">
						<?php endif;?>
					<?php elseif($task['status']==7):?>
						<!--/处理中-->
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['acceptor_uid'], $task['submiter_uid'], $task['worker_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_ok')?>" tab='ok' class="btn_ok">
						<?php endif;?>
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_cancel')?>" tab="cancel" class="btn_cancel">
						<?php endif;?>
					<?php elseif($task['status']==9):?>
						<!--/已完成-->
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['acceptor_uid'], $task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_open')?>" tab='open' class="btn_open">
						<?php endif;?>
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_del')?>" tab='del' class="btn_del">
						<?php endif;?>
					<?php elseif($task['status']==11):?>
						<!--/已取消-->
						<input type="button" value="<?=haha_lang('func_open')?>" tab='open' class="btn_open">
						<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid'])))):?>
							<input type="button" value="<?=haha_lang('func_del')?>" tab='del' class="btn_del">
						<?php endif;?>
					<?php endif;?>
						<!--<input type="button" value="<?=haha_lang('func_split')?>" tab='split' class="btn_split">-->
				<?php endif;?>
			</div>
		</div>

		<div class="main_left">
			<form id="task_edit" class="task_edit clearfix edit_flat_bg<?=($task&&in_array($task['status'],array(9,11))&& $task['urgent']==3)?4:intval(@$task['urgent'])?>" method="post">
				<dl>
					<dt><?=$field_list['type']?>：</dt>
					<dd>
						<?php if($my_tab=='add'):?>
							<?php $def_task_type = 0; ?>
							<?php foreach($task_type as $key => $r):?>
								<label for="form_task_type_<?=$key?>"><input type="radio" name="type" value="<?=$key?>" id="form_task_type_<?=$key?>" <?=$key==$def_task_type?' checked':''?>> <?=$r['title']?></label>
							<?php endforeach;?>
						<?php else:?>
							<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
								<span class="edit_item"><?=@$task_type[$task['type']]['title']?> <i class="icon haha-task icon-edit"></i></span>
								<span class="edit_box" key-type="radio" key-name="type" task_id="<?=$task['id']?>">
									<?php $def_task_type = $task['type']; ?>
									<?php foreach($task_type as $key => $r):?>
										<label for="form_task_type_<?=$key?>"><input type="radio" name="type" value="<?=$key?>" id="form_task_type_<?=$key?>" <?=$key==$def_task_type?' checked':''?>> <?=$r['title']?></label>
									<?php endforeach;?>
									<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
								</span>
							<?php else:?>
								<?=@$task_type[$task['type']]['title']?>
							<?php endif;?>
						<?php endif;?>
					</dd>
				</dl>
				<dl>
					<dt><?=$field_list['position']?>：</dt>
					<dd>
						<?php if($my_tab=='add'):?>
							<?php $def_position = 0; ?>
							<?php foreach($position as $key => $val):?>
							<label for="form_position_<?=$key?>"><input type="radio" name="position" value="<?=$key?>" id="form_position_<?=$key?>" <?=$key==$def_position?' checked':''?>> <?=$val?></label>
							<?php endforeach;?>
						<?php else:?>
							<?php if(in_array($login_user['uid'],array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
								<span class="edit_item"><?=@$position[$task['position']]?> <i class="icon haha-task icon-edit"></i></span>
								<span class="edit_box" key-type="radio" key-name="position" task_id="<?=$task['id']?>">
									<?php $def_position = $task['position']; ?>
									<?php foreach($position as $key => $val):?>
									<label for="form_position_<?=$key?>"><input type="radio" name="position" value="<?=$key?>" id="form_position_<?=$key?>" <?=$key==$def_position?' checked':''?>> <?=$val?></label>
									<?php endforeach;?>
									<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
								</span>
							<?php else:?>
								<?=@$position[$task['position']]?>
							<?php endif;?>
						<?php endif;?>
					</dd>
				</dl>

				<dl>
					<dt><?=$field_list['urgent']?>：</dt>
					<dd>
						<?php if($my_tab=='add'):?>
							<?php $def_urgent = 0; ?>
							<?php foreach($urgent as $key => $val):?>
								<label for="urgent_<?=$key?>"><input type="radio" name="urgent" id="urgent_<?=$key?>" value="<?=$key?>" <?=$key==$def_urgent?' checked':''?>> <?=$val?></label>
							<?php endforeach;?>
						<?php else:?>
							<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
								<span class="edit_item" key_val="<?=$task['urgent']?>"><?=@$urgent[$task['urgent']]?> <i class="icon haha-task icon-edit"></i></span>
								<span class="edit_box" key-type="radio" key-name="urgent" task_id="<?=$task['id']?>">
									<?php $def_urgent = $task['urgent']; ?>
									<?php foreach($urgent as $key => $val):?>
										<label for="urgent_<?=$key?>"><input type="radio" name="urgent" id="urgent_<?=$key?>" value="<?=$key?>" <?=$key==$def_urgent?' checked':''?>> <?=$val?></label>
									<?php endforeach;?>
									<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
								</span>
							<?php else:?>
								<?=@$urgent[$task['urgent']]?>
							<?php endif;?>
						<?php endif;?>
					</dd>
				</dl>

				<dl>
					<dt><?=$field_list['browser']?>：</dt>
					<dd>
						<?php if($my_tab=='add'):?>
							<?php $def_browser = 0; ?>
							<?php foreach($browser as $key => $val):?>
								<label for="browser_<?=$key?>"><input type="radio" name="browser" id="browser_<?=$key?>" value="<?=$key?>" <?=$key==$def_browser?' checked':''?>> <?=$val?></label>
							<?php endforeach;?>
						<?php else:?>
							<?php if(in_array($login_user['uid'],array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
								<span class="edit_item"><?=@$browser[$task['browser']]?> <i class="icon haha-task icon-edit"></i></span>
								<span class="edit_box" key-type="radio" key-name="browser" task_id="<?=$task['id']?>">
									<?php $def_browser = $task['browser']; ?>
									<?php foreach($browser as $key => $val):?>
										<label for="browser_<?=$key?>"><input type="radio" name="browser" id="browser_<?=$key?>" value="<?=$key?>" <?=$key==$def_browser?' checked':''?>> <?=$val?></label>
									<?php endforeach;?>
									<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
								</span>
							<?php else:?>
								<?=@$browser[$task['browser']]?>
							<?php endif;?>
						<?php endif;?>
					</dd>
				</dl>
				<hr style="height: 1px; border: none; background: #999;">
				<dl>
					<span class="edit_item2_left">
						<dt><i class="icon haha-task icon-user-plus"></i> <?=$field_list['submiter_uid']?>：</dt>
						<dd>
							<?php if($my_tab=='add'):?>
								<span class="edit_item_name"><?=$login_user['nickname']?></span>
							<?php else:?>
								<span class="edit_item_name"><?=isset($users[$task['submiter_uid']])?$users[$task['submiter_uid']]:$task['submiter_uid']?></span>
							<?php endif;?>
						</dd>
					</span>
					<span class="edit_item2_center">
						<dt><i class="icon haha-task icon-worker"></i> <?=haha_lang('label_worker')?></dt>
						<dd>
							<?php if($my_tab=='add'):?>
								<select name="worker_uid">
									<option value="0"><?=haha_lang('to_free')?></option>
									<?php foreach($work_users as $uid => $nickname):?>
									<option value="<?=$uid?>"><?=$nickname?></option>
									<?php endforeach;?>
								</select>
							<?php else:?>
								<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
									<span class="edit_item"><span class="edit_item_name"><?=$task['worker_uid']?@$users[$task['worker_uid']]:haha_lang('to_empty')?></span> <i class="icon haha-task icon-edit"></i></span>
									<span class="edit_box" key-type="select" key-name="worker_uid" task_id="<?=$task['id']?>">
										<select name="worker_uid">
											<option value="0"><?=haha_lang('to_free')?></option>
											<?php foreach($work_users as $uid => $nickname):?>
											<option value="<?=$uid?>"><?=$nickname?></option>
											<?php endforeach;?>
										</select>
										<i class="icon haha-task  icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task  icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
									</span>
								<?php else:?>
									<span class="edit_item_name"><?=$task['worker_uid']?@$users[$task['worker_uid']]:haha_lang('to_empty')?></span>
								<?php endif;?>
							<?php endif;?>
						</dd>
					</span>
					<span class="edit_item2_right">
						<dt><i class="icon haha-task icon-xingzhuang"></i> <?=$field_list['acceptor_uid']?>：</dt>
						<dd>
							<?php if($my_tab=='add'):?>
								<select name="acceptor_uid">
								<?=make_select_option_str($users,@$login_user['uid'])?>
								</select>
							<?php else:?>
								<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
									<span class="edit_item"><span class="edit_item_name"><?=$task['acceptor_uid']?@$users[$task['acceptor_uid']]:haha_lang('to_empty')?></span> <i class="icon haha-task icon-edit"></i></span>
									<span class="edit_box" key-type="select" key-name="acceptor_uid" task_id="<?=$task['id']?>">
										<select name="acceptor_uid">
										<?=make_select_option_str($users,@$login_user['uid'])?>
										</select>
										<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>"></i> <i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>"></i>
									</span>
								<?php else:?>
									<span class="edit_item_name"><?=$task['acceptor_uid']?@$users[$task['acceptor_uid']]:haha_lang('to_empty')?></span>
								<?php endif;?>
							<?php endif;?>
						</dd>
					</span>
				</dl>
				<dl>
					<dt style="padding-top:5px; line-height:25px;"><?=haha_lang('label_note')?></dt>
					<dd style="padding-top:5px; vertical-align:text-top;">
						<?php if($my_tab=='add'):?>
							<textarea name="note" id="note"><?=$task?stripslashes($task['note']):''?></textarea>
						<?php else:?>
							<?php if(in_array($login_user['uid'], array_merge($manager_uids,array($task['submiter_uid']))) && in_array($task['status'],array(1,4,7))):?>
								<span class="edit_item clearfix" style="vertical-align:text-top;">
									<div class="note_view" style="float:left;"><?=@stripslashes($task['note'])?></div>
									<i class="icon haha-task icon-edit" title="<?=haha_lang('btn_edit')?>" style="float:left; margin-top:3px; margin-left:5px;"></i>
								</span>
								<span class="edit_box" key-type="textarea" key-name="note" task_id="<?=$task['id']?>">
									<textarea name="note" id="note" style="float:left;"><?=@stripslashes($task['note'])?></textarea>
									<i class="icon haha-task icon-Finished" title="<?=haha_lang('btn_edit')?>" style=""></i>
									<i class="icon haha-task icon-remove" title="<?=haha_lang('btn_cancel')?>" style=""></i>
								</span>
							<?php else:?>
								<div class="note_view"><?=stripslashes($task['note'])?></div>
							<?php endif;?>
						<?php endif;?>
					</dd>
				</dl>
				<?php if($task):?>
					<?php if(in_array($task['status'],array(9,11)) && empty($remark_list)):?>
					<?php else:?>
						<dl>
							<dt><?=haha_lang('remark_list')?></dt>
							<dd style="vertical-align:text-top;">
								<span style="vertical-align:text-top;">
									<div class="remark_view" style="float:left;">
									<?php
										if($remark_list){
											//if(strpos(substr($task['remark'],0,11),'\\')){
											//	$task['remark'] = stripslashes($task['remark']);
											//}
											//$remark_list = json_decode($task['remark'],true);
											foreach($remark_list as $mark){
												$color_class = $mark['sender_uid']==$login_user['uid']?'my_remark':'';
												echo "<li class='{$color_class}'><strong>".(isset($users[$mark['sender_uid']])?$users[$mark['sender_uid']]:$mark['sender_uid']).'</strong> ';
												echo '<em>('.date('Y-m-d H:i',$mark['ctime']).")</em><br>\n";
												echo '<span>'.stripslashes($mark['content'])."</span></li>";
											}
										}
									?>
									</div>
								</span>
							</dd>
						</dl>
					<?php endif; ?>
					<?php if(in_array($task['status'],array(1,4,7))):?>
						<dl>
							<dt><?=haha_lang('remark_title')?></dt>
							<dd style="vertical-align:text-top;">
								<span key-type="textarea" key-name="remark" task_id="<?=$task['id']?>">
									<textarea name="remark" id="remark" style=""></textarea><br>
									<input type="button" class="btn_remark" value="<?=haha_lang('btn_add')?>" style="background:#999;">
									<!--<i class="fa fa-reply fa-lg fa-flip-vertical btn_remark" title="<?=haha_lang('btn_add')?>" style="margin-top:10px; margin-left:5px;"></i>-->
								</span>
							</dd>
						</dl>
					<?php endif;?>
				<?php endif;?>
				<dl>
					<dt>　</dt>
					<dd>
						<?php if(@$task):?>
							<input type="hidden" name="task_id" value="<?=@$task['id']?>">
						<?php endif;?>
						<?php if($my_tab=='add'):?>
							<input type="hidden" name="tab" value="add">
							<input type="button" value="<?=haha_lang('btn_add')?>" tab='add' class="btn_add">
						<?php endif;?>
					</dd>
				</dl>
			</form>
		</div><!--/.main_left-->

		<?php if($my_tab!='add' && $log_list):?>
			<?php //$logs = json_decode(stripslashes($task['log']),true);?>
			<div class="time_axis">
				<h3><?=haha_lang('time_axis')?></h3>
				<ul>
					<?php foreach($log_list as $log):?>
						<li class="log_<?=$log['event']?>">
							<span><?=date('Y-m-d H:i',$log['ctime'])?></span> 
							<strong><?=$task_op_type[$log['event']]?></strong> 
							<?php if(in_array($log['event'],array('to','assign'))):?>
								<?=$log['value']&&isset($users[$log['value']])?'&gt;<u class="log_name">'.$users[$log['value']].'</u>':''?>
							<?php elseif($log['event']=='urgent'):?>
								<?=$log['value']?'&gt;<u class="log_name">'.$urgent[$log['value']].'</u>':''?>
							<?php else:?>
							<?php endif;?>
							
							<span>(<span class="log_name"><?=isset($users[$log['op_uid']])?$users[$log['op_uid']]:$log['op_uid']?></span>)</span> 
						</li>
					<?php endforeach;?>
				</ul>
			</div>
		<?php endif;?>

		<?php if($follow_list):?>
			<div class="follow">
			<strong><?=haha_lang('follower')?></strong><span>
			<?php foreach($follow_list as $follow_index => $r):?>
				<?=$follow_index!=0?', ':''?><?=isset($users[$r['follow_uid']])?$users[$r['follow_uid']]:$r['follow_uid']?>
			<?php endforeach;?>
			</span></div>
		<?php endif;?>

	<?php elseif($my_tab=='msg'):?>
		<style>
			.msg_list ul{margin-top:10px; margin-left:10px; color:#006485;}
			.msg_list li{padding:5px 0;}
			.msg_list button{margin-top:10px; padding:2px 15px;}
		</style>
		<div class="msg_list">
			<h3><?=haha_lang('msg_title')?></h3>
			<?php 
				$msgs = $haha_db->list_msg($login_user['uid']);
			?>
			<ul>
			<?php if($msgs):?>
				<?php foreach($msgs as $r):?>
				<li><?=$r['title']?> <?=date('Y-m-d H:i',$r['ctime'])?></li>
				<?php endforeach;?>
				<button onclick="clear_msg()"><?=haha_lang('msg_btn_clear')?></button>
			<?php else:?>
				<div><?=$haha_lang('msg_tip_empty')?></div>
			<?php endif;?>
			</ul>
		</div>
		<script>
			function clear_msg(){
				$.post('',{tab:'clear_msg'},function(res){
					if(res.status){
						location.reload(true);
					}else{
						alert(res.msg);
					}
				},'json');
			}
		</script>
	<?php elseif($my_tab=='config'):?>
		<style>
			*{margin:0;padding:0;}
			body{font: 12px/1.5 Tahoma,Helvetica,Arial,'Microsoft YaHei',sans-serif;}
			.clearfix:after{display:block; height:0; visibility:hidden; content:"."; clear:both;}
			.clearfix{*+height:1%;}
			.tab_box{width:800px; border:1px solid #ccc;}
			.tab_box .menu{background: #eee;}
			.tab_box .menu .item{float:left; padding:2px 15px; width:auto; font-size:13px; line-height:2;cursor:pointer;}
			.tab_box .menu .item.on{border-top:2px solid #00f; color:#00f; background: #fff; cursor:default;}
			.tab_box .content{}
			.tab_box .content .item{display:none; padding:10px 10px 30px;}
			.tab_box .content .item.on{display:block;}
			.tab_box .content .item p{text-indent:24px;}
			.config{}
			.config .left {float:left; width:400px;}
			.config .right{float:left; width:200px;}
			fieldset{margin-top:15px; padding:10px;}
			input[type="password"]{padding:2px 5px; width:100px; height:20px;}
			input[type="button"]{padding:2px 15px;}
			legend{font-weight:bold; font-size:14px; line-height:20px;}
			label{margin-right:10px;}
		</style>

		<fieldset>
			<legend><?=haha_lang('pswd_title')?></legend>
			<?=haha_lang('pswd_new')?><input type="password" id="password" name="password">
			<?=haha_lang('pswd_new2')?><input type="password" id="password2" name="password2">
			<input type="button" value="<?=haha_lang('pswd_submit')?>" onclick="modify_passord()">
		</fieldset>

		<fieldset>
			<legend><?=haha_lang('view_type')?></legend>
			<?php foreach($ui_type as $uik=>$uiv):?>
				<input type="radio" name="ui_type" value="<?=$uik?>" id="ui_type_<?=$uik?>" <?php if($ui==$uik):?>checked<?php endif;?> onclick="choose_ui('<?=$uik?>')"> <label for="ui_type_<?=$uik?>"><?=$uiv?></label>
			<?php endforeach;?>
		</fieldset>

		<script>
			$(".tab_box .menu .item").click(function(){
				if(!$(this).hasClass('on')){
					var index = $(this).parent().children().index(this);
					$(this).siblings().removeClass('on');
					$(this).addClass('on');
					$(".tab_box .content .item").removeClass('on');
					$(".tab_box .content .item").eq(index).addClass('on');
				}
			});
			function cookie(key, value, days) {
				if(typeof(value)=='undefined'){
					var name = key + '=';
					var ca = document.cookie.split(';');
					for(var i=0; i<ca.length; i++) {
						var c = ca[i].trim();
						if (c.indexOf(name)==0) { return c.substring(name.length,c.length); }
					}
					return '';
				}else{
					var d= new Date();
					d.setTime(d.getTime()+(days*24*60*60*1000));
					var expires = d.toGMTString();
					document.cookie = key+'='+value+';expires='+expires+';SameSite=Lax;Secure=false';
				}
			}
			function choose_ui(uik){
				cookie('ui_type',uik);
			}
			function modify_passord(){
				let password	= $("#password").val();
				let password2	= $("#password2").val();
				if(password==''){
					alert("<?=haha_lang('pswd_tip1')?>"); return false;
				}
				if(password!=password2){
					alert("<?=haha_lang('pswd_tip2')?>"); return false;
				}
				$.post('',{tab:'modify_pswd',password:password},function(res){
					alert(res.msg);
				},'json');
			}
		</script>
	<?php elseif($my_tab=='users'):?>
		<?php
			$users = $haha_db->list_user(0,[],'uid desc');
		?>
		<div class="clearfix">
			<h3 style="float:left;"><?=haha_lang('users_title')?></h3>
			<div style="float:right; margin-right:10px;"><a href="javascript:;" onclick="popup('?tab=edit_user')" ><i class="icon haha-task icon-user-plus"></i> <?=haha_lang('btn_user_add')?></a></div>
		</div>
		<table class="list_table" style="margin-top:10px;">
			<thead><tr><th><?=haha_lang('th_uid')?></th><th><?=haha_lang('th_user_nickname')?></th><th><?=haha_lang('th_user_account')?></th><th><?=haha_lang('th_user_role')?></th><th><?=haha_lang('th_user_status')?></th><th><?=haha_lang('th_user_ctime')?></th><th><?=haha_lang('th_user_func')?></th></tr></thead>
			<tbody>
			<?php foreach($users as $r):?>
				<tr>
					<td><?=$r['uid']?></td>
					<td><?=$r['nickname']?></td>
					<td><?=$r['account']?></td>
					<td style="text-align:center;"><?=isset($task_role[$r['role']])?$task_role[$r['role']]:$r['role']?></td>
					<td style="text-align:center;"><?=$r['status']?></td>
					<td style="text-align:center;"><?=date('Y-m-d H:i',$r['ctime'])?></td>
					<td style="text-align:center;">
						<a href="javascript:;" onclick="popup('?tab=edit_user&uid=<?=$r['uid']?>')" ><i class="icon haha-task icon-edit"></i> <?=haha_lang('btn_user_edit')?></a>
						<a href="javascript:;" onclick="user_del(<?=$r['uid']?>,'<?=$r['nickname']?>')" ><i class="icon haha-task icon-delete"></i> <?=haha_lang('btn_user_del')?></a>
					</td>
				</tr>
			<?php endforeach;?>
			</tbody>
		</table>
		<p style="margin-top:5px;"><?=haha_lang('th_user_total')?><?=count($users)?></p>
		<script>
			function user_del(uid,nickname){
				if(confirm(str_replace("<?=haha_lang('users_tip_del')?>", nickname))){
					$.post('?',{tab:'user_del',uid:uid},function(res){
						if(res.status){
							location.reload();
						}else{
							alert(res.msg);
						}
					},'json');
				}
			}
		</script>
	<?php elseif($my_tab=='search'):?>
		<div class="search">
			<form method="get" target="_parent">
			<?=haha_lang('label_search')?><input type="text" name="q" value="" style="padding-left:5px; padding-right:5px; height:25px; line-height:25px;">
			<input type="hidden" name="tab" value="find">
			<input type="submit" value="<?=haha_lang('search_submit')?>" style="padding-left:10px; padding-right:10px; height:25px; line-height:25p;">
			</form>
		</div>
	<?php elseif($my_tab=='edit_user'):?>
		<?php
			$uid = v_get('uid',0);
			$user = $uid?$haha_db->get_user($uid):[];
		?>
		<h3 style="margin-bottom:10px;"><?php if($uid):?><?=haha_lang('users_form_edit')?><?php else:?><?=haha_lang('users_form_add')?><?php endif;?></h3>
		<form id="user_edit" class="task_edit clearfix" method="post">
			<?php if($uid):?>
			<dl>
				<dt><?=haha_lang('users_form_id')?></dt>
				<dd><?=$uid?><input type="hidden" name="uid" value="<?=$uid?>"></dd>
			</dl>
			<?php endif;?>
			<dl>
				<dt><label for="account"><?=haha_lang('users_form_name')?></label></dt>
				<dd><input type="text" id="account" name="account" value="<?=val($user['account'],'')?>"></dd>
			</dl>
			<dl>
				<dt><label for="password"><?=haha_lang('users_form_pswd')?></label></dt>
				<dd><input type="password" id="password" name="password" value=""><?php if($uid):?><?=haha_lang('users_tip_pswd')?><?php endif;?></dd>
			</dl>
			<dl>
				<dt><label for="nickname"><?=haha_lang('users_form_nick')?></label></dt>
				<dd><input type="text" id="nickname" name="nickname" value="<?=val($user['nickname'],'')?>"></dd>
			</dl>
			<dl>
				<dt><label for="role"><?=haha_lang('users_form_role')?></label></dt>
				<dd><select id="role" name="role"><?=make_select_option_str($task_role, val($user['role'],''))?></select></dd>
			</dl>
			<dl>
				<dt><?=haha_lang('users_form_status')?></dt>
				<dd>
					<input type="radio" name="status" value="1" id="status1" <?php if(!isset($user['status']) or $user['status']==1):?>checked<?php endif;?>> <label for="status1"><?=haha_lang('users_status1')?></label>
					<input type="radio" name="status" value="0" id="status0" <?php if(isset($user['status']) && $user['status']==0):?>checked<?php endif;?>> <label for="status0"><?=haha_lang('users_status0')?></label>

				</dd>
			</dl>
			<dl>
				<dt>&nbsp;</dt>
				<dd>
					<input type="hidden" name="tab" value="<?=$uid?'user_edit':'user_add'?>">
					<input type="button" name="" value="<?=haha_lang('users_form_submit')?>">
				</dd>
			</dl>
		</form>
		<script>
			$("#user_edit input[type='button']").click(function(){
				var obj_form = $("#user_edit");
				var data = {};
				data.tab		= obj_form.find("input[name='tab']").val();
				data.uid	= obj_form.find("input[name='uid']").val();
				data.account	= obj_form.find("input[name='account']").val();
				data.password	= obj_form.find("input[name='password']").val();
				data.nickname	= obj_form.find("input[name='nickname']").val();
				data.role		= obj_form.find("select[name='role']").val();
				data.status		= obj_form.find("input[name='status']:checked").val();
				if(data.account=='') {
					alert("<?=haha_lang('tip_empty')?>"); return false;
				}
				if(data.tab=='user_add' && password=='') {
					alert("<?=haha_lang('tip_empty')?>"); return false;
				}
				if(data.nickname=='') {
					alert("<?=haha_lang('tip_empty')?>"); return false;
				}
				$.post('',data,function(res){
					if(res.status){
						parent.location.reload();
					}else{
						alert(res.msg);
					}
				},'json');
			});
		</script>
	<?php else:?>
		<?php
			$count_wait = $haha_db->count_task(['status'=>1]);
			$count_test = $haha_db->count_task(['status'=>4]);
			$count_msg	= $haha_db->count_msg($login_user['uid']);
			$count_msg	= $count_msg>99?99:$count_msg;
		?>
		<style>
			.desk_inner {padding-top:0;}
		</style>
		<div class="clearfix">
			<div class="top_box">
				<div class="top_logo"><?=HAHA_TITLE?></div>
				<div class="top_bar">
					<span><?=$login_user['nickname']?></span> 
					<?php if($count_msg):?><a href="javascript:;" onclick="popup('?tab=msg',1);"><i class="icon haha-task icon-notice"></i> <span class="msg_num blink"><?=$count_msg?></sapn></a><?php endif;?>
					<a href="javascript:;" onclick="popup('?tab=config',1);"><i class="icon haha-task icon-setup"></i><?=haha_lang('top_config')?></a>
					<a href="javascript:;" onclick="popup('?tab=users');"><i class="icon haha-task icon-users1"></i><?=haha_lang('top_users')?></a>
					<a href="?tab=logout"><i class="icon haha-task icon-logout"></i><?=haha_lang('top_logout')?></a>
				</div>
			</div>
			<div class="tab_bar clearfix">
				<a href="?tab=my" <?php if($my_tab=='my') echo 'class="on"';?> style="margin-right:20px;"><i class="icon haha-task icon-task2"></i> <?=haha_lang('nav_my')?></a>
				<a href="?tab=wait" <?php if($my_tab=='wait') echo 'class="on"';?>><i class="icon haha-task icon-bug"></i> <?=haha_lang('nav_wait')?><?=$count_wait?("(<font color='red'><strong>".$count_wait."</strong></font>)"):''?></a>
				<a href="?tab=list" <?php if($my_tab=='list') echo 'class="on"';?>><i class="icon haha-task icon-hourglass"></i> <?=haha_lang('nav_list')?></a>
				<a href="?tab=test" <?php if($my_tab=='test') echo 'class="on"';?>><i class="icon haha-task icon-eye2"></i> <?=haha_lang('nav_test')?><?=$count_test?("(<font color='blue'><strong>".$count_test."</strong></font>)"):''?></a>
				<a href="?tab=end" <?php if($my_tab=='end') echo 'class="on"';?>><i class="icon haha-task icon-flag"></i> <?=haha_lang('nav_end')?></a>
				<a href="?tab=cancel" <?php if($my_tab=='cancel') echo 'class="on"';?>><i class="icon haha-task icon-remove"></i> <?=haha_lang('nav_cancel')?></a>
				<?php if($my_tab=='find'):?><a href="?tab=find" class="on"><i class="icon haha-task icon-search"></i> <?=haha_lang('nav_find')?>(<?=count($list_task)?>)</a><?php endif;?>
				<a href="?tab=stats" <?php if($my_tab=='stats') echo 'class="on"';?>><i class="icon haha-task icon-stats"></i> <?=haha_lang('nav_stats')?></a>

				<a href="javascript:;" class="add_btn btn_bg7" onclick="popup('?tab=add')"><i class="icon haha-task icon-plus"> <?=haha_lang('nav_add')?></i></a>
				<a href="javascript:;" class="add_btn btn_bg7" onclick="popup('?tab=search')" style="margin-left:1px;"><i class="icon haha-task icon-search"> <?=haha_lang('nav_search')?></i></a>
			</div>
		</div>

		<?php if($my_tab=='stats'):?>

			<script src="?haha_res=sChart.js"></script>
			<?php
				$num_wait = $haha_db->count_task(['status'=>1]);
				$num_work = $haha_db->count_task(['status'=>7]);
				$num_test = $haha_db->count_task(['status'=>4]);
				$num_curr = $num_wait + $num_work + $num_test;
				$num_end = $haha_db->count_task(['status'=>9]);
				$num_cancel = $haha_db->count_task(['status'=>11]);
			?>
			<ul class="stats">
				<dl>
					<dt><?=haha_lang('stats_wait')?></dt>
					<dd><?=$num_wait?><span total='<?=$num_curr?>' count='<?=$num_wait?>'><span></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('stats_work')?></dt>
					<dd><?=$num_work?><span total='<?=$num_curr?>' count='<?=$num_work?>'><span></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('stats_test')?></dt>
					<dd><?=$num_test?><span total='<?=$num_curr?>' count='<?=$num_test?>'><span></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('stats_end')?></dt><dd><?=$num_end?></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('stats_cancel')?></dt><dd>
					<?=$num_cancel?>
					</dd>
				</dl>
			</ul>

			<?php
				$rank_worker_now	= $haha_db->count_rank('worker_uid',['status'=>7]);
				$rank_worker_end	= $haha_db->count_rank('worker_uid',['status'=>9]);
				$rank_submiter		= $haha_db->count_rank('submiter_uid',['status'=>[1,4,7,9]]);
			?>
			<ul class="stats">
				<!--
				<dl>
					<dt><?=haha_lang('label_rank_worker_now')?></dt>
					<dd><?=make_user_rank_str($rank_worker_now, $users)?></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('label_rank_worker_end')?></dt>
					<dd><?=make_user_rank_str($rank_worker_end, $users)?></dd>
				</dl>
				<dl>
					<dt><?=haha_lang('label_rank_submiter')?></dt>
					<dd><?=make_user_rank_str($rank_submiter, $users)?></dd>
				</dl>
				-->
			</ul>

			<style>.canvas-wrapper{float:left; margin-right:10px; margin-bottom:10px; width:600px; height:350px;}</style>
			<div style="clear:both;">
				<?php if($rank_worker_now):?>
				<?php $rank_worker_now_data	= make_user_rank_data($rank_worker_now, $users);?>
				<div class="canvas-wrapper"><canvas id="tu_rank_worker_now"></canvas></div>
				<script>
					new Schart('tu_rank_worker_now', {
						type: 'pie',
						title: {text: "<?=haha_lang('label_rank_worker_now')?>"},
						legend: {position: 'left'},
						bgColor: '#fbfbfb',
						labels: <?=json_encode($rank_worker_now_data['names'])?>,
						datasets: [{
							data: <?=json_encode($rank_worker_now_data['values'])?>
						}]
					});
				</script>
				<?php endif;?>
				<?php if($rank_worker_end):?>
				<?php $rank_worker_end_data = make_user_rank_data($rank_worker_end, $users);?>
				<div class="canvas-wrapper"><canvas id="tu_rank_worker_end"></canvas></div>
				<script>
					new Schart('tu_rank_worker_end', {
						type: 'pie',
						title: {text: "<?=haha_lang('label_rank_worker_end')?>"},
						legend: {position: 'left'},
						bgColor: '#fbfbfb',
						labels: <?=json_encode($rank_worker_end_data['names'])?>,
						datasets: [{
							data: <?=json_encode($rank_worker_end_data['values'])?>
						}]
					});
				</script>
				<?php endif;?>
				<?php if($rank_submiter):?>
				<?php $rank_submiter_data = make_user_rank_data($rank_submiter, $users);?>
				<div class="canvas-wrapper"><canvas id="tu_rank_submiter"></canvas></div>
				<script>
					new Schart('tu_rank_submiter', {
						type: 'pie',
						title: {text: '<?=haha_lang('label_rank_submiter')?>'},
						legend: {position: 'left'},
						bgColor: '#fbfbfb',
						labels: <?=json_encode($rank_submiter_data['names'])?>,
						datasets: [{
							data: <?=json_encode($rank_submiter_data['values'])?>
						}]
					});
				</script>
				<?php endif;?>
			</div>

		<?php elseif($my_tab=='users'):?>

		<?php else:?>
			<?php if($my_tab=='find'&&empty($list_task)):?> <?=haha_lang('not_find',v_get('q',''))?> <?php endif;?>
			<?php if($ui=='sticky'):?>
			<ul class="list_sticky">
				<?php foreach($list_task as $task):?>
					<?php
						if($task['status']==1){
							$task_ico = $task_type[$task['type']]['ico'];
						}else{
							$task_ico = @$task_status[$task['status']]['ico'];
						}
					?>
					<?php if(@$task_id):?>
						<script>popup('?tab=view&task_id=<?=$task_id?>');</script>
					<?php endif;?>
					<li class="item task_sticky_bg<?=(isset($task)&&in_array($task['status'],array(9,11))&& $task['urgent']==3)?4:intval(@$task['urgent'])?>" onclick="popup('?tab=view&task_id=<?=$task['id']?>')"><!--bug_status_<?=$task_status[$task['status']]['class']?>-->
						<div class="bug_top" style="margin-top:25px;padding:0 25px 0 15px;">
							<div class="bug_status">
								<i class="icon haha-task icon-<?=@$task_ico?> "><!--<?=$task['status']==7?'fa-spin':''?>--></i> #<?=$task['id']?> <!--<font color="#666"><?=$task_status[$task['status']]['title']?></font>--> <!-- <?=$task_type[$task['type']]['title']?>-->
							</div>
							<div class="bug_user_name">
								<?php if(in_array($task['status'],array(7))):?>
									<i class="icon haha-task icon-worker"></i> <?=isset($users[$task['worker_uid']])?$users[$task['worker_uid']]:'-'?>
								<?php elseif($task['status']==4):?>
									<i class="icon haha-task icon-xingzhuang"></i> <?=isset($users[$task['acceptor_uid']])?$users[$task['acceptor_uid']]:'-'?>
								<?php elseif(in_array($task['status'],array(1,9,11))):?>
									<i class="icon haha-task icon-user-plus"></i> <?=isset($users[$task['submiter_uid']])?$users[$task['submiter_uid']]:'-'?>
								<?php endif;?>
							</div>
						</div><!--/.bug_top-->
						<div class="bug_buttom urgent_1<?=(isset($task)&&in_array($task['status'],array(9,11))&& $task['urgent']==3)?4:intval(@$task['urgent'])?>" style="padding:0 25px 0 15px;height:155px;">
							<?=str_limit(strip_tags($task['title']),110)?>
						</div>
					</li>
				<?php endforeach;?>
			</ul>
			<?php elseif($ui=='flat'):?>
			<?php if(1):?>
			<ul class="list_flat">
				<?php foreach($list_task as $task):?>
					<?php
						if($task['status']==1){
							$task_ico = $task_type[$task['type']]['ico'];
						}else{
							$task_ico = @$task_status[$task['status']]['ico'];
						}
					?>
					<?php if(@$task_id):?>
						<script>popup('?tab=view&task_id=<?=$task_id?>');</script>
					<?php endif;?>
					<li class="item bug_status_<?=$task_status[$task['status']]['class']?>" onclick="popup('?tab=view&task_id=<?=$task['id']?>')">
						<div class="bug_top">
							<div class="bug_status">
								<i class="icon haha-task icon-<?=@$task_ico?> "><!--<?=$task['status']==7?'fa-spin':''?>--></i> #<?=$task['id']?> <font color="#666"><?=$task_status[$task['status']]['title']?></font> <!-- <?=$task_type[$task['type']]['title']?>-->
							</div>
							<div class="bug_user_name">
								<?php if(in_array($task['status'],array(7))):?>
									<i class="icon haha-task icon-worker"></i> <?=$task['worker_uid']?(isset($users[$task['worker_uid']])?$users[$task['worker_uid']]:$task['worker_uid']):'-'?>
								<?php elseif($task['status']==4):?>
									<i class="icon haha-task icon-xingzhuang"></i> <?=$task['acceptor_uid']?(isset($users[$task['acceptor_uid']])?$users[$task['acceptor_uid']]:$task['acceptor_uid']):'-'?>
								<?php elseif(in_array($task['status'],array(1,9,11))):?>
									<i class="icon haha-task icon-user-plus"></i> <?=$task['submiter_uid']?(isset($users[$task['submiter_uid']])?$users[$task['submiter_uid']]:$task['submiter_uid']):''?>
								<?php endif;?>
							</div>
						</div><!--/.bug_top-->
						<div class="bug_buttom task_flat_bg<?=(isset($task)&&in_array($task['status'],array(9,11))&& $task['urgent']==3)?4:intval(@$task['urgent'])?>">
							<?=str_limit(strip_tags($task['title']),75)?>
						</div>
					</li>
				<?php endforeach;?>
			</ul>
			<?php endif;?>
			<?php elseif($ui=='table'):?>
			<?php if($list_task):?>
			<table class="list_table">
				<thead><tr><th><?=haha_lang('th_id')?></th><th><?=haha_lang('th_note')?></th><th><?=haha_lang('th_urgent')?></th><th><?=haha_lang('th_status')?></th><th><?=haha_lang('th_submiter')?></th><th><?=haha_lang('th_worker')?></th><th><?=haha_lang('th_acceptor')?></th><th><?=haha_lang('th_mtime')?></th></tr></thead><tbody>
				<?php  foreach($list_task as $task):?>
					<tr onclick="popup('?tab=view&task_id=<?=$task['id']?>')">
						<td>#<?=$task['id']?></td>
						<td><?=str_limit(strip_tags($task['title']),35)?></td>
						<td align="center"><span class="font_urgent_<?=$task['urgent']?>"><?=@$urgent[$task['urgent']]?></span></td>
						<td align="center"><i class="icon haha-task icon-<?=$task_status[$task['status']]['ico']?>"></i> <?=$task_status[$task['status']]['title']?></td>
						<td align="center"><?=isset($users[$task['submiter_uid']])?$users[$task['submiter_uid']]:'-'?></td>
						<td align="center"><?=isset($users[$task['worker_uid']])?$users[$task['worker_uid']]:'-'?></td>
						<td align="center"><?=isset($users[$task['acceptor_uid']])?$users[$task['acceptor_uid']]:'-'?></td>
						<td align="center"><?=date('Y-m-d H:i',$task['mtime'])?></td>
					</tr>
				<?php endforeach;?>
				</tbody>
			</table>

			<?php endif;?>
			<?php endif;?>

		<?php endif;?>

	<?php endif;?>

	</div></div>

	<?php if(in_array($tab,['add','edit','view'])):?>
	<script>
		var tip_arr = <?=json_encode($task_op_type)?>;
		$(".task_edit input[type='button']").click(function(){
			//var obj_form = $(this).closest('form');
			var obj_form = $("#task_edit");
			var task_id = obj_form.find("input[name='task_id']").val();
			var tab = $(this).attr('tab');
			if(tab=='add'){
				var type = obj_form.find("input[name='type']:checked").val();
				var position = obj_form.find("input[name='position']:checked").val();
				var browser = obj_form.find("input[name='browser']:checked").val();
				var urgent = obj_form.find("input[name='urgent']:checked").val();
				var acceptor_uid = obj_form.find("select[name='acceptor_uid']").val();
				var worker_uid = obj_form.find("select[name='worker_uid']").val();
				var tester_uid = obj_form.find("select[name='tester_uid']").val();
				var note = $("#note").siblings().find(".nicEdit-main").html();
				//console.log($("#note").siblings().find(".nicEdit-main")); return false;
				if(note=='') {
					alert("<?=haha_lang('tip_empty')?>"); return false;
				}
				if(confirm(str_replace("<?=haha_lang('confirm')?>", tip_arr[tab]))) {
					var data = {task_id:task_id,tab:tab,type:type,position:position,browser:browser,urgent:urgent,acceptor_uid:acceptor_uid,worker_uid:worker_uid,tester_uid:tester_uid,note:note};
					$.post('?',data,function(res){
						if(res.status){
							parent.location.reload();
							//location.reload();
						}else{
							alert(res.msg);
						}
					},'json');
				}
			}
		});
		$(".func_bar input[type='button']").click(function(){
			//var obj_form = $(this).closest('form');
			var obj_form = $("#task_edit");
			var task_id = obj_form.find("input[name='task_id']").val();
			var tab = $(this).attr('tab');
			if(tab!='add'){
				if(confirm(str_replace("<?=haha_lang('confirm')?>", tip_arr[tab]))){
					$.post('?',{task_id:task_id,tab:tab},function(res){
						if(res.status){
							parent.location.reload();
							//location.reload();
						}else{
							alert(res.msg);
						}
					},'json');
				}
			}
		});
	</script>
	<script>
		$(".note_view img,.remark_view img").attr('title',"<?=haha_lang('tip_img_big')?>");
		$(".note_view img,.remark_view img").css('cursor','pointer');
		$(".note_view img,.remark_view img").click(function(){
			window.open(this.src);
		});
		$(".edit_item").click(function(){
			var dd = $(this).closest('dd');
			dd.children(".edit_item").hide();
			dd.children(".edit_box").show();
		});
		$(".edit_box .icon-remove").click(function(){
			var dd = $(this).closest('dd');
			var key_name = dd.children(".edit_box").attr('key-name');
			if(key_name=='urgent'){
				for(i=0;i<4;i++){
					$("#task_edit").removeClass("task_edit_bg"+i);
				}
				var key_val = dd.children('.edit_item').attr('key_val');
				$("#task_edit").addClass("task_edit_bg"+key_val);
			}
			dd.children(".edit_box").hide();
			dd.children(".edit_item").show();
		});
		$(".edit_box .icon-Finished").click(function(){
			var item= $(this).closest('span.edit_item');
			var box = $(this).closest('span.edit_box');
			var key_type = box.attr('key-type');
			var key_name = box.attr('key-name');
			var task_id   = box.attr('task_id');
			if(key_type=='radio'){
				var key_val  = box.find("input[type="+key_type+"]:checked").val();
			}else if(key_type=='textarea'){
				var key_val  = $("#note").siblings().find(".nicEdit-main").html();
			}else{
				var key_val  = box.children(key_type).val();
			}
			//console.log(key_name);
			//console.log(key_val);
			$.post('',{tab:'edit',task_id:task_id,field:key_name,value:key_val},function(res){
				if(res.status==1){
					if(key_name=='urgent'){
						for(i=0;i<4;i++){
							$("#task_edit").removeClass("task_edit_bg"+i);
						}
						$("#task_edit").addClass("task_edit_bg"+key_val);
					}
					box.hide();
					item.show();
					window.location.href='?tab=view&task_id='+task_id;
				}else{
					alert(res.msg);
				}
			},'json');
		});
		
		$("#task_edit input[name='urgent']").change(function(){
			console.log($("input[name='urgent']:checked").val());
			console.log($(this).val());
			for(i=0;i<4;i++){
				$("#task_edit").removeClass("edit_flat_bg"+i);
			}
			$("#task_edit").addClass("edit_flat_bg"+$(this).val());
		});
		/*
		document.querySelectorAll("#task_edit input[name='urgent']").onchange = function(){
			console.log($(this).value);
			for(i=0;i<4;i++){
				$("#task_edit").removeClass("edit_flat_bg"+i);
			}
			$("#task_edit").addClass("edit_flat_bg"+$(this).value);
		};
		*/

		$(".btn_remark").click(function(){
			var obj_form = $("#task_edit");
			var task_id = obj_form.find("input[name='task_id']").val();
			var remark = $("#remark").siblings().find(".nicEdit-main").html();
			if(remark!=''){
				$.post('',{tab:'remark',task_id:task_id,field:'remark',value:remark},function(res){
					if(res.status==1){
						window.location.href='?tab=view&task_id='+task_id;
					}else{
						alert(res.msg);
					}
				},'json');
			}else{
				return false;
			}
		});
		function follow(task_id,status){
			$.post('',{tab:'follow',task_id:task_id,follow_status:status},function(res){
				if(res.status==1){
					if(status==1){
						alert("<?=haha_lang('followed')?>");
					}else{
						alert("<?=haha_lang('unfollowed')?>");
					}
					window.location.href='?tab=view&task_id='+task_id;
				}else{
					alert(res.msg);
				}
			},'json');
		}
	</script>
	<?php endif;?>
	<script>
		$(document).ready(function(){
			blink($('.font_urgent_3'));
			blink($('.msg_num'));
		});
	</script>
	<?php $haha_db->show_log();?>

</body></html>

<?php

/*------数据处理区--------- */

class sqlite_db extends SQLite3 {

	private $sql_history=[];

	function __construct($db_file) {
		$this->open($db_file);
	}

	function log_sql($sql){
		if(IS_DEV){
			$this->sql_history []= $sql;
		}
	}

	function show_log(){
		if(0 && IS_DEV){
			echo "<script>\n";
			foreach($this->sql_history as $k => $v){
				echo "console.log(\"".$v."\");\n";
			}
			echo "\n</script>\n";
		}
	}

	function exists_table($table='haha_task'){
		return $this->get_one("SELECT count(*) FROM sqlite_master WHERE type='table' AND name='{$table}'");
	}

	function init_table(){
		$tables = [
		'haha_task' => "
			CREATE TABLE `haha_task` (
				`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,  -- 编号
				`parent_id` int NOT NULL DEFAULT '0',             -- 父id,拆分包
				`title` nvarchar(255) NOT NULL,                   -- 标题
				`note` ntext NOT NULL,                            -- 说明
				`status` tinyint(1) NOT NULL DEFAULT '0',         -- 状态
				`submiter_uid` int NOT NULL DEFAULT '0',           -- 提交者
				`acceptor_uid` int NOT NULL DEFAULT '0',           -- 验收者
				`worker_uid` int NOT NULL DEFAULT '0',             -- 处理者
				`tester_uid` int NOT NULL DEFAULT '0',             -- 测试者
				`type` int NOT NULL DEFAULT '0',                  -- 类别
				`browser` tinyint(1) NOT NULL DEFAULT '0',        -- 浏览器
				`position` int NOT NULL DEFAULT '0',              -- 位置
				`urgent` tinyint(1) NOT NULL DEFAULT '0',         -- 急迫性
				`ctime` int NOT NULL DEFAULT '0',                 -- 创建时间
				`starttime` int NOT NULL DEFAULT '0',             -- 开始时间
				`endtime` int NOT NULL DEFAULT '0',               -- 结束时间
				`mtime` int NOT NULL DEFAULT '0'                  -- 变更时间
			);",
		'haha_user' => "
			CREATE TABLE `haha_user` (
				`uid` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT, -- 编号
				`account` varchar(16) NOT NULL,                   -- 账户名
				`password` CHAR(32) NOT NULL,                     -- 密码
				`nickname` nvarchar(16) NOT NULL DEFAULT '',      -- 呢称
				`role` tinyint(1) NOT NULL DEFAULT '1',           -- 角色
				`status` tinyint(1) NOT NULL DEFAULT '1',         -- 状态
				`ctime` int NOT NULL DEFAULT '0'                  -- 创建时间
			);",
		'haha_remark' => "
			CREATE TABLE haha_remark(
				`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,  -- 编号
				`task_id` int NOT NULL,                           -- 任务id
				`sender_uid` int NOT NULL,                        -- 提交者uid
				`content` ntext NOT NULL,                         -- 提交内容
				`ctime` int NOT NULL DEFAULT 0                    -- 添加时间
			);",
		'haha_log' => "
			CREATE TABLE haha_log(
				`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,  -- 编号
				`task_id` int NOT NULL,                           -- 任务id
				`event` varchar(16) NOT NULL,                     -- 事件名
				`op_uid` int NOT NULL,                            -- 操作者uid
				`value` varchar(32) NOT NULL DEFAULT '',          -- 事件数据
				`ctime` int NOT NULL DEFAULT 0                    -- 添加时间
			);",
		'haha_follow' => "
			CREATE TABLE haha_follow(
				`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,  -- 编号
				`task_id` int NOT NULL,                           -- 任务id
				`follow_uid` int NOT NULL,                        -- 关注者uid
				`ctime` int NOT NULL DEFAULT 0                    -- 添加时间
			);",
		'haha_msg' => "
			CREATE TABLE `haha_msg` (
				`id` INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,  -- 编号
				`to_uid` int NOT NULL DEFAULT '0',                -- 接收者
				`title` nvarchar(255) NOT NULL,                   -- 标题
				`content` ntext NOT NULL,                         -- 内容
				`status` tinyint(1) NOT NULL DEFAULT '0',         -- 状态
				`send_uid` int NOT NULL DEFAULT '0',              -- 发送者
				`type` int NOT NULL DEFAULT '0',                  -- 类别
				`url` nvarchar(64) NOT NULL DEFAULT '',           -- 链接
				`ctime` int NOT NULL DEFAULT '0',                 -- 生成时间
				`rtime` int NOT NULL DEFAULT '0'                  -- 阅览时间
			);",
		];
		foreach($tables as $tab){
			$this->query($tab);
		}
		$list_index = [
			'haha_task'		=> ['status','urgent','worker_uid'],
			'haha_user'		=> ['account','role'],
			'haha_remark'	=> ['task_id'],
			'haha_log'		=> ['task_id'],
			'haha_follow'	=> ['task_id'],
			'haha_msg'		=> ['to_uid'],
		];
		foreach($list_index as $table => $fields){
			foreach($fields as $field){
				$this->add_index($table, $field);
			}
		}
		$this->add_user('admin','123','admin',99);
		return true;
	}

	function get_all($sql, $id_field=null){
		$list = [];
		$this->log_sql($sql);
		$res = $this->query($sql);
		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			if($id_field && isset($row[$id_field])){
				$list[$row[$id_field]]=$row;
			}else{
				$list[]=$row;
			}
		}
		return $list;
	}

	function get_row($sql){
		$sql.=preg_match('/LIMIT/i', $sql) == false?' LIMIT 1':'';
		$this->log_sql($sql);
		$res  = $this->query($sql);
		$row = $res->fetchArray(SQLITE3_ASSOC);
		return $row;
	}

	function get_col($sql, $field=null){
		$res = $this->get_all($sql);
		$arr = [];
		if($res){
			if($field){
				if(isset($res[0][$field])) {
					$arr= array_column($res,$field);
				}
			}else{
				$arr= array_column($res,key($res[0]));
			}
		}
		return $arr;
	}

	function get_one($sql){
		$arr = $this->get_row($sql);
		return empty($arr) ? false : current($arr);
	}

	function make_where_sql($where_arr){
		$where = '';
		if($where_arr){
			if(is_array($where_arr)){
				foreach($where_arr as $k => $v) {
					$where .= $where == '' ? '' : ' AND ';
					if(is_array($v)){
						$where .= " `$k` in ('".implode("','",$v)."')";
					}else{
						$like_mode = substr_count($k,'%');
						$k = trim(str_replace('%','',$k));
						if($like_mode==0){
							$where .= preg_match('/\<|\>|\=/i', $k) == false ? " `$k` = '$v'" : " $k '$v' ";
						}elseif($like_mode==1){
							$where .= " `$k` LIKE '%{$v}'";
						}else{
							$where .= " `$k` LIKE '%{$v}%'";
						}
					}
				}
			}else{
				$where = $where_arr;
			}
		}
		return $where;
	}

	function get($table, $type='all', $where_arr=[], $order_arr=[], $id_field=null, $select_field='', $limit=null){
		$sql = "SELECT ".($type=='count'?"count(1)":($select_field?:"*"))." FROM $table ";
		$where = $this->make_where_sql($where_arr);
		$sql .= $where ? ' WHERE '.$where : '';
		if($type!='count'){
			$order = '';
			if($order_arr){
				if(is_array($order_arr)){
					foreach($order_arr as $k => $v){
						$order .= $order == '' ? '' : ',';
						$order .= " `$k` $v";
					}
				}else{
					$order = $order_arr;
				}
			}
			$sql .= $order ? ' ORDER BY '.$order : '';
		}
		$sql .= $limit ? ' LIMIT '.$limit : '';
		if($type=='row'){
			$data = $this->get_row($sql);
		}elseif($type=='col'){
			$data = $this->get_col($sql);
		}elseif($type=='one' || $type=='count'){
			$data = $this->get_one($sql);
		}else{
			$data = $this->get_all($sql, $id_field);
		}
		return $data;
	}

	function insert($table, $arr){
		$sql  = "INSERT INTO $table ";
		$keys = array_keys($arr);
		$str_fields = "";
		$str_values = "";
		foreach($keys as $key) {
			if($str_fields != "") {
				$str_fields .= ",";
				$str_values .= ",";
			}
			$str_fields .= "`$key`";
			$str_values .= "'".$this->filter($arr[$key])."'";
		}
		$sql .= "($str_fields) VALUES ($str_values);";
		$this->log_sql($sql);
		$status = $this->query($sql);
		if($status){
			return $this->last_insert_id();
		}else{
			return false;
		}
	}

	function update($table, $where_arr, $param_arr){
		$sql   = "UPDATE `$table` SET ";
		$param = '';
		foreach($param_arr as $k => $v) {
			$param .= $param == '' ? '' : ',';
			$param .= " `$k` = '$v'";
		}
		$where = '';
		foreach($where_arr as $k2 => $v2) {
			$where .= $where == '' ? '' : ' AND ';
			$where .= " `$k2` = '$v2'";
		}
		$sql .= $param . ' WHERE ' .$where;
		$this->log_sql($sql);
		return $this->query($sql);
	}

	function delete($table, $where_arr){
		$sql = "DELETE FROM $table";
		$where = '';
		foreach($where_arr as $k2 => $v2) {
			$where .= $where == '' ? '' : ' AND ';
			$where .= " $k2 = '$v2'";
		}
		$sql .= ' WHERE ' .$where;
		$this->log_sql($sql);
		return $this->query($sql);
	}

	function last_insert_id(){
		return $this->get_one('select last_insert_rowid()');
	}

	function add_index($table, $column){
		$sql = "CREATE INDEX {$table}_{$column} ON {$table}(`{$column}`);";
		$res = $this->query($sql);
		return $res;
	}

	function get_indexs($table){
		return $this->get_col("SELECT `name` FROM sqlite_master WHERE type='index' AND tbl_name='{$table}'");
	}

	function filter($param, $charset='UTF-8') {
		if(is_array($param)) {
			foreach ($param as $key => $val) {
				$param[$key] = $this->filter($val, $charset);
			}
			return $param;
		} else {
			$charset = strtolower($charset);
			if($charset == 'utf8') $charset = 'utf-8';
			if($charset == 'latin1') $charset = 'iso-8859-1';
			$len = mb_strlen($param, $charset);
			$buff = "";
			for($i=0; $i<$len; $i++) {
				$ch = mb_substr($param, $i, 1, $charset);
				if($ch == "\0") {
					$ch = "\\0";
				} else if($ch == "\\" || $ch == "'" || $ch == "\"") {
					$ch = "\\".$ch;
				} else if($ch[0] == "\0" && strlen($ch) == 2) { //宽字符
					if($ch[1] == "\0") {
						$ch = "\0\\\00";
					} else if($ch[1] == "\\" || $ch[1] == "'" || $ch[1] == "\"") {
						$ch = "\0\\".$ch;
					}
				}
				$buff .= $ch;
			}
			return $buff;
		}
	}

	//----------------------------------------

	function list_user($only_name=0, $where_arr=null, $order_arr=null){
		$list = $this->get('haha_user','all',$where_arr,$order_arr,'uid');
		return $only_name?array_column($list, 'nickname','uid'):$list;
	}

	function get_user($uid){
		return $this->get('haha_user','row',['uid'=>$uid]);
	}

	function get_user_by_account($account, $not_id=null){
		$where = ['account'=>$account];
		if($not_id){
			$where['uid !='] = $not_id;
		}
		return $this->get('haha_user','row',$where);
	}

	function get_user_by_nickname($nickname, $not_id=null){
		$where = ['nickname'=>$nickname];
		if($not_id){
			$where['uid !='] = $not_id;
		}
		return $this->get('haha_user','row',$where);
	}

	function get_uids_by_role($role_id){
		$role_ids = is_array($role_id)?$role_id:[$role_id];
		return $this->get('haha_user','col',['role'=>$role_ids]);
	}

	function add_user($account, $password, $nickname='', $role=1, $status=1){
		$nickname = $nickname?:$account;
		$data = [
			'account'	=> $account,
			'password'	=> md5($password),
			'nickname'	=> $nickname,
			'role'		=> $role,
			'status'	=> $status,
			'ctime'		=> time(),
		];
		return $this->insert('haha_user', $data);
	}
	function modify_password($uid, $password){
		return $haha_db->update('haha_user', ['uid'=>$uid], ['password'=>md5($password)]);
	}
	function list_task($where_arr=[], $order_arr=[], $select_field='', $limit=null){
		$list =  $this->get('haha_task', 'all', $where_arr, ($order_arr ?: ['mtime'=>'DESC']), 'id', $select_field, $limit);
		/*
		foreach($list as $i => $r){
			if(isset($r['note'])){
				$list[$i]['note'] = htmlspecialchars_decode($r['note']);
			}
			if(isset($r['title'])){
				$list[$i]['title'] = htmlspecialchars_decode($r['title']);
			}
		}
		*/
		return $list;
	}
	function add_task($data){
		$data = data_diff_field($data, ['parent_id','title','note','status','submiter_uid','acceptor_uid','worker_uid','tester_uid','type','browser','position','urgent','ctime','starttime','endtime','mtime']);
		if(isset($data['note'])) {
			//$data['title']	= htmlspecialchars(note2title($data['note']), ENT_QUOTES);
			$data['title']	= note2title($data['note']);
			//$data['note']	= htmlspecialchars($data['note'], ENT_QUOTES);
		}
		if(!isset($data['ctime'])) $data['ctime']=time();
		if(!isset($data['mtime'])) $data['mtime']=time();
		return $this->insert('haha_task', $data);
	}
	function edit_task($task_id,$data){
		$data = data_diff_field($data, ['parent_id','title','note','status','submiter_uid','acceptor_uid','worker_uid','tester_uid','type','browser','position','urgent','ctime','starttime','endtime','mtime']);
		if(isset($data['note'])) {
		//	$data['title']	= htmlspecialchars(note2title($data['note']), ENT_QUOTES);
			$data['title']	= note2title($data['note']);
		//	$data['note']	= htmlspecialchars($data['note'], ENT_QUOTES);
		}
		if(!isset($data['mtime'])) $data['mtime']=time();
		return $this->update('haha_task', ['id'=>$task_id], $data);
	}
	function list_remark($task_id){
		return $this->get('haha_remark', 'all', ['task_id'=>$task_id]);
	}
	function add_remark($task_id, $login_uid, $content){
		$data = [
			'task_id'		=> $task_id,
			'sender_uid'	=> $login_uid,
			'content'		=> $content,
			'ctime'			=> time(),
		];
		return $this->insert('haha_remark', $data);
	}

	function list_log($task_id){
		return $this->get('haha_log', 'all', ['task_id'=>$task_id], ['id'=>'DESC']);
	}
	function add_log($task_id, $event, $op_uid, $value=''){
		$data = [
			'task_id'	=> $task_id,
			'event'		=> $event,
			'op_uid'	=> $op_uid,
			'value'		=> $value,
			'ctime'		=> time(),
		];
		return $this->insert('haha_log', $data);
	}
	function del_log($task_id, $op_uid){
		return $this->delete('haha_log', ['task_id'=>$task_id,'op_uid'	=> $op_uid]);
	}
	function list_follow($task_id){
		return $this->get('haha_follow', 'all', ['task_id'=>$task_id], ['id'=>'DESC']);
	}
	function get_follow_uids($task_id){
		$uids =  $this->get('haha_follow', 'col', ['task_id'=>$task_id], [], null, 'follow_uid');
		return $uids;
	}
	function add_follow($task_id, $follow_uid){
		$data = ['task_id'=>$task_id,'follow_uid'=>$follow_uid,'ctime'=>time()];
		return $this->insert('haha_follow', $data);
	}
	function del_follow($task_id, $follow_uid){
		return $this->delete('haha_follow', ['task_id'=>$task_id,'follow_uid'=>$follow_uid]);
	}
	function count_task($where_arr=[]){
		return $this->get('haha_task', 'count', $where_arr);
	}

	function count_rank($group_field, $where_arr=null){
		$sql = " SELECT {$group_field},COUNT(*) AS num FROM haha_task "; 
		$where = $this->make_where_sql($where_arr);
		$sql.= $where?" WHERE ".$where:'';
		$sql.= " GROUP BY {$group_field} ORDER BY num DESC";
		$res = $this->get_all($sql);
		return array_column($res, 'num', $group_field);
	}

	function get_task($task_id){
		$task = $this->get('haha_task', 'row', ['id'=>$task_id]);
		//$task['note']	= htmlspecialchars_decode($task['note']);
		//$task['title']	= htmlspecialchars_decode($task['title']);
		return $task;
	}

	function renew_task($task_id, $data){
		return $this->update('haha_task', ['id'=>$task_id], $data);
	}

	function get_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id],array(
			'worker_uid'	=> $op_uid,
			'status'		=> 7,
			'mtime'			=> time(),
		));
		$this->add_log($task_id, 'get', $op_uid);
		return true;
	}

	function cancel_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id],array(
			'status'	=> 11,
			'mtime'		=> time(),
		));
		$this->add_log($task_id, 'cancel', $op_uid);
		return true;
	}

	function open_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id], [
			'status'	=> 7,
			'mtime'		=> time(),
		]);
		$this->add_log($task_id, 'open', $op_uid);
		return true;
	}

	function ok_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id],array(
			'status'	=> 4,
			'mtime'		=> time(),
		));
		$this->add_log($task_id, 'ok', $op_uid);
		return true;
	}

	function end_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id],array(
			'status'	=> 9,
			'mtime'		=> time(),
		));
		$this->add_log($task_id, 'end', $op_uid);
		return true;
	}

	function no_work($task_id, $op_uid){
		$this->update('haha_task',['id'=>$task_id],array(
			'status'	=> 7,
			'mtime'		=> time(),
		));
		$this->add_log($task_id, 'no', $op_uid);
		return true;
	}

	function del_work($task_id){
		$this->delete('haha_task', ['id'=>$task_id]);
		$this->delete('haha_log', ['task_id'=>$task_id]);
		$this->delete('haha_follow', ['task_id'=>$task_id]);
		return true;
	}

	function follow_work($task_id, $uid, $follow_status){
		if($follow_status){
			$find = $this->get('haha_follow','row', ['task_id'=>$task_id,'follow_uid'=>$uid]);
			if(!$find){
				$this->add_follow($task_id, $uid);
			}
		}else{
			$this->delete('haha_follow', ['task_id'=>$task_id,'follow_uid'=>$uid]);
		}
		return $this->get_follow_uids($task_id);
	}

	function count_msg($uid){
		return $this->get('haha_msg','count',['to_uid'=>$uid,'status'=>0]);
	}

	function list_msg($uid){
		return $this->get('haha_msg','all',['to_uid'=>$uid,'status'=>0],['id'=>'DESC']);
	}

	function add_msg($to_uid, $msg_str, $from_uid=0, $url='', $target=''){
		$data =[
			'to_uid'	=> $to_uid,
			'title'		=> note2title($msg_str),
			'content'	=> $msg_str,
			'send_uid'	=> $from_uid,
			'url'		=> $url,
			'ctime'		=> time(),
		];
		return $this->insert('haha_msg', $data);
	}

	function del_msg($msg_id){
		$ids = is_array($msg_id)?:[$msg_id];
		$this->delete('haha_msg',['id'=>$ids]);
		return true;
	}

	function clear_msg($uid){
		$this->delete('haha_msg',['to_uid'=>$uid]);
		return true;
	}

	function add_notice($content, $from_uid=0, $url=''){
		//...
	}
}//endclass

class haha_model {

}

/*----- 公共函数区----------*/

function to_url($url, $type=null){
	if($type=='js'){
		echo "<script>location.href='{$url}'</script>";
	}else{
		if($type==303){
			Header("HTTP/1.1 303 See Other");
		}
		Header("Location: $url");
		exit;
	}
}

function v_get($param_name, $def=''){
	return vals($_GET, $param_name, $def);
}

function v_post($param_name, $def=''){
	return vals($_POST, $param_name, $def);
}

function vals($res, $param_name, $def=null){
	if(is_array($param_name)){
		$data = array();
		if(is_hash($param_name)){
			foreach($param_name as $param => $def){
				$val = val($res[$param], $def);
				if($def!==null || $val!==null){
					$data[$param] = $val;
				}
			}
		}else{
			foreach($param_name as $param){
				$val = val($res[$param], $def);
				if($def!==null || $val!==null){
					$data[$param] = $val;
				}
			}
		}
		return $data;
	}else{
		return val($res[$param_name], $def);
	}
}

function val(&$var, $def=null, $trim=0) {
	$type	= gettype($def);
	$var	= $trim ? trim($var) : $var;
	if($var){
		if($type=='integer'){
			$var = intval($var);
		}
	}else{
		$var = $def;
	}
	return $var;
}

function str_limit($str, $len=20, $s='…'){
	$count = mb_strlen($str);
	if($count>$len){
		$str = mb_substr($str,0,$len).$s;
	}
	return $str;
}

function is_hash(array $var){
	return array_diff_assoc(array_keys($var), range(0, sizeof($var))) ? true : false;
}

function code2arr($code){
	if(empty($code) || $code == 'null' || $code == 'p') {
		return array();
	}else{
		$code = url64_decode($code);
		$code = @unserialize($code);
		foreach($code as &$val){
			if(!is_array($val)){
				$val = urldecode($val);
			}
		}
		return $code;
	}
}

function arr2code($arr, $replace=[], $reset=[]){
	if(!is_array($arr) || empty($arr)){
		return 'p';
	}else{
		foreach($replace as $key => $val){
			if(isset($arr[$key])) $arr[$key] = urlencode($val);
		}
		foreach($reset as $key){
			if(isset($arr[$key])) unset($arr[$key]);
		}
		$code = serialize($arr);
		return url64_encode($code);
	}
}

function url64_encode($str){
	$data = base64_encode($str);
	$data = str_replace(array('+', '/', '='), array('-', '_', ''), $data);
	return $data;
}

function url64_decode($code){
	$data = str_replace(array('-', '_'), array('+', '/'), $code);
	$mod4 = strlen($data)%4;
	if($mod4){
		$data.= substr('====', $mod4);
	}
	return base64_decode($data);
}

function data_diff_field($data_arr, $limit_fields){
	$new_data = [];
	foreach($limit_fields as $field){
		if(isset($data_arr[$field])){
			$new_data[$field] = $data_arr[$field];
		}
	}
	return $new_data;
}

function make_select_option_str($list, $set_val = null, $def_val = null){
	if (empty($list)) {
		return '';
	}
	if (($def_val !== null) && (empty($set_val))) {
		$set_val = $def_val;
	}
	$option_str = '';
	foreach ($list as $k => $v) {
		$selected = ($set_val !== null && $set_val == $k) ? ' selected' : '';
		$option_str .= '<option value="' . $k . '"' . $selected . '>' . $v . '</option>';
	}
	return $option_str;
}

function make_user_rank_str($rank, $users){
	$str = '';
	foreach($rank as $uid => $num){
		$str.= $str?', ':'';
		$str.= (isset($users[$uid])?$users[$uid]:$uid)."($num)";
	}
	return $str;
}

function make_user_rank_data($rank, $users){
	$data = ['names'=>[],'values'=>[]];
	foreach($rank as $uid => $num){
		$data['names'][]= isset($users[$uid])?$users[$uid]:$uid;
		$data['values'][]= $num;
	}
	return $data;
}

function add_load_file($static_arr, $is_local=null){
	$local_str = '';
	foreach($static_arr as $k => $r){
		if(substr($r['local'],-3)=='.js'){
			if($is_local!==1 && val($r['cdn'],'')){
				echo "\t<script src=\"{$r['cdn']}\"></script>\n";
				if($k) {
					$local_str .= "\t\tif(!window.".$k.") {document.write(\"&lt;script src='".$r['local']."'&gt;&lt;/script&gt;\");}\n";
				}
			}else{
				echo "\t<script src=\"{$r['local']}\"></script>\n";
			}
		}
		if(substr($r['local'],-4)=='.css'){
			if($is_local!==1 && val($r['cdn'],'')){
				echo "\t<link href=\"{$r['cdn']}\" rel=\"stylesheet\">\n";
			}else{
				echo "\t<link href=\"{$r['local']}\" rel=\"stylesheet\">\n";
			}
		}
	}
	//$local_str = $local_str?"\t<script>\n".$local_str."\t</script>\n" :'';
	//echo $local_str;
}

function check_nickname($name){
	$chars = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "+", "=", "-", "{", "}", "[", "]", ";", ":", "\'", "\"", "<", ">", ",", ".", "?", "/", "|", "\\", " ", "！", "￥", "…", "《", "》", "｛", "｝", "【", "】", "；", "：", "‘", "“", "’", "”", "？", "、", "，", "·", "　");
	foreach ($chars as $char) {
		if (strstr($name, $char)) {
			return '不能包含特殊符号';
		}
	}
	$reg = "/[\x{4e00}-\x{9fa5}a-zA-Z0-9]+/u";
	if (!preg_match($reg, $name)) {
		return '只能使用中文，英文，数字';
	}
	if (mbstrlen($name) > 12) {
		return '最长只能是12个英文子母或者6个汉字';
	}
	if (mbstrlen($name) < 4) {
		return '至少2个汉字或者4个英文子母';
	}
	return '';
}

function note2title($note){
	$title = strip_tags($note);
	$title = str_replace("\t",'',$title);
	$title = str_replace("\n",'',$title);
	$title = str_replace("\r",'',$title);
	$title = trim($title);
	$title = str_limit($title,100);
	return $title;
}

function niceditor_up_msg($data){
	echo json_encode(!is_array($data)?['data'=>['error'=>$data]]:['data'=>$data]);
	exit;
}

function log_debug($info='', $note = ''){
	$dir = __DIR__ . '/';
	$path = $dir . 'Debug_' . date("Y-m-d") . '_' . substr(md5('$@%!&' . date("Y-m-d")), 0, 6) . '.log';
	$title = date('Y-m-d,H:i:s');
	$trace = debug_backtrace(false, 2);
	$i = isset($trace[1]) ? 1 : 0;
	$title .= isset($trace[$i]['file']) ? ', File: ' . $trace[$i]['file'] : '';
	$title .= isset($trace[$i]['class']) ? ', Class: ' . $trace[$i]['class'] : '';
	$title .= isset($trace[$i]['function']) ? ', Func: ' . $trace[$i]['function'] . '()' : '';
	$title .= isset($trace[$i]['line']) ? ', Line: ' . $trace[$i]['line'] : '';
	$note = is_string($note)||is_numeric($note) ? $note : '';
	$info = is_array($info) ? $info : array($info);
	$info = json_encode($info, JSON_UNESCAPED_UNICODE);
	$info = $title . " ==========>" . $note . "\n" . $info . "\n";
	$info = $info."\n\n";
	$fh = fopen($path, 'a+');
	fwrite($fh, $info);
	fclose($fh);
}

function haha_lang($key, $str=null, $str2=null, $str3=null, $str4=null){
	$lang = array(
		'followed'		=> '你已添加关注此任务，其流程状态发生变化，将通知给你！',
		'unfollowed'	=> '你已取消关注此任务，不再通知你其状态变化！',
		'pswd_title'	=> '修改账户密码',
		'pswd_new'		=> '新密码：',
		'pswd_new2'		=> '重复密码：',
		'pswd_submit'	=> '提交',
		'pswd_tip1'		=> '请输入新密码',
		'pswd_tip2'		=> '两次密码不一样',
		'pswd_not'		=> '密码不能为空',
		'pswd_ok'		=> '修改成功',
		'msg_title'		=> '我的消息',
		'msg_btn_clear'	=> '清理消息',
		'msg_tip_empty'	=> '暂时没有消息',
		'msg_followed'	=> '已经关注了任务',
		'msg_remark'	=> '已经在工作任务[#{str}]下发了留言。',
		'msg_added'		=> '已经添加新任务[#{str}]',
		'msg_add_test'	=> '指定你担任其测验工作！',
		'msg_add_to'	=> '分配给你处理！',
		'msg_get'		=> '已经领取工作任务[#{str}]',
		'msg_end'		=> '已经测验确认工作任务[#{str}]完工',
		'msg_no'		=> '工作任务[#{str}]已经被{str2}打回继续处理！',
		'msg_my_no'		=> '你提交的工作任务[#{str}]已经被{str2}打回继续处理！',
		'msg_ok'		=> '{str}已经提交工作任务[#{str2}]做完工测验。',
		'msg_my_open'	=> '{str}已经把工作任务[#{str2}]重新打开，请继续处理！',
		'msg_to_open'	=> '{str}已经把工作任务[#{str2}]重新打开，请关注！',
		'msg_cancel'	=> '{str}已经把工作任务[#{str2}]取消了！',
		'msg_deled'		=> '{str}把已经取消的工作任务[#{str2}]删除了！',
		'msg_to_me'		=> '{str}已经将工作任务[#{str2}]分配给你处理！',
		'msg_to'		=> '{str}已经把工作任务[#{str2}]分配给{str3}处理',
		'msg_test_me'	=> '{str}已指定你担任工作任务[#{str2}]的测验！',
		'msg_test'		=> '{str}已经指定{str2}担任工作任务[#{str3}]的测验！',
		'msg_edit'		=> '{str}已经修改了工作任务[#{str2}]的{str3}'.($str4!==null?'为{str4}':''),
		'msg_cleared'	=> '清理完成',
		'confirm'		=> '确认{str}吗？',
		'tip_empty'		=> '请输入内容！',
		'tip_success'	=> '提交成功',
		'tip_power'		=> '没有权限',
		'tip_param'		=> '缺少参数',
		'tip_get_data'	=> '取得数据',
		'tip_modify'	=> 'Task已经被修改',
		'tip_deled'		=> '已经删除',
		'tip_no'		=> '已经打回',
		'tip_end'		=> '已经确认完工',
		'tip_get'		=> '已经领取',
		'tip_noget'		=> '不能领取',
		'tip_add'		=> '添加成功！',
		'tip_succeed'	=> '操作成功',
		'tip_failed'	=> '操作失败',
		'tip_img_big'	=> '点击看大图',
		'th_id'			=> '编号',
		'th_note'		=> '任务',
		'th_urgent'		=> '优先级',
		'th_status'		=> '状态',
		'th_submiter'	=> '发布者',
		'th_worker'		=> '承办者',
		'th_acceptor'	=> '检验者',
		'th_mtime'		=> '更新时间',
		'not_find'		=> '没找到含查询关键字“<strong>{str}</strong>”的记录',
		'stats_wait'	=> '现有未完成：',
		'stats_work'	=> '正在处理的：',
		'stats_test'	=> '正在测审的：',
		'stats_end'		=> '已完成：',
		'stats_cancel'	=> '已取消：',
		'nav_my'		=> '我的任务',
		'nav_wait'		=> '待领取',
		'nav_list'		=> '正处理',
		'nav_test'		=> '待核验',
		'nav_end'		=> '已完成',
		'nav_cancel'	=> '已取消',
		'nav_find'		=> '查询结果',
		'nav_stats'		=> '统计',
		'nav_add'		=> '添加',
		'nav_search'	=> '搜索',
		'search_submit'	=> '查询',
		'top_msg'		=> '消息',
		'top_config'	=> '设置',
		'top_users'		=> '成员',
		'top_logout'	=> '退出',
		'label_search'	=> '查询：',
		'label_worker'	=> '工作分配给：',
		'label_note'	=> '工作内容描述：',
		'label_rank_worker_now'	=> '当前工作量排名：',
		'label_rank_worker_end'	=> '已完工作量排名：',
		'label_rank_submiter'	=> '发布任务排名：',
		'view_type'		=> '列表界面样式：',
		'time_axis'		=> '时间轴',
		'follower'		=> '关注者：',
		'add_title'		=> '添加新 Bug/Task',
		'remark_title'	=> '添加留言：',
		'remark_list'	=> '留言：',
		'to_empty'		=> '未指定',
		'to_free'		=> '不指定,自取',
		'btn_add'		=> '添加',
		'btn_edit'		=> '修改',
		'btn_cancel'	=> '取消',
		'btn_get'		=> '领取',
		'btn_to'		=> '工作转给',
		'btn_assign'	=> '测验派给',
		'btn_urgent'	=> '优先级改为',
		'btn_end'		=> '确认完工',
		'btn_no'		=> '打回',
		'btn_ok'		=> '完工提交',
		'btn_open'		=> '重开',
		'btn_del'		=> '删除',
		'btn_split'		=> '拆分',
		'btn_user_add'	=> '添加成员',
		'btn_user_edit'	=> '编辑',
		'btn_user_del'	=> '删除',
		'func_get'			=> '领取此项工作',
		'func_end'			=> '确认工作完工',
		'func_no'			=> '仍有问题-打回',
		'func_ok'			=> '工作完成提交',
		'func_cancel'		=> '取消此项工作',
		'func_open'			=> '重新打开',
		'func_del'			=> '删除此项',
		'func_split'		=> '拆分工作包',
		'func_follow'		=> '关注此任务',
		'func_unfollow'		=> '取消关注',
		'users_form_add'	=> '添加成员',
		'users_form_edit'	=> '修改成员',
		'users_form_id'		=> '编号：',
		'users_form_name'	=> '账号：',
		'users_form_pswd'	=> '密码：',
		'users_form_nick'	=> '昵称：',
		'users_form_role'	=> '角色：',
		'users_form_status'	=> '状态：',
		'users_form_submit'	=> '提交',
		'users_status1'		=> '正常',
		'users_status0'		=> '封禁',
		'users_title'		=> '成员列表',
		'users_tip_del'		=> '你确定要删除 {str} 吗 ？',
		'users_tip_pswd'	=> '不修改原密码时，请保持为空即可',
		'user_tip_admin'	=> '不能删除默认的超级管理员',
		'user_tip_ok'		=> '成功',
		'user_tip_param'	=> '缺少参数',
		'user_tip_need'		=> '有未填项',
		'user_tip_namefind'	=> '同名账号已存在',
		'user_tip_nickfind'	=> '昵称已被别人使用',
		'th_uid'			=> '编号',
		'th_user_nickname'	=> '昵称',
		'th_user_account'	=> '账号',
		'th_user_role'		=> '角色',
		'th_user_status'	=> '状态',
		'th_user_ctime'		=> '添加时间',
		'th_user_func'		=> '操作',
		'th_user_total'		=> '总计：',
		'file_tip_power'	=> '没有权限！',
		'file_tip_empty'	=> '没收到图片!',
		'file_tip_type'		=> '不支持的图片格式!',
		'file_tip_size'		=> '图片太大请压缩后使用!',
		'login_tip_pswd'	=> '账户或密码错误',
		'login_tip_power'	=> '没有权限',
		'nicedit_bold'		=> '加粗',
		'nicedit_italic'	=> '倾斜',
		'nicedit_underline'	=> '下划线',
		'nicedit_left'		=> '左对齐',
		'nicedit_center'	=> '居中',
		'nicedit_right'		=> '右对齐',
		'nicedit_justify'	=> '两端对齐',
		'nicedit_ol'		=> '有序列表',
		'nicedit_ul'		=> '无序列表',
		'nicedit_subscript'		=> '下标',
		'nicedit_superscript'	=> '上标',
		'nicedit_strikethrough'	=> '删除线',
		'nicedit_removeformat'	=> '清除格式',
		'nicedit_indent'	=> '缩进',
		'nicedit_outdent'	=> '退缩',
		'nicedit_hr'		=> '分割线',
		'nicedit_fontSize'		=> '大小',
		'nicedit_fontFamily'	=> '字型',
		'nicedit_fontFormat'	=> '格式',
		'nicedit_link'		=> '添加链接',
		'nicedit_unlink'	=> '移除链接',
		'nicedit_fontcolor'	=> '字体色',
		'nicedit_bgcolor'	=> '背景色',
		'nicedit_image'		=> '添加图片',
		'nicedit_upload'	=> '上传图片',
		'nicedit_xhtml'		=> '源码',
	);
	$text ='';
	if(isset($lang[$key])) {
		$text = $lang[$key];
		$text = $str!==null?str_replace('{str}',$str,$text):$text;
		$text = $str2!==null?str_replace('{str2}',$str2,$text):$text;
		$text = $str3!==null?str_replace('{str3}',$str3,$text):$text;
		$text = $str4!==null?str_replace('{str4}',$str4,$text):$text;
	}
	return $text;
}

function haha_html($html){
	$list = [
		'boot_init' => "
			<!DOCTYPE html><html><head><meta charset='utf-8'><title>".HAHA_TITLE."</title><script>
			function post(url, param, fn, type) {
				var xhr = new XMLHttpRequest();
				var data='';
				if(typeof(param)=='string'||typeof(param)=='number') {
					data = param;
				}else if(typeof(param)=='object'&&param!=null){
					for(var k in param) {
						data += (data==''?'':'&') + k + '=' + encodeURI(param[k]);
					}
				}
				xhr.open('POST', url, true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.onreadystatechange = function() {
					if (xhr.readyState == 4 && (xhr.status == 200 || xhr.status == 304)) {
						console.log(xhr.responseText);
						fn.call(this, JSON.parse(xhr.responseText));
					}
				};
				xhr.send(data);
			};
			function submit(){
				if(confirm('确定开始数据初始化吗？')){
					post('',{tab:'init'},function(res){
						if(res.status){
							location.href = res.url;
						}else{
							alert(res.msg);
						}
					});
				}
			}
			</script></head><body><h1>没找到数据表</h1><p><button onclick='submit()'>初始化数据表</button></p><p>点击按钮会进行数据表初始化，然后会自动跳转到登录界面，初始账户为<strong>admin</strong> 其密码为 <strong>123</strong></p></body></html>",
		'login' => "
			<!DOCTYPE html><html><head><meta charset='utf-8'><title>".HAHA_TITLE."</title>
			<link rel='icon' href='?haha_res=favicon.png&ver=".HAHA_TASK_VER."'>
			<style>
				*{margin:0;padding:0;--c_bg:rgb(255,245,247);--c_box_bg:rgb(178,200,187); --c_btn_font:rgb(255,245,247); --c_btn_bg:rgb(69,137,148); --c_label_font:#fff; --c_title:#fff;}
				body { background-color:var(--c_bg);}
				.login_box{margin:50px auto 0; padding: 30px 20px 30px; width:400px; border-radius:15px; background-color:var(--c_box_bg);}
				h3{margin-bottom:10px; text-align:center; color:var(--c_title);}
				dl{padding:15px; font-size:14px; color:var(--c_label_font); clear:both;}
				dt{float:left; padding-right:5px; width:110px; text-align:right;}
				dd{float:left; width:200px; text-align:left;}
				p{ text-align:center;}
				input[type='text'],input[type='password']{padding:0 5px; height:25px; border:none; border-radius:3px; background-color:var(--c_bg);}
				input[type='button']{padding:5px 15px; border:none; border-radius:3px; color:var(--c_btn_font); background-color:var(--c_btn_bg);}
			</style>
			<script>
				function post(url, param, fn) {
					var xhr = new XMLHttpRequest();
					var data='';
					if(typeof(param)=='string'||typeof(param)=='number') {
						data = param;
					}else if(typeof(param)=='object'&&param!=null){
						for(var k in param) {
							data += (data==''?'':'&') + k + '=' + encodeURI(param[k]);
						}
					}
					xhr.open('POST', url, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onreadystatechange = function() {
						if (xhr.readyState == 4 && (xhr.status == 200 || xhr.status == 304)) {
							fn.call(this, JSON.parse(xhr.responseText));
						}
					};
					xhr.send(data);
				};
				function submit(){
					var account		= document.getElementById('account').value;
					var password	= document.getElementById('password').value;
					if(account=='') {alert('请输入登录账户'); return false;}
					if(password=='') {alert('请输入登录密码'); return false;}
					post('',{tab:'login',account:account,password:password},function(res){
						if(res.status){
							location.href = res.url;
						}else{
							alert(res.msg);
						}
					});
				}
			</script>
			</head><body>
				<div class='login_box'>
					<h3>".HAHA_TITLE."</h3>
					<dl><dt>账户：</dt><dd><input type='text' id='account' name='account'></dd></dl>
					<dl><dt>密码：</dt><dd><input type='password' id='password' name='password'></dd></dl>
					<dl><dt></dt><dd></dd></dl>
					<p>
						<input type='hidden' name='tab' value='login'>
						<input type='button' value='登录' onclick='submit()'>
					</p>
				</div>
			</body></html>",
		'common_head' =>"<!DOCTYPE html><html><head><meta charset='utf-8'>
			<title>".HAHA_TITLE."</title>
			<script src='?haha_res=jquery.min.js&ver=".HAHA_TASK_VER."'></script>
			<link rel='icon' href='?haha_res=favicon.png&ver=".HAHA_TASK_VER."' />
			<link rel='stylesheet' href='?haha_res=style.css&ver=".HAHA_TASK_VER."' />
			<script>
				function str_replace(text, new_str){
					return text.replace(/{str}/, new_str);
				}
				function popup(url,is_reload=0){
					$('#cover').remove();
					$('#popup').remove();
					$('body').append('<div id=\"cover\"></div><div id=\"popup\"><a class=\"close-button\" href=\"javascript:;\" onclick=\"hidepopup('+is_reload+')\"></a><iframe border=\"no\" frameborder=\"no\" id=\"popupFrame\" name=\"popupFrame\" scrolling=\"AUTO\" border=\"0\"></iframe></div>');
					$('#popupFrame').attr('src',url);
					$('#cover').show(); $('#popup').show();
				}
				function hidepopup(is_reload=0){
					$('#cover').hide(); $('#popup').hide();
					if(is_reload) location.reload();
				}
				function blink(obj){
					$(obj).fadeOut('slow', function(){
						$(this).fadeIn('fast', function(){
							blink(this);
						});
					});
				}
			</script>
			</head><body>
			<div id='desk_inner'><div class='desk_inner clearfix'>",
	];
	echo isset($list[$html])?$list[$html]:'';
}

function haha_res($filename){
	//querySelector
	$list=[
	'style.css'=>"
		*{outline: 0;margin: 0; padding: 0;}
		body {min-width:940px; font-size: 12px; font-family: Tahoma,Verdana,Lucida,Helvetica,Arial,Simsun,sans-serif; color: #222;}
		a {outline: none; color: #006485; text-decoration: none;}
		li {list-style: none;}
		.clearfix::after {display: block; content: ''; clear: both;}
		.desk_inner {padding:20px;}
		.btn_bg7{background-image: url('?haha_res=btn_bg7.png&ver=".HAHA_TASK_VER."');}
		.edit_flat_bg0{background-image: url('?haha_res=task_flat_bg10.png&ver=".HAHA_TASK_VER."');}
		.task_flat_bg0{background-image: url('?haha_res=task_flat_bg0.png&ver=".HAHA_TASK_VER."');}
		.task_flat_bg1,.edit_flat_bg1{background-image: url('?haha_res=task_flat_bg1.png&ver=".HAHA_TASK_VER."');}
		.task_flat_bg2,.edit_flat_bg2{background-image: url('?haha_res=task_flat_bg2.png&ver=".HAHA_TASK_VER."');}
		.task_flat_bg3,.edit_flat_bg3{background-image: url('?haha_res=task_flat_bg3.png&ver=".HAHA_TASK_VER."');}
		.task_flat_bg4,.edit_flat_bg4{background-image: url('?haha_res=task_flat_bg4.png&ver=".HAHA_TASK_VER."');}
		.edit_flat_bg0,.edit_flat_bg1,.edit_flat_bg2,.edit_flat_bg3,.edit_flat_bg4 {background-repeat: no-repeat; background-position: 500px top;}
		.task_flat_bg0,.task_flat_bg1,.task_flat_bg2,.task_flat_bg3,.task_flat_bg4 {background-position: center bottom;}
		.task_sticky_bg0{background-image: url('?haha_res=task_sticky_bg0.png&ver=".HAHA_TASK_VER."');}
		.task_sticky_bg1{background-image: url('?haha_res=task_sticky_bg1.png&ver=".HAHA_TASK_VER."');}
		.task_sticky_bg2{background-image: url('?haha_res=task_sticky_bg2.png&ver=".HAHA_TASK_VER."');}
		.task_sticky_bg3{background-image: url('?haha_res=task_sticky_bg3.png&ver=".HAHA_TASK_VER."');}
		.task_sticky_bg4{background-image: url('?haha_res=task_sticky_bg4.png&ver=".HAHA_TASK_VER."');}

		@font-face {font-family: 'haha-task'; src: url('?haha_res=haha_fonts.woff&ver=".HAHA_TASK_VER."') format('woff');}
		.haha-task {font-family: 'haha-task' !important; font-size: 12px; font-style: normal; -webkit-font-smoothing: antialiased;}
		.icon-task:before {content: '\\e7c0';}
		.icon-task2:before {content: '\\e608';}
		.icon-task1:before {content: '\\e637';}
		.icon-issue:before {content: '\\e607';}
		.icon-eye2:before {content: '\\e609';}
		.icon-edit:before {content: '\\e62f';}
		.icon-hourglass:before {content: '\\e70d';}
		.icon-users1:before {content: '\\e604';}
		.icon-doctor:before {content: '\e72f';}
		.icon-plus:before {content: '\\e61c';}
		.icon-refuse:before {content: '\\e670';}
		.icon-flag:before {content: '\\e6f2';}
		.icon-Finished:before {content: '\\e784';}
		.icon-remove:before {content: '\\e87c';}
		.icon-search:before {content: '\\e73c';}
		.icon-setup:before {content: '\\e73b';}
		.icon-user-plus:before {content: '\\e8c4';}
		.icon-leader:before {content: '\\e7fe';}
		.icon-man1:before {content: '\\e668';}
		.icon-role:before {content: '\\e606';}
		.icon-split:before {content: '\\e676';}
		.icon-xingzhuang:before {content: '\\e664';}
		.icon-notice:before {content: '\\e605';}
		.icon-stats:before {content: '\\e6a4';}
		.icon-logout:before {content: '\\e602';}
		.icon-bug:before {content: '\\e636';}
		.icon-bigleader:before {content: '\\e622';}
		.icon-delete:before {content: '\\e648';}
		.icon-message:before {content: '\\e627';}
		.icon-man:before {content: '\\e601';}
		.icon-worker:before {content: '\\e60b';}
		.icon-file:before {content: '\\e617';}

		#cover{position:fixed; left:0; top:0; background:url('?haha_res=pop_bg.jpg&ver=".HAHA_TASK_VER."'); width:100%; height:100%; z-index:100; display:none;}
		#popup{position:fixed; left:5%; top:5%; width:90%; height:90%; z-index:200; background:#fff; display:none; border:5px solid #aaa;}
		#popup iframe{width:100%; height:100%;}
		#popup a{position:absolute; width:57px; height:57px; right:0; top:0; margin-top:-20px;margin-right:-20px; background:url('?haha_res=pop_btn.png&ver=".HAHA_TASK_VER."');cursor:pointer;}

		.top_box{height:40px;}
		.top_logo{float:left; margin-top:5px; margin-left:10px; width:300px; font-weight:bold; font-size:16px; font-family:'Microsoft YaHei',arial,tahoma,sans-serif; letter-spacing:2px; color:#006485;}
		.top_bar {float:right; margin-top:5px; margin-right:10px; width:300px; text-align:right;}
		.top_bar a {margin-left:10px;}
		.top_bar .msg_num{display:inline-block; width:15px; font-size:12px; text-align:center; color:#fff; border-radius:10px; background-color:red;}
		.tab_bar {margin-bottom: 10px; border-bottom: 1px solid #9e9e9e;}
		.tab_bar a {display: block; float: left; margin-bottom: -1px; margin-left: 10px; padding: 5px 10px; border: 1px solid #9e9e9e; border-bottom: 1px solid #9e9e9e; border-top-left-radius: 5px; border-top-right-radius: 5px; background-color: #ddd;}
		.tab_bar .on {border-bottom: 1px solid #fff; background-color: #fff;}
		.tab_bar a.add_btn{margin-top:1px; margin-left:50px; padding:0; width:65px; height:22px; line-height:20px; text-align:center; color:#1F4665; border:none;}
		.tab_bar a.add_btn .fa{font-size:11px; line-height:20px; -webkit-transform: scale(0.90);}
		.tab_bar a.add_btn .fa:hover{color:#DFEDF9;}
		.main_left{display:block; float:left; margin-top:20px; margin-right:10px; width:810px;}
		.task_edit{display:block; padding-bottom:10px; border:1px solid #9e9e9e;}
		.task_edit dl{padding:5px 0; height:20px; font-size:14px; clear:both;}
		.task_edit dt{display:inline-block; float:left; width:120px; font-weight:bold; text-align:right;}
		.task_edit dd{display:inline-block; float:left; width:670px; }
		.task_edit label{margin-right:10px;}
		.task_edit input[type='text'],.task_edit input[type='password']{width:150px; height:22px; padding:0 5px;}
		.task_edit select {width:100px; height:22px; padding:0 5px;}
		.task_edit textarea{padding:5px; width:630px; height:150px; font-size:12px;}
		.task_edit .tip{font-size:12px; line-height:14px; color:#cc3;}
		.task_edit input[type='button']{margin-top:10px; margin-right:20px; padding:2px 10px; color:#fff; background:#c30; border:none; cursor:pointer;}
		.task_edit input.btn_add{background:#f96;}
		.edit_item{color:#69c;}
		.edit_item i{display:inline-block; font-size:16px;}
		.edit_item:hover{color:#9cf; cursor:pointer;}
		.edit_item:hover i{display:inline-block; }
		.edit_item_left dt, .edit_item_right dt{line-height: 25px;}
		.edit_item_left dd, .edit_item_right dd{width: 260px;}
		.edit_item_left{display:inline-block; width:400px;}
		.edit_item_right{display:inline-block; width:400px;}
		.edit_item_right dt{width:100px;}

		.edit_item2_left,.edit_item2_center,.edit_item2_right{display:inline-block;}
		.edit_item2_left{width:210px;}
		.edit_item2_center{width:280px;}
		.edit_item2_right{width:280px;}
		.edit_item2_left dt,.edit_item2_center dt,.edit_item2_right dt{width: 120px; line-height: 25px;}
		.edit_item2_left dd,.edit_item2_center dd,.edit_item2_right dd{line-height: 25px;}
		.edit_item2_left dd{width: 80px;}
		.edit_item2_left dd .edit_item_name{display:inline-block; overflow: hidden; max-width:80px; white-space: nowrap; text-overflow: ellipsis;vertical-align: top;}
		.edit_item2_center dd{width: 150px;}
		.edit_item2_center dd .edit_item_name{display:inline-block; overflow: hidden; max-width:80px; white-space: nowrap; text-overflow: ellipsis;vertical-align: top;}
		.edit_item2_right dd{width: 150px;}
		.edit_item2_right dd .edit_item_name{display:inline-block; overflow: hidden; max-width:80px; white-space: nowrap; text-overflow: ellipsis;vertical-align: top;}

		.edit_box{display:none;}
		.edit_box .icon-Finished, .edit_box .icon-remove{margin-left:0; cursor:pointer;}
		.edit_box .icon-Finished{color:#9cf;}/*color:#0f0;*/
		.edit_box .icon-remove{color:#9cf;}
		.note_view{display:inline-block; overflow-y:auto; padding:5px; width:620px; min-height:50px; max-height:200px; font-size:12px; background:#ccffff;}
		.remark_view{display:inline-block; overflow-y:auto; padding:5px; width:620px; min-height:50px; max-height:200px; font-size:12px; background:#FFEFD5;}
		.remark_view li {margin-bottom:5px;}
		.remark_view li strong{}
		.remark_view li em{font-size:9px; font-style:italic;}
		.remark_view li span{padding-left:20px;}
		.remark_view li.my_remark{color:#2E8B57;}

		.ke-container-default{display: inline-block !important; float:left !important; width:auto !important;}

		.main_top{width:810px;}
		.main_top h3{float:left; margin-top:10px; width:400px;}
		.main_top_follow{margin-left:20px; font-weight:normal; font-size:12px; cursor:pointer;}
		.func_bar{float:right; width:400px; text-align:right;}
		.func_bar input[type='button']{margin-top:10px; margin-right:20px; padding:2px 10px; color:#fff; background:#c30; border:none; cursor:pointer;}
		.func_bar input.btn_get{background:#9cf;}
		.func_bar input.btn_edit{background:#f96;}
		.func_bar input.btn_end{background:#9c9;}
		.func_bar input.btn_no{background:#9cf;}
		.func_bar input.btn_ok{background:#fc3;}
		.func_bar input.btn_open{background:#9cf;}
		.func_bar input.btn_cancel{background:#ccc;}
		.func_bar input.btn_del{color:#fff; background:#000;}

		.time_axis{float:left; margin-top:20px; padding:5px 10px; width:280px; border:1px solid #9e9e9e;}
		.time_axis ul{margin-top:5px; text-decoration:none;}
		.time_axis li{padding:2px 0;}
		.time_axis .log_name{display:inline-block; overflow: hidden; max-width:60px; white-space: nowrap; text-overflow: ellipsis; vertical-align: top;}
		.follow{float:left; margin-top:20px; width:810px;}
		.follow strong{font-weight:bold;}
		.follow span{}

		.list_flat{}
		.list_flat .item{float:left; overflow:hidden; margin-bottom:10px; margin-right:10px; padding:5px; width:200px; height:100px; background-color:#fcc; box-shadow: 0 1px 5px 0 rgba(0,0,0,0.2), 0 1px 8px 0 rgba(0,0,0,0.1); cursor:pointer;}
		.list_flat .item .bug_top{height:20px; clear:both;}
		.list_flat .item .bug_status{float:left; width:120px; height:20px; }
		.list_flat .item .bug_user_name{float:right; overflow: hidden; width:70px; height:20px; line-height: 20px; text-align:right;}
		.list_flat .item .bug_user_name a{color:#fff;}
		.list_flat .item .bug_buttom{overflow:hidden; margin-top:5px; height:75px; font-size:12px; line-height:18px; word-break: break-all;}
		.list_flat .item .bug_buttom:first-letter {letter-spacing:2px; font-size: 20px; height:24px;}
		.list_flat .bug_status_wait{color:#fff; background:#c30;} /*  #EEDFCC */
		.list_flat .bug_status_test{background:#FFCC33;} /*  #FFEFD5 */
		.list_flat .bug_status_work{background:#99CCFF;} /*  #E6E6FA */
		.list_flat .bug_status_end{background:#99CC99;} /*  #EEE9BF */
		.list_flat .bug_status_cancel{background:#CCCCCC;} /**/

		.list_sticky{}
		.list_sticky .item{float:left; overflow:hidden; margin-bottom:10px; margin-right:10px; padding:0; width:240px; height:200px; box-shadow:none; cursor:pointer;}
		.list_sticky .item .bug_top{height:20px; clear:both;}
		.list_sticky .item .bug_status{float:left; width:120px; height:20px; }
		.list_sticky .item .bug_user_name{float:right; overflow: hidden; width:70px; height:20px; line-height: 20px; text-align:right;}
		.list_sticky .item .bug_user_name a{color:#fff;}
		.list_sticky .item .bug_buttom{overflow:hidden; margin-top:5px; height:75px; font-size:12px; line-height:18px; word-break: break-all;}
		.list_sticky .item .bug_buttom:first-letter {letter-spacing:2px; font-size: 20px; height:24px;}
		.list_sticky .bug_status_wait{color:#fff; background-color:#c30;} /*  #EEDFCC */
		.list_sticky .bug_status_test{background-color:#FFCC33;} /*  #FFEFD5 */
		.list_sticky .bug_status_work{background-color:#99CCFF;} /*  #E6E6FA */
		.list_sticky .bug_status_end{background-color:#99CC99;} /*  #EEE9BF */
		.list_sticky .bug_status_cancel{background-color:#CCCCCC;} /**/
		.list_table {width:100%; font-family: verdana,arial,sans-serif; font-size:11px; color:#333; border-width: 1px; border-color: #666; border-collapse: collapse;}
		.list_table th {padding: 8px; border: 1px solid #666; background-color: #dedede;}
		.list_table td {padding: 8px; border: 1px solid #666; background-color: #fff;}
		.list_table tr:nth-of-type(odd) td{background-color:#F0FFFF;}
		.font_urgent_0{color:#ccc;}
		.font_urgent_1{color:#999;}
		.font_urgent_2{color:#f90;}
		.font_urgent_3{color:#f00;}

		.stats dl{clear:both;}
		.stats dt{float:left; width:100px; text-align:right;}
		.stats dd{float:left;color:red;}
		.edit_btn{margin-left:20px;}
		.config{padding:15px;}
		.debug_log{padding:20px;}
		.debug_log h3{}
		.debug_log ul{display:none; padding-top:10px;}
		.debug_log li{padding-left:20px; font:12px/15px consolas;}",
	'sChart.js' =>
		'!function(t,i){"object"==typeof exports&&"undefined"!=typeof module?module.exports=i():"function"==typeof define&&define.amd?define(i):(t=t||self).Schart=i()}(this,function(){"use strict";function a(t,i){for(var e=0;e<i.length;e++){var a=i[e];a.enumerable=a.enumerable||!1,a.configurable=!0,"value"in a&&(a.writable=!0),Object.defineProperty(t,a.key,a)}}function o(t){return function(t){if(Array.isArray(t)){for(var i=0,e=new Array(t.length);i<t.length;i++)e[i]=t[i];return e}}(t)||function(t){if(Symbol.iterator in Object(t)||"[object Arguments]"===Object.prototype.toString.call(t))return Array.from(t)}(t)||function(){throw new TypeError("Invalid attempt to spread non-iterable instance")}()}var f=window.devicePixelRatio||1,g=10*f,u=g/2;return function(){function e(t,i){!function(t,i){if(!(t instanceof i))throw new TypeError("Cannot call a class as a function")}(this,e),this.canvas=function(t){var i=document.getElementById(t),e=i.parentNode.clientWidth,a=i.parentNode.clientHeight;return i.style.width=e+"px",i.style.height=a+"px",i.width=e*f,i.height=a*f,i}(t),this.ctx=this.canvas.getContext("2d"),this.type="bar",this.showValue=!0,this.showGrid=!0,this.topPadding=60*f,this.leftPadding=50*f,this.rightPadding=10*f,this.bottomPadding=50*f,this.yEqual=5,this.yLength=0,this.xLength=0,this.ySpace=0,this.xRorate=0,this.yRorate=0,this.xRotate=0,this.yRotate=0,this.bgColor="#fff",this.axisColor="#666",this.gridColor="#eee",this.title={text:"",color:"#666",position:"top",font:"bold "+18*f+"px Arial",top:g,bottom:u},this.legend={display:!0,position:"top",color:"#666",font:14*f+"px Arial",top:45*f,bottom:15*f,textWidth:0},this.radius=100*f,this.innerRadius=60*f,this.colorList=["#4A90E2","#F5A623","#ff5858","#5e64ff","#2AC766","#743ee2","#b554ff","#199475"],this.init(i)}return function(t,i,e){i&&a(t.prototype,i),e&&a(t,e)}(e,[{key:"init",value:function(t){if(t.title=Object.assign({},this.title,t.title),t.legend=Object.assign({},this.legend,t.legend),Object.assign(this,t),!t.labels||!t.labels.length)throw new Error("缺少主要参数labels");if(!t.datasets||!t.datasets.length)throw new Error("缺少主要参数datasets");this.drawBackground(),"bar"===this.type||"line"===this.type?this.renderBarChart():this.renderPieChart(),this.drawLegend()}},{key:"renderBarChart",value:function(){this.yLength=Math.floor((this.canvas.height-this.topPadding-this.bottomPadding-g)/this.yEqual),this.xLength=Math.floor((this.canvas.width-this.leftPadding-this.rightPadding-g)/this.labels.length),this.ySpace=function(t,i){var e=t.map(function(t){return t.data.reduce(function(t,i){return i<t?t:i})}),a=Math.ceil(Math.max.apply(Math,o(e))/i),s=a.toString().length-1;return s=2<s?2:s,Math.ceil(a/Math.pow(10,s))*Math.pow(10,s)}(this.datasets,this.yEqual),this.drawXAxis(),this.drawYAxis(),this.drawBarContent()}},{key:"drawBarContent",value:function(){var t=this.ctx,i=this.datasets.length;t.beginPath();for(var e=0;e<i;e++){t.font=this.legend.font,this.legend.textWidth+=Math.ceil(t.measureText(this.datasets[e].label).width),t.fillStyle=t.strokeStyle=this.datasets[e].fillColor||this.colorList[e];for(var a=this.datasets[e].data,s=0;s<a.length;s++)if(!(s>this.labels.length-1)){var o=this.xLength/(i+1),h=this.yLength/this.ySpace,l=this.leftPadding+this.xLength*s+o*(e+.5),n=l+o,r=this.canvas.height-this.bottomPadding,d=r-a[s]*h;if("bar"===this.type)t.fillRect(l,d,n-l,r-d),this.drawValue(a[s],l+o/2,d-u);else if("line"===this.type){var c=this.leftPadding+this.xLength*(s+.5);t.beginPath(),t.arc(c,d,3*f,0,2*Math.PI,!0),t.fill(),0!==s&&(t.beginPath(),t.strokeStyle=this.datasets[e].fillColor||this.colorList[e],t.lineWidth=2*f,t.moveTo(c-this.xLength,r-a[s-1]*h),t.lineTo(c,d),t.stroke(),t.lineWidth=1*f),this.drawValue(a[s],c,d-g)}}}t.stroke()}},{key:"renderPieChart",value:function(){for(var t=this.ctx,i=this.labels.length,e=this.datasets[0],a=e.data,s=a.reduce(function(t,i){return t+i}),o=-Math.PI/2,h=this.canvas.width/2,l=this.canvas.height/2,n=0;n<i;n++){t.font=this.legend.font,this.legend.textWidth+=Math.ceil(t.measureText(this.labels[n]).width),t.beginPath(),t.strokeStyle=t.fillStyle=e.colorList&&e.colorList[n]||this.colorList[n],t.moveTo(h,l);var r=o,d=o+=a[n]/s*2*Math.PI;t.arc(h,l,this.radius,r,d),t.closePath(),t.fill();var c=(r+d)/2;this.drawPieValue(a[n],c)}"ring"===this.type&&(t.beginPath(),t.fillStyle=this.bgColor,t.arc(h,l,this.innerRadius,0,2*Math.PI),t.closePath(),t.fill())}},{key:"drawValue",value:function(t,i,e){var a=this.ctx;this.showValue&&(a.textBaseline="middle",a.font=12*f+"px Arial",a.textAlign="center",a.fillText(t,i,e))}},{key:"drawPieValue",value:function(t,i){var e=this.ctx;if(this.showValue){var a=this.canvas.width/2,s=this.canvas.height/2,o=Math.ceil(Math.abs(this.radius*Math.cos(i))),h=Math.floor(Math.abs(this.radius*Math.sin(i)));e.textBaseline="middle",this.showValue&&(i<=0?(e.textAlign="left",e.moveTo(a+o,s-h),e.lineTo(a+o+g,s-h-g),e.moveTo(a+o+g,s-h-g),e.lineTo(a+o+3*g,s-h-g),e.stroke(),e.fillText(t,a+o+3.5*g,s-h-g)):0<i&&i<=Math.PI/2?(e.textAlign="left",e.moveTo(a+o,s+h),e.lineTo(a+o+g,s+h+g),e.moveTo(a+o+g,s+h+g),e.lineTo(a+o+3*g,s+h+g),e.stroke(),e.fillText(t,a+o+3.5*g,s+h+g)):i>Math.PI/2&&i<Math.PI?(e.textAlign="right",e.moveTo(a-o,s+h),e.lineTo(a-o-g,s+h+g),e.moveTo(a-o-g,s+h+g),e.lineTo(a-o-3*g,s+h+g),e.stroke(),e.fillText(t,a-o-3.5*g,s+h+g)):(e.textAlign="right",e.moveTo(a-o,s-h),e.lineTo(a-o-g,s-h-g),e.moveTo(a-o-g,s-h-g),e.lineTo(a-o-3*g,s-h-g),e.stroke(),e.fillText(t,a-o-3.5*g,s-h-g)))}}},{key:"drawBackground",value:function(){this.ctx.fillStyle=this.bgColor,this.ctx.fillRect(0,0,this.canvas.width,this.canvas.height),this.drawTitle()}},{key:"drawTitle",value:function(){var t=this.title;if(t.text){var i=this.ctx;i.beginPath(),i.font=t.font,i.textAlign="center",i.fillStyle=t.color,"top"===t.position?(i.textBaseline="top",i.fillText(t.text,this.canvas.width/2,t.top)):(i.textBaseline="bottom",i.fillText(t.text,this.canvas.width/2,this.canvas.height-t.bottom))}}},{key:"drawXAxis",value:function(){var t=this.ctx,i=this.canvas.height-this.bottomPadding+.5;t.beginPath(),t.strokeStyle=this.axisColor,t.moveTo(this.leftPadding,i),t.lineTo(this.canvas.width-this.rightPadding,i),t.stroke(),this.drawXPoint()}},{key:"drawXPoint",value:function(){var t=this.ctx;t.beginPath(),t.font=12*f+"px Microsoft YaHei",t.textAlign=this.xRorate||this.xRotate?"right":"center",t.textBaseline="top",t.fillStyle=this.axisColor;for(var i=0;i<this.labels.length;i++){var e=this.labels[i],a=this.leftPadding+this.xLength*(i+1)+.5,s=this.canvas.height-this.bottomPadding;this.showGrid?(t.strokeStyle=this.gridColor,t.moveTo(a,s),t.lineTo(a,this.topPadding+g)):(t.moveTo(a,s),t.lineTo(a,s-u)),t.stroke(),t.save(),t.translate(a-this.xLength/2,s+u),this.xRorate?t.rotate(-this.xRorate*Math.PI/180):t.rotate(-this.xRotate*Math.PI/180),t.fillText(e,0,0),t.restore()}}},{key:"drawYAxis",value:function(){var t=this.ctx;t.beginPath(),t.strokeStyle=this.axisColor,t.moveTo(this.leftPadding-.5,this.canvas.height-this.bottomPadding+.5),t.lineTo(this.leftPadding-.5,this.topPadding+.5),t.stroke(),this.drawYPoint()}},{key:"drawYPoint",value:function(){var t=this.ctx;t.font=12*f+"px Microsoft YaHei",t.textAlign="right",t.textBaseline="middle",t.beginPath();for(var i=0;i<this.yEqual;i++){var e=this.leftPadding,a=this.canvas.height-this.bottomPadding-this.yLength*(i+1)+.5;this.showGrid?(t.strokeStyle=this.gridColor,t.moveTo(e,a),t.lineTo(this.canvas.width-this.rightPadding-g,a)):(t.strokeStyle=this.axisColor,t.moveTo(e-u,a),t.lineTo(e,a)),t.stroke(),t.save(),t.fillStyle=this.axisColor,t.translate(e-g,a),this.yRorate?t.rotate(-this.yRorate*Math.PI/180):t.rotate(-this.yRotate*Math.PI/180),t.fillText(this.ySpace*(i+1),0,0),t.restore()}}},{key:"drawLegend",value:function(){var t=this.legend;if(t.display){var i=this.ctx,e="pie"===this.type||"ring"===this.type;i.beginPath(),i.font=t.font,i.textAlign="left",i.textBaseline="middle";for(var a=e?this.labels.length:this.datasets.length,s=(this.canvas.width-(this.legend.textWidth+(5*a-2)*g))/2,o=0,h=0;h<a;h++){var l=e?this.datasets[0]:this.datasets[h],n=(e?this.labels[h]:l.label)||"";i.fillStyle=l.colorList&&l.colorList[h]||l.fillColor||this.colorList[h],"top"===t.position?(this.drawLegendIcon(s+5*g*h+o,t.top-u,2*g,g),i.fillStyle=t.color,i.fillText(n,s+(5*h+3)*g+o,t.top)):"bottom"===t.position?(this.drawLegendIcon(s+5*g*h+o,this.canvas.height-t.bottom-u,2*g,g),i.fillStyle=t.color,i.fillText(n,s+(5*h+3)*g+o,this.canvas.height-t.bottom)):(i.fillRect(g,t.top+2*g*h,2*g,g),i.fillStyle=t.color,i.fillText(n,4*g,t.top+2*g*h+.5*g)),o+=Math.ceil(i.measureText(n).width)}}}},{key:"drawLegendIcon",value:function(t,i,e,a){var s=this.ctx;"line"===this.type?(s.beginPath(),s.strokeStyle=s.fillStyle,s.lineWidth=2*f,s.moveTo(t,i+u),s.lineTo(t+2*g,i+u),s.stroke(),s.lineWidth=1*f,s.arc(t+g,i+u,3*f,0,2*Math.PI,!0),s.fill()):s.fillRect(t,i,e,a)}}]),e}()});',
	'task_flat_bg0.png'=>//11k
		'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',
	'task_flat_bg1.png'=>//21k
		'iVBORw0KGgoAAAANSUhEUgAAAMAAAABkCAYAAADQUT//AAAACXBIWXMAABJ0AAASdAHeZh94AAAKOWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanZZ3VFTXFofPvXd6oc0wAlKG3rvAANJ7k15FYZgZYCgDDjM0sSGiAhFFRJoiSFDEgNFQJFZEsRAUVLAHJAgoMRhFVCxvRtaLrqy89/Ly++Osb+2z97n77L3PWhcAkqcvl5cGSwGQyhPwgzyc6RGRUXTsAIABHmCAKQBMVka6X7B7CBDJy82FniFyAl8EAfB6WLwCcNPQM4BOB/+fpFnpfIHomAARm7M5GSwRF4g4JUuQLrbPipgalyxmGCVmvihBEcuJOWGRDT77LLKjmNmpPLaIxTmns1PZYu4V8bZMIUfEiK+ICzO5nCwR3xKxRoowlSviN+LYVA4zAwAUSWwXcFiJIjYRMYkfEuQi4uUA4EgJX3HcVyzgZAvEl3JJS8/hcxMSBXQdli7d1NqaQffkZKVwBALDACYrmcln013SUtOZvBwAFu/8WTLi2tJFRbY0tba0NDQzMv2qUP91829K3NtFehn4uWcQrf+L7a/80hoAYMyJarPziy2uCoDOLQDI3fti0zgAgKSobx3Xv7oPTTwviQJBuo2xcVZWlhGXwzISF/QP/U+Hv6GvvmckPu6P8tBdOfFMYYqALq4bKy0lTcinZ6QzWRy64Z+H+B8H/nUeBkGceA6fwxNFhImmjMtLELWbx+YKuGk8Opf3n5r4D8P+pMW5FonS+BFQY4yA1HUqQH7tBygKESDR+8Vd/6NvvvgwIH554SqTi3P/7zf9Z8Gl4iWDm/A5ziUohM4S8jMX98TPEqABAUgCKpAHykAd6ABDYAasgC1wBG7AG/iDEBAJVgMWSASpgA+yQB7YBApBMdgJ9oBqUAcaQTNoBcdBJzgFzoNL4Bq4AW6D+2AUTIBnYBa8BgsQBGEhMkSB5CEVSBPSh8wgBmQPuUG+UBAUCcVCCRAPEkJ50GaoGCqDqqF6qBn6HjoJnYeuQIPQXWgMmoZ+h97BCEyCqbASrAUbwwzYCfaBQ+BVcAK8Bs6FC+AdcCXcAB+FO+Dz8DX4NjwKP4PnEIAQERqiihgiDMQF8UeikHiEj6xHipAKpAFpRbqRPuQmMorMIG9RGBQFRUcZomxRnqhQFAu1BrUeVYKqRh1GdaB6UTdRY6hZ1Ec0Ga2I1kfboL3QEegEdBa6EF2BbkK3oy+ib6Mn0K8xGAwNo42xwnhiIjFJmLWYEsw+TBvmHGYQM46Zw2Kx8lh9rB3WH8vECrCF2CrsUexZ7BB2AvsGR8Sp4Mxw7rgoHA+Xj6vAHcGdwQ3hJnELeCm8Jt4G749n43PwpfhGfDf+On4Cv0CQJmgT7AghhCTCJkIloZVwkfCA8JJIJKoRrYmBRC5xI7GSeIx4mThGfEuSIemRXEjRJCFpB+kQ6RzpLuklmUzWIjuSo8gC8g5yM/kC+RH5jQRFwkjCS4ItsUGiRqJDYkjiuSReUlPSSXK1ZK5kheQJyeuSM1J4KS0pFymm1HqpGqmTUiNSc9IUaVNpf+lU6RLpI9JXpKdksDJaMm4ybJkCmYMyF2TGKQhFneJCYVE2UxopFykTVAxVm+pFTaIWU7+jDlBnZWVkl8mGyWbL1sielh2lITQtmhcthVZKO04bpr1borTEaQlnyfYlrUuGlszLLZVzlOPIFcm1yd2WeydPl3eTT5bfJd8p/1ABpaCnEKiQpbBf4aLCzFLqUtulrKVFS48vvacIK+opBimuVTyo2K84p6Ss5KGUrlSldEFpRpmm7KicpFyufEZ5WoWiYq/CVSlXOavylC5Ld6Kn0CvpvfRZVUVVT1Whar3qgOqCmrZaqFq+WpvaQ3WCOkM9Xr1cvUd9VkNFw08jT6NF454mXpOhmai5V7NPc15LWytca6tWp9aUtpy2l3audov2Ax2yjoPOGp0GnVu6GF2GbrLuPt0berCehV6iXo3edX1Y31Kfq79Pf9AAbWBtwDNoMBgxJBk6GWYathiOGdGMfI3yjTqNnhtrGEcZ7zLuM/5oYmGSYtJoct9UxtTbNN+02/R3Mz0zllmN2S1zsrm7+QbzLvMXy/SXcZbtX3bHgmLhZ7HVosfig6WVJd+y1XLaSsMq1qrWaoRBZQQwShiXrdHWztYbrE9Zv7WxtBHYHLf5zdbQNtn2iO3Ucu3lnOWNy8ft1OyYdvV2o/Z0+1j7A/ajDqoOTIcGh8eO6o5sxybHSSddpySno07PnU2c+c7tzvMuNi7rXM65Iq4erkWuA24ybqFu1W6P3NXcE9xb3Gc9LDzWepzzRHv6eO7yHPFS8mJ5NXvNelt5r/Pu9SH5BPtU+zz21fPl+3b7wX7efrv9HqzQXMFb0ekP/L38d/s/DNAOWBPwYyAmMCCwJvBJkGlQXlBfMCU4JvhI8OsQ55DSkPuhOqHC0J4wybDosOaw+XDX8LLw0QjjiHUR1yIVIrmRXVHYqLCopqi5lW4r96yciLaILoweXqW9KnvVldUKq1NWn46RjGHGnIhFx4bHHol9z/RnNjDn4rziauNmWS6svaxnbEd2OXuaY8cp40zG28WXxU8l2CXsTphOdEisSJzhunCruS+SPJPqkuaT/ZMPJX9KCU9pS8Wlxqae5Mnwknm9acpp2WmD6frphemja2zW7Fkzy/fhN2VAGasyugRU0c9Uv1BHuEU4lmmfWZP5Jiss60S2dDYvuz9HL2d7zmSue+63a1FrWWt78lTzNuWNrXNaV78eWh+3vmeD+oaCDRMbPTYe3kTYlLzpp3yT/LL8V5vDN3cXKBVsLBjf4rGlpVCikF84stV2a9021DbutoHt5turtn8sYhddLTYprih+X8IqufqN6TeV33zaEb9joNSydP9OzE7ezuFdDrsOl0mX5ZaN7/bb3VFOLy8qf7UnZs+VimUVdXsJe4V7Ryt9K7uqNKp2Vr2vTqy+XeNc01arWLu9dn4fe9/Qfsf9rXVKdcV17w5wD9yp96jvaNBqqDiIOZh58EljWGPft4xvm5sUmoqbPhziHRo9HHS4t9mqufmI4pHSFrhF2DJ9NProje9cv+tqNWytb6O1FR8Dx4THnn4f+/3wcZ/jPScYJ1p/0Pyhtp3SXtQBdeR0zHYmdo52RXYNnvQ+2dNt293+o9GPh06pnqo5LXu69AzhTMGZT2dzz86dSz83cz7h/HhPTM/9CxEXbvUG9g5c9Ll4+ZL7pQt9Tn1nL9tdPnXF5srJq4yrndcsr3X0W/S3/2TxU/uA5UDHdavrXTesb3QPLh88M+QwdP6m681Lt7xuXbu94vbgcOjwnZHokdE77DtTd1PuvriXeW/h/sYH6AdFD6UeVjxSfNTws+7PbaOWo6fHXMf6Hwc/vj/OGn/2S8Yv7ycKnpCfVEyqTDZPmU2dmnafvvF05dOJZ+nPFmYKf5X+tfa5zvMffnP8rX82YnbiBf/Fp99LXsq/PPRq2aueuYC5R69TXy/MF72Rf3P4LeNt37vwd5MLWe+x7ys/6H7o/ujz8cGn1E+f/gUDmPP8kcBa2wAAAARnQU1BAACxjnz7UZMAAAAgY0hSTQAAeiUAAICDAAD5/wAAgOkAAHUwAADqYAAAOpgAABdvkl/FRgAAMn9JREFUeNpi/P//P8MoGAUjFQAEENNoEIyCkQwAAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAvBSBikMwkAUnUkbKSLq1k1v4c6rddOjeQdv4MKdLlw0JWb6J7QWSgulYP9TmEhm/DOB7Lco2pybTczOMtMFMHjIgoILEqDfHTBA4wDuexPExyCBU057rK+6/1WefFhoEa35D6m/dz6+keZNMsX+Si5pCAPBO1Wm0j5olJF24AC6pVvzElDbmozg7uNnLX10tpYtueAoM9nqzYsnJ5gsu5ivsc64D338R855fDUX8/25p09qT+1mZ3ATQCzDMVeDIuDb/28ML/+/ZPj9/zcokti/Mnw1+P7/+z8lJiVBNka2P0A16LUfEzsj+3teBt5nQPZXakfiKBicACCAhlQGACZkKVBiBpY+sNTJCOT/BtJvgfg7cgYA1Qq//v9i+Mz4mYGFgeU/sHT8BcwADPf+3ZNiYECqQhCAEZgBxCQYJWSB+DqwNHs+mgng4c4HDENuYBiyAulfvxh+vRgufgMIIJpkAEas6YtiM3mf/X+mB2wGsYPTOLTU5mTk/K7NqH0CWO1/R1b/GwiFmIQYQBAYaT+e/nv68j3De2mwFA4ArNqZHv5/KMDFzKXAw8DzCthE+Uu7zhcTuPkwBBI/54N/DwyATSpeYHiAask/QoxCL4Dh/gkYJ0KM4Nhm/AX0zw8QBqr/AMwo34CFx++hkAEAAogmGQDU/qRBBpB//e81O6iNjtyM/vH/B+cThidSHIwcH0FtYWAEMML6A6CaAkQD+f+lmKRefWT4yPP231secPMbV+uJgeEvMKJBZvyjZQ3wHwoHKwCGGweQ4n/095ESMAMIQcPm/+f/n9k+MHyQY2Jk+gfsG4DTD7Av8g9aK/9jYWT5r8qsehcYF++A4fgaqbAalAAggGiSAUAdImrVJKBEAuqUvvr/iusfJN2iBCgwQzABxbmAAc4KpCWANYQoqH0PbML84WTgvAWMmG9Ac/6DEjUIE2s1FwMXM9DsP7RK/KAO62AEwPBiBNaYnF8Yvijf+HND8ev/r4zgYEZyPqiTDesfwzrzMO2gZufFPxfVRJlEf6szq18Ehv2zwZwBAAJo0PcBgCU6E7DtLvvq3ythUEmOJTH9/fj/oyDnf05DYEnEB2zqcIO0AUv8T3JMcnegGYjj0b9HYu//vefD1wSCGQm0i5+XiVcNWN3fBbWMRkI7H9QkY2Nk4wHWqNJv/r+RefvnLScw8TPgqS1x1aBgAKytWUUYReSA+PVgbg4BBNCgzwDAEoT96f+nqu/+v+OAlTho4B9QjuvtX3DTBlxNg9qpEkwST9kY2L6DSidgla3x4v8LYRz6MSIR2IRjvf3vtoIes95raDU+LAEobIGlPTMo8QP7R+JP/z5VBjZxuJESPkXNlyf/ngiJsogKADPAa1r0C6kBAAKIZZBHkDiQ4gCNz+MriaA1A6yaBlXhwEY846dfjL/+AtnCb/69Efn2/xszCRH6HzTnQGLpRzT4RxtjSQlXNmZGZj5gYpe69/ceqMn4/wfDD05g6c9GoJBgRC/p8YGfDD9BTU9eYAb7BLRjUNakAAFEkwxAjc4d0Az5a3+vaQGbP4zADMBEbGIEtWHFmcS/cDNyfwQl4u8M36Wf/3vOQUSkwYsoYA3yX5BR8CdykwvWsR4s4UO23Yz/uYDtdOW7f++Kvv7/mgcYtoxQv//Dk/jBasSYxD6rMqk+OP/3vDKwQOEkEKb/f///zXb7721pLWat578ZBmcrCCCAaJIB/jBQ3ncElhzcwBKKndShSKB6ZmCp/xGYEb5+Y/jGA+RzENHhZORg5PgPHTliVGdSvwm0+xYooYL8AhL/BYTATvWQa+aAhlp5GHngGRgYHtan/pziRCrJ8eZGYP/pPwsjCxMXI9d3bWbtm2//vxUFZiBOIgoUcM0MbE4JgWpgYDg+HozhAxBANMkAoOl3ciIKLaE+BnZixR78eyBIZNsdnJCBbf93ooyiz4EJmenn/58mwNKfkH5Gfkb+P6rMqg+BEfYJ6I6/QL3PhBiFwMsqQIkGmKGGZBsf1NQCLU0wYDYAZmvI8hBgjfjo1O9TOkDpX8RUqNLM0p/4Gfgf8TPxfwbWHiwP/jwQhdYaxFRjTEA7f/Iy8n4ZrDUAQADRJGZJHeIDRQywOmYAJlx48wAo9lmaSfo8sBTXf/XvlSAxTSCgHiYlJqVHwET87uP/jyJP/z3lAZbcePUBm0r/NZg1nrAzsH8ERuw3aMklDq0NwJEIpEGJ5e1QzASg8AQlPtAaHmjh9ESWSVbs8b/HokSMiDEB9X0DJuC7wPDgu/XnlhmwWcnNwEBUBDMCa47fCswKT4BNyh+DtRABCKBB0wd49/8deFEXaMwfNkkETMgfpRil3r9nfC8MbE/+I5D4mdWZ1R8DE+sjIP7zieGTDDDxsxMq6UDj1sC+hiiwtJQEZSBsRgMz5ndBJkHQ9P/1QRR38kAMSsR/ge4GDdvcg7JVgRjURLnFgLQ8BLQmCpggQZn6qxKz0jlg+Jo++fdEEJqY/6O190F8Rj5Gvt9CTEIfXv57yQJsEpq/+PeC2MQPNoeLgeuvCIPIc2DB8muwFhAAAZg1exaEYSAMN0nNYoxoIVgEKbjYtf//T4i4JrRTIDgoiKAp8T0nF0W3DBkC+SC5r+e4y8YsCYGIs4Esrz4eGhTCIYS+YpXxyc8/eSwItQTC+JrVB+yJ8HhbRIDlL+hE3hHKMf22Bhw7o2jSld0Z0ywKO3incqNbU44CXHtIJjdQamZHKy8gOc31CuxPXa8WBtGnIr1Xn68Nb/ZwODskqQug3oSKhVRARKJ7AzbdhzhoI0xQhXKCixRikH8oP8fdsRXtEb97orNzNYCnAMyazwuCMBTH2waNjPDQpYJI/wP///8iPHs0SLA0NBBa6/OkILokePE0dtj29ja+P942OV76yCGjTE/bUOc91vFZ/qFgpuQ1+Ne4aS5Ay2HWsEgkJhijtkE2BQNkk34jnvvHaMg6Bf2vaU9jqkEyFkkxuhJEXm4geZ25bMV+DdMF38aWXNlyVi6ssTsAJBdgcN71XkDWpn+F1dJEJVt81h4p+AA0lgDJsXk2odV2DvhcCl8chBmJua18FQ7IqXw57zDMKTHmU5eILwE0KBtmsAQGWlMEzAR/gInuiiyj7G1RZlFN0ITW239vuZEzAWgR1qN/j4SBtYcYNIL+EpP4gbXMR2BkMQKreAHoaNN/PG4CjRL9BzaHWP6DVgNQ2DkF9ZMozASPgQmb4SPTR01g04QTi39BJfrf1/9ei3AzcYNqiodgT/9nYgDWDrCM/QXYPn+mwaTxElR7yAAhUPgDJyMnFzDxMp37c04XKUyICVMQYFNhVrnPycD59A/jH4bBOgEGAwABNGiHN4CJWgKY+IVAQ2n3/t1jl2CU+Aasnv/9/fcXfQnlP2ApJQge5cNs7+ObuGEElmr/VJhUroowikgAax15YAkIWnaBdak0qKlx598dJT1mvb/AfsodSqf3YeucKOlnAeELYO2nCOwjsf/8/xPryMwXhi/Mvxh/scFWnoLs/YOUf4FmfIUNWsA6/sAM8EKaSVr4xt8bskSOwIHtBtUUwA72W2DNAapxgHU1JKMDM9mgXfgHEIB5q1dBGAaDMRkaUMEfCiJCwD6A7/8ggggdirQVuhSEkMEk3gntIAEHHToEGghtk8t9310+MhUC7LBARwZ9tBWPMJGWlzZamlgCphD5PVqAaU0VxUICEBbELOVRG9t1H/pPg+sRObeIohuMOQO8GkCdKl8NxnA0gyCeg6EL+KcFMs2hkEUNQvxEgD8V1p5a6BsISR2f3GHwNxFz3xtp7tiMj+HbvBtBialn+t3ncxlKwawCOWRzmV/hJeZNaL7JHol3d1ZY4qWMMheewrnoRo+ViWyyGeAlAPNW04IgEERHs0MfimzHwlM/o///ExK8dghrCVEqEZztPTHoUEhF0ILsaVfGeftm3uz4kwPw7lU/gDAttVwwXDMU905yDx9eKZB7EtGByEEBfU1GiQ0lPNZSHyKJNoUU8ycM2VSuWiEN2sHpZwi+vQlMTrZM27SrIjHxASgsUp/c+IZgYFep9iXSj9mfAATA5Jt9aA/TP+z3si5PcEO7TGDLGnZt2ZJA++6/i2ZtJmBtAYC7iph6CpXgj/HMIGQvmOMBf6p1NsZagv+EAxc4zy2xzuJdzT8LYI6bALSbMQuDMBCFbZyqDoqDVvojiv//b7hlKC0usUOh6CCN6XfiUqhFEbMnhHfvLvdeuF0SYO1MLSA1CLpHZatshdPw8ygqmaUHFV//JvYgiaWo1v6MBpAe+EUAL7I39MKO/XeEYlD65RVSpPCnhwiGRKoJaL3Ez56GRGaf/bHiclNar61aSVq26P1fkgyI16MZTFSoQibjRoPBOfdVrKYPvwQinxDPobEmAZdgSTxa1474aqtT7eksV3kTH+IzuD1JNnHNuo1x3W19BGDeDFYQBKIoOlLYIjJqES5CApf+/2/0Ea1cOMIQs6pmPFcsRIiQNuJOXeg4777zfPctBYHuLFYDimRtbDdmngtxrH4yda1RtbJaVY0CQBYHNohqg6mPIdhod/Zl95/qzaQPUv+J+5OhD/Fkg2Rkki0BpY980+C3cOGbKur8W001FyGFnwZCz+Ecymr/YJCmtZxxF3Aj/Crgef4eC4cAVnMvR6g815xs0AjF2Rtf2mBT1jCOfibMIzuoB7Q81qZWhz0HQw9FUlx530UGQCeABkUGACUQUIkNrIq/f/z70QxYShHbvmYUZxT//vL/S+S1Kf+AzRoWYH9BSZpR+gMwwf4WZBS8zvmf0xJYWv5HGwJlQI5kYIJkfvrvKRe6Ja8ZXnMBm0j8wMQNqlVw1nAgOQUmBfDpCqDmDWi4E98oF+x0BQoyACMw8TNDm43/cRQOjMBw/SbIJAja2/AbWOMxAfUJAN2nAlokCPTzu3t/7/ECE74gkP2HgYGBGpuAwKUDaFDh97/fIqDBDGAf5cVgHBECCKBBMwoE7Cz9BFa5X4DtcdACKtAyWiY8pRojqPpXY1Z7AeycfgVW10rIKzdB4w/ATMAHbIvKAdukD4CR/RW0kO07dGIUmAD+A2uIR8CE/PPWv1vyQLWsDIiFYf+xlerAxPFfk0kTnmGxleigkyhApToTFOLL8MjHuVDSlwD2Uf7jCB8GdWb1l6Cl4cAwBdFPH/57CHIfJ7CAsXj/7z3IX2xAddygLY0MDEQt1mGEmc2AtF8A1m/DlhGAhQ4nsA/CK8kk+WIw7oIDCMC82awgCERR2H4Ws2kVpBGY4AP0/m/QOwiFSLZrM+AmvPadYCDEYgiCmN3A6HA99+94z09HIabS/6fmNZ/nHeA8YrBdZVWKI0yiiGwxFIvims2yRhphGt513devbIXRUK+osZ1L3IGSZEMECsyOwGeUJ549aYyjlOnUzBKEl0Sz09hJBIgw7BVLbwbK8VttgM7idCUAW47voyBCJrrgYDcAL/5/eA7Gsbi/goPeK6fvY7+PbMbzOp94sURG5j2zd2+t3RO4tu8yh/6ZYOOUUrQxSbj/jA59CCCaLYYDrcWHlXCwg6oIeR606AyoDrR2nFWLWQvUKdaCbmRBiQtFJsX7wAR8HjRjDOo3AJsSbOiJAFTygdq7wA7tf2ANgdxmYQR20P6BahBgxxA0dMdCTFsXmJCY7v27p6rBpPEBmPDeoidG2MQWvap5oF18QP8LAEtvFmjGB5fOwI4uaOb8MTAM74D2NMOWP4BmgCWYJcAnZADb6Pdf/32tSWh4kwExS84I1A8y+y6wj/YIVpMCm3qgo1K4CfTZ/gCbqKIi/0VUgP28q9CZ/EEDAAKIZseigEpE2CluoGE3Yo8BAUUWJyPnI2ApzwZaq4IlA4BGMX6ANsCD1rEAS3nQOiH0mVAmISahb0D9r78wfJFA7ygDO79sv/7+UoLO6BJbBDMBm1LswAQnBIx0eAYAZXJQ8wfpFDrSmjA49BAqLIB2yj34+wC2zROUqX8DC4Vf0kzSoMVnd0F+g5nNDIVo5jKihQtKIQOsRb4Czfvx8t9LflDzEjS8+er/KzUxRrH3wLb9Z9DiOmA4cEEzAN4w/PX/17+P/z4KA1trzKAlGYMpAwAEEM36AIxIEJQZkCOBYAZg4AS1S3WAVSu23uYfYFtWFpihFECB//z/8z9YSnDQgVl/gGo+AO0XRTdAhEnkx5t/bziA7mInMgOAOpKvQSUZ9HQJWFODATamTm4zBhQ22PQTGm4FrXgF9oGegZZxgJpxyszKr4FuuQVqZoDa87DED6JhNTLUXBZgjSksyij69SvDV0ZgAcOKlgmYQKtqgRnqEbCDzfea4TVo/dNv0NwHaGLyJ+NP0OFYYDOB/v8AbBK9A+oWJdB5Bg2/suLy60ACgADMnDELwjAQhU2K6FSIIKXQrf//97iYtZsoFQeFRN8HDQRJKy7iFGhSCJfk3nt3yf1EBOeejhZkWDKEFrLx0TeEEgseive66cXNfeEgkZrfj3F0731CgPVUX2gucvKc5suV4Ic4b+hsN7SrdtDiX5Iw/kbjzIdLYtEWoApZWjxtCSX07Sj029RVTQbdiI97aZob4prxbPiEvrnnl1Op1LeTDghc8MtsYJx1obf9QRv7pP/OOiB5fRvKoRhRQagT1IoIFLWBtp8oJHRTqH5Fm/ybBngJILqPAkGHPOEjMtgCBCQGGnVhYMDZpiAUin+BNQgP6HiPJ/+f8GCpkplwRBQjMLH/ANrNAkwIn4AdaFDifwhM6G9BVTcwcTFBzxgCL4ijdZsfZDZowgxci/7/DW9mQe0EzVH8AWbIy7BRKJA8aBcb9FQHcDjD3AiFHKyMrLygZSHY2uJAs0D+fw7se4E6/EJ8DHygmXl44gbWAIz3/94XxhIX+OKDEZj4//Ey8n4Emvl3sNUAAAE0YMOgsIQP2j6JXgoCE9xvYID9AR1PQq75oLUo1/9eF8eyfBpfswK0pfIzaLUpMCH9BOr9Cox0CWA7X4CHgecxsOSUA82qAmuF78AOHXiJMSjTQI9lpHnRBgojUAkPaoJAlzRDG2T/GIBtbJQMglywwNhAWh3Yj5EFdmj/ghaqoQPQ8ozn/56rAwsIWWANwQxqXoKaPiQWPhhRDQxDJmDnWUyEQeQWFvMGFAAE0IBmANAUPqgm4AZC0MZtpFWJL6UZpaVBZ9VQkrDQjlEkRv2/O3/vgOz89/7vex7YSAhoTysw0YsAEw//53+fuXiYeD4DMwWoP8AM7K/8EWMSewFMmF+ATTt6HhoLGo8XBNrLDHTXa/ASByx9LFhGgWYC5m//vjGB98Zj1q6gpiXjrb+35KHteWom1P8///3kYGdh5wXNPA+mDAAQQAOWAWClFGzzyw9g302ASQAWab9AS2qB7W6uJ/+e8DDQ93zJv0g0mA0sDTmAGLIuhpHhJ6gPAlpgBovcV/9f8YMm1ySZJJ8Dmx2gah406/qVFmGGvHQZ6Ca51/9eiwI7tG9AiR+tOQbKIKCa6TqSOPhYQ9A9CXgHmEh1FuH4+f/y/0t2if8S0qATOwZTPwAggAZ8JhgWcbCOMaj9Cur8ARPSY3FGcdZnDM90sZ0JSmeAfjAXSukIzAygopfx69+vsqCj2PmZ+CWAfgBN+jwCij8g1AwkueYEQmC7GlSCgJZ087xheMOJpURnBIbhT2NW4+ugGWpQpxjUb5Fklvz85t8bblBpj565QBS24ydxtRhBe4aBfZS/wCYjOyF9vxl+M4I63dQYOKAmAAigQbMUAlZKgWb2QW1RFkYWFtAUvjaLtuDNPzelgB1TRobBe9Lwf1jpCoLA2gw0MsLFxswGmjV9BGpa4cv8RIygsYB2aEGXPcPEhYAJG2TPvz+gQRksYQNMnEzAZqYUkH4myCDIdvnv5UegWgqYCK0YMCcOGeWZ5T8D+wBcwEKIFd/IDugAAmDi/6rEpPSCj4nv/bn/53RAp0bjih/Q2UJQfzINtlOxAQJoUO0IAyYIbtBFDKCRFmAkyHz8/5EHNJ7Pzsj+C9gxo/auCtBcAXgyCFcCRari/5GRIUBDhEzIR5Kgl+SMjIQTP6j0BvYzBIEJUxWY0GGzvuD1To//PRbB5zbQLDewCakqxyQH2lMNOiTAnAGyNBmjDwAMAxYBRoEHfMx8nI/+PhIFrfHHMcLDBDTvlSqL6mXwMPR/Bg5QrYenKcTIysj6F3TI8WAsuQACMHcFKwzCMLS2boexHUQdHtyugv//PcJ2UCZYBZGpw2QJU3CjjrGTP9DQkry+hNdXeyWJv5eWPBVYOMQVHRZ4NaKxW2zljHL8CxvGg3ctd6DmVYuXLNoV4+z/E5EJLYcc8s0s+fD3wBJ5BGhylJtoHxeI4RZgrgxUOFWJ5TGFNMww87/0K4uFqFEfAhH4tBY7RPQjxzeBiUogecQqvkQquitQO7aiofhv9IZ9lEIVXjXomv8lo731nvRuNdTnhXcJ2GHHitXt9C3VmlShTwGYu4IVBIEguqthGxqJIIKgUOf+/yP6hbx2yJuE7Gl3e09GCKGIgugmLAg7zHs7uzPz5qcAkI0zubKR6SIGzp+AybZW2Ywpc0nOzCz6VXMMfRDHdI8QgMMueCn0Ajh+XxFWXWpdezCfmeUPeQeRSs7pgTFVqSnjssFaAqDkAMNavSEpyHVKqcA5DvgPSyeGBTimcujl0D9h9z0Yfwdns6LWvFKflSkzcRUDvIYAeIXVPMqHVrc3F5yHvSxAcO5c14QQHnVV4yqqeoQ0DrY4AphFpjP2VY88qZ80HukiKgLI7EQ7/Fse4C6AWGid4EFn9kBpUEki+uz/M5knf58IQgMC1OmCLS34S+Y4M3qiZwHN3qoxq90DnUoMWsUIzFTvQbPLoPY5MEFJgIYvQYUZsCNo9J3x+0voilBQG/sDsN/xGtRmBTa/wM0UYF/kM7A0fgvaUAKsNSSBHXMhYEkpB12pitetoM05l/9cVjdhMTmLNusKMRsIQc0jLBkAlPBFQPsaoHrISvzAhPybg4HjP9Ce37CtiXf/3uXB0gZnApbkH4WZhDmB/hY9//u8Csi90ASNnGL/gvZMA5tIoPVBoH4CE6jAAPpRAKngwgqkmKQ+gC4rGWxLogECMHcFKwgCQXRNErWLSoWHyJPR3f//h37AROignTyYZtDae8sKElIEBd304CjDe29m3GHmV81wzAstODAA8zc6hPZUDB6wjJzafwHwCli2YXMYE0fwlWwDBmBzht7GaCIU1VElK6voC2/0bubEAt+0HkDgG/4lnIU1bLUgTKbbKW647jhiEcA8wfY5MZMilekOCr+aIq5aNiEsE5kcD4HmUMttgJoVz9djAjiAnSvcKf8dYxEvsnvm6wL3Y79Ada9IZw5UZty3AN6SINVbcp5tyk50DhR/n8vce5XqcSOPCqI6Qg8zk979uWI7BMlCwv9TEfwQgLlzV0EYhsJwWy9oEQehU0ocHZ18/0cQXPoKUiPZBAuJfj9EkIIoRcGhawIl/+WcnHPyEwDgHS1WYaNyBhjluV49Zt+7YFHtylVzg2AvpePOMPRJjN2ERq/JWPy1dcEtAF3+gaUK+N25D75EBXR1b+RZ+VrW3aMKXcpgdBzKFmulhvEdB7xMXnucgK4MyQXGUwnFjJjG8A/Um3Dry7/6cl8Ewr4u6gNrbwHZaoACjExujuyvh/6kNFOC4TX7TTSxDeXrx0XRRbd0mXs7JGyoSj+GDFdF9VcAuAsgmmQAYCL884fxz6+P/z5yMNBuMzSoamcEJqwfEowSD4F9iJfA6vk3kHb+xvBNEFja/AHdC0xiLQNWCxp5gS7DZgL6Q1CIWUgUetcV3BxgJvgizSR9GrRCEphhON78f6MCLEGFgInuH+iYEKDYbVCkg5Z2g+9xYvhL9AUR0Iz2EWj+C2BnE9Q/Yia14ADfnfYfvKsOtPeBERgXTKCSGs+KXEpqY2Z8tQb4AvP/P/8Aw4Kiy8FpAQACiCYZANj8eC7KKCoCvZaUGiU+KOL+owfe63+v2YB2CL5iesUJ7Ij9BXY4n39l+Prx3d93uG6CBN8DAOp8/ifmeHtgOgKW3uzAJow0sKMM2tL3F7avAToh9QWEQe6SZJT8x8vMyweSB62mhJr/D1gLvSdn3y90ufFTbRZt0Pi9HKg5hyWB4ZsbQalamBmZQYcDgJafUGM4khFpBA+0NfUusLARfvDvAR8O9/wGhoMhsD8CO8R30CyHAAggmozNghaHAUtm0HLdHxRmMtApBoygw19VmFU+gUYc0BMpaIku6HQHYEKVA5YwLEC1oMvxPkNLJVgfgQm03kiWSfarHrPeFdAmcRISImgkhRUY0SyghWjIJ6yhldjPgc2mm9yM3Df/gY7KofyaVVCt8QXo53tA/3wFbQMF2s8IXZHKAswYb4D9le/YNII6/qBjzWlwJg/opIffwObdZ2B/6ZMFq8VtaWbp3cDMdRlYsOBrpv0Dup8L2KeRAjXDQE0/UjAtAUAA0exgLGBC+SzGJPbyz78/kqDTAUhIDKCUxQwq8YGB/F2cSfwDMNAfAtvnCsC2PD+2EgZY1YPGq/mBiZwN2Ka+BWyz//nI+FEbtpQXZA4wsTz49f/XQ9BQKNB9qgzErWEB54GX/14KizOLg45qfIi0shI8bIrubxqEJejg3ztCLEIsoLP5Qc2Z+//uswPpT+KM4u9eMLzQZkDd0A464Rm0eO8RGdsPCYUJaGHgNyVmpdPAPgVooADciQdtsAdmAJwdYVDt9YXhyz9SFyfSAwAEEM2GQUG7iIAJ7xo/M//Tc3/OmYGO4IZ25v7jcQto48Q3YM3xDrQJBVia3wWa8wEYeL+AtBQo8nGtOQGNTX9i+PSLh4EHNFlzX4tZ6xu0zQtqA38F1iR/gZEESkCg83pAu6dkiR2SA62jefH/BWgy6SGyOLXuQyaiT/AEtmQcvs3xPzNoqYMWMEH+hI6soW7UZ2TkBja9vsOWPUOHNJlx9clAdwfwMoCXoOM7Bv0fMOGLAPsmUopMindf/XsF7tiC1m7hyfz/P/3/xAqMG1Ac/RlsGQAgAG9XrIMgDESxdTIREmKjDnRSw+j/fwqGuhgcSNlIJLHW90gHQ9A4iIyESwfe3b27tu8m3QcAwEiBbrnMT2yzgaqswdtfC2Me2OL8KY+odWbKpjZ9HMUVbRFhO4I0SAjOPlys9+Fk6RGAqPGza9j0OjTIHkusrVCg7jhtku+01JbKBvjm2y1JIPCyGfbr4eD9VJtpOaoYzbAkQ867Btx6P6h3vH3YhRJqC0A3cARGXgkadUUm6UpXrt54mczmWaUj3RpnDqFzNobqe+EKDTCbRCSekuupSDkt/qddo389TwGYu4IVBIEguusmdigvQXjoIHjx5KH//48lugTFHrqkoLAE7vaeqUiBUBB08Co6O75xZt68+XknmAcIwx+IxnDOGOiek1dPJAdytDCgtt5W3MLSJ7oBHH+U65jSf+cMbJwh32Ctvd6mQXqjEhmpCHDcmMJZCMNjOTZ0IdXPPurHI3lsX58BUUHckSEgCfwW2enEM6UVJYw3Xe2c88cDtz9T2XODjlQVPvAG9ltN+xF4t5BanexDdP/QUlhEqyOuDWwfA4Te1Pf65uAV53Mug/KSiCQHaOymE2HD/blHoFgUy9rVe55XJKOT9PPEJipxD+Od/1QFeggguiyFALX9QOMPoE3qHAwcJ0GJDxSRoC170Akm5PPy/8ISPGiZAOgMG2JGUaCTMX9BywZAVx4hJzK4EigAlpAsJM5IMqozq7+GbTFET8S0nN4HzV7DVo0C2/7gcXQQBPU/QHMgwPDEdqAVaPQJ2FtmYoWu//kHbT69lmWSffzh3wdFoDx6swlUUwgDxZ8CEzNoiPfyL6ZfHA//PcTY8A6KD2CCFvnD+Ofjjb83VIHNG96v//DOjIMmGT9+YfzCBXQ3aFsm3r4GqFCg13ohgACi6wo96GjJf+gIyT98IyWgAAA1fcixBnkMAcv4NiOwEwc6i5SUYogZdNArqM2PjkHHopAzu0mseuSEAEp4wMzLcP/vfYZHfx8xvfj3QooJ67VmDH/v/LsDOt1aHHYmE8xOoHufqDKrPmdgAF/lCTMcPEP8/N9z2ad/n3KC9iED+T9FmETugzIZ2pAqaG0R28O/D1WkmKUeA5uub979e8cBbGbiS7H/Hv17JABSB5ptBjafuPANoNATAATQoFyiCoo02DEe1B7GAzbHfgFLrzfQJQFEOQdY6n4CJoR3+DIriYUARWEDqr2kmKRAVxEpApszWM/lAWZ/UPMSdAwiA2y7JFTvJz4mvmfAQuAJqFkCagVwMnL+BjarHgPVsz/+95gV1LkFZSxgWIGGWkGXjrODzhQFnegozCj8Tp5J/g3ICmDt/FGSSfIJqN/GwEAwEMAFEbCzjfdkCOhJIHQDAAE0KO8IAzV7PgIhhWfnY6QdUOIHdshB16g+ASYEZQYizwQCXbIBjLQPP3GcwgI765MG1Tbo7E5V6FmjoIumP8H6RcCmHmiU7BWwoFBnwLFU4ubfm6Dzk27BRo+AfgfNTIOani+ATaF3wkzCUu/+vlMAZoiXwD7YbWAz5gEw3L/C9i+ATpYD1nwXgImSTYJZQvwz42chUWbRp6z/WV8A+1WsoHgCHZGozKTMd/XvVXlCpTcLA8sf0OgRsAb5gZyhkfXRe6k0QAANyiuSYAkN6c5ggokUdLshsKPLBEwQrFgWZ4FLfmA7/iEw8m8D262MRAY0IzAB/AU2dT6CRqjwHVZFjHmkLgMALRUHNgOl3v97zwMsrWWAdsCPYgHtpQadcMeAZ53Qd4bvoBW4DBKMEuAMCupDgPYmyDPLg8bmf3EwcjwAts2fAvsRf4FhDuonvEY+wAxoD2gw4j3IDGA4vGZmYmYG7VMADVLAEi/4lkomoUdi/8XEoHeV4T2mHaj/M6h2gvVvQBd0ADvUDAO1ShQggAZFBkC+pOErEIICA1YKwTaCE0g4oBlS0LWc95/8eyJz999dIaSIAO1dBZX8D0BDd8BS6zcwsom6yh407wBs6X8HXS5Nwl5ZvM0XUjIAKMGA1vGDBhCg9yawEzvECBo2BjZXQGu6GYCFA3ifA6hPBbq7AFgAgMMclDFAdyKA2KB2PwcTB4MeEIKOdQd1skFzCKBlI9CMC17ICD7XiZEdVPIjx917YBPqIjBsbUA1E66aFdjBZrv576amNrP2MdD8zPE/x8GZYCABQAANeAYAJW5QtQxL6KASCjbkBxsKBJZ0oBGhf/hKWdDKzRf/X0gCE78gcuIHlly/QTfBA0ue+6DKBdS0+s1A3PUDoFIT2AZ+AXQDeB07Mft3kYduUeQYGcmp3n8qMCk8efnvJS/QzUQd4gsDwET/C9jOfwXseII6wqD9AO+Qd7yBwhXYMWVQZFYET4KBhiiBzRMG2MQZKMMACxPweUPiTOJg9cC+AM4+DNDcV8AMde8tw1uxj/8/ckIzDHJfkwlqLgcws3EAO/PfYEdmDiQACEDctaswCATBe4HYCPbRIoV/4P/XdsHGP7CLCMETwsVzRjy4xsQ0ibV4wrnjzuzunPk38lNJSWV6SBI5eIH7LkCv/E1pf6HC0Is+boILyL+lPQuVQVyFLIRRhqrEZ8SWSllvJ2zUKRvx2Pf/yPLwS6XIaanvpS4HfFQZ/UxPBgE9P33nugrPMEh12C7O452ecbByMo42JXjngXo+i4/U/UOgBPmVha6dj3G4KQk9RkBvpmRMaxIeGoi/xq029bVxTYX9CkPyGvxiyFU+zX7OwDceox9d+2o3wsug2lUhrm+F+K0MtApA3NW0IAgEUXclMYgI8qBCF7NTP6D/fxb6AxKWsCfBAg8ZxG7vicYeog+COnh3d9/Mm5ndmfd3A+BArGHUxyMPyU4yUOsKHmPyTJoUDGGrR1L/64KQ6IDw5YiNnfLV4lCGDUTglU75EtF9mySN4KMknlMtuDYC59ukDv/bwLNuIxmFhVMslFbzN4zA9AC82yVAurRDMNFNx9IC4VALAJ7BAm4ikx0YmEqdVI7nortSHFmBms2N04TIsdZ82sxG+NRNFQBcI9km4H3sU4vPw/rt6oXG2fjUajPCjCpdzXKdb5hHdHc9ppXZNRuDsU6xjPe8q/glBm8CEHc+KwgCQRgfU5DAS5fw1sVL7/8KPYuRQYftZrnb91s2iDBUgjqIIAuis864+/2ZvzrDEZCn0/CnHR+9kJuCf8LQlnZANg2r42/Za9FLlkGovRvZosuU1cPcCb10NwoAL1Iz/NmqVWXFV4RYG0C1aSGlCUfGzVvfbmxaJPP6fACRo5mVFrSGTluj9buzR88Am7TOa6pBQeLQtYPOvRavZec7CFCxkri7a7BZTP0ZYKnStf4dewnRWMyu6/T1EQsqdZbUZNAoghscOnHYAlvd76Lj+AvE+CGABiwDgBIV7K4tAm37n8AE9B6oRp7YvAWaDYZuJGckMoFQfSgXWsIyAEs+8JohSpcAgM7UBIbDMxkmGTZgggPtUYDd4Pif4rIIWqMACwXwdkfQKNydv3fYoaNroDVZxqCzVkFLV4A19idQHw3YHGMFq/8P2R4JGjECluC/QX2JV/9fsWGbA8AX7qCwAtYkoLscmESYRL6rMamBrqJ9RY3BB3wAIIBYBqr0B43yCDEKETUKAIx40BT+81t/b8kwkLY9kKjNIrT2K2glJGgZBaX7YUGZALRfWIFZ4T/bPzYV0N1mwCYJOwMDVYdSUGoO0CkYrxheIY+a/YdOUmIk6O8M3xmJHWDAlxmBmYsL2HTVUmFSAa3pounMGEAADVgNAKr6iO0kgjbYiDOKX/zL/Pff3b93pSks9UAnQH8Ddio5gG1VmmcE6HZAcL8A1NmnZNgPNgQJuh0e2F4HJQ6JF/9eiDz7/0zoN6R79J8meZiBmO1z/xne/XvHSqXaFbJ2jJGJH1gL0LRPABBAA7YUQphJGN4UIgSht7D8AmaCV6BRAzKbXIzAjh5os8gvXgbe//QcfgPZBWoKgTrTlM5uQxff/QGWtB+AGeqWHJPcHW4G7v/QBWaMWGo68K46ULMFtP4JvDkdGBI0qgX/UykT/nv77y3v83/PFbDd8ENNABBAA1IDgBIEtuMCiWgKPZdnkhe79veaPAPuaz1hKw0ZoTOP4C2RikyKz0BH9AHbttJvGN6wEln7UCWhwC6woPZsJ6hJ9Be0YY3h3w4NZg2hL/++aADb37ygHVggv4Nmx4EZ7qcss+zzp3+fgtb+SABrUD1hRuFvrEysvx//fcxH5rErNG85gg7TvfH3hiQ0/GlWCwAE0IDcEAOamSQzwv/wM/K/lmCWEH3x9wW2+71ABzz9ArZF2UAjGfLM8h94GHhAw6Cg+YaHoJGMX0y/uG//uy3IQHi8mQlYU3wEdjpfUmu2EjbqQeX1LqDFZaCJrpfA5tErLSYt5a//v4LOWP3LzcQNSvjgC/2A/gAN6f4E9h+egEZYgJ1aNjZGNqM//+EH6w62S3zBi+aAGVqElpYABNCAdYLJ1QeM2EfKjMr/mZiYdF7+ewmaG/gJnSBjFmQU/AJs3/9/9O8RE7DT+UeGUebWu//vXsIm10CJT5BJ8Cn3f24+YCJhJFBqg8Dnp/+efqLWEl2QGySZJeE3vNAo0dz5z/Af24QiiHoG9MszUA0MLIS4BRkE74DCENg/YXvz/40wtFYd6BoBNuMNXqkKvQeZZgAggAblYjgCtQCoCfUYdMvhW8a33MBErwGsDXiEmIQ+azJrPnj179U3LWYtfmAJB9r08hG0XxV5Igs0ni7JJCkNbAoRrAVAJTWo00nNDMDwn55jUHgHIb4C+xBXVZlUQSdYc7z+/1odGHZiwFqC/e//v39hd7jRIUMwQjfYgM8sEmcSv//m3xtRoHv4FJkVnz/7++wRLS0HCKAhlwFgCQkYae9ApztzMHCocDFzfQSdCQRa5gyU+wFsBr0EjbiAdqLB5hqQMtBvYOZ59J3pOyvo8FpcmQA0/gzsp3ACO96c0DvAqDf69f8fwaPR6VSY/AXNyILWCgELhfPAgoITdAiXLJOsFOiCQdACw2f/n3EBS2FmKmUG0HmloNOywScGAhP7T2D8/QEWXu+ANTLoDrafwDj9CswMqsL/hIWlmKQuv/73+jMtwwAggIZkBoAB6Lj6IWBiB3V2/8PWCoEiFvn4Eiz6HgowCvA+ZXjKj6cWAK3A5AcmBDFgBnhIzcwLagKB1gsNlmPCoUtRQFc7fYEOtX4EhtEtYBhxijKISn36/0nk3r97/MCaAXzWEqjjDDrUmJRBOND+bGCG+g00F3Tn8ltFRsWvwIzwCRjGoOUUoDuIwQefQbeAgs5XYqTC2UoEAUAADekMAF0b/5fQLivYcgaYGlBAA0uez6D9tD8YfmA9KgQUYaDrUj///8xO7dEbcC3w7x9opSnG2UKDJVxBi+KA+Asw7G4BS+hXwJKZBdhE4gVmBmVQwQBMsP8YEDPRyEfagxI7C1J4MwET8xdgJ/wtsOa9D70w+xPQ319gs+MYZ6YCa18yzjQiCwAE0JDOABRU/f95GHkeqDOrg+4m0ABGKMrZlqyMrExijGKgu4Y5gW1RNlokMNB6Ia7/XOCl34PtzHws7v0ALQTeADMA6PQOEWVmZZ73/96Lgk7NAxUWUHWgtTxfgaX4U9DZrND1XsxqzGqvgTXep5//f/4gYm8HXQFAAI3IDAACoKoWWDLdAUbOL2AzR/bTv0+cr/6/4gBNlqkyqz5l/s98H9j2ZQdmjk+0sB+03h60FAS0GnawZwC02vQFMAG/kGaSZmVnYOcHZghm6BVJ4AN5Qcc1AsPzI2jzDayEp8UcCLUAQACN2AwAjTDQArMHwozCT0WZReWZ/jEpAzPFezEGsYsvGV7+gpVUVN6bDG+G0cJcOhYgv/+Clu1Aj7SB+Ql2Y/1QAQABBgCxoV9GmIwtFgAAAABJRU5ErkJggg==',
	'task_flat_bg2.png'=>//12k
		'iVBORw0KGgoAAAANSUhEUgAAAMAAAABkCAYAAADQUT//AAAACXBIWXMAABJ0AAASdAHeZh94AAAKOWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanZZ3VFTXFofPvXd6oc0wAlKG3rvAANJ7k15FYZgZYCgDDjM0sSGiAhFFRJoiSFDEgNFQJFZEsRAUVLAHJAgoMRhFVCxvRtaLrqy89/Ly++Osb+2z97n77L3PWhcAkqcvl5cGSwGQyhPwgzyc6RGRUXTsAIABHmCAKQBMVka6X7B7CBDJy82FniFyAl8EAfB6WLwCcNPQM4BOB/+fpFnpfIHomAARm7M5GSwRF4g4JUuQLrbPipgalyxmGCVmvihBEcuJOWGRDT77LLKjmNmpPLaIxTmns1PZYu4V8bZMIUfEiK+ICzO5nCwR3xKxRoowlSviN+LYVA4zAwAUSWwXcFiJIjYRMYkfEuQi4uUA4EgJX3HcVyzgZAvEl3JJS8/hcxMSBXQdli7d1NqaQffkZKVwBALDACYrmcln013SUtOZvBwAFu/8WTLi2tJFRbY0tba0NDQzMv2qUP91829K3NtFehn4uWcQrf+L7a/80hoAYMyJarPziy2uCoDOLQDI3fti0zgAgKSobx3Xv7oPTTwviQJBuo2xcVZWlhGXwzISF/QP/U+Hv6GvvmckPu6P8tBdOfFMYYqALq4bKy0lTcinZ6QzWRy64Z+H+B8H/nUeBkGceA6fwxNFhImmjMtLELWbx+YKuGk8Opf3n5r4D8P+pMW5FonS+BFQY4yA1HUqQH7tBygKESDR+8Vd/6NvvvgwIH554SqTi3P/7zf9Z8Gl4iWDm/A5ziUohM4S8jMX98TPEqABAUgCKpAHykAd6ABDYAasgC1wBG7AG/iDEBAJVgMWSASpgA+yQB7YBApBMdgJ9oBqUAcaQTNoBcdBJzgFzoNL4Bq4AW6D+2AUTIBnYBa8BgsQBGEhMkSB5CEVSBPSh8wgBmQPuUG+UBAUCcVCCRAPEkJ50GaoGCqDqqF6qBn6HjoJnYeuQIPQXWgMmoZ+h97BCEyCqbASrAUbwwzYCfaBQ+BVcAK8Bs6FC+AdcCXcAB+FO+Dz8DX4NjwKP4PnEIAQERqiihgiDMQF8UeikHiEj6xHipAKpAFpRbqRPuQmMorMIG9RGBQFRUcZomxRnqhQFAu1BrUeVYKqRh1GdaB6UTdRY6hZ1Ec0Ga2I1kfboL3QEegEdBa6EF2BbkK3oy+ib6Mn0K8xGAwNo42xwnhiIjFJmLWYEsw+TBvmHGYQM46Zw2Kx8lh9rB3WH8vECrCF2CrsUexZ7BB2AvsGR8Sp4Mxw7rgoHA+Xj6vAHcGdwQ3hJnELeCm8Jt4G749n43PwpfhGfDf+On4Cv0CQJmgT7AghhCTCJkIloZVwkfCA8JJIJKoRrYmBRC5xI7GSeIx4mThGfEuSIemRXEjRJCFpB+kQ6RzpLuklmUzWIjuSo8gC8g5yM/kC+RH5jQRFwkjCS4ItsUGiRqJDYkjiuSReUlPSSXK1ZK5kheQJyeuSM1J4KS0pFymm1HqpGqmTUiNSc9IUaVNpf+lU6RLpI9JXpKdksDJaMm4ybJkCmYMyF2TGKQhFneJCYVE2UxopFykTVAxVm+pFTaIWU7+jDlBnZWVkl8mGyWbL1sielh2lITQtmhcthVZKO04bpr1borTEaQlnyfYlrUuGlszLLZVzlOPIFcm1yd2WeydPl3eTT5bfJd8p/1ABpaCnEKiQpbBf4aLCzFLqUtulrKVFS48vvacIK+opBimuVTyo2K84p6Ss5KGUrlSldEFpRpmm7KicpFyufEZ5WoWiYq/CVSlXOavylC5Ld6Kn0CvpvfRZVUVVT1Whar3qgOqCmrZaqFq+WpvaQ3WCOkM9Xr1cvUd9VkNFw08jT6NF454mXpOhmai5V7NPc15LWytca6tWp9aUtpy2l3audov2Ax2yjoPOGp0GnVu6GF2GbrLuPt0berCehV6iXo3edX1Y31Kfq79Pf9AAbWBtwDNoMBgxJBk6GWYathiOGdGMfI3yjTqNnhtrGEcZ7zLuM/5oYmGSYtJoct9UxtTbNN+02/R3Mz0zllmN2S1zsrm7+QbzLvMXy/SXcZbtX3bHgmLhZ7HVosfig6WVJd+y1XLaSsMq1qrWaoRBZQQwShiXrdHWztYbrE9Zv7WxtBHYHLf5zdbQNtn2iO3Ucu3lnOWNy8ft1OyYdvV2o/Z0+1j7A/ajDqoOTIcGh8eO6o5sxybHSSddpySno07PnU2c+c7tzvMuNi7rXM65Iq4erkWuA24ybqFu1W6P3NXcE9xb3Gc9LDzWepzzRHv6eO7yHPFS8mJ5NXvNelt5r/Pu9SH5BPtU+zz21fPl+3b7wX7efrv9HqzQXMFb0ekP/L38d/s/DNAOWBPwYyAmMCCwJvBJkGlQXlBfMCU4JvhI8OsQ55DSkPuhOqHC0J4wybDosOaw+XDX8LLw0QjjiHUR1yIVIrmRXVHYqLCopqi5lW4r96yciLaILoweXqW9KnvVldUKq1NWn46RjGHGnIhFx4bHHol9z/RnNjDn4rziauNmWS6svaxnbEd2OXuaY8cp40zG28WXxU8l2CXsTphOdEisSJzhunCruS+SPJPqkuaT/ZMPJX9KCU9pS8Wlxqae5Mnwknm9acpp2WmD6frphemja2zW7Fkzy/fhN2VAGasyugRU0c9Uv1BHuEU4lmmfWZP5Jiss60S2dDYvuz9HL2d7zmSue+63a1FrWWt78lTzNuWNrXNaV78eWh+3vmeD+oaCDRMbPTYe3kTYlLzpp3yT/LL8V5vDN3cXKBVsLBjf4rGlpVCikF84stV2a9021DbutoHt5turtn8sYhddLTYprih+X8IqufqN6TeV33zaEb9joNSydP9OzE7ezuFdDrsOl0mX5ZaN7/bb3VFOLy8qf7UnZs+VimUVdXsJe4V7Ryt9K7uqNKp2Vr2vTqy+XeNc01arWLu9dn4fe9/Qfsf9rXVKdcV17w5wD9yp96jvaNBqqDiIOZh58EljWGPft4xvm5sUmoqbPhziHRo9HHS4t9mqufmI4pHSFrhF2DJ9NProje9cv+tqNWytb6O1FR8Dx4THnn4f+/3wcZ/jPScYJ1p/0Pyhtp3SXtQBdeR0zHYmdo52RXYNnvQ+2dNt293+o9GPh06pnqo5LXu69AzhTMGZT2dzz86dSz83cz7h/HhPTM/9CxEXbvUG9g5c9Ll4+ZL7pQt9Tn1nL9tdPnXF5srJq4yrndcsr3X0W/S3/2TxU/uA5UDHdavrXTesb3QPLh88M+QwdP6m681Lt7xuXbu94vbgcOjwnZHokdE77DtTd1PuvriXeW/h/sYH6AdFD6UeVjxSfNTws+7PbaOWo6fHXMf6Hwc/vj/OGn/2S8Yv7ycKnpCfVEyqTDZPmU2dmnafvvF05dOJZ+nPFmYKf5X+tfa5zvMffnP8rX82YnbiBf/Fp99LXsq/PPRq2aueuYC5R69TXy/MF72Rf3P4LeNt37vwd5MLWe+x7ys/6H7o/ujz8cGn1E+f/gUDmPP8kcBa2wAAAARnQU1BAACxjnz7UZMAAAAgY0hSTQAAeiUAAICDAAD5/wAAgOkAAHUwAADqYAAAOpgAABdvkl/FRgAAGDJJREFUeNpi/P//P8MoGAUjFQAEENNoEIyCkQwAAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAmg0A4yCEQ0AAoiFLrZUMg60P1mBWBSI5YFYAoh/A/F7IH4DxP+AWBCImYH4P1LBcAeIXyOJjQTABcRqQMwNDSP0iAOF0WcgvgGVpz1op23wAwQQyyCMBFCg80HZn6iUAEEJ3wTq33/QiBYBYkmofRxI9jBC8d0RWCD+BWJOIFaFhtM/LHHDCC1QrgDxr6HuYYAAGowZQBiIraAlzDkg/gIteXBVI6BI+E4gQ7FDE/0PaEIH4Z9AzAaV/4Om/j80kjmwmM2EJWEMFwAKk9tAzA+tMf/iaDZrQNUN+QwAEECDMQPwQ2sAUKK3B+K30ATHh6XPAkqo14H4Pp7IAKl5AW3OCEMzASOS3H8s6kF26wDxMaRMwwh1mwi06fQZKYEwYsmkf4doRgH57QMQiyEVBthqCkFo2PweyhkAIIAGYwYABf5LIBaHlsCSSIkMm/tBbdZHBMz8CsTPgJgXqucvAfWgSBcCYhloU+gntBaxhJaMT6H4BdQsUJtZCoh5oIn+PzQhPYHaPZzAP2hY6ALxq6GeAQACaDBmgLfQxCMObZrg6wOA5AWAWBraacUFfkAToyK0KfSXiEgGNYG0oAn9J7SGuQ+1Txbamf6GVPJzotUgylA/XID2ZYZbXwHWTHo8lD0CEECDMQMwQxPMZ2iJSqgTzARNlIwE1OLrR+CKZBZoPwGWsO9Cm2I60EzCjWTnb7SMyQStnUD2nob2ZQYqPNmIaK//I2HAAZbJ2Yd6TgYIIJZBWro8h5ayfES0o/9CMwooE7wnEGmkJpwv0NoDpv8PtBYQgpZ+fwgkKFANoQLE76CjJn8HIDxB7tQD4odIfRn0QQLkmpfYcPo3QP6hKgAIoME6DMoMxcRmGBloYjuFx0wOBtSxfmJKuadQc5HFXkMTsw3UPEIZ9De0BGYeoATDiNRk+4ujFmSCtufPkpgJGId6BgAIIKZB6iZhaFv9Pwl6ePFUyczQGoKVyJEZWOl/E0eifQutpZiJzEgDOZn2lgExp8GI5B5k/A862KAOLRT/E5n4h/wkIUAADcYM8BeaAYQYiB9G/APNAJJUaAIxQsPlAVLzB1up/p2EpgLIXSIDVGKC3HoNmhFg7kHHf5HSAzF9ASZozfh+qGcAgAAajBlAENr2J6W58B+aAThwyLNBMxUHgXY7LAOAIvY2A+4hPmYS3AWyDzSmrg3tq9Ab/Ic2bz4RyID/GVDnMxgJlPz3GCBD1kMaAATQYMsAoCYMaBpemoSmAxNU30do+xwb4ITi3wQSASu0ZD/PgH/8ngua4YitVX5A+ymSAxTmvEQ0KWFDyvLQcPiLJ7x/QzPAkO8DAATQYOsEC0MTCqgU+kVk4gcl2CfQ0ugtjhJLBFqz4MsAsIh9BM1IXEiJ9TdacwiUUKSIrKVgQ4bfB7DNLAjF/wk0PUE1lAKU/ZdAAv85HPoAAAE02DIAaHKFm4hmCjK4Dm2usOJJgExEjMIwQ5s+n6GJQAqqDzZC8g5q1mekdjIxHUF2aOa5DB1V+jdAGYCFCLv/QzM3vn4ATBxWAHyBhhvIfFEo/QMaXn8GewYACKDBkgGYoO1kSRI6rCzQWuIlNKAJjcn/JZBg/0L7CBrQzMCN1BYWQFID6lA+hNYUCngSFRPUvK/QxA9bUjEQhYo8keH6H1rb/cdToPyDho8BNA4+Q2tMVmjtzQj1500oHtQAIIAGQwaAjdHLQzExVSsjNDE+JKKpxAltWhEy8x9ULTNS5xW5FGeEqmGBNmdACVoWR83CAtX/Gqru3gAlfhZof0qYSPsZoeEJCwtcBQYjUoeeA2mECxZGIDFNaGa6P5ibSgABNFhqAFAVLcGAGJZjJMLdoIC9ykB4sRkzNAEQ06/4h6NER171yQWN4NfQvocSA2L4kJEBMY7+gAGyceQDw8AtGBOEuu8vkYkQNprGQKC2RC4gkBM+8p4KPmgz6d5grgEAAmgwZABQwhSHNjN+MRAeqmOHJvob0OoXHwBFAmgBHBuV2t5M0GoetAAMNBF2DpoR1KB2PYbWSn+Q+hMDCTigmf8XifHBSEJ44VpSDlsrJcAwiIdLAQJoMGQAUWgiJXaGlhWa+AgFqgC0nSrBgLnphYGEyGVEK+lA5qpCM+EnaCf8BTSxfWIYuEVv2IAsA+nLL0iZuUYu8bHVmqAaSB9aUHwejBkAIIAGOgOwQSOJh8hmAiO02fGQQIYBdfyMoO3ffwykL0eATQixQ931B6k9ywft/IL6Aaeh5r4bpAWcFAPt1h/BCiNm6KgPsj2sUMwGTWO3BmsGAAiggc4A/NBIIiaBwqrmW9CRn384MhSo1FGHNlXI2ZUF2/MKKuFBewyEGBC7wM5DawDlQVbSYwNaeEZyKAGs0DD6DW3ywYaNkddhfYLWiuzQguLzYA0kgAAayAzACu2gEbvsgRFaAr9nwL5Rhg068qAAbY78IyPxwzLZdWgn9iPULB5oRIJKulfQ5tdg3u7IAg1bYuYpYGufGInoLMN2uv2Gjm69hLIfQAsFIeho011oTQ0bHBi0u8YAAohlAO1VgXYe/xAR6OzQEvcaNFGiqwcFPGw1IxsDYiaTlHYvMzTCQJ3rywyIxW4/oXYijxT9GMSJnxlaCxJT+jNB/fKSAbHf+TeO+IA1dU5B4wJ5uPoltFCAjYD9ZBgiewUAAmggMgBsubMkA2K8nFBNAZtJhZW8sEkXdmiAy0E70wxkdvo4oCXgVbTEPxSBKLTjyUlk6Q+b0/gGrTVUGbAvG4fVJtxIBQITUhj+ZBiYuQ6KAEAADVQNIAhNtD+ILNGeQatUWKSAmk2GWCL5P5mJH5SxHkFL/6Gc+EFAHDry9YuIggjUz7mI1Kf6AC1AWHHEw0cobY42+vObATEpyYhlNOgDwyCdDAMIoIHIADwMiMkZQomTHdqBeoCWML9CA5WTzIQPA1zQNv0phiGydoUIcA9aw+IbAYK1958gJX7Y8C43jjY7bE0VL9RsNrRmoRgD9u2Wf6BhC+q/PR9sgQUQQPTOALDteUJEZABmaOA9YcDcpgeqOS5AawIuMtwBK/lBkX+CAbHQbTgAUIFxEoitobUBcseWEanT+xMatrBM/wupCYOt8/yLAbGqlAlL/PHicZMANGPB5k4GDQAIIHpnAE4G4he8wdr6UtCq9x5Stc4PzUSMeCIMX8YClXCgIc3bDAO7TJlWAJTQnkITHS8DYgMPbD7jHtTvX9ESOKggkMYTnkwE4gtfc4sb2j/5MphG0AACiJ4ZgAla+ouRkOB+QatzcWgbkwFacoMOZVJgQKy/YSSx8wtbn/+NYfiC29AwU2RAnH36jQFx8BhsdSwvNHxhB5F9gyZWagLYGiNBhkG2NggggFjomPj5oKULG4mjBf8ZEMOaoD6BKQNk1eg/pFGMW0gjQT8ZCC+mY0QrzRhx1EosDIiVoF+GWAb4BcWXccQHqC+mDg032IkZTNBa4h8NMsB/aPyAau63gyWQAAKInjWAHDQDkLIwC7a2XJoBcbSHDANieQMLtBoHHVPyGtq80sXTmYUldFDH9z1ajfIBmsiRRzZAiUQfWmo9YBjaJ7zBMj0/A2LOhJcBsVeC1LkTbE1PfE1RRmgNIAntp/weDE1PgACiVwYQgWIWBsL7ctEB7IQ2I2ifAFaaMEI7w1eQOrR/oU0sAQbcx3vASvN3UPOMoKNS2JZjwJpXOlC7BnsGgK3PgZ3aAFuezQYdLJCE9qlgnWNKE+FHqD1MSKNqvGgZgQma4GHNWVCT7AV0YGPAAUAA0SsDiELb7N8YyNtI/Q+paYN+IsMPBsR8AihQQUOaDtBM8RtHBvgHTRTa0MT/D0/V/Y+IDuBAl+ys0CamKNQ/oAx+BhoGatBmx29owv/PQPmx5rDa5Bl0MAE5nvWgCR22kBBU0FyH1rjS0Np50BwYDBBA9MoAUkS2zfFVo7w4EipywgRFtDU0MeBaf/IbqgfU7FEmYUTqCcPgOAwKVqLDOv5CUH/AtiNyQktYQ2iTkYMBdXM/JYn+P1J4MUPj9SEDYjXsa2gBJAGtbUCJ/hEDYh/1oKtBAQKIXhngKbRpgquD/BvaLueAqvtN4jAbrPoXgJZyXwl0xpAncohdiAe7MOI7mjjsGEdsm0L+0CDDwI4ml4QWKpxIGf4f1O9C0HD8Q2bbHt3vf5ESPXInWxBasyMvDvwMxXcYhsDwMkAA0SsDfGXAfbMKbFntE2jEkgu4oYn/BxERyslA3GpRJmgpBhsxeY5FXg7aQf+Blhl/Qf31joG6qyHZof0pYaQE/p0BdU7kDxl9LXxp5Ck0Uatjad9LQmuBt1gKgEEPAAKIXhkAWzsbtrrwErQDq8aAWI1ISukEiwg+aFOAmIAnpkRkgibiR1Bz72PxA2yyTpEBMcGG3M8AZY7bUEytFaTvoSNSsAkuXOf3UJr4YQMA36EZmQ3a6eVDMhtWC0hB3fUPjzmDcvk4QADRq2MHu2uLCSnxM0AT1y2oHA8D8UcOIidA2IFOegzEbayBnRHESIS6z9Cmzydoh+8XllLuOQNiIRhsZOUXtBTmgY4gqTBQd3PKI2iGpFYpj62tDzv+5B60FoNtcvmHVvCAgCYD7skz2MZ8UPOUH0oLMCBu6xlQABBA9HIAaE+oMQPivP+/0FLlElJC+sFA+rbFZ9CRJV4GxNk9/wno+YPUnsV37AfseI/7DIiN7riad1eheqTQzPwNbbJoQ0vIp1QIy//QxHgVmugkGai79h7m91/Q0aRn0IwMu1UTean0P6TOLa6amw8a9z+hYciIVLg8gRYgA7ZjDCCA6JUBXkKbAZ+hCeINtJMEaxZwMiDW9pMSUbCzQAWgiYCZCP8+ZUCc5MDLgH8FKCiTEjrc6S/UvGvQZgL6xRm/oBlJhQFx8w01wGdok0SaATGD+59Ac+YfEWEKO2TsBzTOQJnAjgFxJRTMDFaov2Htf1xNvFdQNbpo/S7YgAWsFfCRYQBunQQIIHpWQbC2MLZAF2NAXGBNrLs/QSMA1AE1IaL0h+1+ekREexyWoJ6QUCq/hLpHAi0DwE5bU4BG/jkG6o2Dv4CWzhwMiMk/5PN5mNFKamKaxF+hzZ430MTLD03sv5BqN1j43MERp8jgG7QQgQ1S/EcygwXavwIVGrBtqHTdVAMQQIPlYCzYCQzElgCM0NL5K7Rk5SCQqGGdxTvQDCBBwO//kEauSGma4JrrgGUC2D6I2wzUOTDrFTTTcUCbWXIMiDkCWBMMNsoG6mdpEQhTZgbEgjjYiJc0NG7+oo3+PGLAfzEhesf9LrSgQ76DAFZQCCO5DXaUO122VAIE0GDIAGzQUobYk8vYkTqloMQvQ0SpwcyAuCoVNi7+B08HkgUauS9IzMTcDPhPU/uNVOLdgWJKSjxYgQEy4zK0KSIFdT8TA+LUBpB/udA6udgGQz5DM+YvJDFeKJ+dAXUL5E0G4icGWaDmMOFQ/xWaBnShbrjCgDjzlaYAIIAGQwbgZkDcB0BMFf0X2maEHWRL6Ewh2ETOB6SmhyS0c/YHh/rvZIxICUA7in8IZGDY7K0u1D0PqNgnuAlN9IxIzQ8JaOkqzoB/vdNPaIf/JgNimTg/FKMfe/iOgbiTMRiRmriKSE0nbOp+QNMCPwNiCQXNMwBAAA2GDIC8pp+YUha2jl+OAff2PfTAZYAG6Gckc3CVRrDSn5TFWhzQCBZgIO4I8u/QEpGbBuH5DcmPoKFhdaRm0R8czZ7P0CbKIwbUPRJc0PDgQnI7bP6GmPiC7QPQgIbNHwLx9BOpUKTL5RsAATQYMgDyiczEBOhvaOKXYCDuuiPkqh0GYDOouJoDX0ho/sAWnCkykDbhQ8vzcuQZEMvPWRnwn/fzDToydp8Bdc8DE7SpxsGAOovPCG2CEtN0Y4LGkzgRgxQMSOEH269A8w1LAAE0GE6G04AGMrG3rXAyII7c+0ekntdInTo2BsL7iJkYcC/dQAZS0NEdSQbi7h9DH24UY0ActEUNwAFNbDrQjuVfPG6CLde4Ae0bfccSBrCbN9HD4S2BsOFAGuaEHSFPbNj8gTZP5aCdZ5oeqgUQQAOdAWABRewJZrCZT04G4s8SBSUC5DFmYQbEtkxy1qswQjOuNDSSRBjIuzT6NwNiDdFZMtwCa1vDJhdhk1U8UExoId5faDPvJQP22x5hQ5WMOPoxrEhxACssuJBqHuS9AaTeQv8fOmIGm4WmGQAIoIHOAP9JaDbAIuMmNOEQe0ndJwbURWzIzR9SAQ8D6hGMsKbMPzL9zgo15ykDeUeGKDEgTtZmQqoVCSU4WOl/lwH/xNwHaAZlY0BdCq0AzTgMSAldFtp2h620/YeUWUjN2H+hhRyo/3KclgkQIIAGKgPANmPD1sgQOwL0DjrKASr1BInsAH9Ha2LAzBBgwH67CyOORA1qDxtA9fAhVeuUrHr8hlSjkZOB/iIltr8k1IoM0AT8Bk9b/h+06ajCgHrwLWw5uSg0HGCXGgojFWjU2O74G1qT0BQABNBA1gB8SKXXPyJLhYfQ5swlqH4+HAH9H63qZUSr+u9DM6AMlubXH2jAv4FmHNgID+yya0o7sDD3gNwOOl79AQP5M8PPoKUxJ4m10A8GzMPGsLnzExQjX5cEq21MoIUXO1ocUmvV5z+0jEcTABBAA5UB/kM7Uneg1RyhY8xh48SvoAn0A7T6NmbAnNBiZkCcPPEFqo4RS7PoElSdFNooyR9oQrdCMpsLau4/Cko22OI6WNv5PQNibzK5ADRSdR06kEBMTQorFG4yEHdjJSgMr0FrS06k2hJ2Rig5TRxi0gZsUvEprRMiQAAN5D5XUOnzCFqiE7qZnBFaIiOXWHehEcnJgFhqzAhtVtyFZm5maAL7iyWQP0ITELYxbdglcILQDi8zmZ1m2GgSK9S8VwyIS/OOQUtwSsa7/zAg7iFjI8Is2OTeGyJHZf5Cm0oPsNSklBQGhAZGfkMLxyu0ToQAATTQneA30ERsQmCojAXabkdeKwRiP4Em0D/QThgDtDN5mwGxrgXfHMMjaA0ggZbAKSnZYBkWdus8rG3+GupX2NEr1Lpo+h80gbIijUgxMGAuiIMdjPWMxFoHZN5laAaD1dbUmKFlRGPDCovP0ALiFgMdFsYBBNBAZwDYzeygiFNmwD32zoSjYwU734cd2qTigpawb6ElLBOBQIQ1QYSQwoKcE6ZhIzCwJdmwlapvoSUo7JAqWuwR/g9NMO+hw6LyDIjZ2//QkhTWbn8KDTNSlx3DChvYGU2CUDP/MBA/I4wclyxIHXfYTjrY3o7n0IKRLkujAQJoMMwEf4eW2FzQkhgWSLDAZYNiJhxV9F9oM+YaA+oG7h9ERgzstkk9Btwb3HGV8rBmFuzMoLfQiPzIgFjRSOtVjbAC4y00E7yGuo0dSfwXUniRmwFh+yhAGUEf2i/gwVOTIe9RhsUfLF6+QBP5I2g8MyEl+r8MdNxPDBBAgyED/IdG3DVo4MJujIctwX0BDRhCIyXkJjTYKtGf0JoI1qH8hSOskEeYYKX7e2gmhi3VGIhTz2DNtjd4Sl9KMxps195haJNTCTr8yYIl8f+Biv+ENl9/Qkfe3kHDCnb54F8qu5MkABBAg2U/wC9oCfMMOkQpDA1gNmgJTeuVgV+gGeEnNPHDthoiL/99DQ2vD9A2918G1PvKBsuVQLROSLAmJey8fwNoYkaeBwA1ZR4yILZBfkAq6QdTWDEABNBgyQAMSJ3Q7wyImVFck1K0sv81tI0sDG06MCGVfs+hTZu/NGrLDzXwB1pwnGRAnI6BXBtjC6NBdzIEQAAx/v8/0uNxFIxkABBgABGhWYIIo97YAAAAAElFTkSuQmCC',
	'task_flat_bg3.png'=>//5k
		'iVBORw0KGgoAAAANSUhEUgAAAMAAAABkCAMAAADnj8/NAAAACGFjVEwAAAACAAAAAPONk3AAAAFrUExURQAAAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAP8AAF9Kh2gAAAB5dFJOUwABAgMEBQYHCAkKCwwNDg8QERITFBUWFxgZGhscHR4fICEiIyQlJicoKSorLC0uLzAxMjM0NTY3ODk6Ozw9Pj9AQUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVpbXF1eX2BhYmNkZWZnaGlqa2xtbm9wcXJzdHV3fH0QYG5LAAAAGmZjVEwAAAAAAAAAwAAAAGQAAAAAAAAAAAACAAoBAHWGNAkAAAxFSURBVHja7ZsJVxrZtoAPVYwKKmocMEaRJI4xqG3QCJioICLghKjMQ525iiG5/d77+Y/DoPa93a+hH9Vr3XX5lq6yWKvOrj2dvc9WwZAhQ4YMGTJkyJAhQ4YMGTJkyJAhQ4YMGTJkyN+I9XDfbTFbzNaxUaMB/H3IksFkkCQZ/H8ZV7V04vY28cCU8Az4+1iPfF89CUe8NiCQJIMA/AXMfswFDDEt6gB/G4u8WlRrdeVDKwx8h2/fLa+4zOAvYDnhGAmgVpgG+mKwjY10gyYIKYIIbwizT9zWEcLV+2UZ9I+8XcWoBSvO65wF9nOU/GXW2pJiuaDCasGFMRnIu5hjjBt/KQRsYRWjNjX/KNAVS6CuNWBkXpIdEyPHTMjU6ncfAdgmQhsMN+T+vfquyFEHnvfo7ILpJEL8Z/Ljh3j6sYCRgNZDEvhMaEubiKN/B5x03x/DIj+QgF5IkzPOSc/BNUeIYsQ0jaI29P4NWMrz1o9wtu8MWCsy1IaVjg5OJw0AGEwyGDymk0ahUIIVjBCmFGMobMZw85t+taxlKRYKKC7biG2kH/HOggq7liilHjJXm6urm4fLJjB4ppQapZggAUYKQoiQCuP84ebhyyNpvX8qFQmdn0dcffh1lxPUhTBK1R8/f/78cTUBBo/5U6XrbExLadi8hN9Hri5Xxz4V60jArh4aPxo/fv1m7DmAlgsabC/JKHqBzkt6VIGtkkoJFpaKxorNS8wBTFYjkFYLBAkgwk0gKy/1rECoU8QIfCiwZ19gJWjWJY+D+AkRlny6frNWxIhG93x7cybgzPBuDCCFUDV9cwR6xHGhtc2vRmc2UmkFdzV4MulSiF3b7p0zkrr/unlRQRCzWq12759fV7iQiUVqnHr3btyOd6BHPkEs9Oa1vAeAOR8kXQXyy3qVsjlfoc6zZQ3zemsj0thTDmIEqYqat/wMgH7y77QOESKlePC9aAo38LMC9EynZmj5QYEYcUq1zHWsbb6qihFUlYtriCGlvn58v5bHCKn10LjYeMcmj0knIbBGvTq8/KjTsXijcdyE5b67rTNZ3MpcSgkr702cEIwgU/pwwEeFY0JTgRUgWL8qCwcQypma2LPqkMJrmeskw5AyFbHzKXnWeURE/pWzZVyL2u3XrXao2sehZK/KuXo3J5lFn7gcYaRlj1Jsa2cO6IC0XKwxhEglHI3e5c6CT5enGKFK7nH9wwEpnhxnCUKQ/s9CH4cxn9/vnwctZpO0Hfxxt6Xj8cE3E+u0DpFW8biiecYop6Ial+7TTmDeRVUkUprmYhP9rGixmIAkS+Kkca61EoDfbG993t727mxPy2DQ2L/mNeWxmDgvU0QwIqqQWDkxATB6gLiQr929Bf0hv4+cri/tBU7vcKdJgbgJwjjkGHwQWedjWr5MaUsW5deiKhMvAKNfrosENSHF290eXeDYEGx6UxrNpBXEnmsYbVNtelYH3ucoQrShYUTgvlskcWHFenDd2j8oEzldud2d7CV8Z7OoJCAEE0qE0X8LqawDHTBtlSkpnN0hzO8XQ0Logy9SUbGQmCtT4RKKk73E0egXyEkT1KJVAUQtJ6gLC+pSydYrjBY3DiFWo6ekLZsi3D6PXOQ7sfXd0sNK5gB+trZKSLWqMq4yVO1+Sm/1iCHHhcjcdBYhdp9tKXDzrSouENdiC9cdlXI9HcwmMypsP1o63Q7Fvp8FYzfhT8FCxwkYHchg4Ewk1LbDMVJgS0x8/lY02bX6zaw0lWsJx5UP5l68eapUCUS8rt1NyBazyWy22Szyu1I3itS4HQwc+TOrEo1VGxpDbWAFojyrP5y7DAD4uZBO+L1Z6sWdH1O4SnLX/N4CzNMOWQZGM1gluBNX5LtNj9GQr1y8yfs395801M0/erKy4TAbWgdnlSJSztH93nrbkfWnjNv8dmsvGs/fXx3tRBKhSKW7nV5PG4AOGDd3neMWSXZHYNcJ9Gq8K8q6k8PUv3C226tsix1IGwmFUUK5UiKUKrCrQNAAdEGS2hFsdyW66Ua+WRwLrjae/J4TSFKvp1T0mMmWcWcdjF7A5ADojMX1PBp62A1TSlpgTM68vsPP5p4U+FCl6Pfh2UmgHxMrnuX1+N2rjZyhF5im1gtzvS0Upbj7lKqSVw6gCacedcxgcVg3wqcpMdhiDP0htPyht3h8o/D2dgwjvkCGvCygrA741SWDJFsWlrzZCuKctWKlG7fFooLEB4Ln+cShradl7e5TjAksqtFRYBmPvihQXB5szMz73h8Fg6Uqpc+Zm8eEltNFmg8cpR4uzkJn8UKlhCkhoqfRSr021nZPhnybPPBtBd7Ybp57UhywgEGSzvAnraqR7vIE4VrwJBY991+RJ1JrhI3CnEue9+G761uFEnJ/F+i5OM66nTO7CdwIn78MhkgADBTEX3XsGCoQnXy7coWil/kKViDU0vPPtclmD+L8zvTo7P8dO1ajLDCOTXnW9iOFqoYRoxi9eAAMlBh5ie5iem9+OpR9IgXKGSXiEKWVto3gmZHFeRP4E6yXuxubTTa+KojXGgy380k3BabijArHUq5cLI2aAVjOaPhFaqn8zQr6wnqj8haMUYIh+icwOQaD5U2eEyKOwkcyEMgraUZEMkAF47R/fXFC7q8jeXvLMPpDWMkLBotpY79EEp93Np8t7U5BXFFKmSTDT6Fw7LTfwrlJybO5u3tb95bAp+lBlzAAVn32V/djttnjdCYVKxcJZI3Gr9AF+mMyTDuVpJK5SiNEm2BMMFHK2fyXdGDGMdZk3Aj0wXjl2w4l6GOJUpHFkNC+C890HDOKCePJldHF/cvHh4ekgku8ENhc3Jr4/o+7aKLJ1d7clEOPWbtcRpgRURAIUhBh1dxc/9UxQhArnlyEzOJm0eWaOzr9EvtF3Emfyg1VwGn2/nJXj5PNVw1399UsLmUyW/117zbnrAU4dz5HD6zmOVN3kGgwmiymdosR+a/OqJqrDb5m0KEhfRLDFK4V4qnAQ2bXCWzjoA/WEvcBMTx0eL9sr06Pz2yvOiecExOgi9kbz1HUofpVAoNn6UKh8DYeWNzfS7DQouckvjnae8b9QjlPvgHA4G9wfBe7QaVmvF/deZ9XMNj8HHdb64h10Ck85Rxzzlzgu3dvN46vFEhQRaEairqlHp9/n4CYFxYAMMxnNMI4p4w3+TVkelU8b1TUhhedYLCYj5I3N6H9RDZ0+djgmJLmF0Lkx7mjp2C1bBU1jLRj8bayB9deGoiF18+vEQ6RQEXrMhgokqdQq1YfH7R6jUNWVzrDKNqbpYwHaTFSVC/bxjiFhHVi/dj22yFUre0DyDOLYMAcQ4RqGoKYaPlwrEg6O8a2oQft18uK0gpt/+etnf1Fu69QIFg8Xos7gX3FagCyySh2Jm8uo2DEqoHKf3vBgPEjhK9LWpWT86XVRyImgZTiH1lHD7+qyjLYiuwEZJiQlH9n74qKekhwYwu4C5fbY+5weHd62miZ2sdaI+Of3w4uD7wMQIQft4KPXpd5NKni1mAdZe6Sb//MBcb1rIrbx2b3Tl0jhLJs/CyUJg30yeU7WjhjvHT7xFkplTxYc894Lu+3LCZZNoPBYguL0Jwx2MCk64iKTjvz4PN+mp7605r5LtepgCw3aT8saRAhyvKpS3/sEFjd4UdxzxlGhHNI0jNgbMIMdGA+ixEqzy77DjN5pRX+2ax/pJeeZac7CWKFScm8iljLG6wQOQ18uc5WXrelpFpeBjqxVMAIwXiGY847IqspVy976AZjXQWmAJDXGYPtY5GiIMYIeg0hG0An3uWxEEuIuMC2tNrXXgqx/ZTRFwXAeBRxpX0KEIv9FkK/yEAflivPwhRcVxWhRqO3v5h7m+avFJDGvmp1jP4AnnTrpMF0EndsxP+hxR5/rTGEf9bXpF7KWKA1nVG1pgIC29YNJej3weqx3QD0QFppl/5i8eqT1+XZOczV6X4y0lPfPpesccTOy3imOxeKNRhEvwsrePQasn+vEkzg1ohNliVZNll/ORgbsUs9dRJLgTinnvHnMYD5/cFt9XecACFkfMMA9OFDRqnsfJsDXWS5Z0mScTy0ZXr9gWk1p/3LkILXao2M1ykBfbCblzxm+a+aR/6n1xpb3b8Xw7kOmDFVy4Quoh/1/J8Cg2GgizmWjkqctcmnk3vuCUkSev4bYV7xBwOC4w9WC/iP4n8BPQT9njep870AAAAaZmNUTAAAAAEAAAABAAAAAQAAAAAAAAAAAAIACgAAhqyg1AAAABFmZEFUAAAAAnjaAQIA/f8AAAACAAFAJzJJAAAAHHRFWHRTb2Z0d2FyZQBBUE5HIEFzc2VtYmxlciAyLjkx/v0q+AAAAABJRU5ErkJggg==',
	'task_flat_bg4.png'=>//12.6k
		'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',
	'task_flat_bg10.png'=>//11k
		'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',
	'task_sticky_bg0.png'=>//30.7k
		'iVBORw0KGgoAAAANSUhEUgAAAPAAAADICAYAAADWfGxSAAAACXBIWXMAAAsTAAALEwEAmpwYAAAKOWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanZZ3VFTXFofPvXd6oc0wAlKG3rvAANJ7k15FYZgZYCgDDjM0sSGiAhFFRJoiSFDEgNFQJFZEsRAUVLAHJAgoMRhFVCxvRtaLrqy89/Ly++Osb+2z97n77L3PWhcAkqcvl5cGSwGQyhPwgzyc6RGRUXTsAIABHmCAKQBMVka6X7B7CBDJy82FniFyAl8EAfB6WLwCcNPQM4BOB/+fpFnpfIHomAARm7M5GSwRF4g4JUuQLrbPipgalyxmGCVmvihBEcuJOWGRDT77LLKjmNmpPLaIxTmns1PZYu4V8bZMIUfEiK+ICzO5nCwR3xKxRoowlSviN+LYVA4zAwAUSWwXcFiJIjYRMYkfEuQi4uUA4EgJX3HcVyzgZAvEl3JJS8/hcxMSBXQdli7d1NqaQffkZKVwBALDACYrmcln013SUtOZvBwAFu/8WTLi2tJFRbY0tba0NDQzMv2qUP91829K3NtFehn4uWcQrf+L7a/80hoAYMyJarPziy2uCoDOLQDI3fti0zgAgKSobx3Xv7oPTTwviQJBuo2xcVZWlhGXwzISF/QP/U+Hv6GvvmckPu6P8tBdOfFMYYqALq4bKy0lTcinZ6QzWRy64Z+H+B8H/nUeBkGceA6fwxNFhImmjMtLELWbx+YKuGk8Opf3n5r4D8P+pMW5FonS+BFQY4yA1HUqQH7tBygKESDR+8Vd/6NvvvgwIH554SqTi3P/7zf9Z8Gl4iWDm/A5ziUohM4S8jMX98TPEqABAUgCKpAHykAd6ABDYAasgC1wBG7AG/iDEBAJVgMWSASpgA+yQB7YBApBMdgJ9oBqUAcaQTNoBcdBJzgFzoNL4Bq4AW6D+2AUTIBnYBa8BgsQBGEhMkSB5CEVSBPSh8wgBmQPuUG+UBAUCcVCCRAPEkJ50GaoGCqDqqF6qBn6HjoJnYeuQIPQXWgMmoZ+h97BCEyCqbASrAUbwwzYCfaBQ+BVcAK8Bs6FC+AdcCXcAB+FO+Dz8DX4NjwKP4PnEIAQERqiihgiDMQF8UeikHiEj6xHipAKpAFpRbqRPuQmMorMIG9RGBQFRUcZomxRnqhQFAu1BrUeVYKqRh1GdaB6UTdRY6hZ1Ec0Ga2I1kfboL3QEegEdBa6EF2BbkK3oy+ib6Mn0K8xGAwNo42xwnhiIjFJmLWYEsw+TBvmHGYQM46Zw2Kx8lh9rB3WH8vECrCF2CrsUexZ7BB2AvsGR8Sp4Mxw7rgoHA+Xj6vAHcGdwQ3hJnELeCm8Jt4G749n43PwpfhGfDf+On4Cv0CQJmgT7AghhCTCJkIloZVwkfCA8JJIJKoRrYmBRC5xI7GSeIx4mThGfEuSIemRXEjRJCFpB+kQ6RzpLuklmUzWIjuSo8gC8g5yM/kC+RH5jQRFwkjCS4ItsUGiRqJDYkjiuSReUlPSSXK1ZK5kheQJyeuSM1J4KS0pFymm1HqpGqmTUiNSc9IUaVNpf+lU6RLpI9JXpKdksDJaMm4ybJkCmYMyF2TGKQhFneJCYVE2UxopFykTVAxVm+pFTaIWU7+jDlBnZWVkl8mGyWbL1sielh2lITQtmhcthVZKO04bpr1borTEaQlnyfYlrUuGlszLLZVzlOPIFcm1yd2WeydPl3eTT5bfJd8p/1ABpaCnEKiQpbBf4aLCzFLqUtulrKVFS48vvacIK+opBimuVTyo2K84p6Ss5KGUrlSldEFpRpmm7KicpFyufEZ5WoWiYq/CVSlXOavylC5Ld6Kn0CvpvfRZVUVVT1Whar3qgOqCmrZaqFq+WpvaQ3WCOkM9Xr1cvUd9VkNFw08jT6NF454mXpOhmai5V7NPc15LWytca6tWp9aUtpy2l3audov2Ax2yjoPOGp0GnVu6GF2GbrLuPt0berCehV6iXo3edX1Y31Kfq79Pf9AAbWBtwDNoMBgxJBk6GWYathiOGdGMfI3yjTqNnhtrGEcZ7zLuM/5oYmGSYtJoct9UxtTbNN+02/R3Mz0zllmN2S1zsrm7+QbzLvMXy/SXcZbtX3bHgmLhZ7HVosfig6WVJd+y1XLaSsMq1qrWaoRBZQQwShiXrdHWztYbrE9Zv7WxtBHYHLf5zdbQNtn2iO3Ucu3lnOWNy8ft1OyYdvV2o/Z0+1j7A/ajDqoOTIcGh8eO6o5sxybHSSddpySno07PnU2c+c7tzvMuNi7rXM65Iq4erkWuA24ybqFu1W6P3NXcE9xb3Gc9LDzWepzzRHv6eO7yHPFS8mJ5NXvNelt5r/Pu9SH5BPtU+zz21fPl+3b7wX7efrv9HqzQXMFb0ekP/L38d/s/DNAOWBPwYyAmMCCwJvBJkGlQXlBfMCU4JvhI8OsQ55DSkPuhOqHC0J4wybDosOaw+XDX8LLw0QjjiHUR1yIVIrmRXVHYqLCopqi5lW4r96yciLaILoweXqW9KnvVldUKq1NWn46RjGHGnIhFx4bHHol9z/RnNjDn4rziauNmWS6svaxnbEd2OXuaY8cp40zG28WXxU8l2CXsTphOdEisSJzhunCruS+SPJPqkuaT/ZMPJX9KCU9pS8Wlxqae5Mnwknm9acpp2WmD6frphemja2zW7Fkzy/fhN2VAGasyugRU0c9Uv1BHuEU4lmmfWZP5Jiss60S2dDYvuz9HL2d7zmSue+63a1FrWWt78lTzNuWNrXNaV78eWh+3vmeD+oaCDRMbPTYe3kTYlLzpp3yT/LL8V5vDN3cXKBVsLBjf4rGlpVCikF84stV2a9021DbutoHt5turtn8sYhddLTYprih+X8IqufqN6TeV33zaEb9joNSydP9OzE7ezuFdDrsOl0mX5ZaN7/bb3VFOLy8qf7UnZs+VimUVdXsJe4V7Ryt9K7uqNKp2Vr2vTqy+XeNc01arWLu9dn4fe9/Qfsf9rXVKdcV17w5wD9yp96jvaNBqqDiIOZh58EljWGPft4xvm5sUmoqbPhziHRo9HHS4t9mqufmI4pHSFrhF2DJ9NProje9cv+tqNWytb6O1FR8Dx4THnn4f+/3wcZ/jPScYJ1p/0Pyhtp3SXtQBdeR0zHYmdo52RXYNnvQ+2dNt293+o9GPh06pnqo5LXu69AzhTMGZT2dzz86dSz83cz7h/HhPTM/9CxEXbvUG9g5c9Ll4+ZL7pQt9Tn1nL9tdPnXF5srJq4yrndcsr3X0W/S3/2TxU/uA5UDHdavrXTesb3QPLh88M+QwdP6m681Lt7xuXbu94vbgcOjwnZHokdE77DtTd1PuvriXeW/h/sYH6AdFD6UeVjxSfNTws+7PbaOWo6fHXMf6Hwc/vj/OGn/2S8Yv7ycKnpCfVEyqTDZPmU2dmnafvvF05dOJZ+nPFmYKf5X+tfa5zvMffnP8rX82YnbiBf/Fp99LXsq/PPRq2aueuYC5R69TXy/MF72Rf3P4LeNt37vwd5MLWe+x7ys/6H7o/ujz8cGn1E+f/gUDmPP8kcBa2wAAAARnQU1BAACxjnz7UZMAAAAgY0hSTQAAeiUAAICDAAD5/wAAgOkAAHUwAADqYAAAOpgAABdvkl/FRgAATypJREFUeNpi/PSigmHwAEb8Uv+J4KMb8R9NLSOdvAK0j/v0bAamb28Iqw37T1BJVqSFNdDMHCBmAuLPQKEvQPwaiO8B8QsgfgfEr4D487RVJ75QxQ9zsAfW14AWpIBkxGQzYoozIvMZGbHrg7EZkSOKCSnaGFEjkRGPG7CaxYjFHGQ34zML1TxGJLcx4HQbIxY5BJuZ6RfD5+9SDD9+yzPw8fEycHNxkRxFAAHEwjAKaAa+WOUx8O2sY2Bgo8ycrCgLPiZm5tnsXHya//78Zvj94zvDvz9/0ZWBMvVDIL6bFWZxC0hfBOIHQHwZmKE/jcbG8AQAATSagWnZnvj9neGHtjcDx9mtEAFyM/I/BnFgYa/EysbJwMTGC2R+YvjxFZgn//9nYGHjAGNGRkbe/wz/dP78/qnz59dPhv///oF0vgTim8AMfQJI7wbi08DM/HE0ZoYPAAig0QxMY/BL2ozhH4c4uOXEdWQeollPSnP+P8MDYI3b+vn9C3cmRmYFRgYmKZhuFlZ2Bl5BSQY+YSkGdi5ehr9/fjF8/fia4cuHVwzfPr8R//3zuzgwM9v9//8/GVQrAzPzCiC9A5iRH5PbNRgFgwcABBDjaB+Ydn1gOGBihtj75yeYy314LkT4I7DbCipCE4jLFcDMxw00xwCI64FcV5AdTECzQTUzJ68QAze/KAMHFz9YLTDjMnz79IbhKxD//P6Z4d/fPzD/gyzbAiRTgJn4FUl94N/AxoCgMMN3l8LRPvAg6QMDBNBoDUwP8O8vJN6YIMH9xSUfTHOeXsXA+vgq0cYAM9zXrAiLk0DmWaB5rqD084/hL8PPX18Yfr75wvDhzSNIVgFmalDGZmRkgjSl//9HTmcgl4gBMzAb0YUQpNxh+KOix/DDIo6B8c+P0TgdJAAggEYz8ID0jSEZ4Lt5JMNfscMMHKRp5wZiI4xaHtrK+A+C//8w/PvzB1nPcyC+DsQqQOkfQDwdWBg8ISoD/wLmXzMfSHdAw4mB6ec3+rViRgFBABBAoxl4IDPyz68Mv+StiM7AwNoXlHWUgBnIEiUT/UfLzKgZ+wWQHw6krwNpBSAGNoQZiKv2gXZ8c88AFjJKUPd+QWs2joKBBgABNJqBB0ltTCQA5R5HYMbiRe+awTPtP4yBpu5pK04chrLfkGLZF78aYB+dk4Hx13c6DyCMAmIBQACNZuChBdiAecgbY2wFlrf+odXATAyPgOQSsm0D9dn//RkN9UEMAAJoNAMPLaABzKg2WDPwf6wV5D5g7ftqNNiGLwAIoNEOzZBqbzM4ADEb1pYsIwO2Odpzo4E2vAFAAI1m4CECsiIsQHFli7MbimUq2VTm7YPRkBveACCARjPw0AEqwMzriCuzwsWhg1jiPD++xBrdvTkabMMbAATQaB946AA3IBbAWFmGnHmRVptJ8n6/xPLz/2gNPMwBQACNZuChA0LhGRTbgBXa9JEQx68jDIn/f40G2/AGAAHEQqB5DVpYywZNHswMKItH4TP66GwGLHxGNPwfTd9/LE16Rhxu+s+Ae+UzNr3YJlz+4xBnINI8fPoZcXRTGHGp/fyqCknsP1A9EyMDE2id5H+Gf4KSP9/v3aoOjAFznL7/j1kjCyoqsn/82u/B/OM9M8Pfv2j2ozjlH5QPmYSCrOGFNsYZ/6M2zFFoGP6LxkcW+wvFoLmo31D6L5T9Hw0zEKDR2aPbKoAAIADpZpCDIAxEUQZidGHiJXTnkVx5Em/EETyLK3cm7gppO06bgfxMCoKSNC3lt5B23k+T0jmA0+83N0lnHfDaQIewNV+CtjYgM5TTUbhlAJMJYTIamkGPVkH/y2W/ZYkhgIalRVPxZpfbUugFH65ej2do791eBNtxn5cKy2gMaeH/cDpea/e+CLww9kVfAzDGKWGFFzTE2gVADeAPsFOuD6oJWk65zwBTvu8M1N7AHgD04Xl6T6/Ja+5U46C+1I8vGEtJGydMKpo1Dk8YyRpjSfMSJfT/2ub7CCB8GRi0NUIfmoGpDxgZR9f1IMX7f2ZWBgYWNoanVx4y3Dr3mOH5s28MT94xM3z6zsjwB7QSiglLMvmPvW3BzMrKwCfAycsI2kSBt1RDKg0YcZUvjGii/7GUs4x4yiakZgIjExY9jGQ2hhixFURImPEfKg0Xx2xJMMIz6B+oOHLmRs/syK2Sv2iF3T8srQcc3vjPCgrIP39YexggJ6qAWrrXGCCnrBANAAIIXwb+Cy3ZqJZhqVHNDR3ASELmZWN49vQnw7515xmu3ngP2frHiCOPMDJgz8xIfDYuXgZecSEg+zdxPQucW+nQFDAy4s+4jAxYSxRwUc3ISEQYoW/rYyDCvSia8bTIsJnHSEacYQuf/ySnbPDCuX/MDD9/ccxlYmb8BjSD9f///7lA4VWkmAMQQCz0Sswjq7YlwbdMzAyfP/1jmDvzJsOXV68xRwywjSAw4KiNoRn57+/fDC9uPGXgMZYDNmD/QJdDMlLkZkZGAuoYCRUUjGRkEmIyDw41jHSMQ5L8hhqoTEz/BYERJwja8gnMwLykuhIggGg7DwxqJoPxYMpY9MAkNJ6B/d7H1x4xfHv/GlKzMuGoWdGHD5kZEOqZUNPQr8+fGObMusywcuoxYAkPTBis7Eh7gpGazIwMaBvPsTehUeMPS23DSCDDMqIPP+Co3RkZGLDPkTESGa/EZkZGolq5NE+NkEyL7AxOUs0ACCAm2mXagah1qZex6FJYQPuEjJ/eMvz7/QeeORlZQONZjIg09g9pKAd9WIcBLWNDaVDCOHfxHcOM7mMMH98Ae0KgTMyAnmkZCbqXkRFXGDIS1ZdlxJkZGYmsLRkJ5FNGPM0FcprIDFiaOLi6CJTV3v+hEwD/gHb8+we2h49U1wEEEBMBOSaSM+6AZpDBVMsTE4PA/uHfvwzaNuoMaVGSDNLiXMAmFQswXzMxMLGyAvuy3GDMysHJwMzKxsDMyAostRkRY7C/oOO5v5DGUNGc8fTpZ4aVc84w/GNigxztg9Ot0Cr/P+gEj78QzPAXf5JBzCPgNpeREUtSYyQQVNi6suRmRkYiMykjBZmc3LoONK0COzEFnIFFSDUDIIAIzQMzEhVAjPTOPoOxR02um4DNKC4+BmUnc4Z8+98Mj28+Z7h0/i3D1Ts/GL59gwxygg6uY2XjYmBmYQXXrL9/fAMfLfvn5w/wIXagQa//4IwHOZEDHdx/8pXh8slHDHqW8gyMPz4jMhVQD6IF9x+ejhmRChjQLBAjIxvSCPJ/EhtujBTqg7c3GXCPeDMQGuAagKRErMF/kWNMglRbAAKIheyqhKq1LeMwzvxE2AfMgIx/foMziYy6FIO0tgKD599/DG+evmN4ePsNw8NHXxievPjB8PbjN4afQGXCwkIM7HxCDKAFGn9//wRm5O8Mv75/ZfgDZP/9DcvQ/+HFMKip9uvzByBDEXHszj9o+fwfMoeC3P38Dx45ZoDMwPyHTOsygjvdSJkQZ9+ZEalcx5jyJ9DMxVELYjkkjzpxS+ygGCOBEW1sxuIfmf4PDHcmYPgyAvH/v6AmNLj5xE9q6gIIIHwZmBmMaTL9wzgMMiaV7YYO9IDOkgZh0EosUUleBlFpYQYjYOTy8fxlmDP9OMPcOQ8YtFX5GWSkvzNYWOsy3HzAwPDyFTMDCxcXA+MvZog5oNMj/wAzMaRfBe5PS8oJMvz/A6pxmRn+AfH//0zg/im4kgXRIH3/IVOaoMruP5QGrbFhRJ6/ZSRiHpgRed4BSzeHrMxLqBVIbOYmZjqJUK3+nypxDglzSLBD8H8OJiYmJrTOEF4AEEAseBzCxED1AWTGkZdhSQ1BRmZ4IgGtxmIArcgCQ06Gy3eZGH5zWzGceviWQVzzN4NnmCaD/ccf4Gmj169+M7x794Ph19cfwH7xT2Cp/pPh2bOfDP9+f2NgZOdgEJaWYfjzC9gk/8cGGVpiZAQPnoBGQiHr2yALrZjANHTwCZRZmVjh5TxsVhffoBXBmoqo4PiP1POldq3LQMSgFCOWgoH6aY8JfCzwf/jiNlAGBjJA+BuxZgAEEOEaeFACxsFrJyO13AdJYKCWFScnM8Pz5x8Y7tz5xqAgL8zw+sUDBndHBXAt/ecvEwMTCxeDmBQDEPND1zIygZtomv/A584Ca2JI7frv738G2MgwJJ+CWJBBFEZ4Txiy8AK8uhW0tPM/F1TtfyITPfqsP+4zmklrtv4nshk9WMdIsBVTjOAmNKig/Pv3HzgDA+OElRQzAAJoCGZgxsGb+Rmpbz4oY7FyszLcPfue4fsPXgZ+IUYGDvavDGrKAgz/f/yBnDkNXcEHXvb8nwnc//0HbiozwhMKJH8yQbq98Hr0H4ZdkOnYP9BFBuwQNf8hzT3IYCk0o/9ngWRVWMYG78EAK0QJB3g/mJFGteigSQbkTVH9hw4kQqdeQfPAIEz09TcAAYQvA7MSkB8hNS8Nmsmk+JcJiJmZGY6feM7AzSMBbB1/YtDR5GYQl+Fn+PnlD7gJ/PcvpE/77y8LMLtB2f8hE8KIfSKMkAEpsOh/SMkPr9P+Q/u6f4DW/WVgBE1lMbIjRrWYkJrUsGwJ3pPABMnPjGiLL/4jNlb9Q5qlAF2sCBL5z8iINMBFbFQzkpEsaNX8xt/sJ9psRkQxCi50//3jA9KgueAXxNoKEED4MigbNBMPUOZjHDyZnnFg3A/KHKxswH7v958M1298Z5CQEGd48OAKg2WwOAMTGxvDT9AthcAuE2hN7b9/LMAkBMm8CCfARpshbCbo6CgjE3RwC9TYZoKMhDKCNwoB+czsSMniP571z4yINMj4Hz7Nw4iRqBngi0fAK4/ANCMkYzOgZ2RGlEEwSEvhP7zlAC8YcC4AIbAcFGMQDV+mIzO+GRlIGugCh/1/SE0MzMCCwFoYNBd8i1hbAQIIXwYGdabZ6ZuBBlumJcUs6rsdVCpzcLAx3Lr1guHDJy4GNQ0uhtcvPzOYGqoy/P3KwvDjDxcwabNC+rz/GJEGmKAZFdZtZYRtuIH0ZUHmgjIuuLYFYibGP+CRZkZwk5kJS6GFvnCfCY/fkfu8sI0MiAIFJs4Mmj75j9gd+v8/sr8h5v8DdQcYYWNsjPACCNmm/6SEJ13bcEQuIGEEZ1zYSixQl1WcFFsAAogFjz3sDBTfbEvPjEvsIBOhW9AGmbuBNfCV668ZuHnFgE78ziAtycYgIyXD8OkDM3hQ6g8jZMEcZGCKCbFdF9wk/gtpGsP44HoPlmlh+D/4LiUG8NgJrgu58DRLGXENXiEyMTY/ImdYBmjfGzHWBdkGyQSurZngzRFQhoYXLFA2ShcBqVXwH7trBlUvjBG8wf0/Un8YHCjCpJgBEEAseIow0E147AOfMalkD8GJeFrUtJT5n4mZCbxY48bNrwxqqhoMz58/YdDTlQKW0wIMX398g4wS/2OE92v/M8Iy6n8GJug2V0ZofxeUaVlAe+qZ/kL7uf/BGYSBkQVprJLQ2mS0qSNGUpucxKpnRBpU+49kFfKg21+gfzH3F/9HGrhDaZH8h86LMv+Hq2Nk/D/wg6SMsCkkRtjiGwFStAMEEL4amIf8JjQ9mp2kZFpGOkUI9ewBxSU7KzPD13ffGF6/ZWXQ0uZmuHv7DYOhgQnDt28sDH/+sjCA5vwZoYngH7gHDO3PQjETFDMw/oI2l/8xMIP6vEywjMuEZ0AOV2ZD6pNirXvRp42YiB9IYiR0EhISzcSAZaMEwgjM/UYQ90AyOCM0w0BbLExQA/8zINXgmG0K2FoX0gaz8KsEdWVA/V/QNBKwKc0EHcQiGgAEEKETOZgGV+ZlJEGacZAUPIxk52dWYAZ++OQbg5CQGLA2/sEgIsLOoCgvzfDp02/wSDF0USS0GfYP3J9igta2jEx/gP3M38AE8hu8FJIZ2lRmhDeVkRM2KceBMWAsbWTEmhmRe6lEHB/GyEhiwUyMeqSGNGgQjwmWgf8jpr/g7ReEakgmRdTgSGPviELjPwOWVWmkxDds7TkkA4P3JEG2FwqRkkwAAghfBmYf2MRPqxp2gDMpsR0x0OIKYNp49PgXg6KCOMPT5y+BfV8B8GXeX799ZWBmgo0s/4Ws5mGE7CICTwUBMy0LKPMyA9nM/6F9ZGbsmYQRV6YiZV0ytpVLuC7fZkCroUkJXwq2B8LmsXHagJapocd/QWppRKsI5h/QNB0jA+ZKsf/w5Yuo69Zw2Yq8YwiYeUE1sDQpuQ4ggAjVwIOoyUyNEYkByrwkupURfHs7A8Pv3/8ZXrz4wyAixsVw7dotBntbDWBT6w+4JmFiQowgg9igTMvIAMy4LH+AGR9U4zJABqdwZSBGcv1Nj3ENeszfElfjQ+bLMfcDMDEiZ2rkXMgIG1eH9MOh8+SM/xkRK6/A7f//8ILjP2JlHMgUKUYS1hUABBCuQSxQscNO/3XQtFiuyEj/DIvRnyNkAmb/l5mVieHzl58MnJwcwNbVVwYebkYGTXUhhr+/vzCwMEMyLSSj/mFgYfoJrGmBbHBNDDsoAMf+W4IDSvjOzcLcuMCItUYl1I/G1XrCNjPAREY04Vq+SU7c4q/1GTF6I8gNbujmTuSZNNAa9H+IgTTIji/4PDAjaC6YlRV0NClx59EBBBALHnF2BrqCgci8jDRyHyFdhM1iAVbBnz79YBAT52F48+YTg4Y6N4OIwHeGH7++AJvR0OkfUJ8XNpoMNpMZu12MxCRIHOKMOAaxUPIgiWdgMRKRwalymgZ5tS72QSny7GdEmgOH1eNMTAgxZmbYgZgMsD4wKN+BRqKJWo0FEEC4RqFZGInOwIxDLPPSoNmNpTZhpMBeUCkNOmGHg42VQVqSheHnt98Mior8DKzsv4BR/YeBkRHbVj1CLQBGwoNTWI+5wVc7k5jJGCkIb0Z8GQlfH5uMzMpIny4heCoLOvgIqpVBCzoYIWtYiV5OCRBAuJrQoD1n3AyDCdB9Fp68QoWRSgXGnz//GPj42Rm4//5l4OXmZ+DgZAf2if8h7c3F0+RjpNANFA8OUnmnELF7gnF2F+iQDsg0mxE6EPkPOsUNzMSgaQKiN/YDBBCuGhjUBucZFBmFbk1mCo9VpaJdyEMRzMCmNC8vG8Pfv/9xj6Iykt53w9ncZSSmacuItEQS38F2xJrLSGT8E3lELSOxzWdC+5nJaYWRBsBLWoH4H2QzAwiD+kGCxOoHCCBcfWBQNT7wNTAjNRVTI/PSt+kOy7B//vwnoXAjdpUZIxmFJZk1MiMFemk6nz84WpaM4Br4P6wPzAzERK/GAgggXBkYVANzDWgOpXnNS8TwElVqXXqNcJNbC+MLa+xijFj1kFMb4mEzMlIhSZE4aMVI/4KAkQG2Rp0RloEZgZjoGhgggPBlYG66ZFS6Z15GEtIXrTMuNY7mYSTL34SbpHiWUjIykWAXiau8KGpBMZKfecm+VoUKWQM0iPUftivpHyhPEr2YAyCAcA1igfrGbLTLvIw0yjRE1h4UlbiUFRjUG6yhUAzrfUiE+4iMBPUx4um7EtN/ZSSyjYRrVxSpBQahvcW0q4XBWyQZIItC/iM1oYFCMsQu5gAIIFw1MBsDVbYS0uocKfJqI0bcbWSa2UmbFgYjaWoZSTUTO5sRZdAKx8AVIzkFGa5MSCTNSGG6pHgNPQU5BHzgyR/kPjBoOSVoYz9Rp1MCBBC+JjQrjZxM50EHRqpN7VAkPiDrtsmZUmIkrQVPrfSA1XwiDoNnpFWapI/2f+CzxyBnmkGP1QH1gfmBGZio0ykBAogJx5U4nJTXwIwM9CsAqN3XoaL7UK4foTTzMpLmT0bKzWRkxDeFxkhm0DLSNikMpZs7QKejsPwFTyNBz4YGZWAeYsegAAIIVw0MWgnCRf3MRM6h3eTbxUhy7cdIPTcwUtqfJkGOkcQmNcGWCqF7egnFI7FLJHGvn2YkqbCgVn+VlqdjYt8nDIo6Fua/kHXT/+BbCkELOUAncxC87BsggHC1T0AGsNO+BCI2kTOSLE5a5mUkUx7HlSF4MxShy9gI2YV+Wx+xYYP9dsH/4GNoYcfRMjH8x7pNnlC/lVDmZaSwxkbzAyM5mZaRunmWapmbkYGZEXI0MGiPwz9IJgbdE0zUSDRAALHgyNT8DIPuSFniA4v4Pi+VT+pgpCQiqVHzk+5PRviRr/8hmRi2rxV6SB4T9PjZ/9Blf+jZ8z9V/ESNTSeMA5nkyAfwQ/P/I7Wq/7MxEHk2FkAAsWBZnwfKuDzkj1owUiGxkzfQRPwiDEYa+IXaizhIWXRBqt3o87rQuo3pL+KwOUZoTfwfcSQO7DgaGP8fyqIOBvBeWNRD5ohpApNTY9Nqfp6YDRPUBbCCEdp0hm0rZCF2MQdAAGG7G4mFgdaLOChessdIROalpNYlIgIp8gMp/Vvq18CMODIO4sQJeJZF2XiOckQO8gFR/1EPlENtsv9nwLgqhRFxdA0jzpMriZkqYqQs01Jt0wcpfV9UPvhqKujZ3JCFHJA8SGwGBgggFhxi3NQdUKJW5mLE31wm22xi5ijJ8TsZhQVZfsBzNhVev+DrS+K5hwh2kyLaAemw2x4Qmfkf5P6l/2hLJP9DT42EXm3KhNK//Q9dVohUm1P79BWqHHZIPcAEP7cbVAP/Zfj79y8oD0oQs5gDIICwZWBWBrJ2ItF6BJBAz5bqmZcaUz7U1Eds5sVjF9GrjfCdpEHcIhDk86UgF6VhG/+CnljxH7kVgDg1kgneM2SCXwHzH89RWthPjRwCd08zQjLxv3/Q0QhgHxiIQauxQKuy/uLTChBA2LYTgjrQXDTxDFl9RWKXRFLazKRWjUiO+aQ3hxmJUctIBffhNI8EcxgJFMLwGxfg50JCboqA8v8j9RfBZ05B71Bj+I/UNIfW8kxM/4kMD0YqxCWh5jJx+QTUhGZm/svw5x/svGqwQ0GnU4LWY3zBpxsggLCthQatAOEnbV8kEYHBSI2liOhLIknP+ORnKCplZBIKMUaK59PJiS9CNS6JGxeIvswb7awt5BocPZEi51mkPAM56xlRg8PM+I+j30+4hYqnb072oYBoNoDOPwMdi8T8B3r9K+iMaND2wn/8zMzMBDMwQABhq4FBGViAlBKEfCWke5iRbFWkZF4aNLXJOJmSqn6jSpOSkUj7qHEqBiORQYroJyMyPfQoXUbUQTbYIXOQC87/Q5usTBhNf8R1n/ia6dRtmoMPeIfsRoKNSHMxQKZz8S7mAAggbNNIoEuG+eiTKcjs51G1xqH2ABV5TXBGcjIexSdx4GruElvLMpKReYloelNyeiZKDQ7lMf3HWN8FzrrM/6GHqkP62ZDTIhmhbOi8N1LagJzlzgg+UBAx2EaFziboBlkm6OmUDJDFHNBurAQQ38GnFyCAsA1isTNQfTP/EDohgereptUgGa3CmZFWATHoBpEQmQ96NhXsYkb4aDrq9BisbgffvQxmMCHV2Eha/hPoNWJpqcNaB/+h80jATMwFGsgipBUggFiw2MIBxcMpFxEYXKFWJmEk0jhGEmwlZx6d2NqXmNYSNQ/EI/b8afrnaby3NiBda8oIb66DKmpE7Y3SrP6Pv6WCmPtGanQwQZZSQtzyH1aRErxmBSCAsDWhuYCuY2MYBTSvPcjf5kiDE0AYaR0ujAMcP1S0H33BC0pTG9UqxM0NjPDrkMB17T/ECDqoRgc3oRn+Mvz7yww7mYOdmLEogADCVgODms+sIycb0bKZy0gnv1A4XUS13Vr0illy/EvF673JOgnpP/wkIsiNDEgVJ5APuhIHcgcy/IB3VmIuOgMIIGx9YCx7gUdoH3bAiyjGIWb/YNmTPTjHYf7jPFz0P3hLIQN0TTTyZd9MTPgPNAAIIBYsvuTE9O3/IZCJSViwQVTfl7ojz4yUJnKq9XvJ6a8S0X9lJOPMZ5KOniWxgKF4gwn90js4dzH/g67Ggp8PDcJ8wAwMag3/xqUXIICYoMcAwDAzA2T+iYqeYRzYzIqujtZnYuHdeEWLDRaMZCRyRgLTPNTKvLTqrtCjJfOffukb1AcGG/MPfqMhdCoJtCcB77JmgABiwlEDj8QxJZpYQnrmZWSgfOM7uTUrtcOCyIUfNDmPeSh0+/7DSSZoUxq8kAOxmAM0iIV3VxJAAKFnYGYGul2pQnxEMA650oSRDmXJUEzkjEPIGjqf4MEEuWkSMlL9HzkDi+PTBhBALFj4PEMuv9B8gIX05ZKM1HAT0f04ElfJUaVQINUsehzqMFQBbHUXqPaFiECXVPIAMd6TOQACCFsG5h2Y3EjEriMKzKF+zclIXGuQnAxGlUEYUvv/ZIQjJU17qnYLBkOeJ99i2Eou0B1Jf/+irIfmIJQfAQIIvQkNmjwWGdy1LwUXXzEOloil9RlPA7XIYiRPN/6nOOhg67eRDnlnI9QHBggg9AxM0t2ko4DeJTjjIHQrPRZ/MA509qJLiDIzIq6QhWZgTiAWw6cPIIDQV2JR+Tws8pu9jARXClH5hj4KdjIxUmwPMWuBKegLM5KSAUnZKkjm3C8jOX4bqCKAmu7AvZ4CctH3f+gB73DMwsjIKA2tWLHOBQMEEOY8MF2uFR1sFRx5fWJGkrtyxGZCKjZp0Zfk/IedNknBoB7RBQI5B67T6vTHwd9DYIRtKYRi6MZ+YWAm5oLsT8bEAAHEhFEjD9oMTMlRMLSOLXr3aQkttsAlBDr3mRlymBz4IHdmiNg/RmjGhh3szki7RMxI63Aeuv1l0HJK0EEDsIPCoFet4L1mBSCAWLD0gdmpFmhU3Z42uIpMRlqYwkjrwgHUTPsLXTnwF3orA9JGuf+I7XD/0Y61gXdsGPE3BQk2Wege0ow0MRsRAtRZZgzuA4MyMLAS/vvvH/RwAXDMgCpUnIurAAIIPQNzE98HpsYqH1wmU8McSo+ewa6OuCtKqTUlQ0nhSbgWhY16Iu+YQZb//58JLdEywrfS/Wdggp5R8R9as0Nvm2dkhB9bAx+RQT57jpYFLCNp/U5MuYGr4f9DMzC4gIVuBoROJ4FOx8G5rRAggFjQXM/HSPadSOTMYRI4cJySRQIU9X3xFCnUzryMFGR6rAUBOfpwzMEzomU3lJMq/iEuZIAeRQPO70inRjLC7k1COvniPwNq4UF+rFD5AAYaD1IR1sMIzMDw+V/kDQ0C+BZzAAQQC0oHCTKFNICb+cnZo0r7BjK9dvVSnGApur2AVNcgrlCBnDkFu7HhH9JF4IiUBVbxD7IPFnJ0DBPSobGM8AKCEem0yP9EZxbGId89/v+fAXH2NeKKFVgfGOfULkAAsaBd/spLncREeqJhpHmap9YtdpScP03pcbCk2slIuR6ip4sYCRd30CNoYKdJwk+B/g9rbTOB63VIRkc1CXKDAyPSYfBMeGvxoQjAa6HBp2WiTCWx4cuXAAGEXAMPwo0M1OzrkqePus05ejUNqWkXrUbYkU6IRDqSBtKPhrL+wxqGSPenIU2D/f+PdoQsqCUwFLau4xy2QO6iwD2B92gdgABC7wNzUJwQGGmdEYlczEHSul+8Q1bU393CSOWChio3FtKocGUkP/4xDpND8e4/pAwMbX+CRtVhY2dItzYwYhkiAGd7RsQ65P+DoDJnYoRk4n+ofWBmUB+YCXI0xz90PQABREYGps+JTgNVYzNSOog0WPvHZA+uUdCNovXCB0bEpSsMSNNb8LEzcO5kgrbRmcCpnxFWe0NzM+yAOSb0+5sGpC0J3R/8/z88r0KP1pGE1sTf0fUABBALyuG4oAzMOJTPBR7oTELtZj493Uq7aUHqbK7A0/fGexUSbHToH/SKNET/G15xgzbS/0c01RF3JEO1MsL66YwMBI6oomwQi4kBsScY3EWAnZEFvuybB1sGBggg9FFodho5j4qJgXaJh3FYFRoDXMAM6M4v0q5lgUx3IfqfkD458ipjhLmgDAWbRIPV5rABKMTcOJmuZ4LcUvgXejIl0rZCblytY4AAQs/AnPRLIOTd5EaLRMdIVUlSR3XJbOJiNYsKTXyiDs+jdO0z/Y93JdVAcEaFh8V/aG2MSLNM8HuWYOoZ0USwX6oGvXsRS0MXckshC8s/ht8/EVe+QPvBnMzMzFgrV4AAotIgFpVDn5HC2CHlAA2iBsIGQ4Kj1lWhjBQWLvS8XYFxQNUiDmXHro8JXovDsyUD5uw1RPTfP0S7HiWz/2eA99sh/fC/8FofcW8TA2hbIdYMDBBA6DXwEN6JRNroMyNRhQgVB46oPtPESJl7qNMEweJPaq4R+M9A+jWtVC4kGMnvCiLWSjOg9J3hc9kohQXkvhYmRmgh8P8/vMkObRljzcAAATRI5oHJ3Q+LTwm566cG4ghdSk+KpEXtPIjHABhpETP0PVABvVKHzGNDrhkFL+RgQEwlQTf2Y83AAAGEvBIL1Gvnpn6gEzttw0C7c5Lo0vSn0lpmoq9CpULmpGhdN7GH1JFbGDFSoYYnZaPCfwby9i9TMWX9/w8exEKek4YOZnEyMjJi7d4CBBDyoDgrMHB4BmfmoUVTiZxTG6nRDKN205kefWVqRdFAzUQMtkoAtxvA/WDUUznANTADjl2CAAGE3oTmoKenGMkOTMYhGEWMdPPn4Gwqj96vRTiI/sHbAv9R10NzQc+IxgAAAYS8kAOUmdlQmyi02C9JowOzGamUeKh9SwC1ugUkjxDTMj/hWkjBSGQTdbQMwOlF6EIOxJ5scBMaVLkKMjExYQxzAwQQch+YGYrJryFoeSI+hWaQdz8RhYNFjFQyh2p6yF0nTuI0EqWFC043D6fjdxhxN6EZGVCa0JCuP6MIA2Sn/y9kHQABxIRmIvPIbsJQKwEw0mGDEq1OLRnIjDDCm9lIS7sZ/jPAMy8U8DFg2asPEEBMaH1glpE6eEA9d1JjfpZWu7toENyMdIhXxkGcLCjOraj2gddC/8M4Hxq2nBKjggUIIPSVWEz0DyVK+0aEp2EG7mg7Rqq4n2qFBMnTRsQ6nZEG8UZKAcFI/XgaoHE+0Dzwf9DBB4z/kfvA4GtWQOdEo2sBCKBBkIEpSVSElzwSdQ4IIxUzFbX6vVQ724qYJj05dzcNpv4oKZUANcdYqDvAC17LwQRZg/0PaTMD+KBAyEg0RgYGCCD0DMw4ODItqYE3LDvhFJv1/z/slCVIGkfsn2Uc4ONoGAe5eQPRkmSAn+wJO1GWEVpIgg54B2VgIB+jDwwQQIMgAzNQcfkkLfqjlLYSqNXvJdXe/9BMygTjITIz6Oyp/9AdNdB9qPATZRkRpyQyQm+MJ732pdKcIOMAZdIBLA/AWxn/ww91Rx7IAvWBMTIwQAANWAZmJCukGIdoZA3QCiRGRoysCx4RAO94YYSeP8WEtnSPCUHD7o75B9EJ2fOKxTe0OJJmyDWwqOBg+DWjDPD+LxINOpkSYzklQACxMA5AwmWkViLHU0IzklV7U+MMLgprfbKWSzKS1of+j9itiqspDbqr9j/stgboyRb/oTc2/EcyB2QPI6yNDrfjP4kHuzAOotzNSH7uo7QJDd1SiFzzIo1Cg6aRMHYLAgQQC5oL/lPm76FUbFKxOU3Vfb70WvoI3ez2H28vGseiK8SNC5D7ldCTMRPDX9A5U6CqG7r/FXasLP1PVBtCafI/SiCjzwNzMmA5cAMggFj+4/Qupc0+ajcbKV31RegcJUby3E+V0V1S+76Ems20KrCwWfcPW7HAwMyI0huHbGAHb1JnQooC1EG1/3jrAZTzL1Bqe+xu/4+Fh74SkZEB/ToZBrz54T8eG1DdwojhXsLLTBnhLaL/SF0a+LZCNmxNaIAAol4NPKCNn6FSyo7ElUbAzMyEtEWOEXI2JHrND74xkQFyeDvsDCpG6EHwoIPeIbc/UKuxOhDxRdjV/xng+/hRwgd6lSgoA7Oi6wEIIBZGOmfgQdNW+U+l5Y4kpSYCiqmRMuEXijESMPw/jlqLVPcRNhc1s/7H4jyIHmZ4fc2EdIcxI3SQjBG12c/IyIC1ymb8j6TvP/FuhTNx1ZyU3HtEfBiDZgQY/zOgbycEYVZsGRgggNCb0P8Hx03mtMhFxDVjSEvw1I9AquVq2Inl1A5bosylNCz/wedA0ZvmDMgnTzFiyVywue//mIfLgZvpsNod2+Ad1ap28tMKIwNiPhh5IAvYhGaFNqNRAEAAsdA3Ew22EQMqZXqKvE5OzYiNz0CGf7DpI8J+jExMlaYDmh34hxEIHd+Kcrcx/M5sRvjVLEwoRQMjUqvgP85hBXrd3sD4H9jlgDSbkQeymLAt5AAIIJYhm+EItvYQJRrxZhBKvHgsxdoEo2YmJqMAgnWoGBlIzOxEZmIUs9HNxcansAlPdHggnRTJCFuKiBh0gxwg9x+pvfkfqV8O7IuDDnBnQJTNoJsbkO8ARL1PmVDaIOV+YsTIP/KGfqQllWzoLROAAGKhwTFd9KunUQwjM0FQ7CBS+lFEGsdATF+TmEyCXP3g6/PjqokJ1c7EjCX8J5CJMT2LGq3YCgriwxaz1kSao8YxB87MiGwntNZGvuMYJg32PmQe/D8DYukjI7Tahh0CT1L0w+9VRtkLDMvMvEA2aKjgL0w9QACxDN5hUiIT/pBouVPLkVSqmWnQd6PvuACdEgNsZRQT8q2B/+FDbYxI5SL8ojVGRsha5n/gNi9kEds/hJsgh2rgrwbBmRV+9SrKckrQaiyUDAwQQCyDJ/PSJnHi10FOYqJBf4+kZjS54YReW1IxsaNUpLQoUSk1kwL9OFp5yJmXAXYoOzSDMzGiDpIjT4D9+w8bSUeMsCMtP2dAngyC7+9HZGBBBrRTOQACiIl6NfB/CuVJ1fOfenb8H6gCC4/Yf3L9QyAs/lNqzn8ilP4n3wxcav5TM11RM2n/x2Cjz8dC+uH/4bcvgDEThGZE3LAGmQuHFbIY8+RgAV70VjNAADGhsZnIzoD/sXsGmxkYZcx/ckMTm/7/WIezSIuk/2Qk6v9kJDQiwvT/f/z2YeUTcOd/fPqItQ9PwfOfnDAkMo7+E1sg/CecbkhJb1ReJcEIH2CDYmiGZmZigB+nw8iI0nwGsXlA88HI88MAAcT0H+E26NnwdCu66Fqa/qen7v9UtmtILK/5P8jMGZoAucUOu08JdkMDA2QpJUoeBQggJkZE8xtUwzMPvYTxfwgltP8UiNE7s1EY7v+p4Y//gzyr0cbY/4yoO5GQADt6BgYIIPRTKZkYhhxgpF5k/KdmpP6nct/9P742JQlqSOky/KcsU/+nZoL/T4ZR/6mfIf/TNoPDNvSjxxs0M2NkYIAAQj+VkmnwlX6MZGQ+7H24/2T3mchNb/8ZSKt1/+PPDP//E5mJiPTffwYC/WEGMu1CGX0hTT3RVv+nUsYhxpz/eMKN2q1EhOGweWWkmhh0yRkLch8YIIDQa2AW6tZsg7lPRMwoLXGDcjgT9X8a1Lr/qdk0J7bwIzWx/icjs1EyqEWrqoPSbhIZ/oMehAK+oRBxPzBsMQcoAzMjZ2CAABoE50JTI/P9p1Kc02gkmeK+6H/K3UHXTExpOUzPQUMKLPlPfSsYUU62YkRfTsnBBAEMMAwQQExIuZlpcGTg/3Qx+z893fOfyv76T0rmp1bQ/6fcnv8DnTb+D4Lk+h9/b5HxP8qZ0GhXrHADa2IW6P5gMAYIICa05jPjgGVYkmoAYsLmP42ik9iBIlLUkdFPJHs+lIC+/+T264j0x38qNDOp0hz/T7NMzEiu2f8R+Re91wGdSuKCzgXDMzVAACFnYA6GIQ3+k5GJsS3yIGZEmoxRW1rUPv8R/UvEhndsbvhHWoYkekXVQLSV/tM8P1I385PusP9YdkBAa1zQoXb8yOIAAYScgbkHOmqoFhBktBz/483EVJqrpUp/GI8saCnePyYG0G2U//4zgc+fAt9MCT54jpGK/Why3Pyf9hlpICdOqDigBtnE8A86kIUyF8wKzafwyAQIIBYkPTxU8QQjBVoGZGcRwlKiTowh2cPY9iATs1EB32FtWK6QYYTUtIitaDBx0NmQ0EOW/kOm+hEH0aEeZwvZO8OIZj2lJ5kQE2yDdDcSSfZRx25GpHYhlvOhQSPQHMhNLYAAQs7AXIMiwIg2kliFpGUy3NvdSbGPgYG2m9cxql5obCMSE2LfN9JRrtATDxHHzfyDNsJg+12ZIKdWMELPk4IusAef04RSkzJSNcwpi0NyDmkgbB8jJbuXGBiIOFkThzjk7B+Ms6Fhm/qhXV24IQABBLvgmwmzBqZlqUiN86YYqVygkHtiBCXNDgq2BhIlhj2MUA91gNS6jGi1+T9gkgCvswU14/5BN64jHSrHyIg4Chb9WCFG+CgMI0WZ9j9Nb5bEn7b+E12IUyOfoFYeTNA6+D98UwMjPBODMjAjI/gyHPCeYIAAYoGfIwJqWzNS97Y16ppDLbuIC3CUbdtUKctIOxKI5IRDUvsfj+WMsNuT/kIyNhNS8mJEPjuKEbGJHeXsKaRjYaGnScIOeCfOCeQUauRGEG59jJTGMYXxywgPP5TaFwTYkRUDBBALPDawnPo+sBmNlu1v4kOX/t1yap+oQatTPFCb6YiYRToR8j8jPFPDmumQRAkZO2Vkwp0mGCk5HGAAT2khr/XOiLX1gdz/RQIoTWiAAGJBanmzD9wQHg0O0CbrvKz/OAOTkaA+BgbqDWiR2d9GnmNlJON8KyqdRok4svU/yvGwjPAm9j9oFDEhBvz/M2JpUv6H3lYAsRvlQDeUQ+loWXiS1oohrwuOdNIH+JJviH/R9gLDmtCgiha+axAggFiQLpZgH1r3BhAxsEHFIW7U89KJHUVmpDCRkFNI4MpstKq6yGzO/ke9ewn1ihYmhKJ/kMvUmBhRM/l/pJMiUc4fhV+2xgDfI/ufjtNLWAt6lIP5iBnVRyrEoBkZaWMDJ7IBAAGEfColO8MooH9zmK7NPSr0HWl3rChGIgY3vJmQd+eg1/KMqOUVaCLsH2ywDTTyxoQocLFcrYU/c1N/igh3AYzZ/4VsZGCCL5lEGpVG6QMDBBDyNNLgWInFSMvURPnZxMjLUBipeSgcRQNQOGpnrLUwqbcmYDm6lqhjaolM/FQ4ipcRqWXEDKrJ4YfEQW+7h/bHYQfMMQLZoCE60LlUsJsc4NX2f0IXbdJiJBpNP+M/+N3OsNM4YANZoHlg6Cg0GAAEEIUZmAZVx38qBx5ZYUutUU769r9o539qhwG5I/JEKkTqNjFh1LyQ5ijyaZGQoQjEaZGIMgX5jiVGvDc3/P9P5Tj7D5mvZ8TsBnEh9TEYAAKIBanTMUSa0NQeaSb/4HXieqVEZgbYABQjsQNkxNbE2GpMatySgDSIxPifgkKd2k1VwlM5/5EyM3pDAnEcLHJH+j88eiA1OBO8hkQ1D3KNKhOKDCNmCwbf5Waw1gTa4e5og1jwDAwQQMjbBzmGZkald78N0+D/uAYvaO4oGtaKgyY+6d8agq9pY4RtBIFldui9v9ABt///EdNjiEEnJuw9DFIuw4TO/2LroANrZB7kJjRAACE3odkHTWYl+Z4hIpot1LxCBQcXd/1DjRFpSs0g5mB3cvrHjCQPkA+PFh6i342smgl+AyLqpnxw2DMxwDM+8l1LDIgKl+EfI5IcI+ZcMGg7IQPSNBJAAA2SDDwKUNMPDWsZWlZgZJs9UGMMNKrBGVH3JkP6sf/g69URfWbkUXRobf4PsVwVvRKGDmIJIbeWAQIIaZCenhn4Px4e6fqJNuU/Pr3U2RyPfnwefjtwiJPkTlL99J/Eg+zIPV6HkDv/E+c1MlMIJfoYiTaXvDOzUA50R8NMTP+g17TAat7/yCPQIMzPgLRqEiCAmJBqYnZKvUX6FBAyD9feTloco0LK+VHkXCUC8c9/kuxGswvvKZTE3qiAx///SfEzMcfXUiuhk6LnP9XzMCPZhpFwSNN/As1yRviFxiiDWAyIihaegQECiAlpAIuNFqUZRacX/KelXdRuTPwnwUX/6eylwXRA+si+eYHYYgS2lAX5aFloTcwKzatgCYAAYkLK1awUV6//aRW19KqFKT3qFbuZlGdiahwS/x9PZUtOWOCokam6bvE/fQ8fpEalQ3HWhWwnhM1Q//v3D70PDMqn7LAVWgABBLtahY2B4DTSfypnFtxR9p+ipvR/OkXwf5Iyw38cDW3K+rnULIiIGQegw/lU/ynolZGddP9TKfNRyRzkgX2kZZTQTAvOq0A+I0gMIICYGBCrsMjoA9PqmE9K+z//qRyZ/6ng/v84e8vElxk0qhmocp4TpadL0rhp/Z+0rDigTX7ogQg4TqZkQTpWhwEggGBNaG4Gmu8H/j9EzKR9GPynKLPR4FD3QdEfZiTgLNq7lXHQhAti1RbyHDC0NgafiwWrlQECCJaB+Qj3gYmsbUg+eZGIHiLVTock5rRJCmvh/8SFAc6TMIky6D8VxQhdKk7C3cBU63oNhoKZFhfWE9uERksZSLczQJvRvLDKFyCAYE1ovsFYM/6nS/zQaICIyHYpWTPiRF90RkahR9SgFqXTN/8HIsYHsHtAYt3LiHsvN3RQix+0nBKUmQECCFYDc1KWpf7TMQz/0yD7/6eSGeS57T+J5oLPfwatuf0Hof9Bxy3BK3ewmvaPTiUnOdOA5GyEIOfSOVxpiBrnVv8nMYv+x9lkBjGZGWG1LmbmhTap+WGaAALQdkU7AEAgcPH/v+xMM9KaZDyYN2NzpXNVEMC/DhUhfR4CEDeeGGdgXVhp30Nj87jW+yASP4WY5V9ZSI/MObDNUBdOjUtCrQs1ipplZ0VLTB/xRFa44rVUQTgU8UFotAY9vRuvybixJrkklqwJratydAAzdqsAYqFdBqZWIUDuSY6EApiSzRMkmI9yQDq2WgbtCBW8dRFqJoaJIW9A/8/IiFrQ/0ecL/WfgRGttIecA80EOy+aAXocDXIfDOmsd9yHA+DaEgjb7IDcJCTxoHiS9JIaRQQ2a1B7+TQjA5bwwgRMUCXIq7BgfWBoLcwLMwAgAC1XkAMwCMLW/f/PLHGCiKBw8EqAqLHYxLQM4AtSQhQpU6y24RdkYKyq4gnoio7ZyYxN7rG3Ryfhx4ECrZ5zF/O0BkC1fuhXFSJ8HMSg/zH2Aqbh4lxhFvM2PJNR0lCCIh6OalsP4zEdAdoOE3L6rTa4PpAzYE9uJ3NFpz70D1Q8i5gfYusL0QR/Agh2IyEX9TMwNQ5IY6CTfmodQEeOHCl78QjV1v9xF0gw+xmRa+y/qKdGwgsU5PMqEMfEgrM6qIn+D6mAY2SAHvCOOEwOo6JmRDpUgOyYQTskDm+tTEotT/whfBQf9wSvgZjwxzAjrM+LeTIlFHMyMUHOGgEIoAGogWndlKZSJsbbrMaX6fANyuBovmNtYhNjByOO2hr5jAgGLH1AQqdzoBYKqEfD/oEe6Ix6DCwj9MoW8DHu//8j1RjgMyWRTYPumUU2Ar3mZCIiurBlYjIzLkqrjlbnaDNgbwVga0IzobVA/2Nccsb5H1qqAgQQLANzDdrMitaUJu7kC0oyGCPJzSeS+tsEMzK5fsFuz39czXukop6RYOGBzIc1xTGPi2Fk+gc9VAaWx5BPrIB1qZng2+TAp0YyIGpwCCKy+Ut0PiKyVURWvsWniZHCfI+lfYXIxFyM0FIVIIBYoKNbvNQfgCZ4vB8DqTfwYcoQe8YuAwn24Brk+k9EH5hQzYtPP/IZwgxYwu4/ifYQ019HvlsYEWeMRGVktEEqWHMbKZwYGZEzOkQPM7DZjmjd/0O6igVxJA362dv/kOpwRvQ+8H9GNK+S0AcmdySZ7CthiAOg0zLh3REGjJVYsFM5wJUvQACxQNsrwvQYRKaNgZTeckdk/5fC0pPkvjDOQ+5I6T+TcWMEOE8g3RSAs5nNSGZ/EmlIEtwfR8rgjMjOYIRcdwo7Ywqq7i8og6McpsfIwES1xiByYfMftzzFNT+B9iyoRcL0H+p3RpT7kaA1MKjCBW//BQhA3BXkAAiDMAkP8OD/v0oNyoQQzIjGeNhlpy2EtcDSagLrF8r1M6qMDhI/oNJITZJXEWxSXeQudZdhNJD39uFAFeEmYtAUfUuUCvdEQDvqJHFZ02NSB46ZseszR0tUJp/fsiXtcEgUuCysmNnaKfnm2lK6z4TCWj4rg0b3hN9Q6jqCHAkrGX11bdaJXnYBBMvANBiFpr1HsRtNSa1E4tm9VCk8yAgrnM1sUmp7Yu/bRbQC/hOViSk96A5zEOs/xsUKiKksJqSCAnb9CnwKHOms57/Q5ijkYjWkiTKkQ+RIuo2GVskZ+RYWtP4v0i0NfEAM2oDEABCAtqvdARAEgVy13v+F8yrLj5yAbfVfHbjBeXiD5Ybi9QPSK+5fmsqL33diZDuOgp29kAFOONhbWcuVdJAQYjzJHFmde+1w1qF3MYY9mo9UzoMRxLUbcBB5enJaWPzVzgqoZxRLQu2tFOAQKs7ddJCMorQrehgKFCKOOOHv/QaT/chKLGZBR1OJno9APgMYuwCC1cBsVC0+CCqhxsn/mFMphG8nofaNA5T4l1p9egKtDYxBHkJ9WnL6uITUkCKPXZzkC2H/o1dn6KvY/iNl9P8oTW54Nxg6J/4fXlhCBtRgB0zCL1hjRG6yM2LU5IQvWEO/WgWVjX4/ErQ/DK6BAQLwdgY7AMIgDKVG//+PZXELSrRjcPG6w3YajwKhu4wds8evqXLpE2dT4ndcByczCo4G5QpzRGmdaNjkJFNJ45K7lJxDArpx8qmzaYRkSOkr1Wyairy1DDrFSnOocnjXwQgO8GoBYCZkIw3vYeDEbWruHRMfw/N1y6kLCLXR1u1DX0fha8G7NAEEuuCblfgMTMVbB8ha2UJKX/M/ypgnZjCR0pRnIJDhSKwl/+NaskSq+f9JKFywZBiMq1GIyciIDiNsgIuRYI1NKMPh6UdTfIEaPvfgShuMaFGFagZiSSM07BhRMzZKDwYpA//7jzxFhxiIQ2lp/Ecejce2Ew1uP3jcCiCAYH1gVtIyEhmXTxNtFrn6CLvjP8aY038G0uZSKcnQ2O5DImYwipj5X2Lc8h93zf0f1zpjdLVMaKPViJFqRpw1IJ4LihiJaQpjawaTWokQWrDBiGWAjrQqBbX/jS7+n0A0Ixa8QHaV4XcHtAkNvicYIIBgGZiFqoNWxMYUwf4huYmVyBqZqAUnpGQgcjIRA/6ta4ykNtuJ2WyBp5bFKFSIyJj/kQ9hY8DTBGfAUsMSMysAaVb/Z8QcjSa9v01cx4gSs4gfsELbnMIIWcQBufYU+XYGrE1ocA0MEEBMpNfAlIxS01Ivefc7/CdLP5F7gvGaS+LpG/+pdBg9XjdgO1ieNL/8J3gULDGnl/wnw2vkHIEz2M4IQzSTsV2GhraxgRvEBwggJmhOZic/c1GYwf5T0x5iMha2fgVaSUeyvwjZ+59IvYQ2mf/HxCSF2X8Gks+4wrqrCQ8bdrctsRH8n9gWG66zp8k9XeM/GYOC9ACg+WxGjMszkDf0QzMyeCEHQADBrlRhplkzmSgzSVnyR62+KR6z/mM5DwTHyitGou0lt9lLoLGHKxMzMpLpHkYcmZiRhIX8SJmYEXnzAxP2OEdZAYX/gAB4TxWjb0zCmAjKKPdAbHvFbw4jE3Q1GZbrVZCO1QHPAwMEEAsDaD6JkXaOIa1y/k/kEkVSMzOhQoKI/jS2o1gYsW6txzKqSUm/mcw++X80e0laqUagP0r0dA4DfLSaEWM0mtC2SSLnovHu4iJ1exH9bjAkpk+O3ItB204Ir4EBAgiUz6l4IiUV+qv/Gcg4j4jaTR5S+qc4muVEiFCna0JIPfqNhCT2hbEWCoSasNSKnkF4QDy9MzLu8gScgQECiIWB4DpoUhe1UmmumKKNA4RqGHLUkV47Q0SIG0FmJNjMJ8IdhEbA/6OV70Qt5GAgooYklG4IbedDP2iAlEU+yNswGUmrieETt4On5kXPtNhGoaFHy4LzLUAAMTFQfKQsnWq6gXYDqbUjwVMpsSknNPD0n8QBu3/4/UzyDQjEDNLhqs1JLYyIjTNKDp2HFbGDq9aGlWX/MDI2I3Jzmhd0KgdAABFxLzBdnEs4IgluiKCgBiV14IgU80kpfBgZMQfP/qOdMsmI1u9E21+AGZr/8IctSdPpOBb1MuIbBGNC8sd/BoIbLDBqRWJqcvTtjNiWamLrgzNiMY+8M89IO2gC79gf7HhB8AGFKKeZQAewoOuiBZiZmRkAAgi2kIMGmZKRdvoo2hZC7kHi5Bw6TmIt8x/LemlGUJOJCbqahwnIhozqwo+hgdUg0ITLiDG+gzlYxIh1YAh5VA559xG2mpQJNVywFSxEne1FKLyxhztVG7xUOmGDqqfPQstJxNFEmCPRDJBN/ZwAAQTbjUTDmpWRhvroeYrlfwqj6j95mRl0fQYT9OB1xv/wM4MZYavsUU57hG5qB8tD9sPBpl3g2+jgW+QYkZrgTCSEB47aj+hClYjls0RHCykrpWhbqVC7UME1eIXUFwb1gfkAAgiYgRlp3IT+T4MyioEKB86RWyNTYwM/Ce1WtEULiN7Df6yRzAhfQQ9pqv5HuWiBCR5moJ0z/6H7Y5lQnEDKfDUjnlSMXov+Q2pOM6ANVqGby0RCZqVkKoh600hUrYGZsC1JRfSDoRg0dsUNEECgGpiFgS6AmvO26Mr+ExmCtNppRKndJK63JmpKCNIGY0I/EpYBfpQF0vmSjEinXjAhBsBAKekvaIEuInwZ/0PFkWt1bH1kRgJHxGI9/gJ17TNmAY28P/g/2uF7WA5tZyS9eU5u/UrNGhhlMxJa7QtqPv/9+xfEBl/0DRBAwMz7n4X+Q+j/aZSRiWmbkJtBGalYgBHjBmKW+uEa5EFujmLZSIBU+2HemoJ6quQ/UJsddkoFNDMywW54ANXojMgTZWi1LDwDQvQykryQA2nwi+TrVf4zYNn9QGFc0QeAxzcYkW7MQDsXGjqIxQ7akQQQQCT2gandHKZuKUh6Q4CefWBqFmbY+P+JD0OC+2wZ4MsVwZkV1sZm/I9S4yLYsKzJBD+9ghF2ReZ/RrSrWMiMa+KOXBkc3TsqVMFMOFwFysTQGpiFiYmJHSCAWKBbCmmY8QZhJkYxjtyjfKjdO6JF4YirZiNlKx8DSpMc9bSXv8g9bwbY3UyMjEyQo2f+o46EI2ph6DDaP0RKZSRmEArrSR3USDODaCEH7IAAwpdTgPYvcAEEEAWj0P8pSKyUZmJKehc4+tCY7Rga9P2JCS9qNt8IHM/zH00NI/pwGZbzTAhdOcPIiHTkIOLkR+jRFFA9wIwMuvcHNtiGnMn/Q1Iv8hZ7+Jgc+sAeI6H5WmKnJYlZxUXtTI5jIwPcz9BlPeDT9ZjhtS8SZgYt5gAIIBYGrDuRBqJJQo9SkAT3/f9Pg8xMy0KQlD48MadV4BqhxTbAhC9cGZH6skhH1DEhH1fHhDgPmhEyZfb/P+wSF0Sf+h9KLmZEmvyCjsz+/4+jwUEoQzMykDe4RWnhij3OGBlg04T/MTIurA8Mzbf8AAFE5Qw8kAUADe35/58KcTVIDhwmJIf3cGRC2/3IdQvS4BcsEcMvRvuHMqj2H7b9EHkK6j8jYsDnHxP8+pX/SDXaf0ZGpEMqsU1fDT6Ac+EqYjGHBEAADbIMPFAZmVY1IIFmOs4m+38G4kZq8dWUpDSn0W9mQGTU/yiHjKO5CeMsLWzTcP+Q2r94ajgiT1VnRJpJYESZD4dkZCbm/+AbDWCHuP9HdN/hff//UBqy55YRXjggRrzJ7pRQXqD+R66Jke+vgtCwS76hQBQggFgYiLrLkZr9PlIyCi2G8xkHwE4i7P1PyZEveNQx4rqk/D/RSfI/ypXf//FneoxN/0w4MiiWUynhTWAm4hI8uhAjpM8I60UyMf5Hy/hI5zwjBc8/YEZngp0kAt+7jJqNEK0ARgLxwEhZeoAOYIHwfyzzwLANDdB10cIAAUSjDDxY+ryk9AsHokFEB/v/41rXTGQTGOcla6TGHyNSBiGULEh0HyNpbmJE2xDDzPgPNYgYsQ30QQbY/kCzNPLl54xIZRAjIzXS/3+UwgcPEAIIIBaGAQUDPQ83WOynx0AJcpMXWyZhwN8U/4/eTGZkwHU5OF6x/8TYzUCEPYRujsRVYxNzpy/S7Ywotzj8Q2l5/Ecfq4cdCQta3AI98B0W1oyww94ZCccZpDz4h1LzwkcL/qFsMuQDCKABrIGHwnABvZrt9GrloO06YiR15xAp53gjD3gxkWgGOZeyUaMAJ+X6UGjjn+k/0pG6iMExlGur/jMi7kFGW3oKDh30rgV42o0ZaDYj9KhZJvgaaNAWQqQbHrgAAoiFYVCcQUJPJzAOc//hG3LBc6A7Rn8Zs6/3H7bCCu9gFPqgFhMD7t1G6KPBMHcxYYkqYmp7crtqOGprvMYzInoojNhHDRiZEOEGH2xjRBVjgF6LCjoLGkSDN5kA/c/GxgLE7ED8D5hpWRjY2dkZWFhYGFhZWcEYlJGBgBcggFgYRsEwb1kwkljTkTsfSup2Psqu32EkdCsiyeHERGRNzYiDjTmw9/8/NnnkpjlsUOwffNCNETqKxcnJAczQvMDM+geYuZnhGReEQZkZWivzAARg7uxWAARhKLxjPwRBvf/DppU/7ZhIRRd1J+FURFm1b2c3SKwwgYH99ZEUudJK/Drm9zYOfIfoeqIsUgk1oewH4qoObBL539kDl8rSGckN0SFGek01/B2b7MxpDG072h9ltFCunUuGMlXsQkFwkF4ZIhnmxEqtXFtYk+ZXa05ItI3TmeKiQuPWSOgJMm0yB33mEVTfr5NxnKQfZmmbxV9Wr76xeWBu7x54FYCxK1xiEATBgLttv/b+D7q7VsvcpyBpq7Uf7mwHqCVhgh/5d0IZUeLBLFigvJdEcu97z19A/lsklDZgx9O+tm5EOsBb2OZj3Kac9U+49mngEVS+JpxvixrFLbAbn/ky/4VQ2SfoV6ip82HSTj88BJGbkzQnkDz92Or3mPywHNRNri7CMQUN4GN2lwsVX2pdLgbjrkpWrYzY8nq9rn3Iu845uEKtjcriOlk9Zy8Z6oi1Ya4crZMfitD22WKqxbIAWmgm6ctCT0ixK7/31X2+Nkb8EWDZktOtLygpSmkyRe+BKyBXq8luLdnuk+guV8y7XaBdDIFhgbA81Ajlm1GfwT9x0TkeS535Db435Mxlo5vC9LhdIUdGNP/KdOAdsp6CdkgpZZ6I8vwIwNcV5DYMAsEdlEPUW7/QQ77Qh+Ud/Uu/1UNUKVLrJjbQ2QUM67g9GWNs1jKzkyywowB+4wd8ryAW6UmUdL1bfjp+Xj8up9fL9eXM02e3vSv/EeAbdamk43EbxBxifeJTtmwR22/uKnDwMt+pefTshK+xRgvzo96P86T7E/hjL2h7ZAfH8Z8sVq7ghO/M/+2U7tmcWh22KvfbWVg8RC7NErMPw9TsqAXVQSVVurIxjBFBqoxgyxKrFpGBuj0vrKzTQV77a04DcAwE7JU1MNN/Ylp5ZfYyCdrGUnufUKI51ioM2r0paRL0HuTRcqisDbRsJLZUk8DgoBcb+JqYaGE1wRSiFOKz68E2TWNmGwInTwgED6DYIMhw5wNnAx8IJqsjwCDfLE80+Ytd3grQjBjnel7aKVlm+WHdFJNdX/gukXYoeUbjIB4jjwdbg5LV1qWS67LnxX8FIOwMkhCGQSiaj2sXHsVjeVFP4sqtM2q1BoFQStOOLjpjq0la5AUmhaAAn/3YVEHCp1yux9vw3p8K6mEVl/7XNG3bKeBX/W38dTARoXK8dgG5V2+vrg5aeDDowAgPjFPZTJTk4rDPwstg/5xZG+c0xfLOjufiHWhOc00lJaOfDmAEROyK2foOZeYSliuLIru/FO8qMa9kcnpGanNggObjYpdS/QmxpQ987CxH8kEMKvs5pWcrAZdNGzDwTNzklbbVGsn1UYB7NeUnBUVAUsUXqLio9VHl1+9HkYdaMr32UDAEOIXp3gA06J7M1b1MA/MlbUaR3rsErNaPAqJASxseGqQk7epQq1pIzSqY6tnAPtu9s2ZlUJ32PkFXXxSrtTGe//PSBenNW5v55JqLkm8z8RWAuGtJQhiEoX040zN4Lu/u3jtYO46OxISkIVRw3LlgoB8+pSTv8c1wECtxHa3343S+nCSZwzw/Glum+1UiPuaY0O134ctEt9tbrXswOgXGhwJo00ezUqVfkQhhqlQIcZULbAdIoEpuIT2Hhq+NdK909D2tB+yUETpnELsgOJveBMD20qaI1PUb0sZAUFGs5hGECm0dSB9qMmWmjJuyNEQovcicjjTMrGsuUJ4VbIbe38KlayXanYWHfUGZhQq6SJgFgOkfDLmMIhb6x/6N3cruynkuikwiZPRUASqI43HECWUsQsfPzH9ZORi1yMMSX671P01j4KBfjs6NNnvHx/fSn+dw3gKwd8Y4DIMwFIWmUnuYnqD3P0u2Lh1oFWOn/o2NLAWUpWOZEvMNJPLDQIac+/CW9Hzd0vy4p+sFkxde8sQ558MZwQNkCGnHb1Q36q+n7/k61D6u79/cWgDvfV3TslzIVEd9xvr9tX9GOMVJQGwf40DUAAmZje2ePYB1jBSCmF0TQKsiAh18sHeCBj4GVwJERe1v1cFOlnUAzIL2TU8BpmrLQAVpg0sfgfz8RG1ot4gwb1lpbcCvFv0+vnDW8i8/KB8B2Du7FABBIAjnCeoe3aT736SHRFNTmYHpR/Ktl4QgQbYN5tvVRfQBYDusbsnPPExjmYXUM+9WCOwGSC9UCtQbTC24GA0VrkafIARG5iLAUkQQMVZhCgie43PzgIKCCwBB1yTa3yUb0OaOqZ4FPJt818Onkl0IKdc4tBcBakRmIUhO3vlvEdDUsRnMlP2NsE1wam2DO3nOgaa/Sm7M9eaA9FP0YTsEYO8KcgAEYRgBD3r25A/9/0mUOJ1pyZzEBxg5AYOFhJZuhIQHgUs3h5SmMA6KvZ7depJfBHCbL1QRhj/IJXTsisS9ELBqg4JsRkkIat6GZ+QvC0KtTD9Qoh3rqMQzZC0kgM5ThYF/Bb0SiUD24aCYfjkJTMWoKhIjnrubOtrVj1ObG7hbh1LjwyoXnslr3acN1ub9+nF/+UY5BBgA2RuagEaZ4ykAAAAASUVORK5CYII=',
	'task_sticky_bg1.png'=>//31k
		'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',
	'task_sticky_bg2.png'=>//31k
		'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',
	'task_sticky_bg3.png'=>//31.4k
		'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',
	'task_sticky_bg4.png'=>//31k
		'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',
	'btn_bg7.png'=>//6.7k
		'iVBORw0KGgoAAAANSUhEUgAAAEEAAAAWCAYAAACffPEKAAAACXBIWXMAAAsTAAALEwEAmpwYAAAKOWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanZZ3VFTXFofPvXd6oc0wAlKG3rvAANJ7k15FYZgZYCgDDjM0sSGiAhFFRJoiSFDEgNFQJFZEsRAUVLAHJAgoMRhFVCxvRtaLrqy89/Ly++Osb+2z97n77L3PWhcAkqcvl5cGSwGQyhPwgzyc6RGRUXTsAIABHmCAKQBMVka6X7B7CBDJy82FniFyAl8EAfB6WLwCcNPQM4BOB/+fpFnpfIHomAARm7M5GSwRF4g4JUuQLrbPipgalyxmGCVmvihBEcuJOWGRDT77LLKjmNmpPLaIxTmns1PZYu4V8bZMIUfEiK+ICzO5nCwR3xKxRoowlSviN+LYVA4zAwAUSWwXcFiJIjYRMYkfEuQi4uUA4EgJX3HcVyzgZAvEl3JJS8/hcxMSBXQdli7d1NqaQffkZKVwBALDACYrmcln013SUtOZvBwAFu/8WTLi2tJFRbY0tba0NDQzMv2qUP91829K3NtFehn4uWcQrf+L7a/80hoAYMyJarPziy2uCoDOLQDI3fti0zgAgKSobx3Xv7oPTTwviQJBuo2xcVZWlhGXwzISF/QP/U+Hv6GvvmckPu6P8tBdOfFMYYqALq4bKy0lTcinZ6QzWRy64Z+H+B8H/nUeBkGceA6fwxNFhImmjMtLELWbx+YKuGk8Opf3n5r4D8P+pMW5FonS+BFQY4yA1HUqQH7tBygKESDR+8Vd/6NvvvgwIH554SqTi3P/7zf9Z8Gl4iWDm/A5ziUohM4S8jMX98TPEqABAUgCKpAHykAd6ABDYAasgC1wBG7AG/iDEBAJVgMWSASpgA+yQB7YBApBMdgJ9oBqUAcaQTNoBcdBJzgFzoNL4Bq4AW6D+2AUTIBnYBa8BgsQBGEhMkSB5CEVSBPSh8wgBmQPuUG+UBAUCcVCCRAPEkJ50GaoGCqDqqF6qBn6HjoJnYeuQIPQXWgMmoZ+h97BCEyCqbASrAUbwwzYCfaBQ+BVcAK8Bs6FC+AdcCXcAB+FO+Dz8DX4NjwKP4PnEIAQERqiihgiDMQF8UeikHiEj6xHipAKpAFpRbqRPuQmMorMIG9RGBQFRUcZomxRnqhQFAu1BrUeVYKqRh1GdaB6UTdRY6hZ1Ec0Ga2I1kfboL3QEegEdBa6EF2BbkK3oy+ib6Mn0K8xGAwNo42xwnhiIjFJmLWYEsw+TBvmHGYQM46Zw2Kx8lh9rB3WH8vECrCF2CrsUexZ7BB2AvsGR8Sp4Mxw7rgoHA+Xj6vAHcGdwQ3hJnELeCm8Jt4G749n43PwpfhGfDf+On4Cv0CQJmgT7AghhCTCJkIloZVwkfCA8JJIJKoRrYmBRC5xI7GSeIx4mThGfEuSIemRXEjRJCFpB+kQ6RzpLuklmUzWIjuSo8gC8g5yM/kC+RH5jQRFwkjCS4ItsUGiRqJDYkjiuSReUlPSSXK1ZK5kheQJyeuSM1J4KS0pFymm1HqpGqmTUiNSc9IUaVNpf+lU6RLpI9JXpKdksDJaMm4ybJkCmYMyF2TGKQhFneJCYVE2UxopFykTVAxVm+pFTaIWU7+jDlBnZWVkl8mGyWbL1sielh2lITQtmhcthVZKO04bpr1borTEaQlnyfYlrUuGlszLLZVzlOPIFcm1yd2WeydPl3eTT5bfJd8p/1ABpaCnEKiQpbBf4aLCzFLqUtulrKVFS48vvacIK+opBimuVTyo2K84p6Ss5KGUrlSldEFpRpmm7KicpFyufEZ5WoWiYq/CVSlXOavylC5Ld6Kn0CvpvfRZVUVVT1Whar3qgOqCmrZaqFq+WpvaQ3WCOkM9Xr1cvUd9VkNFw08jT6NF454mXpOhmai5V7NPc15LWytca6tWp9aUtpy2l3audov2Ax2yjoPOGp0GnVu6GF2GbrLuPt0berCehV6iXo3edX1Y31Kfq79Pf9AAbWBtwDNoMBgxJBk6GWYathiOGdGMfI3yjTqNnhtrGEcZ7zLuM/5oYmGSYtJoct9UxtTbNN+02/R3Mz0zllmN2S1zsrm7+QbzLvMXy/SXcZbtX3bHgmLhZ7HVosfig6WVJd+y1XLaSsMq1qrWaoRBZQQwShiXrdHWztYbrE9Zv7WxtBHYHLf5zdbQNtn2iO3Ucu3lnOWNy8ft1OyYdvV2o/Z0+1j7A/ajDqoOTIcGh8eO6o5sxybHSSddpySno07PnU2c+c7tzvMuNi7rXM65Iq4erkWuA24ybqFu1W6P3NXcE9xb3Gc9LDzWepzzRHv6eO7yHPFS8mJ5NXvNelt5r/Pu9SH5BPtU+zz21fPl+3b7wX7efrv9HqzQXMFb0ekP/L38d/s/DNAOWBPwYyAmMCCwJvBJkGlQXlBfMCU4JvhI8OsQ55DSkPuhOqHC0J4wybDosOaw+XDX8LLw0QjjiHUR1yIVIrmRXVHYqLCopqi5lW4r96yciLaILoweXqW9KnvVldUKq1NWn46RjGHGnIhFx4bHHol9z/RnNjDn4rziauNmWS6svaxnbEd2OXuaY8cp40zG28WXxU8l2CXsTphOdEisSJzhunCruS+SPJPqkuaT/ZMPJX9KCU9pS8Wlxqae5Mnwknm9acpp2WmD6frphemja2zW7Fkzy/fhN2VAGasyugRU0c9Uv1BHuEU4lmmfWZP5Jiss60S2dDYvuz9HL2d7zmSue+63a1FrWWt78lTzNuWNrXNaV78eWh+3vmeD+oaCDRMbPTYe3kTYlLzpp3yT/LL8V5vDN3cXKBVsLBjf4rGlpVCikF84stV2a9021DbutoHt5turtn8sYhddLTYprih+X8IqufqN6TeV33zaEb9joNSydP9OzE7ezuFdDrsOl0mX5ZaN7/bb3VFOLy8qf7UnZs+VimUVdXsJe4V7Ryt9K7uqNKp2Vr2vTqy+XeNc01arWLu9dn4fe9/Qfsf9rXVKdcV17w5wD9yp96jvaNBqqDiIOZh58EljWGPft4xvm5sUmoqbPhziHRo9HHS4t9mqufmI4pHSFrhF2DJ9NProje9cv+tqNWytb6O1FR8Dx4THnn4f+/3wcZ/jPScYJ1p/0Pyhtp3SXtQBdeR0zHYmdo52RXYNnvQ+2dNt293+o9GPh06pnqo5LXu69AzhTMGZT2dzz86dSz83cz7h/HhPTM/9CxEXbvUG9g5c9Ll4+ZL7pQt9Tn1nL9tdPnXF5srJq4yrndcsr3X0W/S3/2TxU/uA5UDHdavrXTesb3QPLh88M+QwdP6m681Lt7xuXbu94vbgcOjwnZHokdE77DtTd1PuvriXeW/h/sYH6AdFD6UeVjxSfNTws+7PbaOWo6fHXMf6Hwc/vj/OGn/2S8Yv7ycKnpCfVEyqTDZPmU2dmnafvvF05dOJZ+nPFmYKf5X+tfa5zvMffnP8rX82YnbiBf/Fp99LXsq/PPRq2aueuYC5R69TXy/MF72Rf3P4LeNt37vwd5MLWe+x7ys/6H7o/ujz8cGn1E+f/gUDmPP8kcBa2wAAAARnQU1BAACxjnz7UZMAAAAgY0hSTQAAeiUAAICDAAD5/wAAgOkAAHUwAADqYAAAOpgAABdvkl/FRgAACPlJREFUeNpsjr0KgDAMhC8VCoqDbo4+pji7+ZSOjv6ACNbYmIIVPUgIueRLiJkBdt08L+0wTvu6HUBiQOxA8J7PIhnTSlpavfw7hEXeJp2nE49IIUAEkhYHpAm+AUdn9D/dIU1BMRv33leMP5V5iroqrE2zxh/oLwHE+P//36APnz6tPHntJQsnNw8DNwcbw79/QO8wgVzwjwHmXUYgnwnqmP9gx/wH8v+DPQwCf8Eq/8P9CQ4PqAf+M4IIJgif8T8sDBFqwcYywQMEETCwAP4PCQCwIKrHYGaB5P/D3AMTY2TECACQuvefvjJwM/1hMNaU/cnGxuoCEECM/37/3H3k8kMXAUERBg1ZQQao/9AsRlCMyHxGRGD/Z4T6Csj4B/MgTA4alf8YGYgD/1ETEIYc1I1wGp4qUT0OTklIxsDjB8g4ee0xgxDnfwYtFelNAAHE8unzV7PPP5gYtET4GV5++M7w7z8kJFn+g1IDNOwYGaEBwAixjBGc1iEGQ3wP1AeOejAfllD//vsHtR2SPf4j+4KoEMCMR5h+RpiHoaHAzIgU1/BIYgK66x8ipUAzLRsLC4O8pAjDzbv3GNTl/3oABKCjjFYABGEoelML//9TDUqHtXQTNaj3cbcdDteFM3lngXAQImUNrj+WXjAqvoQKiE558GXGpDFkaf39LjMCoeFjAaRHfFhaJmzrFP4XoqvH455F28C0Tpiz3500MndE+HUDXQaUCI8AbJRLDsAgCEQJae9/2iY1Olj52KLpwhg2BIZ5c3Rp1EYGXAV0F6EXKcZo64WqzEkEWygQsMURfzjCRAAIP5xPbvXlKBP6+Ol9NbHWzLwg6T3ckWzzYUMhZcQWwur2elafcRztEYCrKloCEARhQnX9/8/aS1JjQFkPeHqeHIxtroVOBwgHQqUm74kdBgCj+nhBCoIJrQJiZ+NYBs8ofFw0SyvQ3GQ9n7SJDRrKavTNiS3JMnuZJzCykoE/XnJYxIaD0pCr/plqnx+OdWN/7lswD1e3AEyVUQ7AIAhDTbf7H3fJErWjUnRfRiIG+tDe9as+b7cIuogmn5MBj9+0T7Joxxq9thB07ccSwc8hYv1Pdlsmt8AImQ65nJph8iSSdNmziV5yKdGH60LOBjCXeylOJylH9VAnp02c7oP12Fv7BGCyDJIAhkEQGJv//zjVAmLbQ2ZyklFwEyWBzgEHOOmYjcCHIWqlX42y2zec4irdmMRB57wnEwCh5sGSeJkz1wBVTaB+1Zsw+WN4tmNn/b8F4XXYETZq1rQ0QNVeE/8yiKPXTMak7hwCE7Z3gkJd/xGAyyrJARCEgVWMF///V41dnJZBohdIOLR0NthGyp+3y2VR6M+/AVbvTYIeq2Koq5ZDY9cotNXgM0gi1ZeABF+OBGv0WBlWyV4NIfT1T67xZsHMoWS6yKES2gtEfF6KRaatnCq3Gtw7GTg13GnXJgcRfgSgsjySAIRBKDoQ739iBXmUGHcZFqH9QtGB32LS1hrrv6NMmrADebZMo4ECIP9MjCFIJjCvX9CKtMcsThNNdErzyoBiFcu8c/vnNocviMsuZW2T1rpL4n0fYjl+ZoPWRrklBbStmji0vUr4I9UrAJXlYgQwDIJQq7nuv3C1AbGfBXLmCciqOVARtrxD0D7eN21UYlNiQrohPsntF0Nvv7OxgtLFv71dm41zCq63fJ3wk0AO9QmQqscipW/jZLNT7hmj80CK+DVL2QsyT29yUBrm8/Fd9hwep00k3QIwXSVJAIMgDFD//+FaCiEwvXlwZEtI3BkokzpnhW46kWCUvJFlQB4vZSfvW1YYPQlGbRgEFH15TX/WEhInTDroYOR1LzbnlF37v6DD7d78RkmsczVVKY8yct1+pJaJJkpz0mPqZGi6ol55CgmfAEyXSw7AIAhERWza+x+2iyZAYcDPwsREo5F5juMIeEKVwY+r+EEdtUxXZDsAkVcQlEsrk0zjjDksq1YeLxJmKdRCMQBqbbl6xl3DGE+zrORJh7pnAznwqZ5e0Gt+2/8LrWdVy3+wZ1yH+QZjYcH5rtu7b1L9C0B1GSQBCIMwsNo6/f9XvSsWsqDeOHWAJiEZAaFAwmjT+o9T4fddBzhxvlURTRuP15m+gxy/Bt7MQS3lfp1bNS/k1/ltKZI78Cew+dIkiAgrPiCpYJ9cc/HVN71aURl6pzNd9Vy5YTulQY8AXJdLDgAhCEMJamK8/11nMxqB1k/cupFv6cton31cRUrIAe7qIANEo3w8iQw6IJDelU6v4IXry99d7KDUksBkBpvwYDtuo311/1hzJJ24KqdYEv4FHENmeADp3wYJRm3Lq8ekC7ZxPpvl+/EyTQGYLnsdAEEYCJ8UF2N8/7d0cTGmYr0W6MJCUugPx3dVqvVMLxwbcHoLGPBhEjNJieqyyrZUwhN8BDObv9Edf/7h5FoI4sJ96iFF0OMMKvyFtUw7LEj0CoyiDCxOPkFp+kIbOtXmm3UTWJx7djvnbmofwopPALHw8XAyMP54CvTNDwZREQ6G3/8QqfzfP1iDgwFeUIIDgRFiyT+kbjRy3xCWguDZEJqSYJ1AJiZE3x7W/IU5kgXeeYHIMcBTBuYYAvLQCiIlIHe+MHtqoCgGFgcMbx+9ZeBn/sPAxsbKABBALFwcnK+lRXilb166waCoIQ8sMDggrUIGSE0BL2QYYTU0YgwHafwEbPh/tM4yotmF6hx4SxHWgIJqYkRuIf5H8i1S24EJzWMIt/xHyEFTBHIahbmMmZGZ4dOHzwyv7j1gsNSQBFaVLI8AAgjYIPzf8P3rl/qr1+8wPHn3HRh7rJAczsQEbS78x+jS//+Ppc/PyIg6VIQ2wPEfzTFM/1E9A+tgoYr9x2INI8Kq//+xJw8CoxUs/38zqMmIMSgryTGwsrElAwQQMFL+SwPxwV/fvyq//fCJ4fvXr+ASHFyqEjsShDEIycCAUnRjGxthwD5ihG4ktrDGJk9kGICt4AUWgPx8wsBUzzoZaEohQAAxQkPbGkguBSqQAhYE4Cr+33/sY3SkjQiRop8+gBFeF/+fBeSWAemfAAEGAI3fhyClh1TZAAAAAElFTkSuQmCC',
	'pop_btn.png'=>//3.9k
		'iVBORw0KGgoAAAANSUhEUgAAADkAAAA5CAYAAACMGIOFAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAOdAAADnQBaySz1gAAAB90RVh0U29mdHdhcmUATWFjcm9tZWRpYSBGaXJld29ya3MgOLVo0ngAAAsjSURBVGiB7ZtPaBP7Fse/U02NmXjVNrnxVU1IwAulVhoXQhe9i4dNBPXRdPdoF1pBK6gLhXYhWBQEFXShC/9A1UXK20haUKGdFuHeLgIursVaAgotydW+xqb1ajNt+kfnLeZ3Jr9kJs0fq09478CPSfqbmZzPnDO/3/n9zqmgKArWSgRBEACsB2BixzLWhFUuUwB8YW0FwDKAFWUNFRO+9l4MbB2ActZMXFvHmgBjUIW1z6wtc22Jtc9fC1wyJAdnhgq3gX3ewDUCJYtmC1mQABe5lmLHJfa5ZNj1pVwkCALBEZjFoFE/77bZwrspgc0btHIAi4IgpBRF+VysvkVBMuuZAGxkTQRgBbCJO4qsjyxMlszlrmRJstgCABnAHIAkd5QBrBcEYQHAcjFWLRiSWY+UFxnQTwA2s0agPGC+wSd70OFBCfAja5/YdxOAlCAIC4VatSBIQRDWQ3VBstxmAFsAbGWff+IANwAo9/v9jgMHDuzYvXt35bZt2za53e4KURTL6Z6yLC9NTEzMTk1Nzb169Wqmv7//7cDAQJyBLrKHRg/OivQrUM4eQJkgCPOKoqzk1T+f1QVBMOWAq2Cff2L9G71er+3MmTPVBw8e/MVut1vzP75MmZ6eTj59+vT1zZs3Iy9evEgg7bqfAPwFYBbAB/b5I4OVFUVZLhmSAdLTJLBKdtzK+kSv12u/du3avv379++iaxOJBCKRCKLRKKLRKGKxGObn57V7WywWOJ1OuFwuuFwuVFdXw2azaf1DQ0NvOjo6nr948WIa6Xf0AwOd4YCTAOZWA80JyVzUCtVSFQBsAOzsuIUBWh48eFB/5MiRvXTd8PAwhoeHEYlEcj68XFJdXY2GhgY0NDRof3v48OEfR48eDUMdZeegWjEBYJodZ6FaOpnLdQ0h2SAjcoA/s2Zn3zf5/f6dt2/f/rvb7a4guFAohEQiUTRctthsNjQ3N2uwExMTsydPnnw2MDDwJwOdZZDvWSNQ2Wgw0kGyaYLewQoADgDbGGQlgM0+n29HKBQ6JIpieTQaRU9PT0mWyyfV1dVoaWmBy+WCLMtLzc3NTyRJegv1fZxhgFMA4gyU3tEMKCPIcqgW3MrAqhikDcCWCxcu1F28ePFXQLVeMBjMeNfWWiwWC1pbWzWrdnV1/X7p0qURpN12CsAkVOAPAD4pirKUE5K5KY2iPzO4KqjW3Orz+VwDAwPNBHjv3r1vBpctx48f10D9fn9IkqQoVKg4VMgpqKAfob6fmttmh1pmqHPdJqiDizaK+v3+naFQ6BDw/QEB4N69exgeHgYAhEKhQ36/fyfTk5/ONiEdjGiiWZKNpuSmDgDbAfwNqkW3jI+P/9PtdldkAyaTSaRSqVUVNJvNsFpzT5v57sFfTxadmJiY9Xg8/4Lqtu8B/BvAO6iWJbddATIjHjNr5K4UyYjd3d31bre7IhqNIhgMZiiQSqUgiiIePXpkqGBPT492jREoAV69ehV79uzR9Xd0dGB0dFS7NhgMwul0wu12V3R3d9cfO3bsd6bnPNIx7gLUyCkJMHdlVqTAexMP6PV67W1tbXtJ4exBxmw2Q5Zl9PT0GEI2NTVBFMWclkqlUqitrTUE7Ovrw+joKMzmtPfNz89rv9XW1rbX6/XakZ7uKIbeCMDEuLR30gQ15rQgazVx5cqVfQByTvD0hHt7exGPx3X9oigiEAgAUK3GC30/d+6c7rp4PJ7TAyKRiPZ+Mv1oRUTRmQXp9SzK2LxYjszVhRWAxev12nw+3y4ACIVCOkVIyJrXr1837G9qaoLD4ciwJrnp/v374XA4dNdcv34dsixnWJEX0sfn8+3yer026A1kBlAuCIJQBnW9Z0J68UvrwQ2nTp2qBlQrrhbJWK1WmM1mjI6OIhwO6/pFUURLS4sGB6Tf5fb2dt354XBYc9NcA1YikdCsyfTcgLRFacViArCuDOn9GDqJlkumw4cP/0KQ+YSUIQtkS2Njo2ZNAg0EAhBFMeM83iNWG5F5vZiehgwATGVI767RCWao68FtdrvdSquJQoTc9s6dO4b9ZM1UKgWHw4GmpibdOfnclJdIJIJEIgG73W71+/3bkH7teMj1tGrnQU0ATH6/fzvdqFAhtx0aGsLLly91/Y2NjfB4PBpwthXD4TDC4XDeeTUbFACYvrxXatuiBEnv5np2LKupqbEBQCwWKxiSQAHgxo0bhv0nTpyAx+NBY2Njxt+LcVNeSD+mr44DDFJAGlRrVVVVVgCIRqMF/yCJ2WxGPB43nDv37NmDCxcu6P5ejJvyQvoxfXUcAASCzAYV3G53JX+TYoTcNhgMYnx8XNefPWWU4qYkpB/TV8cBBmkotOlU6jKKlM01CJGU6qYkpB+/SZYtOSHXQmjuNBqESOLxeEluWox8M0iKaOrr6w3jUhKPx4PW1taM+XOtJSekLMtLgLoyL0UoojGKS7OlpaUFHo8n75LNSEg/0tdIypDOLH3hmjIxMTEDAC6Xq+gf5gNvo7nQKCKih1GsNUk/pq+OA4BSZtDxBcCXycnJJH+TQoUPvOvr6zP6ZFnG3bt3DQejUt2W9GP66jgIkk+frbDjl7GxsQQAOJ3OoiApZDMKvPv6+hCPxzE0NGS4LCvFbUk/pq+OA8AXgqQM7yI7Lvf3978D1G3BQoUscPbsWZ2bxuNx9Pb2at9zLcuKdVvSj+lLCVziWAGD5AEXoGaVliRJmpqenk7abLaCQMlNm5qaDEfTu3fvalPFalNLMW5LqYXp6emkJElTyMyKaaBlWfQL/AmPHz9+DSBj2z6XpFIpeDwenDhxQtf38uXLjIgmX3xbqNuSXkxPQwYAy2VIp7Ipy0sbQYu3bt2K0M34ZEy2rLaNAahWBDIjGopv+/r6DK/J57Y2m02DZHoSoMw4UozrcxnbUicz8xne+ZGRkYQkSW8AoLm5OSdgKpVCa2urtoziZXBwEOPj47qIht99M5pS8rkt6SNJ0puRkZEE0rt1c4wjBWBJURSFggEydfaJC52dnc8B1ZpG7ybtttGCmBeaMngoXmiRncuaudyWsl8AwPTLTsHPI+2uGZvLlKazQ00NbIeaJqjs7u7+ta2tbW8sFsPly5czgvZEIgGPx6MbTQHg/fv3iMfjq64uyBNqa2sN+8fHxyHLsva6WCwWnD9/Hk6nE/fv3/+D7bvOQE0TvIOaMpiGurmczIb8JjvohSyfvtsOuqIoK4IgLEI1+0eoO160KVTe3t7+LBQK/aOhoaEcgAbKj5alSqH3IEBZlpfa29ufITMx+4HpLQNY5BOy2QE6zTF0IaWs5yRJ+jMQCDwB1Pfz+PHjXwVWrPBZrUAg8ESSJErIUor9L/ad5npNMiBZumsB6hP6yG5AhQhzg4ODb7u6un4D0qClrlIKFYvFkgHY1dX12+Dg4FtkWpCsOA9AV/pSUqa5sbFxR29v7yFRFMtjsRiCweA3yzS3trbC6XRCluWlQCDwhAF+XaaZgeatGfD5fDvv3Lnz3WoG2tvbn3Eu+nU1AxxoQdUf3d3d9ZT1Iti1rP5g08TaV39woAXV8dTV1dmvXr26j5JDQLqOJxaLabU82XU8VMPjdDp1dTySJL3p7Ox8PjIy8u3qeLJAC6rIqqurs50+fbr68OHDJVdkPX78+PWtW7ciLFT79hVZHGjRtXU+n89x4MCBHTU1NZVVVVVWt9tdaVBbNzM5OZkcGxub6e/vfytJEl9bR0WEn5Ae6TPgAKxNbR0H+qNUSVLwXXCVZFGVyz9AvSutFYuqdy2pPPu/ULlMpdolVS7/T9Sg//+/CYq+2Q/6fyH/ATzegtP6Qc0XAAAAAElFTkSuQmCC',
	'pop_bg.jpg'=>//3.7k
		'iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAACXBIWXMAAAsTAAALEwEAmpwYAAAKOWlDQ1BQaG90b3Nob3AgSUNDIHByb2ZpbGUAAHjanZZ3VFTXFofPvXd6oc0wAlKG3rvAANJ7k15FYZgZYCgDDjM0sSGiAhFFRJoiSFDEgNFQJFZEsRAUVLAHJAgoMRhFVCxvRtaLrqy89/Ly++Osb+2z97n77L3PWhcAkqcvl5cGSwGQyhPwgzyc6RGRUXTsAIABHmCAKQBMVka6X7B7CBDJy82FniFyAl8EAfB6WLwCcNPQM4BOB/+fpFnpfIHomAARm7M5GSwRF4g4JUuQLrbPipgalyxmGCVmvihBEcuJOWGRDT77LLKjmNmpPLaIxTmns1PZYu4V8bZMIUfEiK+ICzO5nCwR3xKxRoowlSviN+LYVA4zAwAUSWwXcFiJIjYRMYkfEuQi4uUA4EgJX3HcVyzgZAvEl3JJS8/hcxMSBXQdli7d1NqaQffkZKVwBALDACYrmcln013SUtOZvBwAFu/8WTLi2tJFRbY0tba0NDQzMv2qUP91829K3NtFehn4uWcQrf+L7a/80hoAYMyJarPziy2uCoDOLQDI3fti0zgAgKSobx3Xv7oPTTwviQJBuo2xcVZWlhGXwzISF/QP/U+Hv6GvvmckPu6P8tBdOfFMYYqALq4bKy0lTcinZ6QzWRy64Z+H+B8H/nUeBkGceA6fwxNFhImmjMtLELWbx+YKuGk8Opf3n5r4D8P+pMW5FonS+BFQY4yA1HUqQH7tBygKESDR+8Vd/6NvvvgwIH554SqTi3P/7zf9Z8Gl4iWDm/A5ziUohM4S8jMX98TPEqABAUgCKpAHykAd6ABDYAasgC1wBG7AG/iDEBAJVgMWSASpgA+yQB7YBApBMdgJ9oBqUAcaQTNoBcdBJzgFzoNL4Bq4AW6D+2AUTIBnYBa8BgsQBGEhMkSB5CEVSBPSh8wgBmQPuUG+UBAUCcVCCRAPEkJ50GaoGCqDqqF6qBn6HjoJnYeuQIPQXWgMmoZ+h97BCEyCqbASrAUbwwzYCfaBQ+BVcAK8Bs6FC+AdcCXcAB+FO+Dz8DX4NjwKP4PnEIAQERqiihgiDMQF8UeikHiEj6xHipAKpAFpRbqRPuQmMorMIG9RGBQFRUcZomxRnqhQFAu1BrUeVYKqRh1GdaB6UTdRY6hZ1Ec0Ga2I1kfboL3QEegEdBa6EF2BbkK3oy+ib6Mn0K8xGAwNo42xwnhiIjFJmLWYEsw+TBvmHGYQM46Zw2Kx8lh9rB3WH8vECrCF2CrsUexZ7BB2AvsGR8Sp4Mxw7rgoHA+Xj6vAHcGdwQ3hJnELeCm8Jt4G749n43PwpfhGfDf+On4Cv0CQJmgT7AghhCTCJkIloZVwkfCA8JJIJKoRrYmBRC5xI7GSeIx4mThGfEuSIemRXEjRJCFpB+kQ6RzpLuklmUzWIjuSo8gC8g5yM/kC+RH5jQRFwkjCS4ItsUGiRqJDYkjiuSReUlPSSXK1ZK5kheQJyeuSM1J4KS0pFymm1HqpGqmTUiNSc9IUaVNpf+lU6RLpI9JXpKdksDJaMm4ybJkCmYMyF2TGKQhFneJCYVE2UxopFykTVAxVm+pFTaIWU7+jDlBnZWVkl8mGyWbL1sielh2lITQtmhcthVZKO04bpr1borTEaQlnyfYlrUuGlszLLZVzlOPIFcm1yd2WeydPl3eTT5bfJd8p/1ABpaCnEKiQpbBf4aLCzFLqUtulrKVFS48vvacIK+opBimuVTyo2K84p6Ss5KGUrlSldEFpRpmm7KicpFyufEZ5WoWiYq/CVSlXOavylC5Ld6Kn0CvpvfRZVUVVT1Whar3qgOqCmrZaqFq+WpvaQ3WCOkM9Xr1cvUd9VkNFw08jT6NF454mXpOhmai5V7NPc15LWytca6tWp9aUtpy2l3audov2Ax2yjoPOGp0GnVu6GF2GbrLuPt0berCehV6iXo3edX1Y31Kfq79Pf9AAbWBtwDNoMBgxJBk6GWYathiOGdGMfI3yjTqNnhtrGEcZ7zLuM/5oYmGSYtJoct9UxtTbNN+02/R3Mz0zllmN2S1zsrm7+QbzLvMXy/SXcZbtX3bHgmLhZ7HVosfig6WVJd+y1XLaSsMq1qrWaoRBZQQwShiXrdHWztYbrE9Zv7WxtBHYHLf5zdbQNtn2iO3Ucu3lnOWNy8ft1OyYdvV2o/Z0+1j7A/ajDqoOTIcGh8eO6o5sxybHSSddpySno07PnU2c+c7tzvMuNi7rXM65Iq4erkWuA24ybqFu1W6P3NXcE9xb3Gc9LDzWepzzRHv6eO7yHPFS8mJ5NXvNelt5r/Pu9SH5BPtU+zz21fPl+3b7wX7efrv9HqzQXMFb0ekP/L38d/s/DNAOWBPwYyAmMCCwJvBJkGlQXlBfMCU4JvhI8OsQ55DSkPuhOqHC0J4wybDosOaw+XDX8LLw0QjjiHUR1yIVIrmRXVHYqLCopqi5lW4r96yciLaILoweXqW9KnvVldUKq1NWn46RjGHGnIhFx4bHHol9z/RnNjDn4rziauNmWS6svaxnbEd2OXuaY8cp40zG28WXxU8l2CXsTphOdEisSJzhunCruS+SPJPqkuaT/ZMPJX9KCU9pS8Wlxqae5Mnwknm9acpp2WmD6frphemja2zW7Fkzy/fhN2VAGasyugRU0c9Uv1BHuEU4lmmfWZP5Jiss60S2dDYvuz9HL2d7zmSue+63a1FrWWt78lTzNuWNrXNaV78eWh+3vmeD+oaCDRMbPTYe3kTYlLzpp3yT/LL8V5vDN3cXKBVsLBjf4rGlpVCikF84stV2a9021DbutoHt5turtn8sYhddLTYprih+X8IqufqN6TeV33zaEb9joNSydP9OzE7ezuFdDrsOl0mX5ZaN7/bb3VFOLy8qf7UnZs+VimUVdXsJe4V7Ryt9K7uqNKp2Vr2vTqy+XeNc01arWLu9dn4fe9/Qfsf9rXVKdcV17w5wD9yp96jvaNBqqDiIOZh58EljWGPft4xvm5sUmoqbPhziHRo9HHS4t9mqufmI4pHSFrhF2DJ9NProje9cv+tqNWytb6O1FR8Dx4THnn4f+/3wcZ/jPScYJ1p/0Pyhtp3SXtQBdeR0zHYmdo52RXYNnvQ+2dNt293+o9GPh06pnqo5LXu69AzhTMGZT2dzz86dSz83cz7h/HhPTM/9CxEXbvUG9g5c9Ll4+ZL7pQt9Tn1nL9tdPnXF5srJq4yrndcsr3X0W/S3/2TxU/uA5UDHdavrXTesb3QPLh88M+QwdP6m681Lt7xuXbu94vbgcOjwnZHokdE77DtTd1PuvriXeW/h/sYH6AdFD6UeVjxSfNTws+7PbaOWo6fHXMf6Hwc/vj/OGn/2S8Yv7ycKnpCfVEyqTDZPmU2dmnafvvF05dOJZ+nPFmYKf5X+tfa5zvMffnP8rX82YnbiBf/Fp99LXsq/PPRq2aueuYC5R69TXy/MF72Rf3P4LeNt37vwd5MLWe+x7ys/6H7o/ujz8cGn1E+f/gUDmPP8kcBa2wAAAARnQU1BAACxjnz7UZMAAAAgY0hSTQAAeiUAAICDAAD5/wAAgOkAAHUwAADqYAAAOpgAABdvkl/FRgAAAEBJREFUeNpibG1tnclABAAIICYGIgFAABGtECCAiFYIEEBEKwQIIKIVAgQQ0QoBAohohQABRLRCgAAiWiFAgAEAgFkCPJ8d6BMAAAAASUVORK5CYII=',
	'nicEditorIcons.gif'=>
		'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',
	'haha_fonts.woff'=>//9k
		"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",
	'favicon.png'=>//3.5k
		'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',
	];
	$jquery= '
		/*! jQuery v1.7.2 jquery.com | jquery.org/license */
		(function(a,b){function cy(a){return f.isWindow(a)?a:a.nodeType===9?a.defaultView||a.parentWindow:!1}function cu(a){if(!cj[a]){var b=c.body,d=f("<"+a+">").appendTo(b),e=d.css("display");d.remove();if(e==="none"||e===""){ck||(ck=c.createElement("iframe"),ck.frameBorder=ck.width=ck.height=0),b.appendChild(ck);if(!cl||!ck.createElement)cl=(ck.contentWindow||ck.contentDocument).document,cl.write((f.support.boxModel?"<!doctype html>":"")+"<html><body>"),cl.close();d=cl.createElement(a),cl.body.appendChild(d),e=f.css(d,"display"),b.removeChild(ck)}cj[a]=e}return cj[a]}function ct(a,b){var c={};f.each(cp.concat.apply([],cp.slice(0,b)),function(){c[this]=a});return c}function cs(){cq=b}function cr(){setTimeout(cs,0);return cq=f.now()}function ci(){try{return new a.ActiveXObject("Microsoft.XMLHTTP")}catch(b){}}function ch(){try{return new a.XMLHttpRequest}catch(b){}}function cb(a,c){a.dataFilter&&(c=a.dataFilter(c,a.dataType));var d=a.dataTypes,e={},g,h,i=d.length,j,k=d[0],l,m,n,o,p;for(g=1;g<i;g++){if(g===1)for(h in a.converters)typeof h=="string"&&(e[h.toLowerCase()]=a.converters[h]);l=k,k=d[g];if(k==="*")k=l;else if(l!=="*"&&l!==k){m=l+" "+k,n=e[m]||e["* "+k];if(!n){p=b;for(o in e){j=o.split(" ");if(j[0]===l||j[0]==="*"){p=e[j[1]+" "+k];if(p){o=e[o],o===!0?n=p:p===!0&&(n=o);break}}}}!n&&!p&&f.error("No conversion from "+m.replace(" "," to ")),n!==!0&&(c=n?n(c):p(o(c)))}}return c}function ca(a,c,d){var e=a.contents,f=a.dataTypes,g=a.responseFields,h,i,j,k;for(i in g)i in d&&(c[g[i]]=d[i]);while(f[0]==="*")f.shift(),h===b&&(h=a.mimeType||c.getResponseHeader("content-type"));if(h)for(i in e)if(e[i]&&e[i].test(h)){f.unshift(i);break}if(f[0]in d)j=f[0];else{for(i in d){if(!f[0]||a.converters[i+" "+f[0]]){j=i;break}k||(k=i)}j=j||k}if(j){j!==f[0]&&f.unshift(j);return d[j]}}function b_(a,b,c,d){if(f.isArray(b))f.each(b,function(b,e){c||bD.test(a)?d(a,e):b_(a+"["+(typeof e=="object"?b:"")+"]",e,c,d)});else if(!c&&f.type(b)==="object")for(var e in b)b_(a+"["+e+"]",b[e],c,d);else d(a,b)}function b$(a,c){var d,e,g=f.ajaxSettings.flatOptions||{};for(d in c)c[d]!==b&&((g[d]?a:e||(e={}))[d]=c[d]);e&&f.extend(!0,a,e)}function bZ(a,c,d,e,f,g){f=f||c.dataTypes[0],g=g||{},g[f]=!0;var h=a[f],i=0,j=h?h.length:0,k=a===bS,l;for(;i<j&&(k||!l);i++)l=h[i](c,d,e),typeof l=="string"&&(!k||g[l]?l=b:(c.dataTypes.unshift(l),l=bZ(a,c,d,e,l,g)));(k||!l)&&!g["*"]&&(l=bZ(a,c,d,e,"*",g));return l}function bY(a){return function(b,c){typeof b!="string"&&(c=b,b="*");if(f.isFunction(c)){var d=b.toLowerCase().split(bO),e=0,g=d.length,h,i,j;for(;e<g;e++)h=d[e],j=/^$-+/.test(h),j&&(h=h.substr(1)||"*"),i=a[h]=a[h]||[],i[j?"unshift":"push"](c)}}}function bB(a,b,c){var d=b==="width"?a.offsetWidth:a.offsetHeight,e=b==="width"?1:0,g=4;if(d>0){if(c!=="border")for(;e<g;e+=2)c||(d-=parseFloat(f.css(a,"padding"+bx[e]))||0),c==="margin"?d+=parseFloat(f.css(a,c+bx[e]))||0:d-=parseFloat(f.css(a,"border"+bx[e]+"Width"))||0;return d+"px"}d=by(a,b);if(d<0||d==null)d=a.style[b];if(bt.test(d))return d;d=parseFloat(d)||0;if(c)for(;e<g;e+=2)d+=parseFloat(f.css(a,"padding"+bx[e]))||0,c!=="padding"&&(d+=parseFloat(f.css(a,"border"+bx[e]+"Width"))||0),c==="margin"&&(d+=parseFloat(f.css(a,c+bx[e]))||0);return d+"px"}function bo(a){var b=c.createElement("div");bh.appendChild(b),b.innerHTML=a.outerHTML;return b.firstChild}function bn(a){var b=(a.nodeName||"").toLowerCase();b==="input"?bm(a):b!=="script"&&typeof a.getElementsByTagName!="undefined"&&f.grep(a.getElementsByTagName("input"),bm)}function bm(a){if(a.type==="checkbox"||a.type==="radio")a.defaultChecked=a.checked}function bl(a){return typeof a.getElementsByTagName!="undefined"?a.getElementsByTagName("*"):typeof a.querySelectorAll!="undefined"?a.querySelectorAll("*"):[]}function bk(a,b){var c;b.nodeType===1&&(b.clearAttributes&&b.clearAttributes(),b.mergeAttributes&&b.mergeAttributes(a),c=b.nodeName.toLowerCase(),c==="object"?b.outerHTML=a.outerHTML:c!=="input"||a.type!=="checkbox"&&a.type!=="radio"?c==="option"?b.selected=a.defaultSelected:c==="input"||c==="textarea"?b.defaultValue=a.defaultValue:c==="script"&&b.text!==a.text&&(b.text=a.text):(a.checked&&(b.defaultChecked=b.checked=a.checked),b.value!==a.value&&(b.value=a.value)),b.removeAttribute(f.expando),b.removeAttribute("_submit_attached"),b.removeAttribute("_change_attached"))}function bj(a,b){if(b.nodeType===1&&!!f.hasData(a)){var c,d,e,g=f._data(a),h=f._data(b,g),i=g.events;if(i){delete h.handle,h.events={};for(c in i)for(d=0,e=i[c].length;d<e;d++)f.event.add(b,c,i[c][d])}h.data&&(h.data=f.extend({},h.data))}}function bi(a,b){return f.nodeName(a,"table")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function U(a){var b=V.split("|"),c=a.createDocumentFragment();if(c.createElement)while(b.length)c.createElement(b.pop());return c}function T(a,b,c){b=b||0;if(f.isFunction(b))return f.grep(a,function(a,d){var e=!!b.call(a,d,a);return e===c});if(b.nodeType)return f.grep(a,function(a,d){return a===b===c});if(typeof b=="string"){var d=f.grep(a,function(a){return a.nodeType===1});if(O.test(b))return f.filter(b,d,!c);b=f.filter(b,d)}return f.grep(a,function(a,d){return f.inArray(a,b)>=0===c})}function S(a){return!a||!a.parentNode||a.parentNode.nodeType===11}function K(){return!0}function J(){return!1}function n(a,b,c){var d=b+"defer",e=b+"queue",g=b+"mark",h=f._data(a,d);h&&(c==="queue"||!f._data(a,e))&&(c==="mark"||!f._data(a,g))&&setTimeout(function(){!f._data(a,e)&&!f._data(a,g)&&(f.removeData(a,d,!0),h.fire())},0)}function m(a){for(var b in a){if(b==="data"&&f.isEmptyObject(a[b]))continue;if(b!=="toJSON")return!1}return!0}function l(a,c,d){if(d===b&&a.nodeType===1){var e="data-"+c.replace(k,"-$1").toLowerCase();d=a.getAttribute(e);if(typeof d=="string"){try{d=d==="true"?!0:d==="false"?!1:d==="null"?null:f.isNumeric(d)?+d:j.test(d)?f.parseJSON(d):d}catch(g){}f.data(a,c,d)}else d=b}return d}function h(a){var b=g[a]={},c,d;a=a.split(/$-s+/);for(c=0,d=a.length;c<d;c++)b[a[c]]=!0;return b}var c=a.document,d=a.navigator,e=a.location,f=function(){function J(){if(!e.isReady){try{c.documentElement.doScroll("left")}catch(a){setTimeout(J,1);return}e.ready()}}var e=function(a,b){return new e.fn.init(a,b,h)},f=a.jQuery,g=a.$,h,i=/^(?:[^#<]*(<[$-w$-W]+>)[^>]*$|#([$-w$--]*)$)/,j=/$-S/,k=/^$-s+/,l=/$-s+$/,m=/^<($-w+)$-s*$-/?>(?:<$-/$-1>)?$/,n=/^[$-],:{}$-s]*$/,o=/$-$-(?:["$-$-$-/bfnrt]|u[0-9a-fA-F]{4})/g,p=/"[^"$-$-$-n$-r]*"|true|false|null|-?$-d+(?:$-.$-d*)?(?:[eE][+$--]?$-d+)?/g,q=/(?:^|:|,)(?:$-s*$-[)+/g,r=/(webkit)[ $-/]([$-w.]+)/,s=/(opera)(?:.*version)?[ $-/]([$-w.]+)/,t=/(msie) ([$-w.]+)/,u=/(mozilla)(?:.*? rv:([$-w.]+))?/,v=/-([a-z]|[0-9])/ig,w=/^-ms-/,x=function(a,b){return(b+"").toUpperCase()},y=d.userAgent,z,A,B,C=Object.prototype.toString,D=Object.prototype.hasOwnProperty,E=Array.prototype.push,F=Array.prototype.slice,G=String.prototype.trim,H=Array.prototype.indexOf,I={};e.fn=e.prototype={constructor:e,init:function(a,d,f){var g,h,j,k;if(!a)return this;if(a.nodeType){this.context=this[0]=a,this.length=1;return this}if(a==="body"&&!d&&c.body){this.context=c,this[0]=c.body,this.selector=a,this.length=1;return this}if(typeof a=="string"){a.charAt(0)!=="<"||a.charAt(a.length-1)!==">"||a.length<3?g=i.exec(a):g=[null,a,null];if(g&&(g[1]||!d)){if(g[1]){d=d instanceof e?d[0]:d,k=d?d.ownerDocument||d:c,j=m.exec(a),j?e.isPlainObject(d)?(a=[c.createElement(j[1])],e.fn.attr.call(a,d,!0)):a=[k.createElement(j[1])]:(j=e.buildFragment([g[1]],[k]),a=(j.cacheable?e.clone(j.fragment):j.fragment).childNodes);return e.merge(this,a)}h=c.getElementById(g[2]);if(h&&h.parentNode){if(h.id!==g[2])return f.find(a);this.length=1,this[0]=h}this.context=c,this.selector=a;return this}return!d||d.jquery?(d||f).find(a):this.constructor(d).find(a)}if(e.isFunction(a))return f.ready(a);a.selector!==b&&(this.selector=a.selector,this.context=a.context);return e.makeArray(a,this)},selector:"",jquery:"1.7.2",length:0,size:function(){return this.length},toArray:function(){return F.call(this,0)},get:function(a){return a==null?this.toArray():a<0?this[this.length+a]:this[a]},pushStack:function(a,b,c){var d=this.constructor();e.isArray(a)?E.apply(d,a):e.merge(d,a),d.prevObject=this,d.context=this.context,b==="find"?d.selector=this.selector+(this.selector?" ":"")+c:b&&(d.selector=this.selector+"."+b+"("+c+")");return d},each:function(a,b){return e.each(this,a,b)},ready:function(a){e.bindReady(),A.add(a);return this},eq:function(a){a=+a;return a===-1?this.slice(a):this.slice(a,a+1)},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},slice:function(){return this.pushStack(F.apply(this,arguments),"slice",F.call(arguments).join(","))},map:function(a){return this.pushStack(e.map(this,function(b,c){return a.call(b,c,b)}))},end:function(){return this.prevObject||this.constructor(null)},push:E,sort:[].sort,splice:[].splice},e.fn.init.prototype=e.fn,e.extend=e.fn.extend=function(){var a,c,d,f,g,h,i=arguments[0]||{},j=1,k=arguments.length,l=!1;typeof i=="boolean"&&(l=i,i=arguments[1]||{},j=2),typeof i!="object"&&!e.isFunction(i)&&(i={}),k===j&&(i=this,--j);for(;j<k;j++)if((a=arguments[j])!=null)for(c in a){d=i[c],f=a[c];if(i===f)continue;l&&f&&(e.isPlainObject(f)||(g=e.isArray(f)))?(g?(g=!1,h=d&&e.isArray(d)?d:[]):h=d&&e.isPlainObject(d)?d:{},i[c]=e.extend(l,h,f)):f!==b&&(i[c]=f)}return i},e.extend({noConflict:function(b){a.$===e&&(a.$=g),b&&a.jQuery===e&&(a.jQuery=f);return e},isReady:!1,readyWait:1,holdReady:function(a){a?e.readyWait++:e.ready(!0)},ready:function(a){if(a===!0&&!--e.readyWait||a!==!0&&!e.isReady){if(!c.body)return setTimeout(e.ready,1);e.isReady=!0;if(a!==!0&&--e.readyWait>0)return;A.fireWith(c,[e]),e.fn.trigger&&e(c).trigger("ready").off("ready")}},bindReady:function(){if(!A){A=e.Callbacks("once memory");if(c.readyState==="complete")return setTimeout(e.ready,1);if(c.addEventListener)c.addEventListener("DOMContentLoaded",B,!1),a.addEventListener("load",e.ready,!1);else if(c.attachEvent){c.attachEvent("onreadystatechange",B),a.attachEvent("onload",e.ready);var b=!1;try{b=a.frameElement==null}catch(d){}c.documentElement.doScroll&&b&&J()}}},isFunction:function(a){return e.type(a)==="function"},isArray:Array.isArray||function(a){return e.type(a)==="array"},isWindow:function(a){return a!=null&&a==a.window},isNumeric:function(a){return!isNaN(parseFloat(a))&&isFinite(a)},type:function(a){return a==null?String(a):I[C.call(a)]||"object"},isPlainObject:function(a){if(!a||e.type(a)!=="object"||a.nodeType||e.isWindow(a))return!1;try{if(a.constructor&&!D.call(a,"constructor")&&!D.call(a.constructor.prototype,"isPrototypeOf"))return!1}catch(c){return!1}var d;for(d in a);return d===b||D.call(a,d)},isEmptyObject:function(a){for(var b in a)return!1;return!0},error:function(a){throw new Error(a)},parseJSON:function(b){if(typeof b!="string"||!b)return null;b=e.trim(b);if(a.JSON&&a.JSON.parse)return a.JSON.parse(b);if(n.test(b.replace(o,"@").replace(p,"]").replace(q,"")))return(new Function("return "+b))();e.error("Invalid JSON: "+b)},parseXML:function(c){if(typeof c!="string"||!c)return null;var d,f;try{a.DOMParser?(f=new DOMParser,d=f.parseFromString(c,"text/xml")):(d=new ActiveXObject("Microsoft.XMLDOM"),d.async="false",d.loadXML(c))}catch(g){d=b}(!d||!d.documentElement||d.getElementsByTagName("parsererror").length)&&e.error("Invalid XML: "+c);return d},noop:function(){},globalEval:function(b){b&&j.test(b)&&(a.execScript||function(b){a.eval.call(a,b)})(b)},camelCase:function(a){return a.replace(w,"ms-").replace(v,x)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toUpperCase()===b.toUpperCase()},each:function(a,c,d){var f,g=0,h=a.length,i=h===b||e.isFunction(a);if(d){if(i){for(f in a)if(c.apply(a[f],d)===!1)break}else for(;g<h;)if(c.apply(a[g++],d)===!1)break}else if(i){for(f in a)if(c.call(a[f],f,a[f])===!1)break}else for(;g<h;)if(c.call(a[g],g,a[g++])===!1)break;return a},trim:G?function(a){return a==null?"":G.call(a)}:function(a){return a==null?"":(a+"").replace(k,"").replace(l,"")},makeArray:function(a,b){var c=b||[];if(a!=null){var d=e.type(a);a.length==null||d==="string"||d==="function"||d==="regexp"||e.isWindow(a)?E.call(c,a):e.merge(c,a)}return c},inArray:function(a,b,c){var d;if(b){if(H)return H.call(b,a,c);d=b.length,c=c?c<0?Math.max(0,d+c):c:0;for(;c<d;c++)if(c in b&&b[c]===a)return c}return-1},merge:function(a,c){var d=a.length,e=0;if(typeof c.length=="number")for(var f=c.length;e<f;e++)a[d++]=c[e];else while(c[e]!==b)a[d++]=c[e++];a.length=d;return a},grep:function(a,b,c){var d=[],e;c=!!c;for(var f=0,g=a.length;f<g;f++)e=!!b(a[f],f),c!==e&&d.push(a[f]);return d},map:function(a,c,d){var f,g,h=[],i=0,j=a.length,k=a instanceof e||j!==b&&typeof j=="number"&&(j>0&&a[0]&&a[j-1]||j===0||e.isArray(a));if(k)for(;i<j;i++)f=c(a[i],i,d),f!=null&&(h[h.length]=f);else for(g in a)f=c(a[g],g,d),f!=null&&(h[h.length]=f);return h.concat.apply([],h)},guid:1,proxy:function(a,c){if(typeof c=="string"){var d=a[c];c=a,a=d}if(!e.isFunction(a))return b;var f=F.call(arguments,2),g=function(){return a.apply(c,f.concat(F.call(arguments)))};g.guid=a.guid=a.guid||g.guid||e.guid++;return g},access:function(a,c,d,f,g,h,i){var j,k=d==null,l=0,m=a.length;if(d&&typeof d=="object"){for(l in d)e.access(a,c,l,d[l],1,h,f);g=1}else if(f!==b){j=i===b&&e.isFunction(f),k&&(j?(j=c,c=function(a,b,c){return j.call(e(a),c)}):(c.call(a,f),c=null));if(c)for(;l<m;l++)c(a[l],d,j?f.call(a[l],l,c(a[l],d)):f,i);g=1}return g?a:k?c.call(a):m?c(a[0],d):h},now:function(){return(new Date).getTime()},uaMatch:function(a){a=a.toLowerCase();var b=r.exec(a)||s.exec(a)||t.exec(a)||a.indexOf("compatible")<0&&u.exec(a)||[];return{browser:b[1]||"",version:b[2]||"0"}},sub:function(){function a(b,c){return new a.fn.init(b,c)}e.extend(!0,a,this),a.superclass=this,a.fn=a.prototype=this(),a.fn.constructor=a,a.sub=this.sub,a.fn.init=function(d,f){f&&f instanceof e&&!(f instanceof a)&&(f=a(f));return e.fn.init.call(this,d,f,b)},a.fn.init.prototype=a.fn;var b=a(c);return a},browser:{}}),e.each("Boolean Number String Function Array Date RegExp Object".split(" "),function(a,b){I["[object "+b+"]"]=b.toLowerCase()}),z=e.uaMatch(y),z.browser&&(e.browser[z.browser]=!0,e.browser.version=z.version),e.browser.webkit&&(e.browser.safari=!0),j.test(" ")&&(k=/^[$-s$-xA0]+/,l=/[$-s$-xA0]+$/),h=e(c),c.addEventListener?B=function(){c.removeEventListener("DOMContentLoaded",B,!1),e.ready()}:c.attachEvent&&(B=function(){c.readyState==="complete"&&(c.detachEvent("onreadystatechange",B),e.ready())});return e}(),g={};f.Callbacks=function(a){a=a?g[a]||h(a):{};var c=[],d=[],e,i,j,k,l,m,n=function(b){var d,e,g,h,i;for(d=0,e=b.length;d<e;d++)g=b[d],h=f.type(g),h==="array"?n(g):h==="function"&&(!a.unique||!p.has(g))&&c.push(g)},o=function(b,f){f=f||[],e=!a.memory||[b,f],i=!0,j=!0,m=k||0,k=0,l=c.length;for(;c&&m<l;m++)if(c[m].apply(b,f)===!1&&a.stopOnFalse){e=!0;break}j=!1,c&&(a.once?e===!0?p.disable():c=[]:d&&d.length&&(e=d.shift(),p.fireWith(e[0],e[1])))},p={add:function(){if(c){var a=c.length;n(arguments),j?l=c.length:e&&e!==!0&&(k=a,o(e[0],e[1]))}return this},remove:function(){if(c){var b=arguments,d=0,e=b.length;for(;d<e;d++)for(var f=0;f<c.length;f++)if(b[d]===c[f]){j&&f<=l&&(l--,f<=m&&m--),c.splice(f--,1);if(a.unique)break}}return this},has:function(a){if(c){var b=0,d=c.length;for(;b<d;b++)if(a===c[b])return!0}return!1},empty:function(){c=[];return this},disable:function(){c=d=e=b;return this},disabled:function(){return!c},lock:function(){d=b,(!e||e===!0)&&p.disable();return this},locked:function(){return!d},fireWith:function(b,c){d&&(j?a.once||d.push([b,c]):(!a.once||!e)&&o(b,c));return this},fire:function(){p.fireWith(this,arguments);return this},fired:function(){return!!i}};return p};var i=[].slice;f.extend({Deferred:function(a){var b=f.Callbacks("once memory"),c=f.Callbacks("once memory"),d=f.Callbacks("memory"),e="pending",g={resolve:b,reject:c,notify:d},h={done:b.add,fail:c.add,progress:d.add,state:function(){return e},isResolved:b.fired,isRejected:c.fired,then:function(a,b,c){i.done(a).fail(b).progress(c);return this},always:function(){i.done.apply(i,arguments).fail.apply(i,arguments);return this},pipe:function(a,b,c){return f.Deferred(function(d){f.each({done:[a,"resolve"],fail:[b,"reject"],progress:[c,"notify"]},function(a,b){var c=b[0],e=b[1],g;f.isFunction(c)?i[a](function(){g=c.apply(this,arguments),g&&f.isFunction(g.promise)?g.promise().then(d.resolve,d.reject,d.notify):d[e+"With"](this===i?d:this,[g])}):i[a](d[e])})}).promise()},promise:function(a){if(a==null)a=h;else for(var b in h)a[b]=h[b];return a}},i=h.promise({}),j;for(j in g)i[j]=g[j].fire,i[j+"With"]=g[j].fireWith;i.done(function(){e="resolved"},c.disable,d.lock).fail(function(){e="rejected"},b.disable,d.lock),a&&a.call(i,i);return i},when:function(a){function m(a){return function(b){e[a]=arguments.length>1?i.call(arguments,0):b,j.notifyWith(k,e)}}function l(a){return function(c){b[a]=arguments.length>1?i.call(arguments,0):c,--g||j.resolveWith(j,b)}}var b=i.call(arguments,0),c=0,d=b.length,e=Array(d),g=d,h=d,j=d<=1&&a&&f.isFunction(a.promise)?a:f.Deferred(),k=j.promise();if(d>1){for(;c<d;c++)b[c]&&b[c].promise&&f.isFunction(b[c].promise)?b[c].promise().then(l(c),j.reject,m(c)):--g;g||j.resolveWith(j,b)}else j!==a&&j.resolveWith(j,d?[a]:[]);return k}}),f.support=function(){var b,d,e,g,h,i,j,k,l,m,n,o,p=c.createElement("div"),q=c.documentElement;p.setAttribute("className","t"),p.innerHTML="   <link/><table></table><a href=$+/a$+ style=$+top:1px;float:left;opacity:.55;$+>a</a><input type=$+checkbox$+/>",d=p.getElementsByTagName("*"),e=p.getElementsByTagName("a")[0];if(!d||!d.length||!e)return{};g=c.createElement("select"),h=g.appendChild(c.createElement("option")),i=p.getElementsByTagName("input")[0],b={leadingWhitespace:p.firstChild.nodeType===3,tbody:!p.getElementsByTagName("tbody").length,htmlSerialize:!!p.getElementsByTagName("link").length,style:/top/.test(e.getAttribute("style")),hrefNormalized:e.getAttribute("href")==="/a",opacity:/^0.55/.test(e.style.opacity),cssFloat:!!e.style.cssFloat,checkOn:i.value==="on",optSelected:h.selected,getSetAttribute:p.className!=="t",enctype:!!c.createElement("form").enctype,html5Clone:c.createElement("nav").cloneNode(!0).outerHTML!=="<:nav></:nav>",submitBubbles:!0,changeBubbles:!0,focusinBubbles:!1,deleteExpando:!0,noCloneEvent:!0,inlineBlockNeedsLayout:!1,shrinkWrapBlocks:!1,reliableMarginRight:!0,pixelMargin:!0},f.boxModel=b.boxModel=c.compatMode==="CSS1Compat",i.checked=!0,b.noCloneChecked=i.cloneNode(!0).checked,g.disabled=!0,b.optDisabled=!h.disabled;try{delete p.test}catch(r){b.deleteExpando=!1}!p.addEventListener&&p.attachEvent&&p.fireEvent&&(p.attachEvent("onclick",function(){b.noCloneEvent=!1}),p.cloneNode(!0).fireEvent("onclick")),i=c.createElement("input"),i.value="t",i.setAttribute("type","radio"),b.radioValue=i.value==="t",i.setAttribute("checked","checked"),i.setAttribute("name","t"),p.appendChild(i),j=c.createDocumentFragment(),j.appendChild(p.lastChild),b.checkClone=j.cloneNode(!0).cloneNode(!0).lastChild.checked,b.appendChecked=i.checked,j.removeChild(i),j.appendChild(p);if(p.attachEvent)for(n in{submit:1,change:1,focusin:1})m="on"+n,o=m in p,o||(p.setAttribute(m,"return;"),o=typeof p[m]=="function"),b[n+"Bubbles"]=o;j.removeChild(p),j=g=h=p=i=null,f(function(){var d,e,g,h,i,j,l,m,n,q,r,s,t,u=c.getElementsByTagName("body")[0];!u||(m=1,t="padding:0;margin:0;border:",r="position:absolute;top:0;left:0;width:1px;height:1px;",s=t+"0;visibility:hidden;",n="style=$+"+r+t+"5px solid #000;",q="<div "+n+"display:block;$+><div style=$+"+t+"0;display:block;overflow:hidden;$+></div></div>"+"<table "+n+"$+ cellpadding=$+0$+ cellspacing=$+0$+>"+"<tr><td></td></tr></table>",d=c.createElement("div"),d.style.cssText=s+"width:0;height:0;position:static;top:0;margin-top:"+m+"px",u.insertBefore(d,u.firstChild),p=c.createElement("div"),d.appendChild(p),p.innerHTML="<table><tr><td style=$+"+t+"0;display:none$+></td><td>t</td></tr></table>",k=p.getElementsByTagName("td"),o=k[0].offsetHeight===0,k[0].style.display="",k[1].style.display="none",b.reliableHiddenOffsets=o&&k[0].offsetHeight===0,a.getComputedStyle&&(p.innerHTML="",l=c.createElement("div"),l.style.width="0",l.style.marginRight="0",p.style.width="2px",p.appendChild(l),b.reliableMarginRight=(parseInt((a.getComputedStyle(l,null)||{marginRight:0}).marginRight,10)||0)===0),typeof p.style.zoom!="undefined"&&(p.innerHTML="",p.style.width=p.style.padding="1px",p.style.border=0,p.style.overflow="hidden",p.style.display="inline",p.style.zoom=1,b.inlineBlockNeedsLayout=p.offsetWidth===3,p.style.display="block",p.style.overflow="visible",p.innerHTML="<div style=$+width:5px;$+></div>",b.shrinkWrapBlocks=p.offsetWidth!==3),p.style.cssText=r+s,p.innerHTML=q,e=p.firstChild,g=e.firstChild,i=e.nextSibling.firstChild.firstChild,j={doesNotAddBorder:g.offsetTop!==5,doesAddBorderForTableAndCells:i.offsetTop===5},g.style.position="fixed",g.style.top="20px",j.fixedPosition=g.offsetTop===20||g.offsetTop===15,g.style.position=g.style.top="",e.style.overflow="hidden",e.style.position="relative",j.subtractsBorderForOverflowNotVisible=g.offsetTop===-5,j.doesNotIncludeMarginInBodyOffset=u.offsetTop!==m,a.getComputedStyle&&(p.style.marginTop="1%",b.pixelMargin=(a.getComputedStyle(p,null)||{marginTop:0}).marginTop!=="1%"),typeof d.style.zoom!="undefined"&&(d.style.zoom=1),u.removeChild(d),l=p=d=null,f.extend(b,j))});return b}();var j=/^(?:$-{.*$-}|$-[.*$-])$/,k=/([A-Z])/g;f.extend({cache:{},uuid:0,expando:"jQuery"+(f.fn.jquery+Math.random()).replace(/$-D/g,""),noData:{embed:!0,object:"clsid:D27CDB6E-AE6D-11cf-96B8-444553540000",applet:!0},hasData:function(a){a=a.nodeType?f.cache[a[f.expando]]:a[f.expando];return!!a&&!m(a)},data:function(a,c,d,e){if(!!f.acceptData(a)){var g,h,i,j=f.expando,k=typeof c=="string",l=a.nodeType,m=l?f.cache:a,n=l?a[j]:a[j]&&j,o=c==="events";if((!n||!m[n]||!o&&!e&&!m[n].data)&&k&&d===b)return;n||(l?a[j]=n=++f.uuid:n=j),m[n]||(m[n]={},l||(m[n].toJSON=f.noop));if(typeof c=="object"||typeof c=="function")e?m[n]=f.extend(m[n],c):m[n].data=f.extend(m[n].data,c);g=h=m[n],e||(h.data||(h.data={}),h=h.data),d!==b&&(h[f.camelCase(c)]=d);if(o&&!h[c])return g.events;k?(i=h[c],i==null&&(i=h[f.camelCase(c)])):i=h;return i}},removeData:function(a,b,c){if(!!f.acceptData(a)){var d,e,g,h=f.expando,i=a.nodeType,j=i?f.cache:a,k=i?a[h]:h;if(!j[k])return;if(b){d=c?j[k]:j[k].data;if(d){f.isArray(b)||(b in d?b=[b]:(b=f.camelCase(b),b in d?b=[b]:b=b.split(" ")));for(e=0,g=b.length;e<g;e++)delete d[b[e]];if(!(c?m:f.isEmptyObject)(d))return}}if(!c){delete j[k].data;if(!m(j[k]))return}f.support.deleteExpando||!j.setInterval?delete j[k]:j[k]=null,i&&(f.support.deleteExpando?delete a[h]:a.removeAttribute?a.removeAttribute(h):a[h]=null)}},_data:function(a,b,c){return f.data(a,b,c,!0)},acceptData:function(a){if(a.nodeName){var b=f.noData[a.nodeName.toLowerCase()];if(b)return b!==!0&&a.getAttribute("classid")===b}return!0}}),f.fn.extend({data:function(a,c){var d,e,g,h,i,j=this[0],k=0,m=null;if(a===b){if(this.length){m=f.data(j);if(j.nodeType===1&&!f._data(j,"parsedAttrs")){g=j.attributes;for(i=g.length;k<i;k++)h=g[k].name,h.indexOf("data-")===0&&(h=f.camelCase(h.substring(5)),l(j,h,m[h]));f._data(j,"parsedAttrs",!0)}}return m}if(typeof a=="object")return this.each(function(){f.data(this,a)});d=a.split(".",2),d[1]=d[1]?"."+d[1]:"",e=d[1]+"!";return f.access(this,function(c){if(c===b){m=this.triggerHandler("getData"+e,[d[0]]),m===b&&j&&(m=f.data(j,a),m=l(j,a,m));return m===b&&d[1]?this.data(d[0]):m}d[1]=c,this.each(function(){var b=f(this);b.triggerHandler("setData"+e,d),f.data(this,a,c),b.triggerHandler("changeData"+e,d)})},null,c,arguments.length>1,null,!1)},removeData:function(a){return this.each(function(){f.removeData(this,a)})}}),f.extend({_mark:function(a,b){a&&(b=(b||"fx")+"mark",f._data(a,b,(f._data(a,b)||0)+1))},_unmark:function(a,b,c){a!==!0&&(c=b,b=a,a=!1);if(b){c=c||"fx";var d=c+"mark",e=a?0:(f._data(b,d)||1)-1;e?f._data(b,d,e):(f.removeData(b,d,!0),n(b,c,"mark"))}},queue:function(a,b,c){var d;if(a){b=(b||"fx")+"queue",d=f._data(a,b),c&&(!d||f.isArray(c)?d=f._data(a,b,f.makeArray(c)):d.push(c));return d||[]}},dequeue:function(a,b){b=b||"fx";var c=f.queue(a,b),d=c.shift(),e={};d==="inprogress"&&(d=c.shift()),d&&(b==="fx"&&c.unshift("inprogress"),f._data(a,b+".run",e),d.call(a,function(){f.dequeue(a,b)},e)),c.length||(f.removeData(a,b+"queue "+b+".run",!0),n(a,b,"queue"))}}),f.fn.extend({queue:function(a,c){var d=2;typeof a!="string"&&(c=a,a="fx",d--);if(arguments.length<d)return f.queue(this[0],a);return c===b?this:this.each(function(){var b=f.queue(this,a,c);a==="fx"&&b[0]!=="inprogress"&&f.dequeue(this,a)})},dequeue:function(a){return this.each(function(){f.dequeue(this,a)})},delay:function(a,b){a=f.fx?f.fx.speeds[a]||a:a,b=b||"fx";return this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,c){function m(){--h||d.resolveWith(e,[e])}typeof a!="string"&&(c=a,a=b),a=a||"fx";var d=f.Deferred(),e=this,g=e.length,h=1,i=a+"defer",j=a+"queue",k=a+"mark",l;while(g--)if(l=f.data(e[g],i,b,!0)||(f.data(e[g],j,b,!0)||f.data(e[g],k,b,!0))&&f.data(e[g],i,f.Callbacks("once memory"),!0))h++,l.add(m);m();return d.promise(c)}});var o=/[$-n$-t$-r]/g,p=/$-s+/,q=/$-r/g,r=/^(?:button|input)$/i,s=/^(?:button|input|object|select|textarea)$/i,t=/^a(?:rea)?$/i,u=/^(?:autofocus|autoplay|async|checked|controls|defer|disabled|hidden|loop|multiple|open|readonly|required|scoped|selected)$/i,v=f.support.getSetAttribute,w,x,y;f.fn.extend({attr:function(a,b){return f.access(this,f.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){f.removeAttr(this,a)})},prop:function(a,b){return f.access(this,f.prop,a,b,arguments.length>1)},removeProp:function(a){a=f.propFix[a]||a;return this.each(function(){try{this[a]=b,delete this[a]}catch(c){}})},addClass:function(a){var b,c,d,e,g,h,i;if(f.isFunction(a))return this.each(function(b){f(this).addClass(a.call(this,b,this.className))});if(a&&typeof a=="string"){b=a.split(p);for(c=0,d=this.length;c<d;c++){e=this[c];if(e.nodeType===1)if(!e.className&&b.length===1)e.className=a;else{g=" "+e.className+" ";for(h=0,i=b.length;h<i;h++)~g.indexOf(" "+b[h]+" ")||(g+=b[h]+" ");e.className=f.trim(g)}}}return this},removeClass:function(a){var c,d,e,g,h,i,j;if(f.isFunction(a))return this.each(function(b){f(this).removeClass(a.call(this,b,this.className))});if(a&&typeof a=="string"||a===b){c=(a||"").split(p);for(d=0,e=this.length;d<e;d++){g=this[d];if(g.nodeType===1&&g.className)if(a){h=(" "+g.className+" ").replace(o," ");for(i=0,j=c.length;i<j;i++)h=h.replace(" "+c[i]+" "," ");g.className=f.trim(h)}else g.className=""}}return this},toggleClass:function(a,b){var c=typeof a,d=typeof b=="boolean";if(f.isFunction(a))return this.each(function(c){f(this).toggleClass(a.call(this,c,this.className,b),b)});return this.each(function(){if(c==="string"){var e,g=0,h=f(this),i=b,j=a.split(p);while(e=j[g++])i=d?i:!h.hasClass(e),h[i?"addClass":"removeClass"](e)}else if(c==="undefined"||c==="boolean")this.className&&f._data(this,"__className__",this.className),this.className=this.className||a===!1?"":f._data(this,"__className__")||""})},hasClass:function(a){var b=" "+a+" ",c=0,d=this.length;for(;c<d;c++)if(this[c].nodeType===1&&(" "+this[c].className+" ").replace(o," ").indexOf(b)>-1)return!0;return!1},val:function(a){var c,d,e,g=this[0];{if(!!arguments.length){e=f.isFunction(a);return this.each(function(d){var g=f(this),h;if(this.nodeType===1){e?h=a.call(this,d,g.val()):h=a,h==null?h="":typeof h=="number"?h+="":f.isArray(h)&&(h=f.map(h,function(a){return a==null?"":a+""})),c=f.valHooks[this.type]||f.valHooks[this.nodeName.toLowerCase()];if(!c||!("set"in c)||c.set(this,h,"value")===b)this.value=h}})}if(g){c=f.valHooks[g.type]||f.valHooks[g.nodeName.toLowerCase()];if(c&&"get"in c&&(d=c.get(g,"value"))!==b)return d;d=g.value;return typeof d=="string"?d.replace(q,""):d==null?"":d}}}}),f.extend({valHooks:{option:{get:function(a){var b=a.attributes.value;return!b||b.specified?a.value:a.text}},select:{get:function(a){var b,c,d,e,g=a.selectedIndex,h=[],i=a.options,j=a.type==="select-one";if(g<0)return null;c=j?g:0,d=j?g+1:i.length;for(;c<d;c++){e=i[c];if(e.selected&&(f.support.optDisabled?!e.disabled:e.getAttribute("disabled")===null)&&(!e.parentNode.disabled||!f.nodeName(e.parentNode,"optgroup"))){b=f(e).val();if(j)return b;h.push(b)}}if(j&&!h.length&&i.length)return f(i[g]).val();return h},set:function(a,b){var c=f.makeArray(b);f(a).find("option").each(function(){this.selected=f.inArray(f(this).val(),c)>=0}),c.length||(a.selectedIndex=-1);return c}}},attrFn:{val:!0,css:!0,html:!0,text:!0,data:!0,width:!0,height:!0,offset:!0},attr:function(a,c,d,e){var g,h,i,j=a.nodeType;if(!!a&&j!==3&&j!==8&&j!==2){if(e&&c in f.attrFn)return f(a)[c](d);if(typeof a.getAttribute=="undefined")return f.prop(a,c,d);i=j!==1||!f.isXMLDoc(a),i&&(c=c.toLowerCase(),h=f.attrHooks[c]||(u.test(c)?x:w));if(d!==b){if(d===null){f.removeAttr(a,c);return}if(h&&"set"in h&&i&&(g=h.set(a,d,c))!==b)return g;a.setAttribute(c,""+d);return d}if(h&&"get"in h&&i&&(g=h.get(a,c))!==null)return g;g=a.getAttribute(c);return g===null?b:g}},removeAttr:function(a,b){var c,d,e,g,h,i=0;if(b&&a.nodeType===1){d=b.toLowerCase().split(p),g=d.length;for(;i<g;i++)e=d[i],e&&(c=f.propFix[e]||e,h=u.test(e),h||f.attr(a,e,""),a.removeAttribute(v?e:c),h&&c in a&&(a[c]=!1))}},attrHooks:{type:{set:function(a,b){if(r.test(a.nodeName)&&a.parentNode)f.error("type property can$+t be changed");else if(!f.support.radioValue&&b==="radio"&&f.nodeName(a,"input")){var c=a.value;a.setAttribute("type",b),c&&(a.value=c);return b}}},value:{get:function(a,b){if(w&&f.nodeName(a,"button"))return w.get(a,b);return b in a?a.value:null},set:function(a,b,c){if(w&&f.nodeName(a,"button"))return w.set(a,b,c);a.value=b}}},propFix:{tabindex:"tabIndex",readonly:"readOnly","for":"htmlFor","class":"className",maxlength:"maxLength",cellspacing:"cellSpacing",cellpadding:"cellPadding",rowspan:"rowSpan",colspan:"colSpan",usemap:"useMap",frameborder:"frameBorder",contenteditable:"contentEditable"},prop:function(a,c,d){var e,g,h,i=a.nodeType;if(!!a&&i!==3&&i!==8&&i!==2){h=i!==1||!f.isXMLDoc(a),h&&(c=f.propFix[c]||c,g=f.propHooks[c]);return d!==b?g&&"set"in g&&(e=g.set(a,d,c))!==b?e:a[c]=d:g&&"get"in g&&(e=g.get(a,c))!==null?e:a[c]}},propHooks:{tabIndex:{get:function(a){var c=a.getAttributeNode("tabindex");return c&&c.specified?parseInt(c.value,10):s.test(a.nodeName)||t.test(a.nodeName)&&a.href?0:b}}}}),f.attrHooks.tabindex=f.propHooks.tabIndex,x={get:function(a,c){var d,e=f.prop(a,c);return e===!0||typeof e!="boolean"&&(d=a.getAttributeNode(c))&&d.nodeValue!==!1?c.toLowerCase():b},set:function(a,b,c){var d;b===!1?f.removeAttr(a,c):(d=f.propFix[c]||c,d in a&&(a[d]=!0),a.setAttribute(c,c.toLowerCase()));return c}},v||(y={name:!0,id:!0,coords:!0},w=f.valHooks.button={get:function(a,c){var d;d=a.getAttributeNode(c);return d&&(y[c]?d.nodeValue!=="":d.specified)?d.nodeValue:b},set:function(a,b,d){var e=a.getAttributeNode(d);e||(e=c.createAttribute(d),a.setAttributeNode(e));return e.nodeValue=b+""}},f.attrHooks.tabindex.set=w.set,f.each(["width","height"],function(a,b){f.attrHooks[b]=f.extend(f.attrHooks[b],{set:function(a,c){if(c===""){a.setAttribute(b,"auto");return c}}})}),f.attrHooks.contenteditable={get:w.get,set:function(a,b,c){b===""&&(b="false"),w.set(a,b,c)}}),f.support.hrefNormalized||f.each(["href","src","width","height"],function(a,c){f.attrHooks[c]=f.extend(f.attrHooks[c],{get:function(a){var d=a.getAttribute(c,2);return d===null?b:d}})}),f.support.style||(f.attrHooks.style={get:function(a){return a.style.cssText.toLowerCase()||b},set:function(a,b){return a.style.cssText=""+b}}),f.support.optSelected||(f.propHooks.selected=f.extend(f.propHooks.selected,{get:function(a){var b=a.parentNode;b&&(b.selectedIndex,b.parentNode&&b.parentNode.selectedIndex);return null}})),f.support.enctype||(f.propFix.enctype="encoding"),f.support.checkOn||f.each(["radio","checkbox"],function(){f.valHooks[this]={get:function(a){return a.getAttribute("value")===null?"on":a.value}}}),f.each(["radio","checkbox"],function(){f.valHooks[this]=f.extend(f.valHooks[this],{set:function(a,b){if(f.isArray(b))return a.checked=f.inArray(f(a).val(),b)>=0}})});var z=/^(?:textarea|input|select)$/i,A=/^([^$-.]*)?(?:$-.(.+))?$/,B=/(?:^|$-s)hover($-.$-S+)?$-b/,C=/^key/,D=/^(?:mouse|contextmenu)|click/,E=/^(?:focusinfocus|focusoutblur)$/,F=/^($-w*)(?:#([$-w$--]+))?(?:$-.([$-w$--]+))?$/,G=function(a){var b=F.exec(a);b&&(b[1]=(b[1]||"").toLowerCase(),b[3]=b[3]&&new RegExp("(?:^|$-$-s)"+b[3]+"(?:$-$-s|$)"));return b},H=function(a,b){var c=a.attributes||{};return(!b[1]||a.nodeName.toLowerCase()===b[1])&&(!b[2]||(c.id||{}).value===b[2])&&(!b[3]||b[3].test((c["class"]||{}).value))},I=function(a){return f.event.special.hover?a:a.replace(B,"mouseenter$1 mouseleave$1")};f.event={add:function(a,c,d,e,g){var h,i,j,k,l,m,n,o,p,q,r,s;if(!(a.nodeType===3||a.nodeType===8||!c||!d||!(h=f._data(a)))){d.handler&&(p=d,d=p.handler,g=p.selector),d.guid||(d.guid=f.guid++),j=h.events,j||(h.events=j={}),i=h.handle,i||(h.handle=i=function(a){return typeof f!="undefined"&&(!a||f.event.triggered!==a.type)?f.event.dispatch.apply(i.elem,arguments):b},i.elem=a),c=f.trim(I(c)).split(" ");for(k=0;k<c.length;k++){l=A.exec(c[k])||[],m=l[1],n=(l[2]||"").split(".").sort(),s=f.event.special[m]||{},m=(g?s.delegateType:s.bindType)||m,s=f.event.special[m]||{},o=f.extend({type:m,origType:l[1],data:e,handler:d,guid:d.guid,selector:g,quick:g&&G(g),namespace:n.join(".")},p),r=j[m];if(!r){r=j[m]=[],r.delegateCount=0;if(!s.setup||s.setup.call(a,e,n,i)===!1)a.addEventListener?a.addEventListener(m,i,!1):a.attachEvent&&a.attachEvent("on"+m,i)}s.add&&(s.add.call(a,o),o.handler.guid||(o.handler.guid=d.guid)),g?r.splice(r.delegateCount++,0,o):r.push(o),f.event.global[m]=!0}a=null}},global:{},remove:function(a,b,c,d,e){var g=f.hasData(a)&&f._data(a),h,i,j,k,l,m,n,o,p,q,r,s;if(!!g&&!!(o=g.events)){b=f.trim(I(b||"")).split(" ");for(h=0;h<b.length;h++){i=A.exec(b[h])||[],j=k=i[1],l=i[2];if(!j){for(j in o)f.event.remove(a,j+b[h],c,d,!0);continue}p=f.event.special[j]||{},j=(d?p.delegateType:p.bindType)||j,r=o[j]||[],m=r.length,l=l?new RegExp("(^|$-$-.)"+l.split(".").sort().join("$-$-.(?:.*$-$-.)?")+"($-$-.|$)"):null;for(n=0;n<r.length;n++)s=r[n],(e||k===s.origType)&&(!c||c.guid===s.guid)&&(!l||l.test(s.namespace))&&(!d||d===s.selector||d==="**"&&s.selector)&&(r.splice(n--,1),s.selector&&r.delegateCount--,p.remove&&p.remove.call(a,s));r.length===0&&m!==r.length&&((!p.teardown||p.teardown.call(a,l)===!1)&&f.removeEvent(a,j,g.handle),delete o[j])}f.isEmptyObject(o)&&(q=g.handle,q&&(q.elem=null),f.removeData(a,["events","handle"],!0))}},customEvent:{getData:!0,setData:!0,changeData:!0},trigger:function(c,d,e,g){if(!e||e.nodeType!==3&&e.nodeType!==8){var h=c.type||c,i=[],j,k,l,m,n,o,p,q,r,s;if(E.test(h+f.event.triggered))return;h.indexOf("!")>=0&&(h=h.slice(0,-1),k=!0),h.indexOf(".")>=0&&(i=h.split("."),h=i.shift(),i.sort());if((!e||f.event.customEvent[h])&&!f.event.global[h])return;c=typeof c=="object"?c[f.expando]?c:new f.Event(h,c):new f.Event(h),c.type=h,c.isTrigger=!0,c.exclusive=k,c.namespace=i.join("."),c.namespace_re=c.namespace?new RegExp("(^|$-$-.)"+i.join("$-$-.(?:.*$-$-.)?")+"($-$-.|$)"):null,o=h.indexOf(":")<0?"on"+h:"";if(!e){j=f.cache;for(l in j)j[l].events&&j[l].events[h]&&f.event.trigger(c,d,j[l].handle.elem,!0);return}c.result=b,c.target||(c.target=e),d=d!=null?f.makeArray(d):[],d.unshift(c),p=f.event.special[h]||{};if(p.trigger&&p.trigger.apply(e,d)===!1)return;r=[[e,p.bindType||h]];if(!g&&!p.noBubble&&!f.isWindow(e)){s=p.delegateType||h,m=E.test(s+h)?e:e.parentNode,n=null;for(;m;m=m.parentNode)r.push([m,s]),n=m;n&&n===e.ownerDocument&&r.push([n.defaultView||n.parentWindow||a,s])}for(l=0;l<r.length&&!c.isPropagationStopped();l++)m=r[l][0],c.type=r[l][1],q=(f._data(m,"events")||{})[c.type]&&f._data(m,"handle"),q&&q.apply(m,d),q=o&&m[o],q&&f.acceptData(m)&&q.apply(m,d)===!1&&c.preventDefault();c.type=h,!g&&!c.isDefaultPrevented()&&(!p._default||p._default.apply(e.ownerDocument,d)===!1)&&(h!=="click"||!f.nodeName(e,"a"))&&f.acceptData(e)&&o&&e[h]&&(h!=="focus"&&h!=="blur"||c.target.offsetWidth!==0)&&!f.isWindow(e)&&(n=e[o],n&&(e[o]=null),f.event.triggered=h,e[h](),f.event.triggered=b,n&&(e[o]=n));return c.result}},dispatch:function(c){c=f.event.fix(c||a.event);var d=(f._data(this,"events")||{})[c.type]||[],e=d.delegateCount,g=[].slice.call(arguments,0),h=!c.exclusive&&!c.namespace,i=f.event.special[c.type]||{},j=[],k,l,m,n,o,p,q,r,s,t,u;g[0]=c,c.delegateTarget=this;if(!i.preDispatch||i.preDispatch.call(this,c)!==!1){if(e&&(!c.button||c.type!=="click")){n=f(this),n.context=this.ownerDocument||this;for(m=c.target;m!=this;m=m.parentNode||this)if(m.disabled!==!0){p={},r=[],n[0]=m;for(k=0;k<e;k++)s=d[k],t=s.selector,p[t]===b&&(p[t]=s.quick?H(m,s.quick):n.is(t)),p[t]&&r.push(s);r.length&&j.push({elem:m,matches:r})}}d.length>e&&j.push({elem:this,matches:d.slice(e)});for(k=0;k<j.length&&!c.isPropagationStopped();k++){q=j[k],c.currentTarget=q.elem;for(l=0;l<q.matches.length&&!c.isImmediatePropagationStopped();l++){s=q.matches[l];if(h||!c.namespace&&!s.namespace||c.namespace_re&&c.namespace_re.test(s.namespace))c.data=s.data,c.handleObj=s,o=((f.event.special[s.origType]||{}).handle||s.handler).apply(q.elem,g),o!==b&&(c.result=o,o===!1&&(c.preventDefault(),c.stopPropagation()))}}i.postDispatch&&i.postDispatch.call(this,c);return c.result}},props:"attrChange attrName relatedNode srcElement altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){a.which==null&&(a.which=b.charCode!=null?b.charCode:b.keyCode);return a}},mouseHooks:{props:"button buttons clientX clientY fromElement offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,d){var e,f,g,h=d.button,i=d.fromElement;a.pageX==null&&d.clientX!=null&&(e=a.target.ownerDocument||c,f=e.documentElement,g=e.body,a.pageX=d.clientX+(f&&f.scrollLeft||g&&g.scrollLeft||0)-(f&&f.clientLeft||g&&g.clientLeft||0),a.pageY=d.clientY+(f&&f.scrollTop||g&&g.scrollTop||0)-(f&&f.clientTop||g&&g.clientTop||0)),!a.relatedTarget&&i&&(a.relatedTarget=i===a.target?d.toElement:i),!a.which&&h!==b&&(a.which=h&1?1:h&2?3:h&4?2:0);return a}},fix:function(a){if(a[f.expando])return a;var d,e,g=a,h=f.event.fixHooks[a.type]||{},i=h.props?this.props.concat(h.props):this.props;a=f.Event(g);for(d=i.length;d;)e=i[--d],a[e]=g[e];a.target||(a.target=g.srcElement||c),a.target.nodeType===3&&(a.target=a.target.parentNode),a.metaKey===b&&(a.metaKey=a.ctrlKey);return h.filter?h.filter(a,g):a},special:{ready:{setup:f.bindReady},load:{noBubble:!0},focus:{delegateType:"focusin"},blur:{delegateType:"focusout"},beforeunload:{setup:function(a,b,c){f.isWindow(this)&&(this.onbeforeunload=c)},teardown:function(a,b){this.onbeforeunload===b&&(this.onbeforeunload=null)}}},simulate:function(a,b,c,d){var e=f.extend(new f.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?f.event.trigger(e,null,b):f.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},f.event.handle=f.event.dispatch,f.removeEvent=c.removeEventListener?function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)}:function(a,b,c){a.detachEvent&&a.detachEvent("on"+b,c)},f.Event=function(a,b){if(!(this instanceof f.Event))return new f.Event(a,b);a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||a.returnValue===!1||a.getPreventDefault&&a.getPreventDefault()?K:J):this.type=a,b&&f.extend(this,b),this.timeStamp=a&&a.timeStamp||f.now(),this[f.expando]=!0},f.Event.prototype={preventDefault:function(){this.isDefaultPrevented=K;var a=this.originalEvent;!a||(a.preventDefault?a.preventDefault():a.returnValue=!1)},stopPropagation:function(){this.isPropagationStopped=K;var a=this.originalEvent;!a||(a.stopPropagation&&a.stopPropagation(),a.cancelBubble=!0)},stopImmediatePropagation:function(){this.isImmediatePropagationStopped=K,this.stopPropagation()},isDefaultPrevented:J,isPropagationStopped:J,isImmediatePropagationStopped:J},f.each({mouseenter:"mouseover",mouseleave:"mouseout"},function(a,b){f.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c=this,d=a.relatedTarget,e=a.handleObj,g=e.selector,h;if(!d||d!==c&&!f.contains(c,d))a.type=e.origType,h=e.handler.apply(this,arguments),a.type=b;return h}}}),f.support.submitBubbles||(f.event.special.submit={setup:function(){if(f.nodeName(this,"form"))return!1;f.event.add(this,"click._submit keypress._submit",function(a){var c=a.target,d=f.nodeName(c,"input")||f.nodeName(c,"button")?c.form:b;d&&!d._submit_attached&&(f.event.add(d,"submit._submit",function(a){a._submit_bubble=!0}),d._submit_attached=!0)})},postDispatch:function(a){a._submit_bubble&&(delete a._submit_bubble,this.parentNode&&!a.isTrigger&&f.event.simulate("submit",this.parentNode,a,!0))},teardown:function(){if(f.nodeName(this,"form"))return!1;f.event.remove(this,"._submit")}}),f.support.changeBubbles||(f.event.special.change={setup:function(){if(z.test(this.nodeName)){if(this.type==="checkbox"||this.type==="radio")f.event.add(this,"propertychange._change",function(a){a.originalEvent.propertyName==="checked"&&(this._just_changed=!0)}),f.event.add(this,"click._change",function(a){this._just_changed&&!a.isTrigger&&(this._just_changed=!1,f.event.simulate("change",this,a,!0))});return!1}f.event.add(this,"beforeactivate._change",function(a){var b=a.target;z.test(b.nodeName)&&!b._change_attached&&(f.event.add(b,"change._change",function(a){this.parentNode&&!a.isSimulated&&!a.isTrigger&&f.event.simulate("change",this.parentNode,a,!0)}),b._change_attached=!0)})},handle:function(a){var b=a.target;if(this!==b||a.isSimulated||a.isTrigger||b.type!=="radio"&&b.type!=="checkbox")return a.handleObj.handler.apply(this,arguments)},teardown:function(){f.event.remove(this,"._change");return z.test(this.nodeName)}}),f.support.focusinBubbles||f.each({focus:"focusin",blur:"focusout"},function(a,b){var d=0,e=function(a){f.event.simulate(b,a.target,f.event.fix(a),!0)};f.event.special[b]={setup:function(){d++===0&&c.addEventListener(a,e,!0)},teardown:function(){--d===0&&c.removeEventListener(a,e,!0)}}}),f.fn.extend({on:function(a,c,d,e,g){var h,i;if(typeof a=="object"){typeof c!="string"&&(d=d||c,c=b);for(i in a)this.on(i,c,d,a[i],g);return this}d==null&&e==null?(e=c,d=c=b):e==null&&(typeof c=="string"?(e=d,d=b):(e=d,d=c,c=b));if(e===!1)e=J;else if(!e)return this;g===1&&(h=e,e=function(a){f().off(a);return h.apply(this,arguments)},e.guid=h.guid||(h.guid=f.guid++));return this.each(function(){f.event.add(this,a,e,d,c)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,c,d){if(a&&a.preventDefault&&a.handleObj){var e=a.handleObj;f(a.delegateTarget).off(e.namespace?e.origType+"."+e.namespace:e.origType,e.selector,e.handler);return this}if(typeof a=="object"){for(var g in a)this.off(g,c,a[g]);return this}if(c===!1||typeof c=="function")d=c,c=b;d===!1&&(d=J);return this.each(function(){f.event.remove(this,a,d,c)})},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},live:function(a,b,c){f(this.context).on(a,this.selector,b,c);return this},die:function(a,b){f(this.context).off(a,this.selector||"**",b);return this},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return arguments.length==1?this.off(a,"**"):this.off(b,a,c)},trigger:function(a,b){return this.each(function(){f.event.trigger(a,b,this)})},triggerHandler:function(a,b){if(this[0])return f.event.trigger(a,b,this[0],!0)},toggle:function(a){var b=arguments,c=a.guid||f.guid++,d=0,e=function(c){var e=(f._data(this,"lastToggle"+a.guid)||0)%d;f._data(this,"lastToggle"+a.guid,e+1),c.preventDefault();return b[e].apply(this,arguments)||!1};e.guid=c;while(d<b.length)b[d++].guid=c;return this.click(e)},hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)}}),f.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){f.fn[b]=function(a,c){c==null&&(c=a,a=null);return arguments.length>0?this.on(b,null,a,c):this.trigger(b)},f.attrFn&&(f.attrFn[b]=!0),C.test(b)&&(f.event.fixHooks[b]=f.event.keyHooks),D.test(b)&&(f.event.fixHooks[b]=f.event.mouseHooks)}),function(){function x(a,b,c,e,f,g){for(var h=0,i=e.length;h<i;h++){var j=e[h];if(j){var k=!1;j=j[a];while(j){if(j[d]===c){k=e[j.sizset];break}if(j.nodeType===1){g||(j[d]=c,j.sizset=h);if(typeof b!="string"){if(j===b){k=!0;break}}else if(m.filter(b,[j]).length>0){k=j;break}}j=j[a]}e[h]=k}}}function w(a,b,c,e,f,g){for(var h=0,i=e.length;h<i;h++){var j=e[h];if(j){var k=!1;j=j[a];while(j){if(j[d]===c){k=e[j.sizset];break}j.nodeType===1&&!g&&(j[d]=c,j.sizset=h);if(j.nodeName.toLowerCase()===b){k=j;break}j=j[a]}e[h]=k}}}var a=/((?:$-((?:$-([^()]+$-)|[^()]+)+$-)|$-[(?:$-[[^$-[$-]]*$-]|[$+"][^$+"]*[$+"]|[^$-[$-]$+"]+)+$-]|$-$-.|[^ >+~,($-[$-$-]+)+|[>+~])($-s*,$-s*)?((?:.|$-r|$-n)*)/g,d="sizcache"+(Math.random()+"").replace(".",""),e=0,g=Object.prototype.toString,h=!1,i=!0,j=/$-$-/g,k=/$-r$-n/g,l=/$-W/;[0,0].sort(function(){i=!1;return 0});var m=function(b,d,e,f){e=e||[],d=d||c;var h=d;if(d.nodeType!==1&&d.nodeType!==9)return[];if(!b||typeof b!="string")return e;var i,j,k,l,n,q,r,t,u=!0,v=m.isXML(d),w=[],x=b;do{a.exec(""),i=a.exec(x);if(i){x=i[3],w.push(i[1]);if(i[2]){l=i[3];break}}}while(i);if(w.length>1&&p.exec(b))if(w.length===2&&o.relative[w[0]])j=y(w[0]+w[1],d,f);else{j=o.relative[w[0]]?[d]:m(w.shift(),d);while(w.length)b=w.shift(),o.relative[b]&&(b+=w.shift()),j=y(b,j,f)}else{!f&&w.length>1&&d.nodeType===9&&!v&&o.match.ID.test(w[0])&&!o.match.ID.test(w[w.length-1])&&(n=m.find(w.shift(),d,v),d=n.expr?m.filter(n.expr,n.set)[0]:n.set[0]);if(d){n=f?{expr:w.pop(),set:s(f)}:m.find(w.pop(),w.length===1&&(w[0]==="~"||w[0]==="+")&&d.parentNode?d.parentNode:d,v),j=n.expr?m.filter(n.expr,n.set):n.set,w.length>0?k=s(j):u=!1;while(w.length)q=w.pop(),r=q,o.relative[q]?r=w.pop():q="",r==null&&(r=d),o.relative[q](k,r,v)}else k=w=[]}k||(k=j),k||m.error(q||b);if(g.call(k)==="[object Array]")if(!u)e.push.apply(e,k);else if(d&&d.nodeType===1)for(t=0;k[t]!=null;t++)k[t]&&(k[t]===!0||k[t].nodeType===1&&m.contains(d,k[t]))&&e.push(j[t]);else for(t=0;k[t]!=null;t++)k[t]&&k[t].nodeType===1&&e.push(j[t]);else s(k,e);l&&(m(l,h,e,f),m.uniqueSort(e));return e};m.uniqueSort=function(a){if(u){h=i,a.sort(u);if(h)for(var b=1;b<a.length;b++)a[b]===a[b-1]&&a.splice(b--,1)}return a},m.matches=function(a,b){return m(a,null,null,b)},m.matchesSelector=function(a,b){return m(b,null,null,[a]).length>0},m.find=function(a,b,c){var d,e,f,g,h,i;if(!a)return[];for(e=0,f=o.order.length;e<f;e++){h=o.order[e];if(g=o.leftMatch[h].exec(a)){i=g[1],g.splice(1,1);if(i.substr(i.length-1)!=="$-$-"){g[1]=(g[1]||"").replace(j,""),d=o.find[h](g,b,c);if(d!=null){a=a.replace(o.match[h],"");break}}}}d||(d=typeof b.getElementsByTagName!="undefined"?b.getElementsByTagName("*"):[]);return{set:d,expr:a}},m.filter=function(a,c,d,e){var f,g,h,i,j,k,l,n,p,q=a,r=[],s=c,t=c&&c[0]&&m.isXML(c[0]);while(a&&c.length){for(h in o.filter)if((f=o.leftMatch[h].exec(a))!=null&&f[2]){k=o.filter[h],l=f[1],g=!1,f.splice(1,1);if(l.substr(l.length-1)==="$-$-")continue;s===r&&(r=[]);if(o.preFilter[h]){f=o.preFilter[h](f,s,d,r,e,t);if(!f)g=i=!0;else if(f===!0)continue}if(f)for(n=0;(j=s[n])!=null;n++)j&&(i=k(j,f,n,s),p=e^i,d&&i!=null?p?g=!0:s[n]=!1:p&&(r.push(j),g=!0));if(i!==b){d||(s=r),a=a.replace(o.match[h],"");if(!g)return[];break}}if(a===q)if(g==null)m.error(a);else break;q=a}return s},m.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)};var n=m.getText=function(a){var b,c,d=a.nodeType,e="";if(d){if(d===1||d===9||d===11){if(typeof a.textContent=="string")return a.textContent;if(typeof a.innerText=="string")return a.innerText.replace(k,"");for(a=a.firstChild;a;a=a.nextSibling)e+=n(a)}else if(d===3||d===4)return a.nodeValue}else for(b=0;c=a[b];b++)c.nodeType!==8&&(e+=n(c));return e},o=m.selectors={order:["ID","NAME","TAG"],match:{ID:/#((?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)+)/,CLASS:/$-.((?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)+)/,NAME:/$-[name=[$+"]*((?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)+)[$+"]*$-]/,ATTR:/$-[$-s*((?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)+)$-s*(?:($-S?=)$-s*(?:([$+"])(.*?)$-3|(#?(?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)*)|)|)$-s*$-]/,TAG:/^((?:[$-w$-u00c0-$-uFFFF$-*$--]|$-$-.)+)/,CHILD:/:(only|nth|last|first)-child(?:$-($-s*(even|odd|(?:[+$--]?$-d+|(?:[+$--]?$-d*)?n$-s*(?:[+$--]$-s*$-d+)?))$-s*$-))?/,POS:/:(nth|eq|gt|lt|first|last|even|odd)(?:$-(($-d*)$-))?(?=[^$--]|$)/,PSEUDO:/:((?:[$-w$-u00c0-$-uFFFF$--]|$-$-.)+)(?:$-(([$+"]?)((?:$-([^$-)]+$-)|[^$-($-)]*)+)$-2$-))?/},leftMatch:{},attrMap:{"class":"className","for":"htmlFor"},attrHandle:{href:function(a){return a.getAttribute("href")},type:function(a){return a.getAttribute("type")}},relative:{"+":function(a,b){var c=typeof b=="string",d=c&&!l.test(b),e=c&&!d;d&&(b=b.toLowerCase());for(var f=0,g=a.length,h;f<g;f++)if(h=a[f]){while((h=h.previousSibling)&&h.nodeType!==1);a[f]=e||h&&h.nodeName.toLowerCase()===b?h||!1:h===b}e&&m.filter(b,a,!0)},">":function(a,b){var c,d=typeof b=="string",e=0,f=a.length;if(d&&!l.test(b)){b=b.toLowerCase();for(;e<f;e++){c=a[e];if(c){var g=c.parentNode;a[e]=g.nodeName.toLowerCase()===b?g:!1}}}else{for(;e<f;e++)c=a[e],c&&(a[e]=d?c.parentNode:c.parentNode===b);d&&m.filter(b,a,!0)}},"":function(a,b,c){var d,f=e++,g=x;typeof b=="string"&&!l.test(b)&&(b=b.toLowerCase(),d=b,g=w),g("parentNode",b,f,a,d,c)},"~":function(a,b,c){var d,f=e++,g=x;typeof b=="string"&&!l.test(b)&&(b=b.toLowerCase(),d=b,g=w),g("previousSibling",b,f,a,d,c)}},find:{ID:function(a,b,c){if(typeof b.getElementById!="undefined"&&!c){var d=b.getElementById(a[1]);return d&&d.parentNode?[d]:[]}},NAME:function(a,b){if(typeof b.getElementsByName!="undefined"){var c=[],d=b.getElementsByName(a[1]);for(var e=0,f=d.length;e<f;e++)d[e].getAttribute("name")===a[1]&&c.push(d[e]);return c.length===0?null:c}},TAG:function(a,b){if(typeof b.getElementsByTagName!="undefined")return b.getElementsByTagName(a[1])}},preFilter:{CLASS:function(a,b,c,d,e,f){a=" "+a[1].replace(j,"")+" ";if(f)return a;for(var g=0,h;(h=b[g])!=null;g++)h&&(e^(h.className&&(" "+h.className+" ").replace(/[$-t$-n$-r]/g," ").indexOf(a)>=0)?c||d.push(h):c&&(b[g]=!1));return!1},ID:function(a){return a[1].replace(j,"")},TAG:function(a,b){return a[1].replace(j,"").toLowerCase()},CHILD:function(a){if(a[1]==="nth"){a[2]||m.error(a[0]),a[2]=a[2].replace(/^$-+|$-s*/g,"");var b=/(-?)($-d*)(?:n([+$--]?$-d*))?/.exec(a[2]==="even"&&"2n"||a[2]==="odd"&&"2n+1"||!/$-D/.test(a[2])&&"0n+"+a[2]||a[2]);a[2]=b[1]+(b[2]||1)-0,a[3]=b[3]-0}else a[2]&&m.error(a[0]);a[0]=e++;return a},ATTR:function(a,b,c,d,e,f){var g=a[1]=a[1].replace(j,"");!f&&o.attrMap[g]&&(a[1]=o.attrMap[g]),a[4]=(a[4]||a[5]||"").replace(j,""),a[2]==="~="&&(a[4]=" "+a[4]+" ");return a},PSEUDO:function(b,c,d,e,f){if(b[1]==="not")if((a.exec(b[3])||"").length>1||/^$-w/.test(b[3]))b[3]=m(b[3],null,null,c);else{var g=m.filter(b[3],c,d,!0^f);d||e.push.apply(e,g);return!1}else if(o.match.POS.test(b[0])||o.match.CHILD.test(b[0]))return!0;return b},POS:function(a){a.unshift(!0);return a}},filters:{enabled:function(a){return a.disabled===!1&&a.type!=="hidden"},disabled:function(a){return a.disabled===!0},checked:function(a){return a.checked===!0},selected:function(a){a.parentNode&&a.parentNode.selectedIndex;return a.selected===!0},parent:function(a){return!!a.firstChild},empty:function(a){return!a.firstChild},has:function(a,b,c){return!!m(c[3],a).length},header:function(a){return/h$-d/i.test(a.nodeName)},text:function(a){var b=a.getAttribute("type"),c=a.type;return a.nodeName.toLowerCase()==="input"&&"text"===c&&(b===c||b===null)},radio:function(a){return a.nodeName.toLowerCase()==="input"&&"radio"===a.type},checkbox:function(a){return a.nodeName.toLowerCase()==="input"&&"checkbox"===a.type},file:function(a){return a.nodeName.toLowerCase()==="input"&&"file"===a.type},password:function(a){return a.nodeName.toLowerCase()==="input"&&"password"===a.type},submit:function(a){var b=a.nodeName.toLowerCase();return(b==="input"||b==="button")&&"submit"===a.type},image:function(a){return a.nodeName.toLowerCase()==="input"&&"image"===a.type},reset:function(a){var b=a.nodeName.toLowerCase();return(b==="input"||b==="button")&&"reset"===a.type},button:function(a){var b=a.nodeName.toLowerCase();return b==="input"&&"button"===a.type||b==="button"},input:function(a){return/input|select|textarea|button/i.test(a.nodeName)},focus:function(a){return a===a.ownerDocument.activeElement}},setFilters:{first:function(a,b){return b===0},last:function(a,b,c,d){return b===d.length-1},even:function(a,b){return b%2===0},odd:function(a,b){return b%2===1},lt:function(a,b,c){return b<c[3]-0},gt:function(a,b,c){return b>c[3]-0},nth:function(a,b,c){return c[3]-0===b},eq:function(a,b,c){return c[3]-0===b}},filter:{PSEUDO:function(a,b,c,d){var e=b[1],f=o.filters[e];if(f)return f(a,c,b,d);if(e==="contains")return(a.textContent||a.innerText||n([a])||"").indexOf(b[3])>=0;if(e==="not"){var g=b[3];for(var h=0,i=g.length;h<i;h++)if(g[h]===a)return!1;return!0}m.error(e)},CHILD:function(a,b){var c,e,f,g,h,i,j,k=b[1],l=a;switch(k){case"only":case"first":while(l=l.previousSibling)if(l.nodeType===1)return!1;if(k==="first")return!0;l=a;case"last":while(l=l.nextSibling)if(l.nodeType===1)return!1;return!0;case"nth":c=b[2],e=b[3];if(c===1&&e===0)return!0;f=b[0],g=a.parentNode;if(g&&(g[d]!==f||!a.nodeIndex)){i=0;for(l=g.firstChild;l;l=l.nextSibling)l.nodeType===1&&(l.nodeIndex=++i);g[d]=f}j=a.nodeIndex-e;return c===0?j===0:j%c===0&&j/c>=0}},ID:function(a,b){return a.nodeType===1&&a.getAttribute("id")===b},TAG:function(a,b){return b==="*"&&a.nodeType===1||!!a.nodeName&&a.nodeName.toLowerCase()===b},CLASS:function(a,b){return(" "+(a.className||a.getAttribute("class"))+" ").indexOf(b)>-1},ATTR:function(a,b){var c=b[1],d=m.attr?m.attr(a,c):o.attrHandle[c]?o.attrHandle[c](a):a[c]!=null?a[c]:a.getAttribute(c),e=d+"",f=b[2],g=b[4];return d==null?f==="!=":!f&&m.attr?d!=null:f==="="?e===g:f==="*="?e.indexOf(g)>=0:f==="~="?(" "+e+" ").indexOf(g)>=0:g?f==="!="?e!==g:f==="^="?e.indexOf(g)===0:f==="$="?e.substr(e.length-g.length)===g:f==="|="?e===g||e.substr(0,g.length+1)===g+"-":!1:e&&d!==!1},POS:function(a,b,c,d){var e=b[2],f=o.setFilters[e];if(f)return f(a,c,b,d)}}},p=o.match.POS,q=function(a,b){return"$-$-"+(b-0+1)};for(var r in o.match)o.match[r]=new RegExp(o.match[r].source+/(?![^$-[]*$-])(?![^$-(]*$-))/.source),o.leftMatch[r]=new RegExp(/(^(?:.|$-r|$-n)*?)/.source+o.match[r].source.replace(/$-$-($-d+)/g,q));o.match.globalPOS=p;var s=function(a,b){a=Array.prototype.slice.call(a,0);if(b){b.push.apply(b,a);return b}return a};try{Array.prototype.slice.call(c.documentElement.childNodes,0)[0].nodeType}catch(t){s=function(a,b){var c=0,d=b||[];if(g.call(a)==="[object Array]")Array.prototype.push.apply(d,a);else if(typeof a.length=="number")for(var e=a.length;c<e;c++)d.push(a[c]);else for(;a[c];c++)d.push(a[c]);return d}}var u,v;c.documentElement.compareDocumentPosition?u=function(a,b){if(a===b){h=!0;return 0}if(!a.compareDocumentPosition||!b.compareDocumentPosition)return a.compareDocumentPosition?-1:1;return a.compareDocumentPosition(b)&4?-1:1}:(u=function(a,b){if(a===b){h=!0;return 0}if(a.sourceIndex&&b.sourceIndex)return a.sourceIndex-b.sourceIndex;var c,d,e=[],f=[],g=a.parentNode,i=b.parentNode,j=g;if(g===i)return v(a,b);if(!g)return-1;if(!i)return 1;while(j)e.unshift(j),j=j.parentNode;j=i;while(j)f.unshift(j),j=j.parentNode;c=e.length,d=f.length;for(var k=0;k<c&&k<d;k++)if(e[k]!==f[k])return v(e[k],f[k]);return k===c?v(a,f[k],-1):v(e[k],b,1)},v=function(a,b,c){if(a===b)return c;var d=a.nextSibling;while(d){if(d===b)return-1;d=d.nextSibling}return 1}),function(){var a=c.createElement("div"),d="script"+(new Date).getTime(),e=c.documentElement;a.innerHTML="<a name=$+"+d+"$+/>",e.insertBefore(a,e.firstChild),c.getElementById(d)&&(o.find.ID=function(a,c,d){if(typeof c.getElementById!="undefined"&&!d){var e=c.getElementById(a[1]);return e?e.id===a[1]||typeof e.getAttributeNode!="undefined"&&e.getAttributeNode("id").nodeValue===a[1]?[e]:b:[]}},o.filter.ID=function(a,b){var c=typeof a.getAttributeNode!="undefined"&&a.getAttributeNode("id");return a.nodeType===1&&c&&c.nodeValue===b}),e.removeChild(a),e=a=null}(),function(){var a=c.createElement("div");a.appendChild(c.createComment("")),a.getElementsByTagName("*").length>0&&(o.find.TAG=function(a,b){var c=b.getElementsByTagName(a[1]);if(a[1]==="*"){var d=[];for(var e=0;c[e];e++)c[e].nodeType===1&&d.push(c[e]);c=d}return c}),a.innerHTML="<a href=$+#$+></a>",a.firstChild&&typeof a.firstChild.getAttribute!="undefined"&&a.firstChild.getAttribute("href")!=="#"&&(o.attrHandle.href=function(a){return a.getAttribute("href",2)}),a=null}(),c.querySelectorAll&&function(){var a=m,b=c.createElement("div"),d="__sizzle__";b.innerHTML="<p class=$+TEST$+></p>";if(!b.querySelectorAll||b.querySelectorAll(".TEST").length!==0){m=function(b,e,f,g){e=e||c;if(!g&&!m.isXML(e)){var h=/^($-w+$)|^$-.([$-w$--]+$)|^#([$-w$--]+$)/.exec(b);if(h&&(e.nodeType===1||e.nodeType===9)){if(h[1])return s(e.getElementsByTagName(b),f);if(h[2]&&o.find.CLASS&&e.getElementsByClassName)return s(e.getElementsByClassName(h[2]),f)}if(e.nodeType===9){if(b==="body"&&e.body)return s([e.body],f);if(h&&h[3]){var i=e.getElementById(h[3]);if(!i||!i.parentNode)return s([],f);if(i.id===h[3])return s([i],f)}try{return s(e.querySelectorAll(b),f)}catch(j){}}else if(e.nodeType===1&&e.nodeName.toLowerCase()!=="object"){var k=e,l=e.getAttribute("id"),n=l||d,p=e.parentNode,q=/^$-s*[+~]/.test(b);l?n=n.replace(/$+/g,"$-$-$&"):e.setAttribute("id",n),q&&p&&(e=e.parentNode);try{if(!q||p)return s(e.querySelectorAll("[id=$+"+n+"$+] "+b),f)}catch(r){}finally{l||k.removeAttribute("id")}}}return a(b,e,f,g)};for(var e in a)m[e]=a[e];b=null}}(),function(){var a=c.documentElement,b=a.matchesSelector||a.mozMatchesSelector||a.webkitMatchesSelector||a.msMatchesSelector;if(b){var d=!b.call(c.createElement("div"),"div"),e=!1;try{b.call(c.documentElement,"[test!=$+$+]:sizzle")}catch(f){e=!0}m.matchesSelector=function(a,c){c=c.replace(/$-=$-s*([^$+"$-]]*)$-s*$-]/g,"=$+$1$+]");if(!m.isXML(a))try{if(e||!o.match.PSEUDO.test(c)&&!/!=/.test(c)){var f=b.call(a,c);if(f||!d||a.document&&a.document.nodeType!==11)return f}}catch(g){}return m(c,null,null,[a]).length>0}}}(),function(){var a=c.createElement("div");a.innerHTML="<div class=$+test e$+></div><div class=$+test$+></div>";if(!!a.getElementsByClassName&&a.getElementsByClassName("e").length!==0){a.lastChild.className="e";if(a.getElementsByClassName("e").length===1)return;o.order.splice(1,0,"CLASS"),o.find.CLASS=function(a,b,c){if(typeof b.getElementsByClassName!="undefined"&&!c)return b.getElementsByClassName(a[1])},a=null}}(),c.documentElement.contains?m.contains=function(a,b){return a!==b&&(a.contains?a.contains(b):!0)}:c.documentElement.compareDocumentPosition?m.contains=function(a,b){return!!(a.compareDocumentPosition(b)&16)}:m.contains=function(){return!1},m.isXML=function(a){var b=(a?a.ownerDocument||a:0).documentElement;return b?b.nodeName!=="HTML":!1};var y=function(a,b,c){var d,e=[],f="",g=b.nodeType?[b]:b;while(d=o.match.PSEUDO.exec(a))f+=d[0],a=a.replace(o.match.PSEUDO,"");a=o.relative[a]?a+"*":a;for(var h=0,i=g.length;h<i;h++)m(a,g[h],e,c);return m.filter(f,e)};m.attr=f.attr,m.selectors.attrMap={},f.find=m,f.expr=m.selectors,f.expr[":"]=f.expr.filters,f.unique=m.uniqueSort,f.text=m.getText,f.isXMLDoc=m.isXML,f.contains=m.contains}();var L=/Until$/,M=/^(?:parents|prevUntil|prevAll)/,N=/,/,O=/^.[^:#$-[$-.,]*$/,P=Array.prototype.slice,Q=f.expr.match.globalPOS,R={children:!0,contents:!0,next:!0,prev:!0};f.fn.extend({find:function(a){var b=this,c,d;if(typeof a!="string")return f(a).filter(function(){for(c=0,d=b.length;c<d;c++)if(f.contains(b[c],this))return!0});var e=this.pushStack("","find",a),g,h,i;for(c=0,d=this.length;c<d;c++){g=e.length,f.find(a,this[c],e);if(c>0)for(h=g;h<e.length;h++)for(i=0;i<g;i++)if(e[i]===e[h]){e.splice(h--,1);break}}return e},has:function(a){var b=f(a);return this.filter(function(){for(var a=0,c=b.length;a<c;a++)if(f.contains(this,b[a]))return!0})},not:function(a){return this.pushStack(T(this,a,!1),"not",a)},filter:function(a){return this.pushStack(T(this,a,!0),"filter",a)},is:function(a){return!!a&&(typeof a=="string"?Q.test(a)?f(a,this.context).index(this[0])>=0:f.filter(a,this).length>0:this.filter(a).length>0)},closest:function(a,b){var c=[],d,e,g=this[0];if(f.isArray(a)){var h=1;while(g&&g.ownerDocument&&g!==b){for(d=0;d<a.length;d++)f(g).is(a[d])&&c.push({selector:a[d],elem:g,level:h});g=g.parentNode,h++}return c}var i=Q.test(a)||typeof a!="string"?f(a,b||this.context):0;for(d=0,e=this.length;d<e;d++){g=this[d];while(g){if(i?i.index(g)>-1:f.find.matchesSelector(g,a)){c.push(g);break}g=g.parentNode;if(!g||!g.ownerDocument||g===b||g.nodeType===11)break}}c=c.length>1?f.unique(c):c;return this.pushStack(c,"closest",a)},index:function(a){if(!a)return this[0]&&this[0].parentNode?this.prevAll().length:-1;if(typeof a=="string")return f.inArray(this[0],f(a));return f.inArray(a.jquery?a[0]:a,this)},add:function(a,b){var c=typeof a=="string"?f(a,b):f.makeArray(a&&a.nodeType?[a]:a),d=f.merge(this.get(),c);return this.pushStack(S(c[0])||S(d[0])?d:f.unique(d))},andSelf:function(){return this.add(this.prevObject)}}),f.each({parent:function(a){var b=a.parentNode;return b&&b.nodeType!==11?b:null},parents:function(a){return f.dir(a,"parentNode")},parentsUntil:function(a,b,c){return f.dir(a,"parentNode",c)},next:function(a){return f.nth(a,2,"nextSibling")},prev:function(a){return f.nth(a,2,"previousSibling")},nextAll:function(a){return f.dir(a,"nextSibling")},prevAll:function(a){return f.dir(a,"previousSibling")},nextUntil:function(a,b,c){return f.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return f.dir(a,"previousSibling",c)},siblings:function(a){return f.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return f.sibling(a.firstChild)},contents:function(a){return f.nodeName(a,"iframe")?a.contentDocument||a.contentWindow.document:f.makeArray(a.childNodes)}},function(a,b){f.fn[a]=function(c,d){var e=f.map(this,b,c);L.test(a)||(d=c),d&&typeof d=="string"&&(e=f.filter(d,e)),e=this.length>1&&!R[a]?f.unique(e):e,(this.length>1||N.test(d))&&M.test(a)&&(e=e.reverse());return this.pushStack(e,a,P.call(arguments).join(","))}}),f.extend({filter:function(a,b,c){c&&(a=":not("+a+")");return b.length===1?f.find.matchesSelector(b[0],a)?[b[0]]:[]:f.find.matches(a,b)},dir:function(a,c,d){var e=[],g=a[c];while(g&&g.nodeType!==9&&(d===b||g.nodeType!==1||!f(g).is(d)))g.nodeType===1&&e.push(g),g=g[c];return e},nth:function(a,b,c,d){b=b||1;var e=0;for(;a;a=a[c])if(a.nodeType===1&&++e===b)break;return a},sibling:function(a,b){var c=[];for(;a;a=a.nextSibling)a.nodeType===1&&a!==b&&c.push(a);return c}});var V="abbr|article|aside|audio|bdi|canvas|data|datalist|details|figcaption|figure|footer|header|hgroup|mark|meter|nav|output|progress|section|summary|time|video",W=/ jQuery$-d+="(?:$-d+|null)"/g,X=/^$-s+/,Y=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([$-w:]+)[^>]*)$-/>/ig,Z=/<([$-w:]+)/,$=/<tbody/i,_=/<|&#?$-w+;/,ba=/<(?:script|style)/i,bb=/<(?:script|object|embed|option|style)/i,bc=new RegExp("<(?:"+V+")[$-$-s/>]","i"),bd=/checked$-s*(?:[^=]|=$-s*.checked.)/i,be=/$-/(java|ecma)script/i,bf=/^$-s*<!(?:$-[CDATA$-[|$--$--)/,bg={option:[1,"<select multiple=$+multiple$+>","</select>"],legend:[1,"<fieldset>","</fieldset>"],thead:[1,"<table>","</table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],col:[2,"<table><tbody></tbody><colgroup>","</colgroup></table>"],area:[1,"<map>","</map>"],_default:[0,"",""]},bh=U(c);bg.optgroup=bg.option,bg.tbody=bg.tfoot=bg.colgroup=bg.caption=bg.thead,bg.th=bg.td,f.support.htmlSerialize||(bg._default=[1,"div<div>","</div>"]),f.fn.extend({text:function(a){return f.access(this,function(a){return a===b?f.text(this):this.empty().append((this[0]&&this[0].ownerDocument||c).createTextNode(a))},null,a,arguments.length)},wrapAll:function(a){if(f.isFunction(a))return this.each(function(b){f(this).wrapAll(a.call(this,b))});if(this[0]){var b=f(a,this[0].ownerDocument).eq(0).clone(!0);this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){var a=this;while(a.firstChild&&a.firstChild.nodeType===1)a=a.firstChild;return a}).append(this)}return this},wrapInner:function(a){if(f.isFunction(a))return this.each(function(b){f(this).wrapInner(a.call(this,b))});return this.each(function(){var b=f(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=f.isFunction(a);return this.each(function(c){f(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){f.nodeName(this,"body")||f(this).replaceWith(this.childNodes)}).end()},append:function(){return this.domManip(arguments,!0,function(a){this.nodeType===1&&this.appendChild(a)})},prepend:function(){return this.domManip(arguments,!0,function(a){this.nodeType===1&&this.insertBefore(a,this.firstChild)})},before:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this)});if(arguments.length){var a=f.clean(arguments);a.push.apply(a,this.toArray());return this.pushStack(a,"before",arguments)}},after:function(){if(this[0]&&this[0].parentNode)return this.domManip(arguments,!1,function(a){this.parentNode.insertBefore(a,this.nextSibling)});if(arguments.length){var a=this.pushStack(this,"after",arguments);a.push.apply(a,f.clean(arguments));return a}},remove:function(a,b){for(var c=0,d;(d=this[c])!=null;c++)if(!a||f.filter(a,[d]).length)!b&&d.nodeType===1&&(f.cleanData(d.getElementsByTagName("*")),f.cleanData([d])),d.parentNode&&d.parentNode.removeChild(d);return this},empty:function(){for(var a=0,b;(b=this[a])!=null;a++){b.nodeType===1&&f.cleanData(b.getElementsByTagName("*"));while(b.firstChild)b.removeChild(b.firstChild)}return this},clone:function(a,b){a=a==null?!1:a,b=b==null?a:b;return this.map(function(){return f.clone(this,a,b)})},html:function(a){return f.access(this,function(a){var c=this[0]||{},d=0,e=this.length;if(a===b)return c.nodeType===1?c.innerHTML.replace(W,""):null;if(typeof a=="string"&&!ba.test(a)&&(f.support.leadingWhitespace||!X.test(a))&&!bg[(Z.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(Y,"<$1></$2>");try{for(;d<e;d++)c=this[d]||{},c.nodeType===1&&(f.cleanData(c.getElementsByTagName("*")),c.innerHTML=a);c=0}catch(g){}}c&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(a){if(this[0]&&this[0].parentNode){if(f.isFunction(a))return this.each(function(b){var c=f(this),d=c.html();c.replaceWith(a.call(this,b,d))});typeof a!="string"&&(a=f(a).detach());return this.each(function(){var b=this.nextSibling,c=this.parentNode;f(this).remove(),b?f(b).before(a):f(c).append(a)})}return this.length?this.pushStack(f(f.isFunction(a)?a():a),"replaceWith",a):this},detach:function(a){return this.remove(a,!0)},domManip:function(a,c,d){var e,g,h,i,j=a[0],k=[];if(!f.support.checkClone&&arguments.length===3&&typeof j=="string"&&bd.test(j))return this.each(function(){f(this).domManip(a,c,d,!0)});if(f.isFunction(j))return this.each(function(e){var g=f(this);a[0]=j.call(this,e,c?g.html():b),g.domManip(a,c,d)});if(this[0]){i=j&&j.parentNode,f.support.parentNode&&i&&i.nodeType===11&&i.childNodes.length===this.length?e={fragment:i}:e=f.buildFragment(a,this,k),h=e.fragment,h.childNodes.length===1?g=h=h.firstChild:g=h.firstChild;if(g){c=c&&f.nodeName(g,"tr");for(var l=0,m=this.length,n=m-1;l<m;l++)d.call(c?bi(this[l],g):this[l],e.cacheable||m>1&&l<n?f.clone(h,!0,!0):h)}k.length&&f.each(k,function(a,b){b.src?f.ajax({type:"GET",global:!1,url:b.src,async:!1,dataType:"script"}):f.globalEval((b.text||b.textContent||b.innerHTML||"").replace(bf,"/*$0*/")),b.parentNode&&b.parentNode.removeChild(b)})}return this}}),f.buildFragment=function(a,b,d){var e,g,h,i,j=a[0];b&&b[0]&&(i=b[0].ownerDocument||b[0]),i.createDocumentFragment||(i=c),a.length===1&&typeof j=="string"&&j.length<512&&i===c&&j.charAt(0)==="<"&&!bb.test(j)&&(f.support.checkClone||!bd.test(j))&&(f.support.html5Clone||!bc.test(j))&&(g=!0,h=f.fragments[j],h&&h!==1&&(e=h)),e||(e=i.createDocumentFragment(),f.clean(a,i,e,d)),g&&(f.fragments[j]=h?e:1);return{fragment:e,cacheable:g}},f.fragments={},f.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){f.fn[a]=function(c){var d=[],e=f(c),g=this.length===1&&this[0].parentNode;if(g&&g.nodeType===11&&g.childNodes.length===1&&e.length===1){e[b](this[0]);return this}for(var h=0,i=e.length;h<i;h++){var j=(h>0?this.clone(!0):this).get();f(e[h])[b](j),d=d.concat(j)}return this.pushStack(d,a,e.selector)}}),f.extend({clone:function(a,b,c){var d,e,g,h=f.support.html5Clone||f.isXMLDoc(a)||!bc.test("<"+a.nodeName+">")?a.cloneNode(!0):bo(a);if((!f.support.noCloneEvent||!f.support.noCloneChecked)&&(a.nodeType===1||a.nodeType===11)&&!f.isXMLDoc(a)){bk(a,h),d=bl(a),e=bl(h);for(g=0;d[g];++g)e[g]&&bk(d[g],e[g])}if(b){bj(a,h);if(c){d=bl(a),e=bl(h);for(g=0;d[g];++g)bj(d[g],e[g])}}d=e=null;return h},clean:function(a,b,d,e){var g,h,i,j=[];b=b||c,typeof b.createElement=="undefined"&&(b=b.ownerDocument||b[0]&&b[0].ownerDocument||c);for(var k=0,l;(l=a[k])!=null;k++){typeof l=="number"&&(l+="");if(!l)continue;if(typeof l=="string")if(!_.test(l))l=b.createTextNode(l);else{l=l.replace(Y,"<$1></$2>");var m=(Z.exec(l)||["",""])[1].toLowerCase(),n=bg[m]||bg._default,o=n[0],p=b.createElement("div"),q=bh.childNodes,r;b===c?bh.appendChild(p):U(b).appendChild(p),p.innerHTML=n[1]+l+n[2];while(o--)p=p.lastChild;if(!f.support.tbody){var s=$.test(l),t=m==="table"&&!s?p.firstChild&&p.firstChild.childNodes:n[1]==="<table>"&&!s?p.childNodes:[];for(i=t.length-1;i>=0;--i)f.nodeName(t[i],"tbody")&&!t[i].childNodes.length&&t[i].parentNode.removeChild(t[i])}!f.support.leadingWhitespace&&X.test(l)&&p.insertBefore(b.createTextNode(X.exec(l)[0]),p.firstChild),l=p.childNodes,p&&(p.parentNode.removeChild(p),q.length>0&&(r=q[q.length-1],r&&r.parentNode&&r.parentNode.removeChild(r)))}var u;if(!f.support.appendChecked)if(l[0]&&typeof (u=l.length)=="number")for(i=0;i<u;i++)bn(l[i]);else bn(l);l.nodeType?j.push(l):j=f.merge(j,l)}if(d){g=function(a){return!a.type||be.test(a.type)};for(k=0;j[k];k++){h=j[k];if(e&&f.nodeName(h,"script")&&(!h.type||be.test(h.type)))e.push(h.parentNode?h.parentNode.removeChild(h):h);else{if(h.nodeType===1){var v=f.grep(h.getElementsByTagName("script"),g);j.splice.apply(j,[k+1,0].concat(v))}d.appendChild(h)}}}return j},cleanData:function(a){var b,c,d=f.cache,e=f.event.special,g=f.support.deleteExpando;for(var h=0,i;(i=a[h])!=null;h++){if(i.nodeName&&f.noData[i.nodeName.toLowerCase()])continue;c=i[f.expando];if(c){b=d[c];if(b&&b.events){for(var j in b.events)e[j]?f.event.remove(i,j):f.removeEvent(i,j,b.handle);b.handle&&(b.handle.elem=null)}g?delete i[f.expando]:i.removeAttribute&&i.removeAttribute(f.expando),delete d[c]}}}});var bp=/alpha$-([^)]*$-)/i,bq=/opacity=([^)]*)/,br=/([A-Z]|^ms)/g,bs=/^[$--+]?(?:$-d*$-.)?$-d+$/i,bt=/^-?(?:$-d*$-.)?$-d+(?!px)[^$-d$-s]+$/i,bu=/^([$--+])=([$--+.$-de]+)/,bv=/^margin/,bw={position:"absolute",visibility:"hidden",display:"block"},bx=["Top","Right","Bottom","Left"],by,bz,bA;f.fn.css=function(a,c){return f.access(this,function(a,c,d){return d!==b?f.style(a,c,d):f.css(a,c)},a,c,arguments.length>1)},f.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=by(a,"opacity");return c===""?"1":c}return a.style.opacity}}},cssNumber:{fillOpacity:!0,fontWeight:!0,lineHeight:!0,opacity:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":f.support.cssFloat?"cssFloat":"styleFloat"},style:function(a,c,d,e){if(!!a&&a.nodeType!==3&&a.nodeType!==8&&!!a.style){var g,h,i=f.camelCase(c),j=a.style,k=f.cssHooks[i];c=f.cssProps[i]||i;if(d===b){if(k&&"get"in k&&(g=k.get(a,!1,e))!==b)return g;return j[c]}h=typeof d,h==="string"&&(g=bu.exec(d))&&(d=+(g[1]+1)*+g[2]+parseFloat(f.css(a,c)),h="number");if(d==null||h==="number"&&isNaN(d))return;h==="number"&&!f.cssNumber[i]&&(d+="px");if(!k||!("set"in k)||(d=k.set(a,d))!==b)try{j[c]=d}catch(l){}}},css:function(a,c,d){var e,g;c=f.camelCase(c),g=f.cssHooks[c],c=f.cssProps[c]||c,c==="cssFloat"&&(c="float");if(g&&"get"in g&&(e=g.get(a,!0,d))!==b)return e;if(by)return by(a,c)},swap:function(a,b,c){var d={},e,f;for(f in b)d[f]=a.style[f],a.style[f]=b[f];e=c.call(a);for(f in b)a.style[f]=d[f];return e}}),f.curCSS=f.css,c.defaultView&&c.defaultView.getComputedStyle&&(bz=function(a,b){var c,d,e,g,h=a.style;b=b.replace(br,"-$1").toLowerCase(),(d=a.ownerDocument.defaultView)&&(e=d.getComputedStyle(a,null))&&(c=e.getPropertyValue(b),c===""&&!f.contains(a.ownerDocument.documentElement,a)&&(c=f.style(a,b))),!f.support.pixelMargin&&e&&bv.test(b)&&bt.test(c)&&(g=h.width,h.width=c,c=e.width,h.width=g);return c}),c.documentElement.currentStyle&&(bA=function(a,b){var c,d,e,f=a.currentStyle&&a.currentStyle[b],g=a.style;f==null&&g&&(e=g[b])&&(f=e),bt.test(f)&&(c=g.left,d=a.runtimeStyle&&a.runtimeStyle.left,d&&(a.runtimeStyle.left=a.currentStyle.left),g.left=b==="fontSize"?"1em":f,f=g.pixelLeft+"px",g.left=c,d&&(a.runtimeStyle.left=d));return f===""?"auto":f}),by=bz||bA,f.each(["height","width"],function(a,b){f.cssHooks[b]={get:function(a,c,d){if(c)return a.offsetWidth!==0?bB(a,b,d):f.swap(a,bw,function(){return bB(a,b,d)})},set:function(a,b){return bs.test(b)?b+"px":b}}}),f.support.opacity||(f.cssHooks.opacity={get:function(a,b){return bq.test((b&&a.currentStyle?a.currentStyle.filter:a.style.filter)||"")?parseFloat(RegExp.$1)/100+"":b?"1":""},set:function(a,b){var c=a.style,d=a.currentStyle,e=f.isNumeric(b)?"alpha(opacity="+b*100+")":"",g=d&&d.filter||c.filter||"";c.zoom=1;if(b>=1&&f.trim(g.replace(bp,""))===""){c.removeAttribute("filter");if(d&&!d.filter)return}c.filter=bp.test(g)?g.replace(bp,e):g+" "+e}}),f(function(){f.support.reliableMarginRight||(f.cssHooks.marginRight={get:function(a,b){return f.swap(a,{display:"inline-block"},function(){return b?by(a,"margin-right"):a.style.marginRight})}})}),f.expr&&f.expr.filters&&(f.expr.filters.hidden=function(a){var b=a.offsetWidth,c=a.offsetHeight;return b===0&&c===0||!f.support.reliableHiddenOffsets&&(a.style&&a.style.display||f.css(a,"display"))==="none"},f.expr.filters.visible=function(a){return!f.expr.filters.hidden(a)}),f.each({margin:"",padding:"",border:"Width"},function(a,b){f.cssHooks[a+b]={expand:function(c){var d,e=typeof c=="string"?c.split(" "):[c],f={};for(d=0;d<4;d++)f[a+bx[d]+b]=e[d]||e[d-2]||e[0];return f}}});var bC=/%20/g,bD=/$-[$-]$/,bE=/$-r?$-n/g,bF=/#.*$/,bG=/^(.*?):[ $-t]*([^$-r$-n]*)$-r?$/mg,bH=/^(?:color|date|datetime|datetime-local|email|hidden|month|number|password|range|search|tel|text|time|url|week)$/i,bI=/^(?:about|app|app$--storage|.+$--extension|file|res|widget):$/,bJ=/^(?:GET|HEAD)$/,bK=/^$-/$-//,bL=/$-?/,bM=/<script$-b[^<]*(?:(?!<$-/script>)<[^<]*)*<$-/script>/gi,bN=/^(?:select|textarea)/i,bO=/$-s+/,bP=/([?&])_=[^&]*/,bQ=/^([$-w$-+$-.$--]+:)(?:$-/$-/([^$-/?#:]*)(?::($-d+))?)?/,bR=f.fn.load,bS={},bT={},bU,bV,bW=["*/"]+["*"];try{bU=e.href}catch(bX){bU=c.createElement("a"),bU.href="",bU=bU.href}bV=bQ.exec(bU.toLowerCase())||[],f.fn.extend({load:function(a,c,d){if(typeof a!="string"&&bR)return bR.apply(this,arguments);if(!this.length)return this;var e=a.indexOf(" ");if(e>=0){var g=a.slice(e,a.length);a=a.slice(0,e)}var h="GET";c&&(f.isFunction(c)?(d=c,c=b):typeof c=="object"&&(c=f.param(c,f.ajaxSettings.traditional),h="POST"));var i=this;f.ajax({url:a,type:h,dataType:"html",data:c,complete:function(a,b,c){c=a.responseText,a.isResolved()&&(a.done(function(a){c=a}),i.html(g?f("<div>").append(c.replace(bM,"")).find(g):c)),d&&i.each(d,[c,b,a])}});return this},serialize:function(){return f.param(this.serializeArray())},serializeArray:function(){return this.map(function(){return this.elements?f.makeArray(this.elements):this}).filter(function(){return this.name&&!this.disabled&&(this.checked||bN.test(this.nodeName)||bH.test(this.type))}).map(function(a,b){var c=f(this).val();return c==null?null:f.isArray(c)?f.map(c,function(a,c){return{name:b.name,value:a.replace(bE,"$-r$-n")}}):{name:b.name,value:c.replace(bE,"$-r$-n")}}).get()}}),f.each("ajaxStart ajaxStop ajaxComplete ajaxError ajaxSuccess ajaxSend".split(" "),function(a,b){f.fn[b]=function(a){return this.on(b,a)}}),f.each(["get","post"],function(a,c){f[c]=function(a,d,e,g){f.isFunction(d)&&(g=g||e,e=d,d=b);return f.ajax({type:c,url:a,data:d,success:e,dataType:g})}}),f.extend({getScript:function(a,c){return f.get(a,b,c,"script")},getJSON:function(a,b,c){return f.get(a,b,c,"json")},ajaxSetup:function(a,b){b?b$(a,f.ajaxSettings):(b=a,a=f.ajaxSettings),b$(a,b);return a},ajaxSettings:{url:bU,isLocal:bI.test(bV[1]),global:!0,type:"GET",contentType:"application/x-www-form-urlencoded; charset=UTF-8",processData:!0,async:!0,accepts:{xml:"application/xml, text/xml",html:"text/html",text:"text/plain",json:"application/json, text/javascript","*":bW},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText"},converters:{"* text":a.String,"text html":!0,"text json":f.parseJSON,"text xml":f.parseXML},flatOptions:{context:!0,url:!0}},ajaxPrefilter:bY(bS),ajaxTransport:bY(bT),ajax:function(a,c){function w(a,c,l,m){if(s!==2){s=2,q&&clearTimeout(q),p=b,n=m||"",v.readyState=a>0?4:0;var o,r,u,w=c,x=l?ca(d,v,l):b,y,z;if(a>=200&&a<300||a===304){if(d.ifModified){if(y=v.getResponseHeader("Last-Modified"))f.lastModified[k]=y;if(z=v.getResponseHeader("Etag"))f.etag[k]=z}if(a===304)w="notmodified",o=!0;else try{r=cb(d,x),w="success",o=!0}catch(A){w="parsererror",u=A}}else{u=w;if(!w||a)w="error",a<0&&(a=0)}v.status=a,v.statusText=""+(c||w),o?h.resolveWith(e,[r,w,v]):h.rejectWith(e,[v,w,u]),v.statusCode(j),j=b,t&&g.trigger("ajax"+(o?"Success":"Error"),[v,d,o?r:u]),i.fireWith(e,[v,w]),t&&(g.trigger("ajaxComplete",[v,d]),--f.active||f.event.trigger("ajaxStop"))}}typeof a=="object"&&(c=a,a=b),c=c||{};var d=f.ajaxSetup({},c),e=d.context||d,g=e!==d&&(e.nodeType||e instanceof f)?f(e):f.event,h=f.Deferred(),i=f.Callbacks("once memory"),j=d.statusCode||{},k,l={},m={},n,o,p,q,r,s=0,t,u,v={readyState:0,setRequestHeader:function(a,b){if(!s){var c=a.toLowerCase();a=m[c]=m[c]||a,l[a]=b}return this},getAllResponseHeaders:function(){return s===2?n:null},getResponseHeader:function(a){var c;if(s===2){if(!o){o={};while(c=bG.exec(n))o[c[1].toLowerCase()]=c[2]}c=o[a.toLowerCase()]}return c===b?null:c},overrideMimeType:function(a){s||(d.mimeType=a);return this},abort:function(a){a=a||"abort",p&&p.abort(a),w(0,a);return this}};h.promise(v),v.success=v.done,v.error=v.fail,v.complete=i.add,v.statusCode=function(a){if(a){var b;if(s<2)for(b in a)j[b]=[j[b],a[b]];else b=a[v.status],v.then(b,b)}return this},d.url=((a||d.url)+"").replace(bF,"").replace(bK,bV[1]+"//"),d.dataTypes=f.trim(d.dataType||"*").toLowerCase().split(bO),d.crossDomain==null&&(r=bQ.exec(d.url.toLowerCase()),d.crossDomain=!(!r||r[1]==bV[1]&&r[2]==bV[2]&&(r[3]||(r[1]==="http:"?80:443))==(bV[3]||(bV[1]==="http:"?80:443)))),d.data&&d.processData&&typeof d.data!="string"&&(d.data=f.param(d.data,d.traditional)),bZ(bS,d,c,v);if(s===2)return!1;t=d.global,d.type=d.type.toUpperCase(),d.hasContent=!bJ.test(d.type),t&&f.active++===0&&f.event.trigger("ajaxStart");if(!d.hasContent){d.data&&(d.url+=(bL.test(d.url)?"&":"?")+d.data,delete d.data),k=d.url;if(d.cache===!1){var x=f.now(),y=d.url.replace(bP,"$1_="+x);d.url=y+(y===d.url?(bL.test(d.url)?"&":"?")+"_="+x:"")}}(d.data&&d.hasContent&&d.contentType!==!1||c.contentType)&&v.setRequestHeader("Content-Type",d.contentType),d.ifModified&&(k=k||d.url,f.lastModified[k]&&v.setRequestHeader("If-Modified-Since",f.lastModified[k]),f.etag[k]&&v.setRequestHeader("If-None-Match",f.etag[k])),v.setRequestHeader("Accept",d.dataTypes[0]&&d.accepts[d.dataTypes[0]]?d.accepts[d.dataTypes[0]]+(d.dataTypes[0]!=="*"?", "+bW+"; q=0.01":""):d.accepts["*"]);for(u in d.headers)v.setRequestHeader(u,d.headers[u]);if(d.beforeSend&&(d.beforeSend.call(e,v,d)===!1||s===2)){v.abort();return!1}for(u in{success:1,error:1,complete:1})v[u](d[u]);p=bZ(bT,d,c,v);if(!p)w(-1,"No Transport");else{v.readyState=1,t&&g.trigger("ajaxSend",[v,d]),d.async&&d.timeout>0&&(q=setTimeout(function(){v.abort("timeout")},d.timeout));try{s=1,p.send(l,w)}catch(z){if(s<2)w(-1,z);else throw z}}return v},param:function(a,c){var d=[],e=function(a,b){b=f.isFunction(b)?b():b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};c===b&&(c=f.ajaxSettings.traditional);if(f.isArray(a)||a.jquery&&!f.isPlainObject(a))f.each(a,function(){e(this.name,this.value)});else for(var g in a)b_(g,a[g],c,e);return d.join("&").replace(bC,"+")}}),f.extend({active:0,lastModified:{},etag:{}});var cc=f.now(),cd=/($-=)$-?(&|$)|$-?$-?/i;f.ajaxSetup({jsonp:"callback",jsonpCallback:function(){return f.expando+"_"+cc++}}),f.ajaxPrefilter("json jsonp",function(b,c,d){var e=typeof b.data=="string"&&/^application$-/x$--www$--form$--urlencoded/.test(b.contentType);if(b.dataTypes[0]==="jsonp"||b.jsonp!==!1&&(cd.test(b.url)||e&&cd.test(b.data))){var g,h=b.jsonpCallback=f.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,i=a[h],j=b.url,k=b.data,l="$1"+h+"$2";b.jsonp!==!1&&(j=j.replace(cd,l),b.url===j&&(e&&(k=k.replace(cd,l)),b.data===k&&(j+=(/$-?/.test(j)?"&":"?")+b.jsonp+"="+h))),b.url=j,b.data=k,a[h]=function(a){g=[a]},d.always(function(){a[h]=i,g&&f.isFunction(i)&&a[h](g[0])}),b.converters["script json"]=function(){g||f.error(h+" was not called");return g[0]},b.dataTypes[0]="json";return"script"}}),f.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/javascript|ecmascript/},converters:{"text script":function(a){f.globalEval(a);return a}}}),f.ajaxPrefilter("script",function(a){a.cache===b&&(a.cache=!1),a.crossDomain&&(a.type="GET",a.global=!1)}),f.ajaxTransport("script",function(a){if(a.crossDomain){var d,e=c.head||c.getElementsByTagName("head")[0]||c.documentElement;return{send:function(f,g){d=c.createElement("script"),d.async="async",a.scriptCharset&&(d.charset=a.scriptCharset),d.src=a.url,d.onload=d.onreadystatechange=function(a,c){if(c||!d.readyState||/loaded|complete/.test(d.readyState))d.onload=d.onreadystatechange=null,e&&d.parentNode&&e.removeChild(d),d=b,c||g(200,"success")},e.insertBefore(d,e.firstChild)},abort:function(){d&&d.onload(0,1)}}}});var ce=a.ActiveXObject?function(){for(var a in cg)cg[a](0,1)}:!1,cf=0,cg;f.ajaxSettings.xhr=a.ActiveXObject?function(){return!this.isLocal&&ch()||ci()}:ch,function(a){f.extend(f.support,{ajax:!!a,cors:!!a&&"withCredentials"in a})}(f.ajaxSettings.xhr()),f.support.ajax&&f.ajaxTransport(function(c){if(!c.crossDomain||f.support.cors){var d;return{send:function(e,g){var h=c.xhr(),i,j;c.username?h.open(c.type,c.url,c.async,c.username,c.password):h.open(c.type,c.url,c.async);if(c.xhrFields)for(j in c.xhrFields)h[j]=c.xhrFields[j];c.mimeType&&h.overrideMimeType&&h.overrideMimeType(c.mimeType),!c.crossDomain&&!e["X-Requested-With"]&&(e["X-Requested-With"]="XMLHttpRequest");try{for(j in e)h.setRequestHeader(j,e[j])}catch(k){}h.send(c.hasContent&&c.data||null),d=function(a,e){var j,k,l,m,n;try{if(d&&(e||h.readyState===4)){d=b,i&&(h.onreadystatechange=f.noop,ce&&delete cg[i]);if(e)h.readyState!==4&&h.abort();else{j=h.status,l=h.getAllResponseHeaders(),m={},n=h.responseXML,n&&n.documentElement&&(m.xml=n);try{m.text=h.responseText}catch(a){}try{k=h.statusText}catch(o){k=""}!j&&c.isLocal&&!c.crossDomain?j=m.text?200:404:j===1223&&(j=204)}}}catch(p){e||g(-1,p)}m&&g(j,k,m,l)},!c.async||h.readyState===4?d():(i=++cf,ce&&(cg||(cg={},f(a).unload(ce)),cg[i]=d),h.onreadystatechange=d)},abort:function(){d&&d(0,1)}}}});var cj={},ck,cl,cm=/^(?:toggle|show|hide)$/,cn=/^([+$--]=)?([$-d+.$--]+)([a-z%]*)$/i,co,cp=[["height","marginTop","marginBottom","paddingTop","paddingBottom"],["width","marginLeft","marginRight","paddingLeft","paddingRight"],["opacity"]],cq;f.fn.extend({show:function(a,b,c){var d,e;if(a||a===0)return this.animate(ct("show",3),a,b,c);for(var g=0,h=this.length;g<h;g++)d=this[g],d.style&&(e=d.style.display,!f._data(d,"olddisplay")&&e==="none"&&(e=d.style.display=""),(e===""&&f.css(d,"display")==="none"||!f.contains(d.ownerDocument.documentElement,d))&&f._data(d,"olddisplay",cu(d.nodeName)));for(g=0;g<h;g++){d=this[g];if(d.style){e=d.style.display;if(e===""||e==="none")d.style.display=f._data(d,"olddisplay")||""}}return this},hide:function(a,b,c){if(a||a===0)return this.animate(ct("hide",3),a,b,c);var d,e,g=0,h=this.length;for(;g<h;g++)d=this[g],d.style&&(e=f.css(d,"display"),e!=="none"&&!f._data(d,"olddisplay")&&f._data(d,"olddisplay",e));for(g=0;g<h;g++)this[g].style&&(this[g].style.display="none");return this},_toggle:f.fn.toggle,toggle:function(a,b,c){var d=typeof a=="boolean";f.isFunction(a)&&f.isFunction(b)?this._toggle.apply(this,arguments):a==null||d?this.each(function(){var b=d?a:f(this).is(":hidden");f(this)[b?"show":"hide"]()}):this.animate(ct("toggle",3),a,b,c);return this},fadeTo:function(a,b,c,d){return this.filter(":hidden").css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){function g(){e.queue===!1&&f._mark(this);var b=f.extend({},e),c=this.nodeType===1,d=c&&f(this).is(":hidden"),g,h,i,j,k,l,m,n,o,p,q;b.animatedProperties={};for(i in a){g=f.camelCase(i),i!==g&&(a[g]=a[i],delete a[i]);if((k=f.cssHooks[g])&&"expand"in k){l=k.expand(a[g]),delete a[g];for(i in l)i in a||(a[i]=l[i])}}for(g in a){h=a[g],f.isArray(h)?(b.animatedProperties[g]=h[1],h=a[g]=h[0]):b.animatedProperties[g]=b.specialEasing&&b.specialEasing[g]||b.easing||"swing";if(h==="hide"&&d||h==="show"&&!d)return b.complete.call(this);c&&(g==="height"||g==="width")&&(b.overflow=[this.style.overflow,this.style.overflowX,this.style.overflowY],f.css(this,"display")==="inline"&&f.css(this,"float")==="none"&&(!f.support.inlineBlockNeedsLayout||cu(this.nodeName)==="inline"?this.style.display="inline-block":this.style.zoom=1))}b.overflow!=null&&(this.style.overflow="hidden");for(i in a)j=new f.fx(this,b,i),h=a[i],cm.test(h)?(q=f._data(this,"toggle"+i)||(h==="toggle"?d?"show":"hide":0),q?(f._data(this,"toggle"+i,q==="show"?"hide":"show"),j[q]()):j[h]()):(m=cn.exec(h),n=j.cur(),m?(o=parseFloat(m[2]),p=m[3]||(f.cssNumber[i]?"":"px"),p!=="px"&&(f.style(this,i,(o||1)+p),n=(o||1)/j.cur()*n,f.style(this,i,n+p)),m[1]&&(o=(m[1]==="-="?-1:1)*o+n),j.custom(n,o,p)):j.custom(n,h,""));return!0}var e=f.speed(b,c,d);if(f.isEmptyObject(a))return this.each(e.complete,[!1]);a=f.extend({},a);return e.queue===!1?this.each(g):this.queue(e.queue,g)},stop:function(a,c,d){typeof a!="string"&&(d=c,c=a,a=b),c&&a!==!1&&this.queue(a||"fx",[]);return this.each(function(){function h(a,b,c){var e=b[c];f.removeData(a,c,!0),e.stop(d)}var b,c=!1,e=f.timers,g=f._data(this);d||f._unmark(!0,this);if(a==null)for(b in g)g[b]&&g[b].stop&&b.indexOf(".run")===b.length-4&&h(this,g,b);else g[b=a+".run"]&&g[b].stop&&h(this,g,b);for(b=e.length;b--;)e[b].elem===this&&(a==null||e[b].queue===a)&&(d?e[b](!0):e[b].saveState(),c=!0,e.splice(b,1));(!d||!c)&&f.dequeue(this,a)})}}),f.each({slideDown:ct("show",1),slideUp:ct("hide",1),slideToggle:ct("toggle",1),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){f.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),f.extend({speed:function(a,b,c){var d=a&&typeof a=="object"?f.extend({},a):{complete:c||!c&&b||f.isFunction(a)&&a,duration:a,easing:c&&b||b&&!f.isFunction(b)&&b};d.duration=f.fx.off?0:typeof d.duration=="number"?d.duration:d.duration in f.fx.speeds?f.fx.speeds[d.duration]:f.fx.speeds._default;if(d.queue==null||d.queue===!0)d.queue="fx";d.old=d.complete,d.complete=function(a){f.isFunction(d.old)&&d.old.call(this),d.queue?f.dequeue(this,d.queue):a!==!1&&f._unmark(this)};return d},easing:{linear:function(a){return a},swing:function(a){return-Math.cos(a*Math.PI)/2+.5}},timers:[],fx:function(a,b,c){this.options=b,this.elem=a,this.prop=c,b.orig=b.orig||{}}}),f.fx.prototype={update:function(){this.options.step&&this.options.step.call(this.elem,this.now,this),(f.fx.step[this.prop]||f.fx.step._default)(this)},cur:function(){if(this.elem[this.prop]!=null&&(!this.elem.style||this.elem.style[this.prop]==null))return this.elem[this.prop];var a,b=f.css(this.elem,this.prop);return isNaN(a=parseFloat(b))?!b||b==="auto"?0:b:a},custom:function(a,c,d){function h(a){return e.step(a)}var e=this,g=f.fx;this.startTime=cq||cr(),this.end=c,this.now=this.start=a,this.pos=this.state=0,this.unit=d||this.unit||(f.cssNumber[this.prop]?"":"px"),h.queue=this.options.queue,h.elem=this.elem,h.saveState=function(){f._data(e.elem,"fxshow"+e.prop)===b&&(e.options.hide?f._data(e.elem,"fxshow"+e.prop,e.start):e.options.show&&f._data(e.elem,"fxshow"+e.prop,e.end))},h()&&f.timers.push(h)&&!co&&(co=setInterval(g.tick,g.interval))},show:function(){var a=f._data(this.elem,"fxshow"+this.prop);this.options.orig[this.prop]=a||f.style(this.elem,this.prop),this.options.show=!0,a!==b?this.custom(this.cur(),a):this.custom(this.prop==="width"||this.prop==="height"?1:0,this.cur()),f(this.elem).show()},hide:function(){this.options.orig[this.prop]=f._data(this.elem,"fxshow"+this.prop)||f.style(this.elem,this.prop),this.options.hide=!0,this.custom(this.cur(),0)},step:function(a){var b,c,d,e=cq||cr(),g=!0,h=this.elem,i=this.options;if(a||e>=i.duration+this.startTime){this.now=this.end,this.pos=this.state=1,this.update(),i.animatedProperties[this.prop]=!0;for(b in i.animatedProperties)i.animatedProperties[b]!==!0&&(g=!1);if(g){i.overflow!=null&&!f.support.shrinkWrapBlocks&&f.each(["","X","Y"],function(a,b){h.style["overflow"+b]=i.overflow[a]}),i.hide&&f(h).hide();if(i.hide||i.show)for(b in i.animatedProperties)f.style(h,b,i.orig[b]),f.removeData(h,"fxshow"+b,!0),f.removeData(h,"toggle"+b,!0);d=i.complete,d&&(i.complete=!1,d.call(h))}return!1}i.duration==Infinity?this.now=e:(c=e-this.startTime,this.state=c/i.duration,this.pos=f.easing[i.animatedProperties[this.prop]](this.state,c,0,1,i.duration),this.now=this.start+(this.end-this.start)*this.pos),this.update();return!0}},f.extend(f.fx,{tick:function(){var a,b=f.timers,c=0;for(;c<b.length;c++)a=b[c],!a()&&b[c]===a&&b.splice(c--,1);b.length||f.fx.stop()},interval:13,stop:function(){clearInterval(co),co=null},speeds:{slow:600,fast:200,_default:400},step:{opacity:function(a){f.style(a.elem,"opacity",a.now)},_default:function(a){a.elem.style&&a.elem.style[a.prop]!=null?a.elem.style[a.prop]=a.now+a.unit:a.elem[a.prop]=a.now}}}),f.each(cp.concat.apply([],cp),function(a,b){b.indexOf("margin")&&(f.fx.step[b]=function(a){f.style(a.elem,b,Math.max(0,a.now)+a.unit)})}),f.expr&&f.expr.filters&&(f.expr.filters.animated=function(a){return f.grep(f.timers,function(b){return a===b.elem}).length});var cv,cw=/^t(?:able|d|h)$/i,cx=/^(?:body|html)$/i;"getBoundingClientRect"in c.documentElement?cv=function(a,b,c,d){try{d=a.getBoundingClientRect()}catch(e){}if(!d||!f.contains(c,a))return d?{top:d.top,left:d.left}:{top:0,left:0};var g=b.body,h=cy(b),i=c.clientTop||g.clientTop||0,j=c.clientLeft||g.clientLeft||0,k=h.pageYOffset||f.support.boxModel&&c.scrollTop||g.scrollTop,l=h.pageXOffset||f.support.boxModel&&c.scrollLeft||g.scrollLeft,m=d.top+k-i,n=d.left+l-j;return{top:m,left:n}}:cv=function(a,b,c){var d,e=a.offsetParent,g=a,h=b.body,i=b.defaultView,j=i?i.getComputedStyle(a,null):a.currentStyle,k=a.offsetTop,l=a.offsetLeft;while((a=a.parentNode)&&a!==h&&a!==c){if(f.support.fixedPosition&&j.position==="fixed")break;d=i?i.getComputedStyle(a,null):a.currentStyle,k-=a.scrollTop,l-=a.scrollLeft,a===e&&(k+=a.offsetTop,l+=a.offsetLeft,f.support.doesNotAddBorder&&(!f.support.doesAddBorderForTableAndCells||!cw.test(a.nodeName))&&(k+=parseFloat(d.borderTopWidth)||0,l+=parseFloat(d.borderLeftWidth)||0),g=e,e=a.offsetParent),f.support.subtractsBorderForOverflowNotVisible&&d.overflow!=="visible"&&(k+=parseFloat(d.borderTopWidth)||0,l+=parseFloat(d.borderLeftWidth)||0),j=d}if(j.position==="relative"||j.position==="static")k+=h.offsetTop,l+=h.offsetLeft;f.support.fixedPosition&&j.position==="fixed"&&(k+=Math.max(c.scrollTop,h.scrollTop),l+=Math.max(c.scrollLeft,h.scrollLeft));return{top:k,left:l}},f.fn.offset=function(a){if(arguments.length)return a===b?this:this.each(function(b){f.offset.setOffset(this,a,b)});var c=this[0],d=c&&c.ownerDocument;if(!d)return null;if(c===d.body)return f.offset.bodyOffset(c);return cv(c,d,d.documentElement)},f.offset={bodyOffset:function(a){var b=a.offsetTop,c=a.offsetLeft;f.support.doesNotIncludeMarginInBodyOffset&&(b+=parseFloat(f.css(a,"marginTop"))||0,c+=parseFloat(f.css(a,"marginLeft"))||0);return{top:b,left:c}},setOffset:function(a,b,c){var d=f.css(a,"position");d==="static"&&(a.style.position="relative");var e=f(a),g=e.offset(),h=f.css(a,"top"),i=f.css(a,"left"),j=(d==="absolute"||d==="fixed")&&f.inArray("auto",[h,i])>-1,k={},l={},m,n;j?(l=e.position(),m=l.top,n=l.left):(m=parseFloat(h)||0,n=parseFloat(i)||0),f.isFunction(b)&&(b=b.call(a,c,g)),b.top!=null&&(k.top=b.top-g.top+m),b.left!=null&&(k.left=b.left-g.left+n),"using"in b?b.using.call(a,k):e.css(k)}},f.fn.extend({position:function(){if(!this[0])return null;var a=this[0],b=this.offsetParent(),c=this.offset(),d=cx.test(b[0].nodeName)?{top:0,left:0}:b.offset();c.top-=parseFloat(f.css(a,"marginTop"))||0,c.left-=parseFloat(f.css(a,"marginLeft"))||0,d.top+=parseFloat(f.css(b[0],"borderTopWidth"))||0,d.left+=parseFloat(f.css(b[0],"borderLeftWidth"))||0;return{top:c.top-d.top,left:c.left-d.left}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||c.body;while(a&&!cx.test(a.nodeName)&&f.css(a,"position")==="static")a=a.offsetParent;return a})}}),f.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(a,c){var d=/Y/.test(c);f.fn[a]=function(e){return f.access(this,function(a,e,g){var h=cy(a);if(g===b)return h?c in h?h[c]:f.support.boxModel&&h.document.documentElement[e]||h.document.body[e]:a[e];h?h.scrollTo(d?f(h).scrollLeft():g,d?g:f(h).scrollTop()):a[e]=g},a,e,arguments.length,null)}}),f.each({Height:"height",Width:"width"},function(a,c){var d="client"+a,e="scroll"+a,g="offset"+a;f.fn["inner"+a]=function(){var a=this[0];return a?a.style?parseFloat(f.css(a,c,"padding")):this[c]():null},f.fn["outer"+a]=function(a){var b=this[0];return b?b.style?parseFloat(f.css(b,c,a?"margin":"border")):this[c]():null},f.fn[c]=function(a){return f.access(this,function(a,c,h){var i,j,k,l;if(f.isWindow(a)){i=a.document,j=i.documentElement[d];return f.support.boxModel&&j||i.body&&i.body[d]||j}if(a.nodeType===9){i=a.documentElement;if(i[d]>=i[e])return i[d];return Math.max(a.body[e],i[e],a.body[g],i[g])}if(h===b){k=f.css(a,c),l=parseFloat(k);return f.isNumeric(l)?l:k}f(a).css(c,h)},c,a,arguments.length,null)}}),a.jQuery=a.$=f,typeof define=="function"&&define.amd&&define.amd.jQuery&&define("jquery",[],function(){return f})})(window);';//95k
	$nicEdit='
		/* NicEdit - Micro Inline WYSIWYG Copyright 2007-2008 Brian Kirchoff */ 
		var bkExtend=function(){var A=arguments;if(A.length==1){A=[this,A[0]]}for(var B in A[1]){A[0][B]=A[1][B]}return A[0]};function bkClass(){}bkClass.prototype.construct=function(){};bkClass.extend=function(C){var A=function(){if(arguments[0]!==bkClass){return this.construct.apply(this,arguments)}};var B=new this(bkClass);bkExtend(B,C);A.prototype=B;A.extend=this.extend;return A};var bkElement=bkClass.extend({construct:function(B,A){if(typeof (B)=="string"){B=(A||document).createElement(B)}B=&,BK(B);return B},appendTo:function(A){A.appendChild(this);return this},appendBefore:function(A){A.parentNode.insertBefore(this,A);return this},addEvent:function(B,A){bkLib.addEvent(this,B,A);return this},setContent:function(A){this.innerHTML=A;return this},pos:function(){var C=curtop=0;var B=obj=this;if(obj.offsetParent){do{C+=obj.offsetLeft;curtop+=obj.offsetTop}while(obj=obj.offsetParent)}var A=(!window.opera)?parseInt(this.getStyle("border-width")||this.style.border)||0:0;return[C+A,curtop+A+this.offsetHeight]},noSelect:function(){bkLib.noSelect(this);return this},parentTag:function(A){var B=this;do{if(B&&B.nodeName&&B.nodeName.toUpperCase()==A){return B}B=B.parentNode}while(B);return false},hasClass:function(A){return this.className.match(new RegExp("(&-&-s|^)nicEdit-"+A+"(&-&-s|&,)"))},addClass:function(A){if(!this.hasClass(A)){this.className+=" nicEdit-"+A}return this},removeClass:function(A){if(this.hasClass(A)){this.className=this.className.replace(new RegExp("(&-&-s|^)nicEdit-"+A+"(&-&-s|&,)")," ")}return this},setStyle:function(A){var B=this.style;for(var C in A){switch(C){case"float":B.cssFloat=B.styleFloat=A[C];break;case"opacity":B.opacity=A[C];B.filter="alpha(opacity="+Math.round(A[C]*100)+")";break;case"className":this.className=A[C];break;default:B[C]=A[C]}}return this},getStyle:function(A,C){var B=(!C)?document.defaultView:C;if(this.nodeType==1){return(B&&B.getComputedStyle)?B.getComputedStyle(this,null).getPropertyValue(A):this.currentStyle[bkLib.camelize(A)]}},remove:function(){this.parentNode.removeChild(this);return this},setAttributes:function(A){for(var B in A){this[B]=A[B]}return this}});var bkLib={isMSIE:(navigator.appVersion.indexOf("MSIE")!=-1),addEvent:function(C,B,A){(C.addEventListener)?C.addEventListener(B,A,false):C.attachEvent("on"+B,A)},toArray:function(C){var B=C.length,A=new Array(B);while(B--){A[B]=C[B]}return A},noSelect:function(B){if(B.setAttribute&&B.nodeName.toLowerCase()!="input"&&B.nodeName.toLowerCase()!="textarea"){B.setAttribute("unselectable","on")}for(var A=0;A<B.childNodes.length;A++){bkLib.noSelect(B.childNodes[A])}},camelize:function(A){return A.replace(/&--(.)/g,function(B,C){return C.toUpperCase()})},inArray:function(A,B){return(bkLib.search(A,B)!=null)},search:function(A,C){for(var B=0;B<A.length;B++){if(A[B]==C){return B}}return null},cancelEvent:function(A){A=A||window.event;if(A.preventDefault&&A.stopPropagation){A.preventDefault();A.stopPropagation()}return false},domLoad:[],domLoaded:function(){if(arguments.callee.done){return }arguments.callee.done=true;for(i=0;i<bkLib.domLoad.length;i++){bkLib.domLoad[i]()}},onDomLoaded:function(A){this.domLoad.push(A);if(document.addEventListener){document.addEventListener("DOMContentLoaded",bkLib.domLoaded,null)}else{if(bkLib.isMSIE){document.write("<style>.nicEdit-main p { margin: 0; }</style><script id=__ie_onload defer "+((location.protocol=="https:")?"src=&+javascript:void(0)&+":"src=//0")+"><&-/script>");&,BK("__ie_onload").onreadystatechange=function(){if(this.readyState=="complete"){bkLib.domLoaded()}}}}window.onload=bkLib.domLoaded}};function &,BK(A){if(typeof (A)=="string"){A=document.getElementById(A)}return(A&&!A.appendTo)?bkExtend(A,bkElement.prototype):A}var bkEvent={addEvent:function(A,B){if(B){this.eventList=this.eventList||{};this.eventList[A]=this.eventList[A]||[];this.eventList[A].push(B)}return this},fireEvent:function(){var A=bkLib.toArray(arguments),C=A.shift();if(this.eventList&&this.eventList[C]){for(var B=0;B<this.eventList[C].length;B++){this.eventList[C][B].apply(this,A)}}}};function __(A){return A}Function.prototype.closure=function(){var A=this,B=bkLib.toArray(arguments),C=B.shift();return function(){if(typeof (bkLib)!="undefined"){return A.apply(C,B.concat(bkLib.toArray(arguments)))}}};Function.prototype.closureListener=function(){var A=this,C=bkLib.toArray(arguments),B=C.shift();return function(E){E=E||window.event;if(E.target){var D=E.target}else{var D=E.srcElement}return A.apply(B,[E,D].concat(C))}};
		var nicEditorConfig = bkClass.extend({
			buttons : {
				&+bold&+ : {name : __(&+加粗&+), command : &+Bold&+, tags : [&+B&+,&+STRONG&+], css : {&+font-weight&+ : &+bold&+}, key : &+b&+},
				&+italic&+ : {name : __(&+倾斜&+), command : &+Italic&+, tags : [&+EM&+,&+I&+], css : {&+font-style&+ : &+italic&+}, key : &+i&+},
				&+underline&+ : {name : __(&+下划线&+), command : &+Underline&+, tags : [&+U&+], css : {&+text-decoration&+ : &+underline&+}, key : &+u&+},
				&+left&+ : {name : __(&+左对齐&+), command : &+justifyleft&+, noActive : true},
				&+center&+ : {name : __(&+居中&+), command : &+justifycenter&+, noActive : true},
				&+right&+ : {name : __(&+右对齐&+), command : &+justifyright&+, noActive : true},
				&+justify&+ : {name : __(&+两端对齐&+), command : &+justifyfull&+, noActive : true},
				&+ol&+ : {name : __(&+有序列表&+), command : &+insertorderedlist&+, tags : [&+OL&+]},
				&+ul&+ : 	{name : __(&+无序列表&+), command : &+insertunorderedlist&+, tags : [&+UL&+]},
				&+subscript&+ : {name : __(&+下标&+), command : &+subscript&+, tags : [&+SUB&+]},
				&+superscript&+ : {name : __(&+上标&+), command : &+superscript&+, tags : [&+SUP&+]},
				&+strikethrough&+ : {name : __(&+删除线&+), command : &+strikeThrough&+, css : {&+text-decoration&+ : &+line-through&+}},
				&+removeformat&+ : {name : __(&+清除格式&+), command : &+removeformat&+, noActive : true},
				&+indent&+ : {name : __(&+缩进&+), command : &+indent&+, noActive : true},
				&+outdent&+ : {name : __(&+退缩&+), command : &+outdent&+, noActive : true},
				&+hr&+ : {name : __(&+分割线&+), command : &+insertHorizontalRule&+, noActive : true}
			},
			iconsPath : &+./nicEditorIcons.gif&+,
			buttonList : [&+save&+,&+bold&+,&+italic&+,&+underline&+,&+left&+,&+center&+,&+right&+,&+justify&+,&+ol&+,&+ul&+,&+fontSize&+,&+fontFamily&+,&+fontFormat&+,&+indent&+,&+outdent&+,&+image&+,&+upload&+,&+link&+,&+unlink&+,&+forecolor&+,&+bgcolor&+],
			iconList : {"xhtml":1,"bgcolor":2,"forecolor":3,"bold":4,"center":5,"hr":6,"indent":7,"italic":8,"justify":9,"left":10,"ol":11,"outdent":12,"removeformat":13,"right":14,"save":25,"strikethrough":16,"subscript":17,"superscript":18,"ul":19,"underline":20,"image":21,"link":22,"unlink":23,"close":24,"arrow":26,"upload":27}
		});
		var nicEditors={nicPlugins:[],editors:[],registerPlugin:function(B,A){this.nicPlugins.push({p:B,o:A})},allTextAreas:function(C){var A=document.getElementsByTagName("textarea");for(var B=0;B<A.length;B++){nicEditors.editors.push(new nicEditor(C).panelInstance(A[B]))}return nicEditors.editors},findEditor:function(C){var B=nicEditors.editors;for(var A=0;A<B.length;A++){if(B[A].instanceById(C)){return B[A].instanceById(C)}}}};var nicEditor=bkClass.extend({construct:function(C){this.options=new nicEditorConfig();bkExtend(this.options,C);this.nicInstances=new Array();this.loadedPlugins=new Array();var A=nicEditors.nicPlugins;for(var B=0;B<A.length;B++){this.loadedPlugins.push(new A[B].p(this,A[B].o))}nicEditors.editors.push(this);bkLib.addEvent(document.body,"mousedown",this.selectCheck.closureListener(this))},panelInstance:function(B,C){B=this.checkReplace(&,BK(B));var A=new bkElement("DIV").setStyle({width:(parseInt(B.getStyle("width"))||B.clientWidth)+"px"}).appendBefore(B);this.setPanel(A);return this.addInstance(B,C)},checkReplace:function(B){var A=nicEditors.findEditor(B);if(A){A.removeInstance(B);A.removePanel()}return B},addInstance:function(B,C){B=this.checkReplace(&,BK(B));if(B.contentEditable||!!window.opera){var A=new nicEditorInstance(B,C,this)}else{var A=new nicEditorIFrameInstance(B,C,this)}this.nicInstances.push(A);return this},removeInstance:function(C){C=&,BK(C);var B=this.nicInstances;for(var A=0;A<B.length;A++){if(B[A].e==C){B[A].remove();this.nicInstances.splice(A,1)}}},removePanel:function(A){if(this.nicPanel){this.nicPanel.remove();this.nicPanel=null}},instanceById:function(C){C=&,BK(C);var B=this.nicInstances;for(var A=0;A<B.length;A++){if(B[A].e==C){return B[A]}}},setPanel:function(A){this.nicPanel=new nicEditorPanel(&,BK(A),this.options,this);this.fireEvent("panel",this.nicPanel);return this},nicCommand:function(B,A){if(this.selectedInstance){this.selectedInstance.nicCommand(B,A)}},getIcon:function(D,A){var C=this.options.iconList[D];var B=(A.iconFiles)?A.iconFiles[D]:"";return{backgroundImage:"url(&+"+((C)?this.options.iconsPath:B)+"&+)",backgroundPosition:((C)?((C-1)*-18):0)+"px 0px"}},selectCheck:function(C,A){var B=false;do{if(A.className&&A.className.indexOf("nicEdit")!=-1){return false}}while(A=A.parentNode);this.fireEvent("blur",this.selectedInstance,A);this.lastSelectedInstance=this.selectedInstance;this.selectedInstance=null;return false}});nicEditor=nicEditor.extend(bkEvent);
		var nicEditorInstance=bkClass.extend({isSelected:false,construct:function(G,D,C){this.ne=C;this.elm=this.e=G;this.options=D||{};newX=parseInt(G.getStyle("width"))||G.clientWidth;newY=parseInt(G.getStyle("height"))||G.clientHeight;this.initialHeight=newY-8;var H=(G.nodeName.toLowerCase()=="textarea");if(H||this.options.hasPanel){var B=(bkLib.isMSIE&&!((typeof document.body.style.maxHeight!="undefined")&&document.compatMode=="CSS1Compat"));var E={width:newX+"px",border:"1px solid #ccc",borderTop:0,overflowY:"auto",overflowX:"hidden"};E[(B)?"height":"maxHeight"]=(this.ne.options.maxHeight)?this.ne.options.maxHeight+"px":null;this.editorContain=new bkElement("DIV").setStyle(E).appendBefore(G);var A=new bkElement("DIV").setStyle({width:(newX-8)+"px",margin:"4px",minHeight:newY+"px"}).addClass("main").appendTo(this.editorContain);G.setStyle({display:"none"});A.innerHTML=G.innerHTML;if(H){A.setContent(G.value);this.copyElm=G;var F=G.parentTag("FORM");if(F){bkLib.addEvent(F,"submit",this.saveContent.closure(this))}}A.setStyle((B)?{height:newY+"px"}:{overflow:"hidden"});this.elm=A}this.ne.addEvent("blur",this.blur.closure(this));this.init();this.blur()},init:function(){this.elm.setAttribute("contentEditable","true");if(this.getContent()==""){this.setContent("<br />")}this.instanceDoc=document.defaultView;this.elm.addEvent("mousedown",this.selected.closureListener(this)).addEvent("keypress",this.keyDown.closureListener(this)).addEvent("focus",this.selected.closure(this)).addEvent("blur",this.blur.closure(this)).addEvent("keyup",this.selected.closure(this));this.ne.fireEvent("add",this)},remove:function(){this.saveContent();if(this.copyElm||this.options.hasPanel){this.editorContain.remove();this.e.setStyle({display:"block"});this.ne.removePanel()}this.disable();this.ne.fireEvent("remove",this)},disable:function(){this.elm.setAttribute("contentEditable","false")},getSel:function(){return(window.getSelection)?window.getSelection():document.selection},getRng:function(){var A=this.getSel();if(!A||A.rangeCount===0){return }return(A.rangeCount>0)?A.getRangeAt(0):A.createRange()},selRng:function(A,B){if(window.getSelection){B.removeAllRanges();B.addRange(A)}else{A.select()}},selElm:function(){var C=this.getRng();if(!C){return }if(C.startContainer){var D=C.startContainer;if(C.cloneContents().childNodes.length==1){for(var B=0;B<D.childNodes.length;B++){var A=D.childNodes[B].ownerDocument.createRange();A.selectNode(D.childNodes[B]);if(C.compareBoundaryPoints(Range.START_TO_START,A)!=1&&C.compareBoundaryPoints(Range.END_TO_END,A)!=-1){return &,BK(D.childNodes[B])}}}return &,BK(D)}else{return &,BK((this.getSel().type=="Control")?C.item(0):C.parentElement())}},saveRng:function(){this.savedRange=this.getRng();this.savedSel=this.getSel()},restoreRng:function(){if(this.savedRange){this.selRng(this.savedRange,this.savedSel)}},keyDown:function(B,A){if(B.ctrlKey){this.ne.fireEvent("key",this,B)}},selected:function(C,A){if(!A&&!(A=this.selElm)){A=this.selElm()}if(!C.ctrlKey){var B=this.ne.selectedInstance;if(B!=this){if(B){this.ne.fireEvent("blur",B,A)}this.ne.selectedInstance=this;this.ne.fireEvent("focus",B,A)}this.ne.fireEvent("selected",B,A);this.isFocused=true;this.elm.addClass("selected")}return false},blur:function(){this.isFocused=false;this.elm.removeClass("selected")},saveContent:function(){if(this.copyElm||this.options.hasPanel){this.ne.fireEvent("save",this);(this.copyElm)?this.copyElm.value=this.getContent():this.e.innerHTML=this.getContent()}},getElm:function(){return this.elm},getContent:function(){this.content=this.getElm().innerHTML;this.ne.fireEvent("get",this);return this.content},setContent:function(A){this.content=A;this.ne.fireEvent("set",this);this.elm.innerHTML=this.content},nicCommand:function(B,A){document.execCommand(B,false,A)}});
		var nicEditorIFrameInstance=nicEditorInstance.extend({savedStyles:[],init:function(){var B=this.elm.innerHTML.replace(/^&-s+|&-s+&,/g,"");this.elm.innerHTML="";(!B)?B="<br />":B;this.initialContent=B;this.elmFrame=new bkElement("iframe").setAttributes({src:"javascript:;",frameBorder:0,allowTransparency:"true",scrolling:"no"}).setStyle({height:"100px",width:"100%"}).addClass("frame").appendTo(this.elm);if(this.copyElm){this.elmFrame.setStyle({width:(this.elm.offsetWidth-4)+"px"})}var A=["font-size","font-family","font-weight","color"];for(itm in A){this.savedStyles[bkLib.camelize(itm)]=this.elm.getStyle(itm)}setTimeout(this.initFrame.closure(this),50)},disable:function(){this.elm.innerHTML=this.getContent()},initFrame:function(){var B=&,BK(this.elmFrame.contentWindow.document);B.designMode="on";B.open();var A=this.ne.options.externalCSS;B.write("<html><head>"+((A)?&+<link href="&++A+&+" rel="stylesheet" type="text/css" />&+:"")+&+</head><body id="nicEditContent" style="margin: 0 !important; background-color: transparent !important;">&++this.initialContent+"</body></html>");B.close();this.frameDoc=B;this.frameWin=&,BK(this.elmFrame.contentWindow);this.frameContent=&,BK(this.frameWin.document.body).setStyle(this.savedStyles);this.instanceDoc=this.frameWin.document.defaultView;this.heightUpdate();this.frameDoc.addEvent("mousedown",this.selected.closureListener(this)).addEvent("keyup",this.heightUpdate.closureListener(this)).addEvent("keydown",this.keyDown.closureListener(this)).addEvent("keyup",this.selected.closure(this));this.ne.fireEvent("add",this)},getElm:function(){return this.frameContent},setContent:function(A){this.content=A;this.ne.fireEvent("set",this);this.frameContent.innerHTML=this.content;this.heightUpdate()},getSel:function(){return(this.frameWin)?this.frameWin.getSelection():this.frameDoc.selection},heightUpdate:function(){this.elmFrame.style.height=Math.max(this.frameContent.offsetHeight,this.initialHeight)+"px"},nicCommand:function(B,A){this.frameDoc.execCommand(B,false,A);setTimeout(this.heightUpdate.closure(this),100)}});
		var nicEditorPanel=bkClass.extend({construct:function(E,B,A){this.elm=E;this.options=B;this.ne=A;this.panelButtons=new Array();this.buttonList=bkExtend([],this.ne.options.buttonList);this.panelContain=new bkElement("DIV").setStyle({overflow:"hidden",width:"100%",border:"1px solid #cccccc",backgroundColor:"#efefef"}).addClass("panelContain");this.panelElm=new bkElement("DIV").setStyle({margin:"2px",marginTop:"0px",zoom:1,overflow:"hidden"}).addClass("panel").appendTo(this.panelContain);this.panelContain.appendTo(E);var C=this.ne.options;var D=C.buttons;for(button in D){this.addButton(button,C,true)}this.reorder();E.noSelect()},addButton:function(buttonName,options,noOrder){var button=options.buttons[buttonName];var type=(button.type)?eval("(typeof("+button.type+&+) == "undefined") ? null : &++button.type+";"):nicEditorButton;var hasButton=bkLib.inArray(this.buttonList,buttonName);if(type&&(hasButton||this.ne.options.fullPanel)){this.panelButtons.push(new type(this.panelElm,buttonName,options,this.ne));if(!hasButton){this.buttonList.push(buttonName)}}},findButton:function(B){for(var A=0;A<this.panelButtons.length;A++){if(this.panelButtons[A].name==B){return this.panelButtons[A]}}},reorder:function(){var C=this.buttonList;for(var B=0;B<C.length;B++){var A=this.findButton(C[B]);if(A){this.panelElm.appendChild(A.margin)}}},remove:function(){this.elm.remove()}});
		var nicEditorButton=bkClass.extend({construct:function(D,A,C,B){this.options=C.buttons[A];this.name=A;this.ne=B;this.elm=D;this.margin=new bkElement("DIV").setStyle({"float":"left",marginTop:"2px"}).appendTo(D);this.contain=new bkElement("DIV").setStyle({width:"20px",height:"20px"}).addClass("buttonContain").appendTo(this.margin);this.border=new bkElement("DIV").setStyle({backgroundColor:"#efefef",border:"1px solid #efefef"}).appendTo(this.contain);this.button=new bkElement("DIV").setStyle({width:"18px",height:"18px",overflow:"hidden",zoom:1,cursor:"pointer"}).addClass("button").setStyle(this.ne.getIcon(A,C)).appendTo(this.border);this.button.addEvent("mouseover",this.hoverOn.closure(this)).addEvent("mouseout",this.hoverOff.closure(this)).addEvent("mousedown",this.mouseClick.closure(this)).noSelect();if(!window.opera){this.button.onmousedown=this.button.onclick=bkLib.cancelEvent}B.addEvent("selected",this.enable.closure(this)).addEvent("blur",this.disable.closure(this)).addEvent("key",this.key.closure(this));this.disable();this.init()},init:function(){},hide:function(){this.contain.setStyle({display:"none"})},updateState:function(){if(this.isDisabled){this.setBg()}else{if(this.isHover){this.setBg("hover")}else{if(this.isActive){this.setBg("active")}else{this.setBg()}}}},setBg:function(A){switch(A){case"hover":var B={border:"1px solid #666",backgroundColor:"#ddd"};break;case"active":var B={border:"1px solid #666",backgroundColor:"#ccc"};break;default:var B={border:"1px solid #efefef",backgroundColor:"#efefef"}}this.border.setStyle(B).addClass("button-"+A)},checkNodes:function(A){var B=A;do{if(this.options.tags&&bkLib.inArray(this.options.tags,B.nodeName)){this.activate();return true}}while(B=B.parentNode&&B.className!="nicEdit");B=&,BK(A);while(B.nodeType==3){B=&,BK(B.parentNode)}if(this.options.css){for(itm in this.options.css){if(B.getStyle(itm,this.ne.selectedInstance.instanceDoc)==this.options.css[itm]){this.activate();return true}}}this.deactivate();return false},activate:function(){if(!this.isDisabled){this.isActive=true;this.updateState();this.ne.fireEvent("buttonActivate",this)}},deactivate:function(){this.isActive=false;this.updateState();if(!this.isDisabled){this.ne.fireEvent("buttonDeactivate",this)}},enable:function(A,B){this.isDisabled=false;this.contain.setStyle({opacity:1}).addClass("buttonEnabled");this.updateState();this.checkNodes(B)},disable:function(A,B){this.isDisabled=true;this.contain.setStyle({opacity:0.6}).removeClass("buttonEnabled");this.updateState()},toggleActive:function(){(this.isActive)?this.deactivate():this.activate()},hoverOn:function(){if(!this.isDisabled){this.isHover=true;this.updateState();this.ne.fireEvent("buttonOver",this)}},hoverOff:function(){this.isHover=false;this.updateState();this.ne.fireEvent("buttonOut",this)},mouseClick:function(){if(this.options.command){this.ne.nicCommand(this.options.command,this.options.commandArgs);if(!this.options.noActive){this.toggleActive()}}this.ne.fireEvent("buttonClick",this)},key:function(A,B){if(this.options.key&&B.ctrlKey&&String.fromCharCode(B.keyCode||B.charCode).toLowerCase()==this.options.key){this.mouseClick();if(B.preventDefault){B.preventDefault()}}}});
		var nicPlugin=bkClass.extend({construct:function(B,A){this.options=A;this.ne=B;this.ne.addEvent("panel",this.loadPanel.closure(this));this.init()},loadPanel:function(C){var B=this.options.buttons;for(var A in B){C.addButton(A,this.options)}C.reorder()},init:function(){}});
		var nicPaneOptions = { };
		var nicEditorPane=bkClass.extend({construct:function(D,C,B,A){this.ne=C;this.elm=D;this.pos=D.pos();this.contain=new bkElement("div").setStyle({zIndex:"99999",overflow:"hidden",position:"absolute",left:this.pos[0]+"px",top:this.pos[1]+"px"});this.pane=new bkElement("div").setStyle({fontSize:"12px",border:"1px solid #ccc",overflow:"hidden",padding:"4px",textAlign:"left",backgroundColor:"#ffffc9"}).addClass("pane").setStyle(B).appendTo(this.contain);if(A&&!A.options.noClose){this.close=new bkElement("div").setStyle({"float":"right",height:"16px",width:"16px",cursor:"pointer"}).setStyle(this.ne.getIcon("close",nicPaneOptions)).addEvent("mousedown",A.removePane.closure(this)).appendTo(this.pane)}this.contain.noSelect().appendTo(document.body);this.position();this.init()},init:function(){},position:function(){if(this.ne.nicPanel){var B=this.ne.nicPanel.elm;var A=B.pos();var C=A[0]+parseInt(B.getStyle("width"))-(parseInt(this.pane.getStyle("width"))+8);if(C<this.pos[0]){this.contain.setStyle({left:C+"px"})}}},toggle:function(){this.isVisible=!this.isVisible;this.contain.setStyle({display:((this.isVisible)?"block":"none")})},remove:function(){if(this.contain){this.contain.remove();this.contain=null}},append:function(A){A.appendTo(this.pane)},setContent:function(A){this.pane.setContent(A)}});
		var nicEditorAdvancedButton=nicEditorButton.extend({init:function(){this.ne.addEvent("selected",this.removePane.closure(this)).addEvent("blur",this.removePane.closure(this))},mouseClick:function(){if(!this.isDisabled){if(this.pane&&this.pane.pane){this.removePane()}else{this.pane=new nicEditorPane(this.contain,this.ne,{width:(this.width||"270px"),backgroundColor:"#fff"},this);this.addPane();this.ne.selectedInstance.saveRng()}}},addForm:function(C,G){this.form=new bkElement("form").addEvent("submit",this.submit.closureListener(this));this.pane.append(this.form);this.inputs={};for(itm in C){var D=C[itm];var F="";if(G){F=G.getAttribute(itm)}if(!F){F=D.value||""}var A=C[itm].type;if(A=="title"){new bkElement("div").setContent(D.txt).setStyle({fontSize:"14px",fontWeight:"bold",padding:"0px",margin:"2px 0"}).appendTo(this.form)}else{var B=new bkElement("div").setStyle({overflow:"hidden",clear:"both"}).appendTo(this.form);if(D.txt){new bkElement("label").setAttributes({"for":itm}).setContent(D.txt).setStyle({margin:"2px 4px",fontSize:"13px",width:"50px",lineHeight:"20px",textAlign:"right","float":"left"}).appendTo(B)}switch(A){case"text":this.inputs[itm]=new bkElement("input").setAttributes({id:itm,value:F,type:"text"}).setStyle({margin:"2px 0",fontSize:"13px","float":"left",height:"20px",border:"1px solid #ccc",overflow:"hidden"}).setStyle(D.style).appendTo(B);break;case"select":this.inputs[itm]=new bkElement("select").setAttributes({id:itm}).setStyle({border:"1px solid #ccc","float":"left",margin:"2px 0"}).appendTo(B);for(opt in D.options){var E=new bkElement("option").setAttributes({value:opt,selected:(opt==F)?"selected":""}).setContent(D.options[opt]).appendTo(this.inputs[itm])}break;case"content":this.inputs[itm]=new bkElement("textarea").setAttributes({id:itm}).setStyle({border:"1px solid #ccc","float":"left"}).setStyle(D.style).appendTo(B);this.inputs[itm].value=F}}}new bkElement("input").setAttributes({type:"submit"}).setStyle({backgroundColor:"#efefef",border:"1px solid #ccc",margin:"3px 0","float":"left",clear:"both"}).appendTo(this.form);this.form.onsubmit=bkLib.cancelEvent},submit:function(){},findElm:function(B,A,E){var D=this.ne.selectedInstance.getElm().getElementsByTagName(B);for(var C=0;C<D.length;C++){if(D[C].getAttribute(A)==E){return &,BK(D[C])}}},removePane:function(){if(this.pane){this.pane.remove();this.pane=null;this.ne.selectedInstance.restoreRng()}}});
		var nicButtonTips=bkClass.extend({construct:function(A){this.ne=A;A.addEvent("buttonOver",this.show.closure(this)).addEvent("buttonOut",this.hide.closure(this))},show:function(A){this.timer=setTimeout(this.create.closure(this,A),400)},create:function(A){this.timer=null;if(!this.pane){this.pane=new nicEditorPane(A.button,this.ne,{fontSize:"12px",marginTop:"5px"});this.pane.setContent(A.options.name)}},hide:function(A){if(this.timer){clearTimeout(this.timer)}if(this.pane){this.pane=this.pane.remove()}}});nicEditors.registerPlugin(nicButtonTips);
		var nicSelectOptions = {
			buttons : {
				&+fontSize&+ : {name : __(&+大小&+), type : &+nicEditorFontSizeSelect&+, command : &+fontsize&+},
				&+fontFamily&+ : {name : __(&+字形&+), type : &+nicEditorFontFamilySelect&+, command : &+fontname&+},
				&+fontFormat&+ : {name : __(&+格式&+), type : &+nicEditorFontFormatSelect&+, command : &+formatBlock&+}
			}
		};
		var nicEditorSelect=bkClass.extend({construct:function(D,A,C,B){this.options=C.buttons[A];this.elm=D;this.ne=B;this.name=A;this.selOptions=new Array();this.margin=new bkElement("div").setStyle({"float":"left",margin:"2px 1px 0 1px"}).appendTo(this.elm);this.contain=new bkElement("div").setStyle({width:"50px",height:"20px",cursor:"pointer",overflow:"hidden"}).addClass("selectContain").addEvent("click",this.toggle.closure(this)).appendTo(this.margin);this.items=new bkElement("div").setStyle({overflow:"hidden",zoom:1,border:"1px solid #ccc",paddingLeft:"3px",backgroundColor:"#fff"}).appendTo(this.contain);this.control=new bkElement("div").setStyle({overflow:"hidden","float":"right",height:"18px",width:"16px"}).addClass("selectControl").setStyle(this.ne.getIcon("arrow",C)).appendTo(this.items);this.txt=new bkElement("div").setStyle({overflow:"hidden","float":"left",width:"26px",height:"14px",marginTop:"1px",fontFamily:"sans-serif",textAlign:"center",fontSize:"12px"}).addClass("selectTxt").appendTo(this.items);if(!window.opera){this.contain.onmousedown=this.control.onmousedown=this.txt.onmousedown=bkLib.cancelEvent}this.margin.noSelect();this.ne.addEvent("selected",this.enable.closure(this)).addEvent("blur",this.disable.closure(this));this.disable();this.init()},disable:function(){this.isDisabled=true;this.close();this.contain.setStyle({opacity:0.6})},enable:function(A){this.isDisabled=false;this.close();this.contain.setStyle({opacity:1})},setDisplay:function(A){this.txt.setContent(A)},toggle:function(){if(!this.isDisabled){(this.pane)?this.close():this.open()}},open:function(){this.pane=new nicEditorPane(this.items,this.ne,{width:"88px",padding:"0px",borderTop:0,borderLeft:"1px solid #ccc",borderRight:"1px solid #ccc",borderBottom:"0px",backgroundColor:"#fff"});for(var C=0;C<this.selOptions.length;C++){var B=this.selOptions[C];var A=new bkElement("div").setStyle({overflow:"hidden",borderBottom:"1px solid #ccc",width:"88px",textAlign:"left",overflow:"hidden",cursor:"pointer"});var D=new bkElement("div").setStyle({padding:"0px 4px"}).setContent(B[1]).appendTo(A).noSelect();D.addEvent("click",this.update.closure(this,B[0])).addEvent("mouseover",this.over.closure(this,D)).addEvent("mouseout",this.out.closure(this,D)).setAttributes("id",B[0]);this.pane.append(A);if(!window.opera){D.onmousedown=bkLib.cancelEvent}}},close:function(){if(this.pane){this.pane=this.pane.remove()}},over:function(A){A.setStyle({backgroundColor:"#ccc"})},out:function(A){A.setStyle({backgroundColor:"#fff"})},add:function(B,A){this.selOptions.push(new Array(B,A))},update:function(A){this.ne.nicCommand(this.options.command,A);this.close()}});var nicEditorFontSizeSelect=nicEditorSelect.extend({sel:{1:"1&nbsp;(8pt)",2:"2&nbsp;(10pt)",3:"3&nbsp;(12pt)",4:"4&nbsp;(14pt)",5:"5&nbsp;(18pt)",6:"6&nbsp;(24pt)"},init:function(){this.setDisplay("大小");for(itm in this.sel){this.add(itm,&+<font size="&++itm+&+">&++this.sel[itm]+"</font>")}}});var nicEditorFontFamilySelect=nicEditorSelect.extend({sel:{arial:"Arial","comic sans ms":"Comic Sans","courier new":"Courier New",georgia:"Georgia",helvetica:"Helvetica",impact:"Impact","times new roman":"Times","trebuchet ms":"Trebuchet",verdana:"Verdana",Consolas:&+Consolas&+,SimSun:&+宋体&+,SimHei:&+黑体&+,YouYuan:&+幼圆&+,LiSu:&+隶书&+,&+KaiTi&+:&+楷体&+,&+Microsoft YaHei&+:&+微软雅黑&+},init:function(){this.setDisplay("字型");for(itm in this.sel){this.add(itm,&+<font face="&++itm+&+">&++this.sel[itm]+"</font>")}}});var nicEditorFontFormatSelect=nicEditorSelect.extend({sel:{p:"段落P",pre:"Pre",h6:"标题6",h5:"标题5",h4:"标题4",h3:"标题3",h2:"标题2",h1:"标题1"},init:function(){this.setDisplay("格式");for(itm in this.sel){var A=itm.toUpperCase();this.add("<"+A+">","<"+itm+&+ style="padding: 0px; margin: 0px;">&++this.sel[itm]+"</"+A+">")}}});nicEditors.registerPlugin(nicPlugin,nicSelectOptions);
		var nicLinkOptions = {
			buttons : {
				&+link&+ : {name : &+添加链接&+, type : &+nicLinkButton&+, tags : [&+A&+]},
				&+unlink&+ : {name : &+移除链接&+,  command : &+unlink&+, noActive : true}
			}
		};
		var nicLinkButton=nicEditorAdvancedButton.extend({addPane:function(){this.ln=this.ne.selectedInstance.selElm().parentTag("A");this.addForm({"":{type:"title",txt:"编辑链接 "},href:{type:"text",txt:"URL",value:"http://",style:{width:"150px"}},title:{type:"text",txt:"提示"},target:{type:"select",txt:"打开在",options:{"":"当前窗口",_blank:"新建窗口"},style:{width:"100px"}}},this.ln)},submit:function(C){var A=this.inputs.href.value;if(A=="http://"||A==""){alert("必须输入URL才能创建链接");return false}this.removePane();if(!this.ln){var B="javascript:nicTemp();";this.ne.nicCommand("createlink",B);this.ln=this.findElm("A","href",B)}if(this.ln){this.ln.setAttributes({href:this.inputs.href.value,title:this.inputs.title.value,target:this.inputs.target.options[this.inputs.target.selectedIndex].value})}}});nicEditors.registerPlugin(nicPlugin,nicLinkOptions);
		var nicColorOptions = {
			buttons : {
				&+forecolor&+ : {name : __(&+字体色&+), type : &+nicEditorColorButton&+, noClose : true},
				&+bgcolor&+ : {name : __(&+背景色&+), type : &+nicEditorBgColorButton&+, noClose : true}
			}
		};
		var nicEditorColorButton=nicEditorAdvancedButton.extend({addPane:function(){var D={0:"00",1:"33",2:"66",3:"99",4:"CC",5:"FF"};var H=new bkElement("DIV").setStyle({width:"270px"});for(var A in D){for(var F in D){for(var E in D){var I="#"+D[A]+D[E]+D[F];var C=new bkElement("DIV").setStyle({cursor:"pointer",height:"15px","float":"left"}).appendTo(H);var G=new bkElement("DIV").setStyle({border:"2px solid "+I}).appendTo(C);var B=new bkElement("DIV").setStyle({backgroundColor:I,overflow:"hidden",width:"11px",height:"11px"}).addEvent("click",this.colorSelect.closure(this,I)).addEvent("mouseover",this.on.closure(this,G)).addEvent("mouseout",this.off.closure(this,G,I)).appendTo(G);if(!window.opera){C.onmousedown=B.onmousedown=bkLib.cancelEvent}}}}this.pane.append(H.noSelect())},colorSelect:function(A){this.ne.nicCommand("foreColor",A);this.removePane()},on:function(A){A.setStyle({border:"2px solid #000"})},off:function(A,B){A.setStyle({border:"2px solid "+B})}});var nicEditorBgColorButton=nicEditorColorButton.extend({colorSelect:function(A){this.ne.nicCommand("hiliteColor",A);this.removePane()}});nicEditors.registerPlugin(nicPlugin,nicColorOptions);
		var nicImageOptions = {
			buttons : {
				&+image&+ : {name : &+添加图片&+, type : &+nicImageButton&+, tags : [&+IMG&+]}
			}
		};
		var nicImageButton=nicEditorAdvancedButton.extend({addPane:function(){this.im=this.ne.selectedInstance.selElm().parentTag("IMG");this.addForm({"":{type:"title",txt:"编辑图片超链"},src:{type:"text",txt:"URL",value:"http://",style:{width:"150px"}},alt:{type:"text",txt:"Alt Text",style:{width:"100px"}},align:{type:"select",txt:"Align",options:{none:"Default",left:"Left",right:"Right"}}},this.im)},submit:function(B){var C=this.inputs.src.value;if(C==""||C=="http://"){alert("必须输入图片URL才能插入");return false}this.removePane();if(!this.im){var A="javascript:nicImTemp();";this.ne.nicCommand("insertImage",A);this.im=this.findElm("IMG","src",A)}if(this.im){this.im.setAttributes({src:this.inputs.src.value,alt:this.inputs.alt.value,align:this.inputs.align.value})}}});nicEditors.registerPlugin(nicPlugin,nicImageOptions);
		var nicUploadOptions = {
			buttons : {
				&+upload&+ : {name : &+上传图片&+, type : &+nicUploadButton&+}
			}
		};
		var nicUploadButton=nicEditorAdvancedButton.extend({nicURI:"./up_img.php",errorText:"上传图片失败",addPane:function(){if(typeof window.FormData==="undefined"){return this.onError("此浏览器不支持图像上载，请改用Chrome、Firefox或Safari。")}this.im=this.ne.selectedInstance.selElm().parentTag("IMG");var A=new bkElement("div").setStyle({padding:"10px"}).appendTo(this.pane.pane);new bkElement("div").setStyle({fontSize:"14px",fontWeight:"bold",paddingBottom:"5px"}).setContent("插入图片").appendTo(A);this.fileInput=new bkElement("input").setAttributes({type:"file"}).appendTo(A);this.progress=new bkElement("progress").setStyle({width:"100%",display:"none"}).setAttributes("max",100).appendTo(A);this.fileInput.onchange=this.uploadFile.closure(this)},onError:function(A){this.removePane();alert(A||"上传图片失败")},uploadFile:function(){var B=this.fileInput.files[0];if(!B||!B.type.match(/image.*/)){this.onError("只能上传图片文件");return }this.fileInput.setStyle({display:"none"});this.setProgress(0);var A=new FormData();A.append("image",B);var C=new XMLHttpRequest();C.open("POST",this.ne.options.uploadURI||this.nicURI);C.onload=function(){try{var D=JSON.parse(C.responseText).data}catch(E){return this.onError()}if(D.error){return this.onError(D.error)}this.onUploaded(D)}.closure(this);C.onerror=this.onError.closure(this);C.upload.onprogress=function(D){this.setProgress(D.loaded/D.total)}.closure(this);C.setRequestHeader("Authorization","Client-ID c37fc05199a05b7");C.send(A)},setProgress:function(A){this.progress.setStyle({display:"block"});if(A<0.98){this.progress.value=A}else{this.progress.removeAttribute("value")}},onUploaded:function(B){this.removePane();var D=B.link;if(!this.im){this.ne.selectedInstance.restoreRng();var C="javascript:nicImTemp();";this.ne.nicCommand("insertImage",D);this.im=this.findElm("IMG","src",D)}var A=parseInt(this.ne.selectedInstance.elm.getStyle("width"));if(this.im){this.im.setAttributes({src:D,width:(A&&B.width)?Math.min(A,B.width):""})}}});nicEditors.registerPlugin(nicPlugin,nicUploadOptions);
		var nicCodeOptions = {
			buttons : {
				&+xhtml&+ : {name : &+源码&+, type : &+nicCodeButton&+}
			}
		};
		var nicCodeButton=nicEditorAdvancedButton.extend({width:"350px",addPane:function(){this.addForm({"":{type:"title",txt:"编辑源码"},code:{type:"content",value:this.ne.selectedInstance.getContent(),style:{width:"340px",height:"200px"}}})},submit:function(B){var A=this.inputs.code.value;this.ne.selectedInstance.setContent(A);this.removePane()}});nicEditors.registerPlugin(nicPlugin,nicCodeOptions);';
	$types=[
		'js'	=> ['mime'=>'text/JavaScript'],
		'css'	=> ['mime'=>'text/css'],
		'jpg'	=> ['mime'=>'image/jpeg', 'base64'=>'data:image/jpeg;base64,'],
		'gif'	=> ['mime'=>'image/gif', 'base64'=>'data:image/gif;base64,'],
		'png'	=> ['mime'=>'image/png', 'base64'=>'data:image/png;base64,'],
		'ico'	=> ['mime'=>'image/png', 'base64'=>'data:image/png;base64,'],
		'woff'	=> ['mime'=>'application/font-woff', 'base64'=>'data:application/x-font-woff2;charset=utf-8;base64,'],
		];
	if($filename=='jquery.min.js'){
		header('Content-Type: '.$types['js']['mime']);
		echo str_replace('$-', '\\', str_replace('$+', "'",$jquery));
	}elseif($filename=='nicEdit.js'){
		header('Content-Type: '.$types['js']['mime']);
		echo str_replace('&,', '$', str_replace('&-', '\\', str_replace('&+', "'",$nicEdit)));
	}else{
		$dot_pos = strrpos($filename,'.');
		$type = substr($filename,$dot_pos+1);
		if($type && isset($list[$filename]) && isset($types[$type])) {
			header('Content-Type: '.$types[$type]['mime']);
			if(substr($types[$type]['mime'],0,3)=='app'){
				header("Content-Disposition: attachment; filename=".$filename);
			}
			if(substr($types[$type]['mime'],0,4)=='text'){
				echo $list[$filename];
			}else{
				echo base64_decode($list[$filename]);
			}
		}else{
			header('HTTP/1.1 404 Not Found');
		}
	}
	exit;
}

?>