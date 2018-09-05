<?php
/**
 * Created by PhpStorm.
 * User: moTzxx
 * Date: 2018/3/13
 * Time: 8:43
 */
namespace WxApi\Controller;
use Think\Controller;
use Common\Controller\WXBizDataCryptController;
use WxApi\Model\OrderModel;
use WxApi\service\UserToken;

class WxController extends Controller
{
    protected $app_id;
    protected $app_secret;
    protected $get_token_url;

    public function __construct()
    {
        /**
         * 请在此处填写你的小程序 APPID和秘钥
         */
        $this->app_id = C('WeChat')['app_id']; //自行补充
        $this->app_secret = C('WeChat')['app_secret']; //自行补充
        $this->get_token_url = 'https://api.weixin.qq.com/cgi-bin/token?'
            .'grant_type=client_credential&appid=%s&secret=%s';
    }

    /**
     * 微信获取 AccessToken
     */
    public function getAccessToken(){
        $access_token = $this->opGetAccessToken();
        if(!$access_token){
            $this->return_err('获取access_token时异常，微信内部错误');
        }else{
            $this->return_data(['access_token'=>$access_token]);
        }
    }

    /**
     * 提取公共方法 - 获取 AccessToken
     * @return bool
     */
    public function opGetAccessToken(){
        $get_token_url = sprintf($this->get_token_url,
            $this->app_id,$this->app_secret);
        $result = $this->curl_get($get_token_url);
        $wxResult = json_decode($result,true);
        if(empty($wxResult)){
            return false;
        }else{
            $access_token = $wxResult['access_token'];
            return $access_token;
        }
    }
    /**
     * 获取小程序模板库标题列表
     * TODO 没必要使用，小程序账号后台可以视图查看
     */
    public function getAllTemplateList(){
        $opUrl = 'https://api.weixin.qq.com/cgi-bin/wxopen/template/library/list?'
            .'access_token=%s';
        $rawPost = ['count'=>20,'offset'=>0];
        $this->opTemplateData($opUrl,$rawPost,'getAllTemplateList');
    }

    /**
     * 获取模板库某个模板标题下关键词库
     * TODO 没必要使用，小程序账号后台可以视图查看
     */
    public function getTemplateKey(){
        $opUrl = "https://api.weixin.qq.com/cgi-bin/wxopen/template/library/get?access_token=%s";
        $rawPost = ['id'=>'AT0002'];
        $this->opTemplateData($opUrl,$rawPost,'getTemplateKey');
    }

    /**
     * 获取帐号下已存在的模板列表
     * TODO 没必要使用，小程序账号后台可以视图查看
     */
    public function getExistTemplateList(){
        $opUrl = "https://api.weixin.qq.com/cgi-bin/wxopen/template/list?access_token=%s";
        $rawPost = ['count'=>20,'offset'=>0];
        $this->opTemplateData($opUrl,$rawPost,'getExistTemplateList');
    }

    /**
     * 提取公共方法 获取模板数据
     * @param string $opUrl
     * @param array $rawPost
     * @param string $method
     */
    public function opTemplateData($opUrl = '',$rawPost = [],$method = ''){
        $access_token = $this->opGetAccessToken();
        if(!$access_token){
            $this->return_err('获取 access_token 时异常，微信内部错误');
        }else{
            $templateUrl = sprintf($opUrl,$access_token);
            $listRes = $this->curl_post($templateUrl,$rawPost);
            $wxResult = json_decode($listRes,true);
            if($wxResult['errcode']){
                $this->return_err($method.' - Failed!',$wxResult);
            }else{
                //var_dump($wxResult);
                $this->return_data($wxResult);
            }
        }
    }

    /**
     * 支付成功后的 服务号通知消息的发送
     * form_id  表单提交场景下， 为 submit 事件带上的 formId；
     *          支付场景下，为本次支付的 prepay_id
     * $rawPost TODO 其参数解释请参考 sendTemplate()!!!
     */
    public function sendTemplatePaySuccess(){
        if (IS_POST){
            $form_id = I('post.form_id');
            /*-------------------此为项目的特定业务处理---------------------------*/
            $order_sn = I('post.sn')?I('post.sn'):'';
            $orderModel = new OrderModel();
            $sendTemplateData = $orderModel->getSendTemplateData($order_sn);
            /*-----------以上数据 $sendTemplateData 可根据自己的实际业务进行获取-----*/
            $rawPost = [
                'touser' => $sendTemplateData['mini_openid'] ,
                'template_id' => 'yASr1SdzgV7_gRzKgqYI3t7um-3pIGXrpCcHUHVIJz4',
                'form_id' => $form_id,
                'data' => [
                    'keyword1' => ['value' => $sendTemplateData['order_sn']],
                    'keyword2' => ['value' => $sendTemplateData['pay_time']],
                    'keyword3' => ['value' => $sendTemplateData['goodsMsg']],
                    'keyword4' => ['value' => $sendTemplateData['order_amount']],
                    'keyword5' => ['value' => $sendTemplateData['addressMsg']],
                    'keyword6' => ['value' => $sendTemplateData['tipMsg']],
                ]
            ];
            $this->sendTemplate($rawPost,'sendTemplatePaySuccess');
        }else{
            return $this->return_err('Sorry,请求不合法');
        }

    }
    /**
     * 发送模版消息
     * @param string $method_msg  方法名称
     * TODO @param array $rawPost 参数说明：
     *    参数              必填   说明
     *  touser	            是   接收者（用户）的 openid
        template_id	        是	所需下发的模板消息的id
        page	            否	点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
        form_id	            是	表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
        data	            是	模板内容，不填则下发空模板
        color	            否	模板内容字体的颜色，不填默认黑色 【废弃】
        emphasis_keyword	否	模板需要放大的关键词，不填则默认无放大
     */
    public function sendTemplate($rawPost = [],$method_msg = 'sendTemplate'){
        $opUrl = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=%s";
        $this->opTemplateData($opUrl,$rawPost,$method_msg);
    }


    /**
     * 错误返回提示
     * @param string $errMsg 错误信息
     * @param string $errMsg
     * @param array $data
     */
    protected function return_err($errMsg = 'fail',$data = array())
    {
        exit(json_encode(array('status' => 0, 'result' => $errMsg, 'data' => $data)));
    }


    /**
     * 正确返回
     * @param    array $data 要返回的数组
     * @return  json的数据
     */
    protected function return_data($data = array())
    {
        exit(json_encode(array('status' => 1, 'result' => 'success', 'data' => $data)));
    }

    /**
     * PHP 处理 post数据请求
     * @param $url 请求地址
     * @param array $params 参数数组
     * @return mixed
     */
    protected function curl_post($url,array $params = array()){
        //TODO 转化为 json 数据
        $data_string = json_encode($params);
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data_string);
        curl_setopt($ch,CURLOPT_HTTPHEADER,
            array(
                'Content-Type: application/json'
            )
        );
        $data = curl_exec($ch);
        curl_close($ch);
        return ($data);
    }

    /**
     * @param string $url get请求地址
     * @param int $httpCode 返回状态码
     * @return mixed
     */
    protected function curl_get($url,&$httpCode = 0){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);

        //不做证书校验，部署在linux环境下请改位true
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,10);
        $file_contents = curl_exec($ch);
        $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $file_contents;
    }
}