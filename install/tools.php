<?php
header("Content-type: text/html; charset=utf-8");
ini_set("display_errors","On");
error_reporting(E_ALL ^ E_NOTICE);
session_start();
define('Sitestarroot',preg_replace("#[\\\\\/]install#", '', dirname(__FILE__)));
/*>您的系统不支持php<div style="display:none">
 *
 */

/*- class-begin -*/
class mdl_serverinfo{

    var $allow_change_db = false;
    var $maxLevel = 6;
    
    function run(){
        $return=array();

        $totalScore = 0;
        $allow_install = true;
        foreach(get_class_methods($this) as $func){
            if(substr($func,0,5)=='test_'){
                $score = 0;
                $result = $this->$func($score);
                if($result['items']){
                    $group[$result['group']]['type'] = $result['type'];
                    $group[$result['group']]['items'] = array_merge($group[$result['group']['items']]?$group[$result['group']['items']]:array(),$result['items']);
                    if($allow_install && isset($result['allow_install'])){
                        $allow_install = $result['allow_install'];
                    }
                    if($result['key']){
                        $return[$result['key']] = &$group[$result['group']]['items'];
                    }
                }
                $totalScore += $score;
            }
        }

        $score = floor($totalScore/100)+1;
        $rank = min($score,$this->maxLevel+1);
        $level = array('E','D','C','B','A','S');

        $return['data']=$group;
        $return['score']=$totalScore;
        $return['level']=$level[$rank-1];
        $return['rank'] = $rank;
        $return['allow_install'] = $allow_install;

        return $return;
    }

    function test_basic($score){
        $items['操作系统']=PHP_OS;
        $items['服务器软件']=$_SERVER["SERVER_SOFTWARE"];

        $runMode = null;

        $runMode = php_sapi_name();
        switch($runMode){
        case 'cgi-fcgi':
            $score+=50;
            break;
        }

        $safemodeStr = '<span style="color:red">(安全模式)</span>';
        if($runMode){
            if(ini_get('safe_mode')){
                $runMode.='&nbsp;';
            }
            $items['php运行方式']=$runMode;
        }elseif(ini_get('safe_mode')){
            $items['php运行方式']=$safemodeStr;
        }

        return array('group'=>'服务器基本信息','key'=>'basic','items'=>$items);
    }

    function test_php(&$score){
        $items['php版本']=PHP_VERSION;
        if(is_callable('file_put_contents')){
            $score += 40;
        }
        if(is_callable('str_ireplace')){
            $score += 20;
        }
        if(is_callable('ftp_chmod')){
            $score += 10;
        }
        if(is_callable('http_build_query')){
            $score += 20;
        }

        $items['程序最多允许使用内存量&nbsp;memory_limit']=ini_get("memory_limit");
        $items['POST最大字节数&nbsp;post_max_size']=ini_get("post_max_size");
        $items['允许最大上传文件&nbsp;upload_max_filesize']=ini_get("upload_max_filesize");
        $items['程序最长运行时间&nbsp;max_execution_time']=ini_get("max_execution_time");
        $disableFunc = get_cfg_var("disable_functions");
        $items['被禁用的函数&nbsp;disable_functions']=$disableFunc?$disableFunc:'无';
        return array('group'=>'php基本信息','items'=>$items);
    }

