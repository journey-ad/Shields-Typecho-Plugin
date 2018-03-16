<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * 为 Typecho 添加 <a href="https://shields.io/" target="_blank">shields.io</a> 支持
 * 
 * @package Shields
 * @author journey.ad
 * @version 1.0.0
 * @link https://imjad.cn
 */
class Shields_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Helper::addAction('shields', 'Shields_Action');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->content = array('Shields_Plugin','parselink');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->excerpt = array('Shields_Plugin','parselink');
        $info = self::is_really_writable(dirname(__FILE__)."/cache") ? "插件启用成功！！" : "Shields插件目录的cache目录不可写，可能会导致博客加载缓慢！";
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){
        Helper::removeAction('shields');
        $files = glob('usr/plugins/Shields/cache/*');
        foreach($files as $file){
            if (is_file($file)){
                @unlink($file);
            }
        }
        return _t('Shields插件禁用成功，所有缓存已清空!');
    }
   
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form){
        if (isset($_GET['action']) && $_GET['action'] == 'deletefile')
            self::deletefile();
        
		$type = new Typecho_Widget_Helper_Form_Element_Radio('type', array('0'=> 'PNG', '1'=> 'SVG'), 1, _t('图像类型'), _t('图像类型，默认为 SVG'));
        $form->addInput($type);
		$style = new Typecho_Widget_Helper_Form_Element_Radio('style', array('0'=> 'plastic', '1'=> 'flat', '2'=> 'flat-square', '3'=> 'for-the-badge', '4'=> 'social'), 4, _t('图像样式'), _t('图像样式，默认为 social'));
        $form->addInput($style);
		$expire = new Typecho_Widget_Helper_Form_Element_Text('expire', NULL, '1800', _t('缓存过期时间'), _t('图像缓存过期时间，单位为秒，默认1800'));
        $form->addInput($expire);
        
        $cache = new Typecho_Widget_Helper_Form_Element_Radio('cache',
            array('false'=>_t('否')),'false',_t('清空缓存'),_t('清空插件生成的缓存文件，必要时可以使用'));
        $form->addInput($cache);

        $submit = new Typecho_Widget_Helper_Form_Element_Submit();
        $submit->value(_t('清空图片缓存'));
        $submit->setAttribute('style','position:relative;');
        $submit->input->setAttribute('style','position:absolute;bottom:37px;');
        $submit->input->setAttribute('class','btn btn-s btn-warn btn-operate');
        $submit->input->setAttribute('formaction',Typecho_Common::url('/options-plugin.php?config=Shields&action=deletefile',Helper::options()->adminUrl));
        $form->addItem($submit);
}
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 输出标签替换
     * 
     * @access public
     * @param string $content
     * @return string
     */
    public static function parselink($content,$widget,$lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;
        
        if ($widget instanceof Widget_Archive) {
            $content = preg_replace_callback('/!(http|https)?(:\/\/)?github.com\/(.+)\b/i',array('Shields_Plugin',"parseCallback"),$content);
            $content = $widget->isMarkdown ? $widget->markdown($content) : $widget->autoP($content);
        }
        
        return $content;
    }
    
    /**
     * 回调解析
     * @param unknown $matches
     * @return string
     */
    public static function parseCallback($matches)
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('Shields');
        $type = $options->type ? 'svg' : 'png';
        
        $api = Typecho_Common::url('action/shields',Helper::options()->index);
        
        $all = urlencode(trim($matches[3]));
        $style = array('0'=> 'plastic', '1'=> 'flat', '2'=> 'flat-square', '3'=> 'for-the-badge', '4'=> 'social');
        $url = "{$api}?type={$type}&all={$all}&style={$style[$options->style]}";
        
        if($type == 'svg'){
            $result = "<object data=\"{$url}\" type=\"image/svg+xml\"></object>";
        }elseif ($type == 'png') {
            $result = "<img src=\"{$url}\"/>";
        }
        
        return $result;
    }
    
    /**
     * 缓存清空
     *
     * @access private
     * @return void
     */
    private function deletefile()
    {
        $path = __TYPECHO_ROOT_DIR__ .'/usr/plugins/Shields/cache/';

        foreach (glob($path.'*') as $filename) {
            @unlink($filename);
        }

        Typecho_Widget::widget('Widget_Notice')->set(_t('图片缓存已清空!'),NULL,'success');

        Typecho_Response::getInstance()->goBack();
    }
    
    /**
     * Tests for file writability
     *
     * is_writable() returns TRUE on Windows servers when you really can't write to
     * the file, based on the read-only attribute. is_writable() is also unreliable
     * on Unix servers if safe_mode is on.
     *
     * @link    https://bugs.php.net/bug.php?id=54709
     * @param   string
     * @return  bool
     */
    private static function is_really_writable($file)
    {
        // Create cache directory if not exists
        if (!file_exists($file))
        {
            mkdir($file, 0755);
        }
        // If we're on a Unix server with safe_mode off we call is_writable
        if (DIRECTORY_SEPARATOR === '/' && (version_compare(PHP_VERSION, '5.4', '>=') OR ! ini_get('safe_mode')))
        {
            return is_writable($file);
        }
        /* For Windows servers and safe_mode "on" installations we'll actually
         * write a file then read it. Bah...
         */
        if (is_dir($file))
        {
            $file = rtrim($file, '/').'/'.md5(mt_rand());
            if (($fp = @fopen($file, 'ab')) === FALSE)
            {
                return FALSE;
            }
            fclose($fp);
            @chmod($file, 0777);
            @unlink($file);
            return TRUE;
        }
        elseif ( ! is_file($file) OR ($fp = @fopen($file, 'ab')) === FALSE)
        {
            return FALSE;
        }
        fclose($fp);
        return TRUE;
    }
}