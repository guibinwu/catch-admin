<?php
// +----------------------------------------------------------------------
// | CatchAdmin [Just Like ～ ]
// +----------------------------------------------------------------------
// | Copyright (c) 2017~2020 http://catchadmin.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( https://github.com/yanwenwu/catch-admin/blob/master/LICENSE.txt )
// +----------------------------------------------------------------------
// | Author: JaguarJack [ njphper@gmail.com ]
// +----------------------------------------------------------------------
namespace catchAdmin\wechat\repository;

use catchAdmin\wechat\model\WechatReply;
use catcher\base\CatchRepository;
use catcher\library\WeChat;

class WechatReplyRepository extends CatchRepository
{
    protected $reply;

    public function __construct(WechatReply $reply)
    {
        $this->reply = $reply;
    }

    public function model()
    {
        return $this->reply;
    }

    public function storeBy(array $data)
    {
        $material = WeChat::officialAccount()->material;

        $mediaUrl = $this->getLocalPath($data['media_url'] ?? '');
        $imageUrl = $this->getLocalPath($data['image_url'] ?? '');

        if ($imageUrl) {
            // 音乐
            if ($data['type'] == WechatReply::MUSIC) {
               $data['media_id'] = $material->uploadThumb($imageUrl)['media_id'];
            } else {
                $data['media_id'] = $material->uploadImage($imageUrl)['media_id'];
            }
        }
        // 语音
        if ($data['type'] == WechatReply::VOICE) {
            $data['media_id'] = $material->uploadVoice($mediaUrl)['media_id'];
        }
        // 视频
        if ($data['type'] == WechatReply::VIDEO) {
            $data['media_id'] = $material->uploadVideo($mediaUrl, $data['title'], $data['content'])['media_id'];
        }

        return parent::storeBy($data); // TODO: Change the autogenerated stub
    }

    /**
     * 获取本地地址
     *
     * @time 2020年06月29日
     * @param $url
     * @return string
     */
    protected function getLocalPath($url)
    {
        return $url ? '.' . trim(str_replace(config('filesystem.disks.local.domain'), '', $url)) : '';
    }
}