    function test_server_req(&$score){


        $rst = version_compare(PHP_VERSION,'5.0','>=');
        $items['PHP5.0以上'] = array(
            'value'=>PHP_VERSION,
            'result'=>$rst,
        );
        if(!$rst){
            $allow_install = false;
        }

   
		    if(ini_get('allow_url_fopen')){
            $rst = ini_get('allow_url_fopen');
            if(!$rst){
                $allow_url_fopen = false;
            }
            $items['allow_url_fopen'] = array(
                'value'=>$rst?'可用':'不可用',
                'result'=>$rst,
            );
        }
       
        if(!$rst){
            $allow_install = false;
        }

        $rst = function_exists('zip_open');
        $items['zip组件'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
                
        
        if(!$rst){
            $allow_install = false;
        }

        $rst = function_exists('mysql_connect') && function_exists('mysql_get_server_info');
        $items['MySQL函数库可用'] = array(
            'value'=>$rst?mysql_get_client_info():'未安装',
            'result'=>$rst,
        );
        $rst = function_exists('mysqli_set_charset');
        $items['MySQLi函数库可用'] = array(
            'value'=>$rst?mysqli_set_charset():'未安装',
            'result'=>$rst,
        );
             
        if(!$rst){
            $allow_install = false;
        }else{
            $rst = false;
            if(defined('DB_HOST')){
                if(defined('DB_PASSWORD')){
                    $rs = mysql_connect(DB_HOST,DB_USER,DB_PASSWORD);
                }elseif(defined('DB_USER')){
                    $rs = mysql_connect(DB_HOST,DB_USER);
                }else{
                    $rs = mysql_connect(DB_HOST);
                }
                $db_ver = mysql_get_server_info($rs);
            }elseif($db_ver = mysql_get_server_info()){
                define('DB_HOST','');
            }else{
                $sock = get_cfg_var('mysql.default_socket');
                if(PHP_OS!='WINNT' && file_exists($sock) && is_writable($sock)){
                    define('DB_HOST',$sock);
                }else{
                    $host = ini_get('mysql.default_host');
                    $port = ini_get('mysql.default_port');
                    if(!$host)$host = '127.0.0.1';
                    if(!$port)$port = 3306;
                    define('DB_HOST',$host.':'.$port);
                }
            }
            if(!$db_ver){
                if(substr(DB_HOST,0,1)=='/'){
                    $fp = @fsockopen("unix://".DB_HOST);
                }else{
                    if($p = strrpos(DB_HOST,':')){
                        $port = substr(DB_HOST,$p+1);
                        $host = substr(DB_HOST,0,$p);
                    }else{
                        $port = 3306;
                        $host = DB_HOST;
                    }
                    $fp = @fsockopen("tcp://".$host, $port, $errno, $errstr,2);
                }
                if (!$fp){
                    $db_ver = '无法连接';
                } else {
                    fwrite($fp, "\n");
                    $db_ver = fread($fp, 20);
                    fclose($fp);
                    if(preg_match('/([2-8]\.[0-9\.]+)/',$db_ver,$match)){
                        $db_ver = $match[1];
                        $rst = version_compare($db_ver,'3.2.23','>=');
                    }else{
                        $db_ver = '无法识别';
                    }
                }
            }else{
                $rst = version_compare($db_ver,'3.2.23','>=');
            }

            $this->db_ver = $db_ver;

            $mysql_key = '数据库Mysql 3.2.23以上&nbsp;<i style="color:#060">'.DB_HOST.'</i>';
            if($this->allow_change_db){
                $mysql_key.='<form method="get" action="" style="margin:0;padding:0"><table><tr><td><label for="db_host">MySQL主机</label></td><td>&nbsp;</td></tr><tr><td><input id="db_host" value="'.DB_HOST.'" name="db_host" style="width:100px;" type="text" /></td><td><input type="submit" value="连接"></td></tr></table></form>';
            }
            $items[$mysql_key] = array(
                'value'=>$db_ver,
                'result'=>$rst,
            );
            if(!$rst){
                $allow_install = false;
            }

            $rst = (defined('OPTIMIZER_VERSION') || (function_exists('extension_loaded') && extension_loaded('Zend Optimizer')));
            if($rst){
                if(defined('OPTIMIZER_VERSION')){
                    $rst = version_compare(OPTIMIZER_VERSION,'2.5.7','>=');
                    $value = OPTIMIZER_VERSION;
                }else{
                    $value = '通过';
                }
            }else{
                $value = '未安装';
            }
           
            if(!$rst){
                $allow_install = false;
            }
        }

        if(ini_get('safe_mode')){
            $rst = is_callable('ftp_connect');
            if(!$rst){
                $allow_install = false;
            }
            $items['当安全模式开启时,ftp函数可用'] = array(
                'value'=>$rst?'可用':'不可用',
                'result'=>$rst,
            );
        }

        $rst = preg_match('/[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/',gethostbyname('www.example.com'));
        $items['DNS配置完成,本机上能通过域名访问网络'] = array(
            'value'=>$rst?'成功':'失败 (将影响部分功能)',
            'result'=>$rst,
        );

        return array('group'=>'基本需求','key'=>'require','items'=>$items,'type'=>'require','allow_install'=>$allow_install);
    }
    
    function test_php_req(&$score){

        $rst = PHP_OS!='WINNT';
        $items['unix/linux 主机'] = array(
            'value'=>PHP_OS,
            'result'=>$rst,
        );
        if($rst){
            $score+=30;
        }else{
            $this->maxLevel(5);
        }

        

        $rst = version_compare(PHP_VERSION,'5.2','>=');
        $items['php 版本5.2.0以上'] = array(
            'value'=>PHP_VERSION,
            'result'=>$rst,
        );
        if($rst){
            $score+=20;
        }else{
            $this->maxLevel(5);
        }

        $rst = version_compare($this->db_ver,'5.0','>=');
        $items['MySQL版本 5.0 以上'] = array(
            'value'=>$this->db_ver,
            'result'=>$rst,
        );
        if($rst){
            $score+=100;
        }else{
            $this->maxLevel(5);
        }

        $gdscore = 0;
        $gd_rst = array();
        if($rst = is_callable('gd_info')){
            $gdinfo = gd_info();
            if($gdinfo['FreeType Support']){
                $gd_rst[] = 'freetype';
                $gdscore+=15;
            }
            if($gdinfo['GIF Read Support']){
                $gd_rst[] = 'gif';
                $gdscore+=10;
            }
            if($gdinfo['JPG Support']){
                $gd_rst[] = 'jpg';
                $gdscore+=10;
            }
            if($gdinfo['PNG Support']){
                $gd_rst[] = 'png';
                $gdscore+=10;
            }
            if($gdinfo['WBMP Support']){
                $gd_rst[] = 'bmp';
                $gdscore+=5;
            }
        }
        $items['GD支持'] = array(
            'value'=>$rst?implode(',',$gd_rst):'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=$gdscore;
        }else{
            $this->maxLevel(2);
        }
        
         if(!$rst){
            $allow_install = false;
        }

        $rst = function_exists('mime_content_type');                
        $items['mime_content_type'] = array(
            'value'=>$rst?'支持':'不支持,仅在报未获取文件类型出错，要求用户支持',
            'result'=>$rst,
        );
        


        //if(isset($GLOBALS['system'])){
       if(function_exists('apache_get_modules')){
              $rst =in_array('mod_rewrite',apache_get_modules());
            $items['支持rewrite'] = array(
                'value'=>$rst?'支持':'不支持',
                'result'=>$rst,
                'key'=>'rewrite',
            );
         

      }

        $rst = is_callable('gzcompress');
        $items['Zlib支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=80;
        }else{
            $this->maxLevel(2);
        }

        $rst = is_callable('json_decode');
        $items['Json支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=30;
        }else{
            $this->maxLevel(5);
        }

        $rst = is_callable('mb_internal_encoding');
        $items['mbstring支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=25;
        }else{
            $this->maxLevel(5);
        }

        $rst = is_callable('fsockopen');
        $items['fsockopen支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=50;
        }else{
            $this->maxLevel(5);
        }

        $rst = is_callable('iconv');
        $items['iconv支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=25;
        }else{
            $this->maxLevel(5);
        }

	
			if($xmlDoc = new DOMDocument())$rst = 1; else $rst = 0;
        $items['dom支持'] = array(
            'value'=>$rst?'支持':'不支持',
            'result'=>$rst,
        );
        if($rst){
            $score+=25;
        }else{
            $this->maxLevel(5);
        }

        //    $rst = get_magic_quotes_gpc();
        //    $items['magic_quotes_gpc关闭'] = array(
        //      'value'=>$rst?'开启':'已关闭',
        //      'result'=>!$rst,
        //    );
        //    if($rst){
        //      $score+=20;
        //    }

        $rst = ini_get('register_globals');
        $items['register_globals关闭'] = array(
            'value'=>$rst?'开启':'已关闭',
            'result'=>!$rst,
        );
        if(!$rst){
            $score+=15;
        }else{
            $this->maxLevel(2);
        }

          $rst = ini_get('allow_url_fopen');
           $items['allow_url_fopen开启'] = array(
             'value'=>$rst?'开启':'已关闭',
             'result'=>$rst,
            );
            if($rst){
              $score+=40;
            }

        if(version_compare(PHP_VERSION,'5.2.0','>=')){
            $rst = ini_get('allow_url_include');
            $items['allow_url_include关闭 (php5.2.0以上)'] = array(
                'value'=>$rst?'开启':'已关闭',
                'result'=>!$rst,
            );
            if($rst){
                $score+=30;
            }else{
                $this->maxLevel(5);
            }
        }else{
            $rst = ini_get('allow_url_fopen');
            $items['allow_url_fopen关闭 (版本小于php5.2.0)'] = array(
                'value'=>$rst?'开启':'已关闭',
                'result'=>!$rst,
            );
            if($rst){
                $score+=30;
            }else{
                $this->maxLevel(5);
            }
        }

        $rst=null;
        if($cache_apc = is_callable('apc_store')){
            $rst[] = 'APC';
        }
        if($cache_memcached = class_exists('Memcache')){
            $rst[] = 'Memcached';
        }
        $items['高速缓存模块(apc,memcached)'] = array(
            'value'=>$rst?implode(',',$rst):'无',
            'result'=>($cache_apc || $cache_memcached)
        );
        if($cache_apc || $cache_memcached){
            $score+=150;
        }else{
            $this->maxLevel(4);
        }
        return array('group'=>'推荐配置','items'=>$items,'type'=>'require');
    }

    function maxLevel($level){
        $this->maxLevel = min($this->maxLevel,$level-1);
    }

  
/*- class-end -*/
}
if(!defined('IN_INSTALLER')){

    if(isset($_GET['cmd'])){
    }elseif(isset($_GET['img'])){
        $img = $_GET['img'];
        if(false){;}


        /*- img-begin -*/

elseif($img=='rank_1.gif'){$imgsize='1164';$imgmeta='gif';$imgdata = 'R0lGODlhZAAYAPcAAO3t7evq6uno59/d29jW0tbW1dPT0vXOitHPzNDOytDNyMzKx8vJx/PFdszJxMvIwsnIxcrHw8fGw8bFw8bEwMXCvsLBwMHBv8PAvMLAvfG7XsC+u7+6sO+xRrmzqLazrbaxqrSxrLSwqLOwq7Kwq+2nLO2nLquoo+ueGOudFuudFOqcE6WgmOiaE+aZE+WYEuOXEt+UEtuSEp6YjtmREpyWjNaOEZaQh9CKEc6JEc2IEMuHEMWDEMGBEL5+D7p8D4iBdrh6D7Z5D7N3DrF2DoZ5Y4Z6ZK1zDqlxDqhvDaRtDaJsDX5qSJtnDJVjDHllRJNiDHxiNXVjQ4BiLHViQnpgM45eC4xdC3ZfOHtdKXBdPGxZOINXCl5ZUIFWCnhVGW5WLX9UCmVVOGNVPWtTKHtSClxTQ2NRMXFQGGFRNVlRQ3ZOCXRNCW9OFWhNHmRMIVdNPGFKImhKF2VKHGtHCFBKQFFJO2JHF2FGFV5GG1hGJ2dECFhFIlxFG1FAIV4+B1g7B088HFI8E007HE47GlE5EVY5B006G0Q5Jk83Dk0zBj00JkkxBjswHDktGTotFjUrGy4gCP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEHAJIALAAAAABkABgAAAj/ACUJHEiwoMGDCBMqXMiwoUODFi48nEixosWGdtRc3Mix48IMgQhl8Eiy5MYxXryMMcmy5UIMd3LkuBPBpc2bkrQEKVFCyBacQEs++NJBg4YObRwEXfowAIQRNYowyYKiwYEDDVxMeWJkRggGAJiKJVin0aA8dLw4GZJDxosXMnIMceJlT59DjbqMHTsh0h8uTY74aOsiRQoXcX0cacIF0KMJe8du0LNGyQ8dblVoVgFXxw8lbPxIiLyXAh8rP3DEaGHYcIsYOH5cObOAdOQEcZzskOFiswrEO6AkGmCbtIIqRGzAWMF8BQwbR6IQKG4bzBHVLrK7gJ0EC3XSAsgcaeFhQ4Z5GTZ4IHnzPXIFN0uC9JhPP8iSOQjaj2WBx8kSJFCggYYTSCwBBR4f6CcWEICEEYYcVHDggRRylFGGITcoyBQciggihggEgZBGIYyYoeFSkCBCAkInOLLIiUEZwFABMNZoI0cBAQA7';}
elseif($img=='rank_2.gif'){$imgsize='1342';$imgmeta='gif';$imgdata = 'R0lGODlhZAAYAPcAAO3t7evq6uno59/d29jW0tbW1dPT0vXOitHPzNDOytDNyMzKx8vJx/PFdszJxMvIwsnIxcrHw8fGw8bFw8bEwMXCvsLBwMHBv8PAvMLAvfG7XsC+u7+6sO+xRrmzqLazrbaxqrSxrLSwqLOwq7Kwq+2nLO2nLquoo+ueGOudFuudFOqcE6WgmOiaE+aZE+WYEuOXEt+UEtuSEp6YjtmREpyWjNaOEZaQh9CKEc6JEc2IEMuHEMWDEMGBEL5+D7p8D4iBdrh6D7Z5D7N3DrF2DoZ5Y4Z6ZK1zDqlxDqhvDaRtDaJsDX5qSJtnDJVjDHllRJNiDHxiNXVjQ4BiLHViQnpgM45eC4xdC3ZfOHtdKXBdPGxZOINXCl5ZUIFWCnhVGW5WLX9UCmVVOGNVPWtTKHtSClxTQ2NRMXFQGGFRNVlRQ3ZOCXRNCW9OFWhNHmRMIVdNPGFKImhKF2VKHGtHCFBKQFFJO2JHF2FGFV5GG1hGJ2dECFhFIlxFG1FAIV4+B1g7B088HFI8E007HE47GlE5EVY5B006G0Q5Jk83Dk0zBj00JkkxBjswHDktGTotFjUrGy4gCP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEHAJIALAAAAABkABgAAAj/ACUJHEiwoMGDCBMqXMiwoUODFi48PBhxosWLGAnaUZNR4MaOIEMezBCIUIaMJE2KXBlyjBcvYzK6hMmy5kUMd3LkuBPhZs6dPW0KZaglSIkSQrZcLHo06dCnCB986aBBQ4c2DiZKpWoVK9ShASCMqFGESRYUDQ4caOBiyhMjM0IwAEAw7NiyZ9Oubfs27tyvIOs0GpSHjhcnQ3LIePFCRo4hTrzs6XOoUReBggkbRqyYsWPIkilbBpxxQqQ/XJoc8aHYRYoULhz7ONKEC6BHEwSaRq2atQzXsGXTto2bdMYNetYo+aFjsYrnKhrr+KGEjR8JBJErZ+4cunTq1rEb/89IgY+VHzhitHj9ukUMHD+unFlgsPz59OvZu4cvn/74jgnE4cQOv0GnQmw7QJHIAAgFOGCB0CGoIIP/gaRAFUTYAMMKHK4Agw1HREGAQhdmuGGHH4Y4YoUhgXFEei7E6MJ7SWDBkIswykijjSyCJAAZR/BggwxEymADD0i8sdCPQQ5Z5JFJ9ghSBW4sEUQPWGYZxBJzIKAQlVZmqSWXXkqJEQt4OLEEElCggYYTSCwBBR4fKISmmmy6CaecdJqJERCAhBGGHFRw4IEUcpRRhiE3KASooIQaiqiijPp5ERyKCCKGCASBkEYhjJihEKaacjqQp6CKaulEkCBCAkInOBuyiEKtvnpQrLOu+pABDBWgEK8L+arrsMQyFBAAOw==';}
elseif($img=='rank_3.gif'){$imgsize='1510';$imgmeta='gif';$imgdata = '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';}
elseif($img=='rank_4.gif'){$imgsize='1628';$imgmeta='gif';$imgdata = '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';}
elseif($img=='rank_5.gif'){$imgsize='1772';$imgmeta='gif';$imgdata = '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';}
elseif($img=='rank_6.gif'){$imgsize='1770';$imgmeta='gif';$imgdata = 'R0lGODlheAAYAPcAAO3t7erq6unn59/b29bV1djS0tPS0tHMzNDKytDHx8zHx8rHx8nFxc3Dw8rDw8fDw8vBwcbDw8bAwMLAwMG/v8K9vcW9vcC7u8O7u8Cwr7asrLaqqbSrq7Kqqrqop7OqqrSop6uiov2GgKWYl56OjZ2Mi/xxa5aGhvxZUYh1dIhkYodjYfxBN/soHoFHRPsmHF1QT3xCP3hBPnhAPV1CQVlDQnM7OHk2M2Q8Ok9AP38zMPsRBm43NPsQBFg8On4xLvoPA2g2NPkOAvcOAvUOAlE6OWM0MvMOAvEOAoUqJu0NAmYwLekNAucNAoAnI+MNAnIrKN0MAtsMAtkMAtcMAtEMAm4mI80MAskLAsMLAsELAsULAlsmI70LArsKAmUhHWcgHbcKAlsiHmsdGbMKAn0XErEKAq0KAmgbF0UlI6sKAlMgHXYXEj0lJKQJAWIaFmAaFp4JAZwJAWwVEXMTDpQIAZYIAU8bGGYVEVIaF04aF2UTD1EYFYoIAYgHAYYHATwbGYIHAXoHATUaGVYRDToYFnwHAVMQDHAGATsVE2wGAVMMCGIFAVwFAVoFAVEEAE0EAC8HBf///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEHAJIALAAAAAB4ABgAAAj/ACUJHEiwoMGDCBMqXMiwocOHCydQgGhQIkWCFi8KzKhREseOkorUABlyZEeRIFGmNAmyQh4+FTq6hKlxZsyLNmW+vNkRhx8/OHr+DHrRJ1CNRokWHQoSAx4pUvA4uOgUqlSKVaNOhZj1KtanWjvayPLihRYeF8eWPUtRrVm0EN2ybUv2rUYIZVigQMGCTgOIePXy9fsw8N6+fx0aHpxYcd7DhBcGYPChxAoXTnaYECHCBJEkMVSQ4LAAAMHJlS9n3tz5c+jRpQeitoxZM2fPoEWTNi1wtmrbrXPD5t2bMu3Vt13rjk0wB6A7bxD5idNFCpMjR5hI6RLHjyI4egDB/xDoHLp06taxa+fuHbx4SeWjT69+Pfv27t/Dj49/nr76++3pR95z8qFX33r4uTfeQBFEwkgfboSBhXVE9NADEdphEYYbfTSSSAQCNfhghBMyUeGFGW7Y4YeSiAihhBRaiKEUGnLoIYgukhgjijSqeGOIDr5Y4okz1rgiiARdwIUhZ2wxxXVARAlEdlNscYYgazyQ5JJNPnmElFMyUeWVWQ6kJJNOQikllVZiqaVAZ3apZpRskvkmnFym+eWaYrZZpkESiGHHFlEoMYSFFg6hRBRb1LGEAoAKSqihiPagKKOOQkpQoIMWeiiilzb6aEGcTvppoouKqummknpaaaiZJv+EwBdxUGEiETvkiiEVciwyAEKz1nprrjvs2uuvBgVrKxG46soEr74epOywzkKLbLK0Lttssc8eu1ACP3jxBBJClCsEEk+EoUMBCoErLrnmoqsuuwi5O66556a7bkL2wluuvPvyG+698epL70JQhFEos8wuasYNDCW8MMMOQ6yQxEowTETFCCucMcVRPByxxxpzzJAAVoRRxRMst1wFGWAshLLKLbsMs0Izr1zzEy/HnFDOO/N8M84p61xzzw1ZMIYaWVzh9NNZqIHGAQopzfTTUEtNNUJWN431FVFPnVDXX4OtddVLe4112FsrNMIecahBhhxssBEHGWrIsYcGbsPHLTfdduOtN98IvR333HXfnffeCRn+d+KCM96434gHvjjhCqXQyB9/zDFDBh7IMEcggThyQuabd/556KOXfjpCmnPuOeiik256QrGrTnvrt+Oe+uys2/66Qj48QkgQIBC0gRGHQEID8cYjrzzzzidU/PHJD7R8888jdL302lPfvffRZy/Q9tUvNEgaHSAUQiFtKLR++we9Hz9C87sPf0L5178//+zT3/0UYgCGEICABkxIARdyQIQsUCENdGACSULBClrwggYJCAA7';}
elseif($img=='yes.gif'){$imgsize='234';$imgmeta='gif';$imgdata = 'R0lGODlhEAAQAMQAACyDLGGiYUy9T0SZRnbLd2XKbDuOPEa/SWDFZXXQgFjFXUSxR33SiWvMclLDVD+TQH3SfzKHMnrRhE3CUEahSEarSWioaHHOemvOa0WkR0OaRUeySv///wAAAAAAAAAAACH5BAEHABwALAAAAAAQABAAAAVnICeOZFlCTqpCJjcVTCwXUyk0Uq7njTASiIRwSEQQRJuLcslUbkSVhnRKlVZElIJ2y9VSRAOEeEwWD0QPhXrNVj9EBpV8bhBZMpO8fp+xjCILe3sLESUAGgeJihoALQEAkJEBLZQlIQA7';}
elseif($img=='no.gif'){$imgsize='236';$imgmeta='gif';$imgdata = 'R0lGODlhEAAQAMQAAMwhAPiDaf9UMuhGJP9wTv+efNozEtlZQP9lQ/+Na/96WPlePP+kgv9ZN/+WdO1NK+NAHv9rQtIoB/9fPd45F/FTMf+DYf+thOBiSf+McvRNK+c8Gv///wAAAAAAAAAAACH5BAEHABwALAAAAAAQABAAAAVpICeOZFlmSKpmJodYVyxbSLk4TK7nzjIGioJwSFQERBWHcslUVkSPhHRKlT5EA4t2y9UORBCFeEwWQ0QUgnrNVlNEBpV8bhBhBpO8fj/AjCQaDYKDghoSJQAbAouMGwAtBwCSkwctliUhADs=';}

/*- img-end -*/
        header('Content-type: '.$imgmeta);
        header('Content-Disposition: attachment; filename="'.$img.'"');
        echo base64_decode($imgdata);
    }elseif(isset($_GET['phpinfo'])){
        phpinfo();
    }elseif(isset($_GET['download'])){
        header('Content-type: text/plain');
        header('Content-Disposition: attachment; filename="dd.php"');
        readfile(__FILE__);
    }else{
        error_reporting(0);
        header('Content-type: text/html;charset=utf-8');
        header("Cache-Control: no-cache,no-store , must-revalidate");
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

        $disFunc = get_cfg_var("disable_functions");
        if(($disFunc || !preg_match('/phpinfo/i',$disFunc))){
            $phpinfo = '<a href="?phpinfo=true">查看phpinfo</a>&nbsp;&nbsp;';
        }else{
            $phpinfo = null;
        }

        $version = '$Rev: v1.3 $';
        $tester = new mdl_serverinfo();
        if(isset($_GET['db_host'])){
            if($_GET['db_host']=='config'){
                include('/config.php');
            }else{
                $tester->allow_change_db=true;
                define('DB_HOST', $_GET['db_host']);
            }
        }else{
            $tester->allow_change_db=true;
        }

        $result = $tester->run($allow_install);
        $txtline = '<div style="display:none">================================================================</div>';
        foreach($result['data'] as $group=>$items){
            $body.="<tbody><tr class=\"title\"><td colspan=\"3\"><div style=\"display:none\">&nbsp;</div>{$txtline}{$group}{$txtline}</td></tr><tbody>";
            $i=0;
            if($items['type']=='require'){
                foreach($items['items'] as $key=>$value){
                    $rowOpt = $i%2?'':' style="background:#E0EAF2"';
                    $body.="<tr{$rowOpt}><th width=\"60%\">{$key}</th><td>".$value['value']."</td><td width=\"50px\">".($value['result']?'<img src="?img=yes.gif" title="pass" height="16px" width="16px">':'<img src="?img=no.gif" title="failed" height="16px" width="16px">')."</td></tr>";
                    $i++;
                }
            }else{
                foreach($items['items'] as $key=>$value){
                    $rowOpt = $i%2?'':' style="background:#E0EAF2"';
                    $body.="<tr{$rowOpt}><th>{$key}</th><td colspan=\"2\">{$value}</td></tr>";
                    $i++;
                }
            }
            $body.='</tbody>';
        }

        $html =<<<EOF
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
 <head>
 <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
 <meta name="robots" content="index,follow,noarchive" />
 <meta name="googlebot" content="noarchive" />
 <META NAME="ROBOTS" CONTENT="NOINDEX,NOFOLLOW">
    <title>服务器评测</title>
    <style>
    body{
        background:#327EA3;
    }
    p,td,div,th{
        font: normal 13px/20px Verdana Geneva Arial Helvetica sans-serif;
    }
    #container{
        width:560px;
        margin:20px auto;
        text-align:left;
        border:1px solid #CED5DA;
        padding:10px;
        background:#003563;
    }
    a{color:#009}
    #main{
        background:#D0DEE8;
        padding:15px;
    }
    #setting{
        width:100%;
    }
    #setting th{
        text-align:left;
        padding-left:20px;
        font-weight:normal;
    }    
    tr.title{
        background:#327EA3;
        color:#fff;
    }
    tr.title td{
        padding:5px;
    }
    label{font-weight:bold}
    </style>
 </head>
 <body>
