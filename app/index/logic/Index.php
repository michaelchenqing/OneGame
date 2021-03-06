<?php
// +---------------------------------------------------------------------+
// | OneBase    | [ WE CAN DO IT JUST THINK ]                            |
// +---------------------------------------------------------------------+
// | Licensed   | http://www.apache.org/licenses/LICENSE-2.0 )           |
// +---------------------------------------------------------------------+
// | Author     | Bigotry <3162875@qq.com>                               |
// +---------------------------------------------------------------------+
// | Repository | https://gitee.com/Bigotry/OneBase                      |
// +---------------------------------------------------------------------+

namespace app\index\logic;

/**
 * 首页逻辑
 */
class Index extends IndexBase
{
    
    /**
     * 获取首页数据
     */
    public function getIndexData()
    {
        
        $cache_key = 'cache_index_index_data';
        
        $data = cache($cache_key);
        
        if (empty($data)) {

            $data['slider_list']            =   $this->getSlider();

            $data['article_list']           =   $this->getArticle();

            $data['server_list']            =   $this->getServer();

            $data['recommend_game_list']    =   $this->getRecommendGame();

            $data['more_game_list']         =   $this->getMoreGame();

            $data['gift_list']              =   $this->getGift();
        
            cache($cache_key, $data, 60);
        }
        
        $data['play_list']              =   $this->getPlay();
        
        return $data;
    }
    
    /**
     * 轮播
     */
    public function getSlider()
    {
        
        return $this->modelSlider->getList([], true, 'sort desc', false);
    }
    
    /**
     * 新闻资讯
     */
    public function getArticle()
    {
        
        $this->modelArticle->alias('a');
        
        $join = [
                    [SYS_DB_PREFIX . 'article_category c', 'a.category_id = c.id'],
                ];
        
        $where['a.' . DATA_STATUS_NAME] = ['neq', DATA_DELETE];
        
        $field = 'a.id,a.name,c.name as category_name';
        
        return $this->modelArticle->getList($where, $field, 'a.create_time desc', false, $join, null, 9);
    }
    
    /**
     * 最近新服
     */
    public function getServer()
    {
        
        $this->modelWgServer->alias('s');
        
        $join = [
                    [SYS_DB_PREFIX . 'wg_game g', 's.game_id = g.id'],
                ];
        
        $where['s.' . DATA_STATUS_NAME] = DATA_NORMAL;
        $where['s.start_time']          = ['elt', TIME_NOW];
        $where['s.maintain_end_time']   = ['elt', TIME_NOW];
        
        $field = 'g.game_logo,g.game_name,g.game_code,s.server_name,s.start_time,s.cp_server_id';
        
        return $this->modelWgServer->getList($where, $field, 's.start_time desc', false, $join, null, 7);
    }
    
    /**
     * 推荐游戏
     */
    public function getRecommendGame()
    {
        
        $where[DATA_STATUS_NAME]      = ['neq', DATA_DELETE];
        $where['maintain_end_time']   = ['elt', TIME_NOW];
        $where['is_recommend']        = DATA_NORMAL;
        
        return $this->modelWgGame->getList($where, 'id,game_name,game_code,game_cover', 'sort desc', false, [], null, 2);
    }
    
    /**
     * 更多游戏
     */
    public function getMoreGame()
    {
        
        $this->modelWgGame->alias('g');
        
        $join = [
                    [SYS_DB_PREFIX . 'wg_category c', 'g.game_category_id = c.id'],
                ];
        
        $where['g.' . DATA_STATUS_NAME] = ['neq', DATA_DELETE];
        $where['g.maintain_end_time']   = ['elt', TIME_NOW];
        $where['g.is_hot']              = ['neq', DATA_NORMAL];
        
        $field = 'g.game_logo,g.game_head,g.game_name,g.game_code,c.category_name';
        
        return $this->modelWgGame->getList($where, $field, 'g.sort desc', false, $join, null, 6);
    }
    
