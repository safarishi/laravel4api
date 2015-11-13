<?php

class UserAvatarController extends CommonController
{
    /**
     * 上传头像
     *
     * @param string $uid 用户id
     */
    public static function uploadAvatar($uid)
    {
        $ext = 'png';
        $subDir = substr($uid, -1);
        $storageDir = Config::get('imagecache::paths.avatar_url').'/'.$subDir.'/';
        $storageName = $uid;
        $storagePath = $subDir.'/'.$storageName.'.'.$ext;

        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        Image::make(Input::file('avatar_url'))->encode($ext)->save($storageDir.$storageName.'.'.$ext);

        return Config::get('imagecache::paths.avatar_url_prefix').'/'.$storagePath;
    }
}