<center><div id="container"><div id="main">
    <span style="float:right"><!--$version--></span>
    <div><!--SiteStar--> 服务器测评</div>
    <hr />
<table id="setting">
 {$body} 
</table>
<hr />
<div>
    <div style="float:right;">$phpinfo<!--<a href="?download=true">下载本文件</a>--></div>
    <strong>可以单独上传本文件到服务器上完成检测.</strong>
</div>
</div></div>
<!--<a href="http://www.sitestar.cn" target="_blank" style="color:#fff;font-size:12px">SiteStar&copy;2010</a>
--></center>

EOF;

        echo $html;
    }

}


$sp_testdirs = array(
        '/',
        '/sql/*',
        '/upload/*',
        '/data/*',
		'/cache/*',
		'/navigation/*',
        '/template/*'
       
        
   );

?>
<div class="pr-title" style="text-align: center;">
<h3>目录权限检测</h3>
</div>
<div
	style="padding: 2px 8px 0px; line-height: 33px; height: 23px; overflow: hidden; color: #993300; text-align: center;"> 系统要求必须满足下列所有的目录权限全部可读写的需求才能使用，其它应用目录可安装后在管理后台检测。</div>
<?php if(PHP_OS==WINNT){//判断系统是否windows系统 ?>
<table width="726" border="0" align="center" cellpadding="0"
	cellspacing="0" class="twbox" style="text-align: center;">
	<tr>
		<th width="300" align="center"><strong>目录名</strong></th>
		<th width="212"><strong>读取权限</strong></th>
		<th width="212"><strong>写入权限</strong></th>
		<th width="212"><strong>修改权限</strong></th>
	</tr>
			<?php
			foreach($sp_testdirs as $d)
			{
			?>
			<tr>
		<td><?php echo $d; ?></td>
					<?php
				$fulld=$d;
      		$fulld = Sitestarroot.str_replace('/*','',$d);
			
      
      		$rsta = (is_readable($fulld) ? '<font color=#993300>[√]读</font>' : '<font color=#993300>[×]读</font>');
	    	
				$w2sta = (is_writable($fulld) ? '<font color=#993300>[√]写</font>' : '<font color=#993300>[×]写</font>');
	    		echo "<td>$rsta</td><td>$w2sta</td><td>$wsta</td>\r\n";
      ?>
			</tr>
			<?php
			}//windows系统结束
			?>
		</table>
<?php }else {//非windows系统开始?>
<table width="726" border="0" align="center" cellpadding="0"
	cellspacing="0" class="twbox" style="text-align: center;">
	<tr>
		<th width="300" align="center"><strong>目录名</strong></th>
		<th width="212"><strong>绝对路径</strong></th>
		<th width="212"><strong>权限</strong></th>
	</tr>
			<?php
			foreach($sp_testdirs as $d)
			{
			?>
			<tr>
		<td><?php echo $d; ?></td>
					<?php
				$fulld=$d;
      		$fulld = Sitestarroot.str_replace('/*','',$d);            		
echo "<td>$fulld</td><td>";echo substr(sprintf("%o",fileperms("$fulld")),-4);  echo "</td>\r\n";
clearstatcache();
	          ?>
			</tr>
			<?php
			}
			?>
		</table>
<?php }//非windows系统结束?>
<?php
function is_ip($gonten){ 
$ip = explode(".",$gonten); 
for($i=0;$i<count($ip);$i++) 
{ 
if($ip[$i]>255){ 
return (0); 
} 
} 
return ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$",$gonten); 
}
?>
<br />

