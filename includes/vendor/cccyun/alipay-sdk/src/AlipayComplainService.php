<?php

namespace Alipay;

use Exception;

/**
 * 支付宝交易投诉处理类
 * @see https://opendocs.alipay.com/open/02z18r
 * @see https://opendocs.alipay.com/pre-open/repo-02ei7s
 */
class AlipayComplainService extends AlipayService
{
    public function __construct($config)
    {
        parent::__construct($config);
    }

	/**
	 * 查询单条交易投诉详情
	 * @param string $complain_event_id 支付宝侧投诉单号
	 * @return mixed {"complain_event_id":"支付宝侧投诉单号","status":"MERCHANT_PROCESSING","trade_no":"支付宝交易号","merchant_order_no":"商家订单号","gmt_create":"投诉单创建时间","gmt_modified":"投诉单修改时间","gmt_finished":"投诉单完结时间","leaf_category_name":"用户投诉诉求","complain_reason":"用户投诉原因","content":"用户投诉内容","images":[],"phone_no":"投诉人电话号码","trade_amount":"交易金额","reply_detail_infos":[]}
	 * @throws Exception
	 */
    public function query(string $complain_event_id)
    {
        $apiName = 'alipay.merchant.tradecomplain.query';
        $bizContent = [
            'complain_event_id' => $complain_event_id,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

	/**
	 * 查询交易投诉列表
	 * @param string|null $status 状态
	 * @param string|null $begin_time 查询开始时间
	 * @param string|null $end_time 查询结束时间
	 * @param int $page_num 当前页
	 * @param int $page_size 每页条数,最多支持20条
	 * @return mixed {"page_size":10,"page_num":1,"total_page_num":5,"total_num":55,"trade_complain_infos":[]}
	 * @throws Exception
	 */
    public function batchQuery(string $status = null, string $begin_time = null, string $end_time = null, int $page_num = 1, int $page_size = 10)
    {
        $apiName = 'alipay.merchant.tradecomplain.batchquery';
        $bizContent = [
            'page_num' => $page_num,
            'page_size' => $page_size,
        ];
        if ($status) $bizContent['status'] = $status;
        if ($begin_time) $bizContent['begin_time'] = $begin_time;
        if ($end_time) $bizContent['end_time'] = $end_time;
        return $this->aopExecute($apiName, $bizContent);
    }

	/**
	 * 商户上传处理图片
	 * @param string $file_path 文件路径
	 * @param string $file_name 文件名
	 * @return string 图片资源标识
	 * @throws Exception
	 */
    public function imageUpload(string $file_path, string $file_name): string
    {
        $image_type = array_pop(explode('.',$file_name));
        if (empty($image_type)) $image_type = 'png';
        $apiName = 'alipay.merchant.image.upload';
        $params = [
            'image_type' => $image_type,
            'image_content' => new \CURLFile($file_path, '', $file_name),
        ];
        $result = $this->aopExecute($apiName, null, $params);
        return $result['image_id'];
    }

	/**
	 * 商家处理交易投诉
	 * @param string $complain_event_id 投诉单号
	 * @param string $feedback_code 反馈类目ID
	 * @param string $feedback_content 反馈内容
	 * @param string|null $feedback_images 反馈图片id列表(多个用逗号隔开)
	 * @return bool
	 * @throws Exception
	 */
    public function feedbackSubmit(string $complain_event_id, string $feedback_code, string $feedback_content, string $feedback_images = null): bool
    {
        $apiName = 'alipay.merchant.tradecomplain.feedback.submit';
        $bizContent = [
            'complain_event_id' => $complain_event_id,
            'feedback_code' => $feedback_code,
            'feedback_content' => $feedback_content,
        ];
        if ($feedback_images) $bizContent['feedback_images'] = $feedback_images;
        $this->aopExecute($apiName, $bizContent);
        return true;
    }

	/**
	 * 商家留言回复
	 * @param string $complain_event_id 投诉单号
	 * @param string $reply_content 回复内容
	 * @param string|null $reply_images 回复图片(多个用逗号隔开)
	 * @return bool
	 * @throws Exception
	 */
    public function replySubmit(string $complain_event_id, string $reply_content, string $reply_images = null): bool
    {
        $apiName = 'alipay.merchant.tradecomplain.reply.submit';
        $bizContent = [
            'complain_event_id' => $complain_event_id,
            'reply_content' => $reply_content,
        ];
        if ($reply_images) $bizContent['reply_images'] = $reply_images;
        $this->aopExecute($apiName, $bizContent);
        return true;
    }

	/**
	 * 商家补充凭证
	 * @param string $complain_event_id 投诉单号
	 * @param string $supplement_content 文字凭证
	 * @param string|null $supplement_images 图片凭证(多个用逗号隔开)
	 * @return bool
	 * @throws Exception
	 */
    public function supplementSubmit(string $complain_event_id, string $supplement_content, string $supplement_images = null): bool
    {
        $apiName = 'alipay.merchant.tradecomplain.supplement.submit';
        $bizContent = [
            'complain_event_id' => $complain_event_id,
            'supplement_content' => $supplement_content,
        ];
        if ($supplement_images) $bizContent['supplement_images'] = $supplement_images;
        $this->aopExecute($apiName, $bizContent);
        return true;
    }


	/**
	 * RiskGO查询单条交易投诉详情
	 * @param string $complain_id 支付宝侧投诉单号
	 * @return mixed
	 * @throws Exception
	 */
    public function riskquery(string $complain_id)
    {
        $apiName = 'alipay.security.risk.complaint.info.query';
        $bizContent = [
            'complain_id' => $complain_id,
        ];
        return $this->aopExecute($apiName, $bizContent);
    }

	/**
	 * RiskGO查询交易投诉列表
	 * @param string|null $status 状态
	 * @param string|null $begin_time 查询开始时间
	 * @param string|null $end_time 查询结束时间
	 * @param int $page_num 当前页
	 * @param int $page_size 每页条数,最多支持20条
	 * @return mixed {"page_size":10,"page_num":1,"total_page_num":5,"total_num":55,"trade_complain_infos":[]}
	 * @throws Exception
	 */
    public function riskbatchQuery(string $status = null, string $begin_time = null, string $end_time = null, int $page_num = 1, int $page_size = 10)
    {
        $apiName = 'alipay.security.risk.complaint.info.batchquery';
        $bizContent = [
            'current_page_num' => $page_num,
            'page_size' => $page_size,
        ];
        if ($status) $bizContent['status_list'] = [$status];
        if ($begin_time) $bizContent['begin_time'] = $begin_time;
        if ($end_time) $bizContent['end_time'] = $end_time;
        return $this->aopExecute($apiName, $bizContent);
    }

	/**
	 * RiskGO商户上传处理图片
	 * @param string $file_path 文件路径
	 * @param string $file_name 文件名
	 * @return mixed 图片资源标识
	 * @throws Exception
	 */
    public function riskimageUpload(string $file_path, string $file_name)
    {
        $apiName = 'alipay.security.risk.complaint.file.upload';
        $params = [
            'file_content' => new \CURLFile($file_path, '', $file_name),
        ];
	    return $this->aopExecute($apiName, null, $params);
    }

	/**
	 * RiskGO商家处理交易投诉
	 * @param string $complain_id 投诉单号
	 * @param string $process_code 投诉处理结果码
	 * @param string $remark 备注
	 * @param array|null $img_file_list 图片文件列表
	 * @return bool
	 * @throws Exception
	 */
    public function riskfeedbackSubmit(string $complain_id, string $process_code, string $remark, array $img_file_list = null): bool
    {
        $apiName = 'alipay.security.risk.complaint.process.finish';
        $bizContent = [
            'id_list' => [$complain_id],
            'process_code' => $process_code,
            'remark' => $remark
        ];
        if ($img_file_list) $bizContent['img_file_list'] = $img_file_list;
        $result = $this->aopExecute($apiName, $bizContent);
        return $result['complaint_process_success'];
    }

    /**
     * RiskGO商家留言回复
     * @param string $complain_id 投诉单号
     * @param string $reply_content 回复内容
     * @return bool
     * @throws Exception
     */
    public function riskReplySend(string $complain_id, string $reply_content): bool
    {
        $apiName = 'alipay.security.risk.complaint.reply.send';
        $bizContent = [
            'complaint_id' => $complain_id,
            'reply_content' => $reply_content,
        ];
        $this->aopExecute($apiName, $bizContent);
        return true;
    }

}