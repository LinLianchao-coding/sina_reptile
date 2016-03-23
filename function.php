<?php
/**
 * CURL请求 
 * @param String $url 请求地址 
 * @param Array $data 请求数据 
 * @param String $cookieFile cookie文件地址
 * @return string 请求地址返回内容
 */
function curlRequest($url, $data = '', $cookieFile = '')
{
    $ch = curl_init();
    $header[] = 'Accept:  text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8 ';
    $header[] = 'Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3 ';
    $header[] = 'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:45.0) Gecko/20100101 Firefox/45.0 ';
    $header[] = 'Host: weibo.cn ';
    $header[] = 'Connection: Keep-Alive ';
    $header[] = 'Cookie: SUHB=******* ; _T_WM=******* ; SUB=***** ; gsid_CTandWM=********** ';
    $option = array(
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_RETURNTRANSFER => 1
    );
    if ($cookieFile) {
        $option[CURLOPT_COOKIEJAR] = $cookieFile;
        $option[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($data) {
        $option[CURLOPT_POST] = 1;
        $option[CURLOPT_POSTFIELDS] = $data;
    }
    curl_setopt_array($ch, $option);
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 302 || !$response) {
        echo $url." 302\n";
        curlRequest($url);
    } else {
        $real_response = $response;
    }
    curl_close($ch);
    return $real_response;
}

/**
 * 获取内容预处理函数，获取所有a标签
 * @param string $return 爬取到的页面内容   
 * @return array 所有a标签数组
 */
function pre_match($return)
{
    $pattern = '/<a(.*?)a>/i';
    $weibo_pre_match = array();
    preg_match_all($pattern, $return, $weibo_pre_match);
    //$file_path = './weibo_content.txt';
    //file_put_contents($file_path, $weibo_pre_match[0]);
    //有私信时要过滤前面两个a标签
    if (strpos($weibo_pre_match[0][1], '[X]')) {
        array_splice($weibo_pre_match[0], 0, 2);
    }
    return $weibo_pre_match[0];
}

/**
 * 根据用户id获取该用户主页信息，并存储主页信息到txt文件中
 * @param string $uid 用户id
 * @return array 用户主页信息，预处理成a标签数组形式返回
 */
function get_home_info($uid){
    $url = 'http://weibo.cn/'.$uid.'?vt=4';
    $uid_md5 = md5($uid);
    $dir = $uid_md5{0};
    if (strpos($uid, '/')) {
        $uid = explode('/', $uid);
        $file_path = './home/'.$dir.'/'.$uid[1].'.txt';
    } else {
        $file_path = './home/'.$dir.'/'.$uid.'.txt';
    }
    if (file_exists($file_path)) {
        $file_content = @file_get_contents($file_path);
        if ($file_content) {
            return $file_content;
        } else {
            die('文件不可读'.$file_path);
        }
    } else {
        $return = curlRequest($url);
        if ($return) {
            if (@file_put_contents($file_path, $return)) {
                return $return;
            } else {
                die('文件目录不可写'.$file_path);
            }
        } else {
            die('无法登陆首页'.$file_path);
        }
    }
}

/**
 * 根据用户主页信息获取该用户某一页的粉丝列表，并存储粉丝列表到txt文件中
 * @param string $uid 用户id
 * @param string $home_info 首页内容
 * @param int $page 要获取粉丝信息的页数
 * @return string 粉丝列表
 */
function get_fans_list($uid, $home_info, $page){
    $uid_md5 = md5($uid);
    $dir = $uid_md5{0};
    if (strpos($uid, '/')) {
        $uid = explode('/', $uid);
        $file_path = './fans/'.$dir.'/'.$uid[1].'_'.$page.'.txt';
    } else {
        $file_path = './fans/'.$dir.'/'.$uid.'_'.$page.'.txt';
    }
    if (file_exists($file_path)) {
        $file_content = @file_get_contents($file_path);
        if ($file_content) {
            return $file_content;
        } else {
            die('文件不可读'.$file_path);
        }
    } else {
        $pre_matches = pre_match($home_info);
        preg_match('/"(.*)".?/i', $pre_matches[12], $list);
        $follow_url = 'http://weibo.cn'.$list[1].'&page='.$page;
        unset($list);
        $fans_list = curlRequest($follow_url);
        if ($fans_list) {
            if (@file_put_contents($file_path, $fans_list)) {
                return $fans_list;
            } else {
                die('文件目录不可写'.$file_path);
            }
        } else {
            die('无法登陆首页'.$file_path);
        }
    }
    
}

/**
 * 根据用户粉丝列表页进行处理，返回某一页粉丝信息的数组
 * @param string $fans_list 粉丝列表页
 * @return array 某一页粉丝信息的数组
 */
function get_fans_info($fans_list)
{
    $space = 3; //用户的粉丝列表正则过滤后每一个用户拥有3个a标签，间隔一致
    $page_num = 10; //手机版粉丝列表一页有10个用户
    $fans_list = pre_match($fans_list);
    array_splice($fans_list, 0, 9);
    $user_info = array();
    for ($i = 0; $i < $page_num; $i++) {
        preg_match('/cn\/(.*)\?.?/i', $fans_list[$i * $space], $weibo_id);
        if (empty($weibo_id[1])) {  //用户列表中再无匹配到用户
            break;
        }
        preg_match('/src="(.*?)"/i', $fans_list[$i * $space], $photocat_add);
        preg_match('/>(.*)</i', $fans_list[$i * $space + 1], $weibo_name);
        $home_info = get_home_info($weibo_id[1]);
        //有微博私信时要获取地区和性别信息需要特殊处理
        if (strpos($home_info, '[X]')) {
            preg_match('/\[X\](.*)/i', $home_info, $temp_personal_content);
            preg_match('/\[(.*?)\]/i', $temp_personal_content[1], $weibo_num);
            preg_match('/&nbsp;(.*?)&nbsp;/i', $temp_personal_content[1], $other_info);
        } else {
            preg_match('/\[(.*?)\]/i', $home_info, $weibo_num);
            preg_match('/&nbsp;(.*?)&nbsp;/i', $home_info, $other_info);
        }
        $other_info[1] = trim($other_info[1]);
        $other_info = explode('/', $other_info[1]);
        $home_info_pre_matches = pre_match($home_info);
        unset($home_info);
        //下面几个的位数不固定，需要加if判断
        preg_match('/\[(.*)\]/i', $home_info_pre_matches[11], $follow_num);
        if (empty($follow_num)) {
            preg_match('/\[(.*)\]/i', $home_info_pre_matches[12], $follow_num);
            if (empty($follow_num)) {
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[13], $follow_num);
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[14], $fans_num);
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[15], $group_num);
            } else {
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[12], $follow_num);
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[13], $fans_num);
                preg_match('/\[(.*)\]/i', $home_info_pre_matches[14], $group_num);
            }
        } else {
            preg_match('/\[(.*)\]/i', $home_info_pre_matches[11], $follow_num);
            preg_match('/\[(.*)\]/i', $home_info_pre_matches[12], $fans_num);
            preg_match('/\[(.*)\]/i', $home_info_pre_matches[13], $group_num);
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
    }
    return $user_info;
}