<table width="580" border="0" align="center" cellpadding="0" cellspacing="0" class="twbox">
	<tr>
<td>
模板ip
<?php


if(is_ip(gethostbyname('template.sitestar.cn')))echo gethostbyname('template.sitestar.cn');
else {?>
<script type="text/javascript">
alert("模板template.sitestar.cnip不能解析");
</script> 
<?php echo gethostbyname('template.sitestar.cn'); }
flush();
?>
<br />
授权服务器ip
<?php


if(is_ip(gethostbyname('licence.sitestar.cn')))echo gethostbyname('licence.sitestar.cn');
else {?>
<script type="text/javascript">
alert("模板授权iplicence.sitestar.cn不能解析");
</script> 
<?php echo gethostbyname('licence.sitestar.cn'); }
flush();
?>


<br />
//判断locktable 和 数据库信息

<?php
define('IN_CONTEXT', 1);
if (!defined('IN_CONTEXT')) die('access violation error!');
ini_set("display_errors","off");
error_reporting(0);

if(!(ini_get('date.timezone'))){
date_default_timezone_set("Etc/GMT-8");
}

define('DS', DIRECTORY_SEPARATOR);
define('IS_INSTALL', 1); // 0:share 1:install
define('FCK_UPLOAD_PATH','../../../');
define('FCK_UPLOAD_PATH_AB','/admin/fckeditor/upload/');
//define('ROOT', dirname(__FILE__));
define('ROOT',preg_replace("#[\\\\\/]install#", '', dirname(__FILE__)));

