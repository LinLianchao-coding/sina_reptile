<?php

//配置文件

//主数据库配置
$db_config['master']['host'] = '127.0.0.1';
$db_config['master']['port'] = '3306';
$db_config['master']['dbname'] = 'sina';
$db_config['master']['username'] = 'root';
$db_config['master']['password'] = 'linyujian';
//从数据库配置
$db_config['slave']['host'] = '127.0.0.1';
$db_config['slave']['port'] = '3306';
$db_config['slave']['dbname'] = 'sina';
$db_config['slave']['username'] = 'root';
$db_config['slave']['password'] = 'linyujian';

// 微博账号配置
$weibo_account = array(
  1 => 'Cookie: SUHB=06Z1FN1iVN2PhY ; _T_WM=f331a401a54d003e71dab51c4b11886d ; SUB=_2A25767dIDeRxGeVI71MQ9SzFzDmIHXVZF9kArDV6PUJbrdAKLWvCkW1LHes_h5L8qRsJgoB9kOSnbGCYBVC22Q.. ; gsid_CTandWM=4upQb13111Duq6CnXoGwofhegfJ ',  
);