    /**
     * 游戏礼包
     */
    public function getGift()
    {
        
        $this->modelWgGift->alias('gi');
        
        $join = [
                    [SYS_DB_PREFIX . 'wg_game ga', 'ga.id = gi.game_id'],
                ];
        
        $where['gi.' . DATA_STATUS_NAME] = ['neq', DATA_DELETE];
        
        $field = 'gi.id,gi.gift_name,gi.create_time,ga.game_logo,ga.game_head';
        
        $list = $this->modelWgGift->getList($where, $field, 'gi.create_time desc', false, $join, null, 6);
        
        $list_ids = array_extract($list);
        
        $key_where['is_get']   = DATA_DISABLE;
        $key_where['gift_id']  = ['in', $list_ids];
        
        $list_number = $this->modelWgGiftKey->where($key_where)->group("gift_id")->field("gift_id,count('id') as number")->select();
        
        foreach ($list as &$info)
        {
            foreach ($list_number as $number_info)
            {
                $info['id'] != $number_info['gift_id'] ?: $info['number'] = $number_info['number'];
            }
        }
        
        return $list;
    }
    
    /**
     * 最近玩过
     */
    public function getPlay()
    {
        
        $member_id = is_login();
        
        if (empty($member_id)) {
            
            return [];
        }
        
        $this->modelWgPlayer->alias('wp');
        
        $join = [
                    [SYS_DB_PREFIX . 'wg_game ga', 'ga.id = wp.game_id'],
                    [SYS_DB_PREFIX . 'wg_server ws', 'ws.id = wp.server_id'],
                ];
        
        $where['wp.' . DATA_STATUS_NAME] = ['neq', DATA_DELETE];
        $where['wp.member_id'] = $member_id;
        
        $field = 'wp.id,ga.game_logo,ga.game_head,ga.game_name,ga.game_code,ws.server_name,ws.cp_server_id';
        
        $list = $this->modelWgPlayer->getList($where, $field, 'wp.update_time desc', false, $join, null, 7);
        
        return $list;
    }
    
    /**
     * 获取服务器选项
     */
    public function getServerOption($game_id = 0)
    {
        
        !empty($game_id) && $where['game_id'] = $game_id;
        
        $where['status'] = DATA_NORMAL;
        
        $game_server_list = $this->modelWgServer->getList($where, true, 'id desc', false);
        
        $text = "<option value='0'>请选择服务器</option>";
        
        foreach ($game_server_list as $info)
        {
            $text .= "<option value='".$info['id']."'>".$info['server_name']."</option>";
        }
        
        return $text;
    }
    
    /**
     * 获取角色选项
     */
    public function getRoleOption($game_id = 0, $server_id = 0, $mid = 0)
    {
        
        $game_info = $this->modelWgGame->getInfo(['id' => $game_id]);
        
        if (empty($game_info)) {

            throw_response_exception('游戏不存在', 'html');
        }
        
        $driver = SYS_DRIVER_DIR_NAME . ucfirst($game_info['game_code']);
        
        $server_info = $this->modelWgServer->getInfo(['id' => $server_id]);
        
        if (empty($server_info)) {

            throw_response_exception('服务器不存在', 'html');
        }
        
        if (empty($mid)) {
            
            $mid = is_login();
        }
        
        if (!class_exists('app\common\service\webgame\driver\\' . ucfirst($game_info['game_code']))) {
            
             return "<option value=''>未接入游戏</option>";
        }
            
        $roles = $this->serviceWebgame->$driver->roles($mid, $server_info['cp_server_id']);
        
        $text = "<option value=''>请选择角色</option>";
        
        if ($roles == false) {
            
            return $text;
        } else {
        
            foreach ($roles as $info)
            {
                $text .= "<option value='".$info['role_id']."' id='".$info['role_id']."'>".$info['nickname']."</option>";
            }

            return $text;
        }
    }
    
    /**
     * 通过code获取游戏信息
     */
    public function getGameByCode($code = '')
    {
        
        $where['code'] = $code;
        
        $code_info = $this->modelWgCode->getInfo($where);
        
        if (empty($code_info)) {

            throw_response_exception('CODE不存在', 'html');
        }
        
        $game_info = $this->modelWgGame->getInfo(['id' => $code_info['game_id']]);
        
        return $game_info;
    }
}