define('SCREENSHOT_URL','http://screenshots.sitestar.cn/');

define('P_FLT', ROOT.'/filter');
define('P_INC', ROOT.'/include');
define('P_LIB', ROOT.'/library');
define('P_MDL', ROOT.'/model');
define('P_MOD', ROOT.'/module');
define('P_MTPL', ROOT.'/m-template');

include_once(ROOT.'/config.php');
include_once(P_LIB.'/memorycache.php');
include_once(P_LIB.'/pager.php');

include_once(P_LIB.'/toolkit.php');
include_once(P_INC.'/json_encode.php');
//include_once(P_INC.'/china_ds_data.php');

header("Content-type: text/html; charset=utf-8");

include_once(P_LIB.'/'.Config::$mysql_ext.'.php');
$db = new MysqlConnection(
    Config::$db_host,
    Config::$db_user,
    Config::$db_pass,
    Config::$db_name
);
if (Config::$enable_db_debug === true) {
    $db->debug = true;
}

include_once(P_INC.'/autoload.php');

define('CACHE_DIR', ROOT.'/cache');
include_once(P_LIB.'/record.php');
include_once(P_LIB.'/validator.php');

include_once(P_INC.'/db_param.php');
include_once(P_INC.'/userlevel.php');

if (intval(DB_SESSION) == 1) {
    include_once(P_LIB.'/session_db.php');
}

