<?php

date_default_timezone_set("Asia/Shanghai");
header("Content-Type: text/html; charset=utf-8");
include 'sql.php';
include 'function.php';
$sql = new sql();
$url = 'http://weibo.cn/?vt=4';//zzzzzz
$return = curlRequest($url);
if ($return) {
    $file_path = './weibo_content.txt';
    if (@file_put_contents($file_path, $return)) {  //处理自身微博
        // 数据预处理
        $pre_matches = pre_match($return);
        unset($return);

        //此处进入我的关注
        $has_user = 1; //标记是否还有用户可获取
        $space = 6; //关注列表正则过滤后每一个用户拥有6个a标签，间隔一致
        $page = 1; //页数
        $page_num = 10; //手机版关注列表一页有10个用户
        $weibo_id_array = array(); //关注列表中每个用户的id
        while ($has_user == 1) {
            $follow = '';
            preg_match('/"(.*)".?/i', $pre_matches[13], $follow);
            $follow_url = 'http://weibo.cn'.$follow[1].'&page='.$page;
            unset($follow);
            $follow_list = curlRequest($follow_url);
            $follow_list = pre_match($follow_list);
            array_splice($follow_list, 0, 11);
            $user_info = array();
            for ($i = 0; $i < $page_num; $i++) {
                preg_match('/cn\/(.*)\?.?/i', $follow_list[$i * $space], $weibo_id);
                if (empty($weibo_id[1])) {  //用户列表中再无匹配到用户
                    $has_user = 0;
                    break;
                }
                preg_match('/src="(.*?)"/i', $follow_list[$i * $space], $photocat_add);
                preg_match('/"(.*?)"/i', $follow_list[$i * $space], $weibo_personal_addr);
                preg_match('/>(.*)</i', $follow_list[$i * $space + 1], $weibo_name);
                $weibo_personal_content = curlRequest($weibo_personal_addr[1]);
                //有微博私信时要获取地区和性别信息需要特殊处理
                if (strpos($weibo_personal_content, '[X]')){
                    preg_match('/\[X\](.*)/i', $weibo_personal_content, $temp_personal_content);
                    preg_match('/\[(.*?)\]/i', $temp_personal_content[1], $weibo_num);
                    preg_match('/&nbsp;(.*?)&nbsp;/i', $temp_personal_content[1], $other_info);
                }else {
                    preg_match('/\[(.*?)\]/i', $weibo_personal_content, $weibo_num);
                    preg_match('/&nbsp;(.*?)&nbsp;/i', $weibo_personal_content, $other_info);
                }
                $other_info[1] = trim($other_info[1]);
                $other_info = explode('/', $other_info[1]);
                $weibo_personal_pre_matches = pre_match($weibo_personal_content);
                unset($weibo_personal_content);
                //下面几个的位数不固定，需要加if判断
                preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[11], $follow_num);
                if (empty($follow_num)) {
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[12], $follow_num);
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[13], $fans_num);
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[14], $group_num);
                } else {
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[11], $follow_num);
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[12], $fans_num);
                    preg_match('/\[(.*)\]/i', $weibo_personal_pre_matches[13], $group_num);
                }
                $user_info[$i]['weibo_id'] = $weibo_id[1];
                $user_info[$i]['weibo_name'] = $weibo_name[1];
                $user_info[$i]['weibo_num'] = $weibo_num[1];
                $user_info[$i]['follow_num'] = $follow_num[1];
                $user_info[$i]['fans_num'] = $fans_num[1];
                $user_info[$i]['group_num'] = $group_num[1];
                $user_info[$i]['area'] = $other_info[1];
                if ($other_info[0] == '男') {
                    $user_info[$i]['sex'] = '1';
                } else if ($other_info[0] == '女') {
                    $user_info[$i]['sex'] = '2';
                } else {
                    $user_info[$i]['sex'] = '3';
                }
                $user_info[$i]['photocat_add'] = $photocat_add[1];
                $weibo_id_array[] = $user_info[$i]['weibo_id'];
                try {
                    $table_name = 'weibo_user_';
                    $md5 = md5($user_info[$i]['weibo_id']);
                    $table_name .= $md5{0};
                    $return = $sql->insert($table_name, $user_info[$i], TRUE);
                } catch (Exception $ex) {
                    continue;
                }
            }
            ++$page;
        }
        if ($weibo_id_array) {
            $page = 1; //所有用户均从粉丝列表第一页开始获取
            $ignore = array(); //已无更多粉丝可获取的用户数组
            while (1) {
                foreach ($weibo_id_array as $weibo_id_key => $weibo_id_val) {
                    if(in_array($weibo_id_val, $ignore)) continue;
                    $home_info = get_home_info($weibo_id_val);
                    $fans_list = get_fans_list($weibo_id_val, $home_info, $page);
                    $fans_info = get_fans_info($fans_list);
                    if(empty($fans_info)){
                        $ignore[] = $weibo_id_val;
                    }else {
                        foreach ($fans_info as $fans_info_key => $fans_info_val) {
                            try {
                                $table_name = 'weibo_user_';
                                $md5 = md5($fans_info_val['weibo_id']);
                                $table_name .= $md5{0};
                                $return = $sql->insert($table_name, $fans_info_val, TRUE);
                            } catch (Exception $ex) {
                                continue;
                            }
                        }
                    }
                }
                echo '已获取第'.$page."页\n";
                ++$page;
            }
        }
    }else {
        die('文件目录不可写'.$file_path);
    }
} else {
    die('无法登陆首页'.$file_path);
}
    