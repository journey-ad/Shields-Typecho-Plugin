<?php
if(!defined('__TYPECHO_ROOT_DIR__'))exit;
class Shields_Action extends Typecho_Widget implements Widget_Interface_Do {
    public function execute(){}
    public function action(){
        self::filterReferer();
        $all = explode("/", urldecode(trim($this->request->get('all'))), 5);
        $info = array(
            'type' => $this->request->get('type'),
            'style' => $this->request->get('style'),
            'user' => $all[0],
            'repo' => $all[1],
            'action' => $all[2],
            'tag' => $all[3]
        );
        $info['ver'] = $info['tag'] == 'tag' ? $all[4] : NULL;

        $shields = 'https://img.shields.io/github/';

        if(isset($info['user'])){
            $url = "{$shields}followers/{$info['user']}.{$info['type']}?style={$info['style']}&logo=github&label=Follow";
            if(isset($info['repo'])){
                $url = "{$shields}stars/{$info['user']}/{$info['repo']}.{$info['type']}?style={$info['style']}&logo=github&label=Stars";
                if(isset($info['action'])){
                    if(strtolower($info['action']) == 'fork'){
                        $url = "{$shields}forks/{$info['user']}/{$info['repo']}.{$info['type']}?style={$info['style']}&logo=github&label=Fork";
                    }elseif(strtolower($info['action']) == 'releases'){
                        $url = "{$shields}downloads/{$info['user']}/{$info['repo']}/total.svg?style={$info['style']}&logo=github";
                        if(strtolower($info['tag']) == 'latest'){
                            $url = "{$shields}downloads/{$info['user']}/{$info['repo']}/latest/total.svg?style={$info['style']}&logo=github";
                        }elseif(strtolower($info['tag']) == 'tag'){
                            $url = "{$shields}downloads/{$info['user']}/{$info['repo']}/{$info['ver']}/total.svg?style={$info['style']}&logo=github";
                        }
                    }
                }
            }
        }
        
        $cachedir = dirname(__FILE__)."/cache";
        $key = md5($url).".".$info['type'];
        $expire = Typecho_Widget::widget('Widget_Options')->plugin('Shields')->expire;
        $img = self::cache_get($key);
        if($img == false || time() - filemtime("{$cachedir}/{$key}") > $expire){
            $img = self::fetch_url($url);
            self::cache_set($key, $img);
        }
        
        if($info['type'] == 'svg'){
            header('Content-Type: image/svg+xml');
        }elseif ($info['type'] == 'png') {
            header('Content-Type: image/png');
        }
        echo($img);
    }
    
    /**
     * url抓取,两种方式,优先用curl,当主机不支持curl时候采用file_get_contents
     * 
     * @param unknown $url
     * @param array $data
     * @return boolean|mixed
     */
    private function fetch_url($url){
        if(function_exists('curl_init')){
            $curl=curl_init();
            curl_setopt($curl,CURLOPT_URL,$url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            $result=curl_exec($curl);
            $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
            curl_close($curl);
            if ($httpCode != 200) return false;
            return $result;
        }else{
            //若主机不支持openssl则file_get_contents不能打开https的url
            if($result = @file_get_contents($url)){
                if (strpos($http_response_header[0],'200')){
                    return $result;
                }
            }
            return false;
        }
    }
    
    private function filterReferer(){
        if(isset($_SERVER['HTTP_REFERER'])&&strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST'])===false){
            http_response_code(403);
            die();
        }
    }
    
    /**
     * 缓存写入
     * 
     * @param unknown $key
     * @param unknown $value
     * @return number
     */
    private function cache_set($key, $value)
    {
        $cachedir = dirname(__FILE__)."/cache";
        
        $fp = fopen("{$cachedir}/{$key}", "w+");
        $status = fwrite($fp, $value);
        fclose($fp);
        return $status;
    }
    
    /**
     * 缓存读取
     * 
     * @param unknown $key
     * @return mixed|boolean
     */
    private function cache_get($key)
    {
        $cachedir = dirname(__FILE__)."/cache";
        
        //找到缓存直接读取缓存目录的文件
        if(file_exists("{$cachedir}/{$key}")){
            return file_get_contents("{$cachedir}/{$key}");
        }else{
            return false;
        }
    }
}