include_once(P_INC.'/magic_quotes.php');

define('P_TPL', ROOT.'/template/'.DEFAULT_TPL);
define('P_TPL_VIEW','.');
define('P_SCP', 'script');
define('P_TPL_WEB', 'template/'.DEFAULT_TPL);
include_once(P_INC.'/template_limit.php');
// Include template infomation
include_once(P_TPL.'/template_info.php');

//include_once(P_LIB.'/rand_math.php');
include_once(P_LIB.'/param.php');
include_once(P_LIB.'/notice.php');
SessionHolder::initialize();
Notice::dump();

/**
 * Edit 02/08/2010
 */
$act =& ParamHolder::get('_m');
switch ($act) {
	case 'mod_order':
		include_once(P_INC.'/china_ds_data.php');
		break;
	case 'mod_auth':
	case 'mod_message':
		include_once(P_LIB.'/rand_math.php');
		break;
}

define('P_LOCALE', ROOT.'/locale');
//include_once(P_LIB.'/php-gettext/gettext.inc');
include_once(P_INC.'/locale.php');

include_once(P_INC.'/siteinfo.php');

include_once(P_LIB.'/acl.php');
ACL::loginGuest();

include_once(P_LIB.'/module.php');
include_once(P_LIB.'/form.php');

include_once(P_LIB.'/content.php');

