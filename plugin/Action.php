<?php

namespace TypechoPlugin\TeXtend;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Widget;
use Utils\Helper;
use Widget\ActionInterface;

class Action extends Widget implements ActionInterface
{
    private $db;
    private $options;
    private $prefix;

    public function action()
    {
        $this->db = Db::get();
        $this->prefix = $this->db->getPrefix();
        $this->options = Helper::options();

        $cid = $this->request->cid;
        if (!$cid)
            $this->response->throwJson(array('status' => 0, 'msg' => _t('请选择喜欢的文章!')));
        $likes = Cookie::get('__post_likes');
        if (empty($likes)) {
            $likes = array();
        } else {
            $likes = explode(',', $likes);
        }

        if (!in_array($cid, $likes)) {
            $row = $this->db->fetchRow($this->db->select('likesNum')->from('table.contents')->where('cid = ?', $cid)->limit(1));
            $this->db->query($this->db->update('table.contents')->rows(array('likesNum' => (int)$row['likesNum'] + 1))->where('cid = ?', $cid));
            array_push($likes, $cid);
            $likes = implode(',', $likes);
            Cookie::set('__post_likes', $likes); //记录查看cookie
            $this->response->throwJson(array('status' => 1, 'msg' => _t('成功点赞!')));
        }
        $this->response->throwJson(array('status' => 0, 'msg' => _t('你已经点赞过了!')));
    }

}
