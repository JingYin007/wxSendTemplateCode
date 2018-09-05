
/**
 * 进行Form表单的提交
 */
bindPayFormSubmit: function(event) {
    console.log(event);
    var formId = event.detail.formId;
    this.setData({
        formId: formId
    });
}

/**
 * 微信支付成功后的 消息模板的发送
 */
sendTemplatePaySuccess: function() {
    var self = this;
    var postData = {
        //sn: self.data.order_sn,
        form_id: self.data.formId
    };
    self.http_post('https://xxx.com/wx/sendTemplatePaySuccess', postData, (data) => {
        //TODO 此处可做跳转业务
        // wx.navigateTo({
        // url: '/pages/cart/results/index?status=1&type=pay&orderInfo=' + JSON.stringify(self.data.orderInfo),
    });
})
},

/**
 * 封装 http 函数，默认‘GET’ 提交
 */
http_post:function(toUrl, postData, httpCallBack) {
    wx.request({
        url:  toUrl,
        data: postData,
        method: 'POST', // OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, CONNECT
        header: {
            'content-type': 'application/x-www-form-urlencoded;charset=utf-8',
        },
        success: function (res) {
            //回调处理
            return typeof httpCallBack == "function" && httpCallBack(res.data);
        },
        fail: function (error) {
            console.log(error);
        }
    })
},