include_once(P_LIB.'/to_pinyin.php');
include_once(P_INC.'/global_filters.php');
/*
if(!Toolkit::getAuthTpl()){
	_e('Template Corp');
	exit;
}
*/
$curr_locale = trim(SessionHolder::get('_LOCALE'));

include_once(ROOT.'process.php');

// 数据库连接
$sql="SELECT `key`,val FROM ".Config::$tbl_prefix.parameters." WHERE `key` = 'sysver' or `key` = 'DEFAULT_TPL' or `key` = 'SVC_TPL';";
$result= mysql_query($sql);

   while($row=mysql_fetch_object($result)) {
       

	   $sitestar[$row->key]= $row->val;
	    
   }
echo "建站之星版本为$sitestar[SYSVER]"."<br/>";
echo "建站之星模板安装路径为$sitestar[SVC_TPL]"."<br/>";
echo "建站之星当前使用的模板安装路径为$sitestar[DEFAULT_TPL]"."<br/>";


echo "<br/>";

//处理参数表中日志
$sql=" LOCK TABLES ".Config::$tbl_prefix.products ." WRITE";
if(mysql_query($sql)) echo '<h4>恭喜您 ！有lock table权限</h3>';else echo '<h4>糟糕您的没有lock table权限，请联系您的数据库服务商。</h2>';	 
ob_end_flush();
echo "<br/>";
$ips[domain] = $_SERVER['SERVER_NAME'];
echo "您当前SERVER_NAME值为"; echo $ips[domain];
if($ips[domain]!=$_SERVER['HTTP_HOST']){
	?>
	<script type="text/javascript">
alert("SERVER_NAME值不正确");
</script> 
<?php }
echo "<br/>";
$ip=file_get_contents('http://ip.qq.com/');
preg_match('/\<span class="red">(.*)<input/',$ip, $client_source);
echo "电信对外访问ip为$client_source[1]";
$ipurl="http://licence.sitestar.cn/check_ip.php?l_ip=$client_source[1]";
function getDns2(){
$ips['domain'] = $_SERVER['SERVER_NAME'];
if (strtoupper(substr(PHP_OS,0,3)) !== 'WIN') {
$ips[] = "447f32d25b7d55524675c64d649ae5d9";
}else{
$win_ips = gethostbynamel(php_uname('n'));
foreach ($win_ips as $ip){
$ips[] = $ip;
echo $ip;
echo "<br/>";
}
}
return array_unique($ips);
}
echo "<br/>";
echo "用户空间所有ip为:";
$client_source=getdns2();
echo "<br/>";
	echo "用户绝对路径为";echo  ROOT;
?>
<li class="h1"><a target="_blank" title="点击此处验证ip授权"
	href="<?php echo $ipurl;?>"
	class="ellipsis">点击此处验证ip授权</a></li>	
	
 </li><li class="h1"><form action="#" method="POST" ><lable>请输入模板编号如：2550</label>
<input accept="text/html" name="downloadRemote"  /><input type="submit"  name='submit'   value="提交"  />
</form> </li>	
 
 <?php
 echo "<br/>";
$domain=$_SERVER["SERVER_NAME"] ;
$key = sha1($domain."ssiuhIUAHSiu!husashu11dd@kjdjsah==");
if (function_exists('curl_init') &&function_exists('curl_exec')) {
$curl = curl_init();
$timeout = 60;
curl_setopt($curl,CURLOPT_URL,"http://licence.sitestar.cn/licencedat.php?domain={$domain}&key={$key}");
curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,$timeout);
$str = curl_exec($curl);
curl_close($curl);
}
if($str=='') echo "curl_init故障";
echo $str;
flush();
?>
</td></tr>
	<tr>
	  <td>
<?php
function getDns(){
	$ips['domain'] = $_SERVER['SERVER_NAME'];
	if (strtoupper(substr(PHP_OS,0,3)) !== 'WIN') {
		$ips[] = "447f32d25b7d55524675c64d649ae5d9";
	}else{
		$win_ips = gethostbynamel(php_uname('n'));
		foreach ($win_ips as $ip){
			$ips[] = $ip;
		}
	}
	return array_unique($ips);
}
function _rmdir_r($root_dir) {
	$files = scandir($root_dir);
	foreach ($files as $file) {
		if ($file == '.'||$file == '..') {
			continue;
		}
		$f_path = $root_dir.'/'.$file;
		if (is_dir($f_path)) {
			if (!self::_rmdir_r($f_path)) {
				return false;
			}
		}else {
			if (!unlink($f_path)) {
				return false;
			}
		}
	}
	if (!rmdir($root_dir)) {
		return false;
	}
	return true;
}
function rmdir_template($template) {
	$dir_template = ROOT.'/template/'.$template;
	return _rmdir_r($dir_template);
}

function get_filename($name,$ex='.zip') {
	return substr($name,0,-strlen($ex));
}
function removeDir($dirName)
{
	if(!is_dir($dirName))
	{
		@unlink($dirName);
		return false;
	}
	$handle = @opendir($dirName);
	while(($file = @readdir($handle)) !== false)
	{
		if($file != '.'&&$file != '..')
		{
			$dir = $dirName .'/'.$file;
			is_dir($dir) ?removeDir($dir) : @unlink($dir);
		}
	}
	closedir($handle);
	return @rmdir($dirName);
}

 
function _downloadRemote($tplid) {
include_once(P_LIB.'/zip.php');
ini_set('max_execution_time',600);
$tpl_path = ROOT.DS.'template';
$ezsite_uid = EZSITE_UID;
$client_source = getDns();
$sour = serialize($client_source);
$tpl_info = file_get_contents(SVC_TPL."getTplInfo.php?ezsite_uid=$ezsite_uid&tplid=$tplid&tag=$sour");
$tpl_info = unserialize($tpl_info);
if (!$tpl_info) {echo "验证授权失败，检查授权ip和域名是否对应";var_dump($sour);var_dump($tpl_info);
return false;
}
echo "验证授权成功";
echo "<br/>";var_dump ($tpl_info);
echo "<br/>";
if (!is_writable($tpl_path)) { echo "template目录不可写入";
return false;
}
$folder_name = get_filename($tpl_info['archive']);

if(file_exists($tpl_path.DS.$folder_name)) {
removeDir($tpl_path.DS.$folder_name);
}
$remote_file = fopen(SVC_TPL."../template_jingjian/{$tpl_info['archive']}",'r');
if (!$remote_file) {echo "远程文件不存在,或不可用fopen下载";
die();
}
$local_file = fopen($tpl_path.DS.$tpl_info['archive'],'w');
while (!feof($remote_file)) {
fwrite($local_file,
fgets($remote_file,4096),
4096);
}
fclose($local_file);
fclose($remote_file);
echo "下载的文件为".$tpl_path.DS.$tpl_info['archive'];
echo "<br/>";
$filename=$tpl_path.DS.$tpl_info['archive'];
$filesize=abs(filesize($filename));
echo $filesize;
if($filesize==710){echo "710错误";}
if(extension_loaded('zip'))
{
echo "使用的是zip组件解压";
$tpl_zip = new ZipArchive();
$tpl_zip->open($tpl_path.DS.$tpl_info['archive']);
@$tpl_zip->extractTo($tpl_path.DS.$folder_name);
$tpl_zip->close();
}
else
{
echo "使用的是zip.php解压";
if(!file_exists($tpl_path.DS.$folder_name))
{
echo $tpl_path.DS.$folder_name."文件夹不存在建立";
mkdir($tpl_path.DS.$folder_name,0755);
}
$zipper = new zipper();
$zipper->ExtractTotally($tpl_path.DS.$tpl_info['archive'],$tpl_path.DS.$folder_name);
}

if (!file_exists($tpl_path.DS.$folder_name.DS.'conf.php') ||
!file_exists($tpl_path.DS.$folder_name.DS.'template_info.php')) {
echo $tpl_path.DS.$folder_name.DS.'conf.php'.'和'.$tpl_path.DS.$folder_name.DS.'template_info.php'."文件不存在检查文件完整性";


return false;
}
if(file_exists($tpl_path.DS.$folder_name.DS.'conf.php'))
{
rmdir_template($folder_name);
include $tpl_path.DS.$folder_name.DS.'conf.php';
}
else
{
die(__('Install Error!'));
}
echo "模板安装成功".$tpl_name;
}
if($_POST['submit']){
	var_dump($_POST['downloadRemote']);
_downloadRemote($_POST['downloadRemote']);
}

?>      </td>
  </tr>
</table>


