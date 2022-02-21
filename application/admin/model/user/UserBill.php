<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\admin\model\user;

use service\SystemConfigService;
use traits\ModelTrait;
use basic\ModelBasic;
use app\admin\model\wechat\WechatUser;

/**
 * 用户消费新增金额明细 model
 * Class User
 * @package app\admin\model\user
 */

class UserBill extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time'];

    protected function setAddTimeAttr()
    {
        return time();
    }


    public static function income($title,$uid,$category,$type,$number,$link_id = 0,$balance = 0,$mark = '',$status = 1){
        $pm = 1;
        return self::set(compact('title','uid','link_id','category','type','number','balance','mark','status','pm'));
    }
    public static function expend($title, $uid, $category, $type, $number, $link_id = 0, $balance = 0, $mark = '', $status = 1)
    {
        $pm = 0;
        return self::set(compact('title', 'uid', 'link_id', 'category', 'type', 'number', 'balance', 'mark', 'status', 'pm'));
    }
    /*
     *  获取佣金记录
     * */
    public static function getBillList($where,$uid)
    {
        $model=self::where('uid',$uid)->where('category','now_money')->where('type','in',['rake_back','rake_back_one','rake_back_two','extract'])
            ->order('add_time desc');
        if($where['start_date'] && $where['end_date']) $model=$model->where('add_time','between',[strtotime($where['start_date']),strtotime($where['end_date'])]);
        if($where['excel'])
            $list=$model->select();
        else
            $list=$model->page((int)$where['page'],(int)$where['limit'])->select();
        $list=count($list) ? $list->toArray() : [];
        $excel=[];
        foreach($list as &$item){
            $item['add_time']=date('Y-m-d H:i:s',$item['add_time']);
            $item['_type']=$item['pm'] ? '入账':'提现';
            switch ($item['type']){
                case 'rake_back':
                    $item['order_type']='直推订单';
                    break;
                case 'rake_back_one':
                    $item['order_type']='直推订单';
                    break;
                case 'rake_back_two':
                    $item['order_type']='裂变订单';
                    break;
                default:
                    $item['order_type']='';
                    break;
            }
            if($item['pm']){
                $item['pay_pice']=self::getDb('store_order')->where('id',$item['link_id'])->value('pay_price');
            }else{
                $item['pay_pice']=0;
            }
            $excel[]=[$item['add_time'],$item['_type'],$item['order_type'],$item['pay_pice'],$item['number'],$item['balance']];
        }
        if($where['excel']){
            \service\PHPExcelService::setExcelHeader(['时间','入账/结算','订单类型','订单金额','佣金金额','佣金余额'])
                ->setExcelTile('佣金记录导出','佣金记录信息'.time(),' 生成时间：'.date('Y-m-d H:i:s',time()))
                ->setExcelContent($excel)->ExcelSave();
        }
        return $list;
    }

    /**获取用户佣金金额
     * @param int $uid
     */
    public static function getCommissionAmount($uid=0){
        $brokerage=self::where('uid','in',$uid)->where('category','now_money')
            ->where('type','brokerage')->where('pm',1)->where('status',1)->sum('number');
        $brokerage_return=self::where('uid','in',$uid)->where('category','now_money')
            ->where('type','brokerage_return')->where('pm',0)->where('status',1)->sum('number');
        $commission=bcsub($brokerage,$brokerage_return,2);
        return $commission;
    }
    /**
     * 获取柱状图和饼状图数据
     *
     * */
    public static function getUserBillChart($where,$category='now_money',$type='brokerage',$pm=1,$zoom=15){
        $model=self::getModelTime($where,new self());
        $list=$model->field(['FROM_UNIXTIME(add_time,"%Y-%c-%d") as un_time','sum(number) as sum_number'])
            ->order('un_time asc')
            ->where(['category'=>$category,'type'=>$type,'pm'=>$pm])
            ->group('un_time')
            ->select();
        if(count($list)) $list=$list->toArray();
        $legdata=[];
        $listdata=[];
        $dataZoom='';
        foreach ($list as $item){
            $legdata[]=$item['un_time'];
            $listdata[]=$item['sum_number'];
        }
        if(count($legdata)>=$zoom) $dataZoom=$legdata[$zoom-1];
        //获取用户分布钱数
        $fenbulist=self::getModelTime($where,new self(),'a.add_time')
            ->alias('a')
            ->join('user r','a.uid=r.uid')
            ->field(['a.uid','sum(a.number) as sum_number','r.nickname'])
            ->where(['a.category'=>$category,'a.type'=>$type,'a.pm'=>$pm])
            ->order('sum_number desc')
            ->group('a.uid')
            ->limit(8)
            ->select();
        //获取用户当前时间段总钱数
        $sum_number=self::getModelTime($where,new self())
            ->where(['category'=>$category,'type'=>$type,'pm'=>$pm])
            ->sum('number');
        if(count($fenbulist)) $fenbulist=$fenbulist->toArray();
        $fenbudate=[];
        $fenbu_legend=[];
        $color=['#ffcccc','#99cc00','#fd99cc','#669966','#66CDAA','#ADFF2F','#00BFFF','#00CED1','#66cccc','#ff9900','#ffcc00','#336699','#cccc00','#99ccff','#990066'];
        foreach ($fenbulist as $key=>$value){
            $fenbu_legend[]=$value['nickname'];
            $items['name']=$value['nickname'];
            $items['value']=bcdiv($value['sum_number'],$sum_number,2)*100;
            $items['itemStyle']['color']=$color[$key];
            $fenbudate[]=$items;
        }
        return compact('legdata','listdata','fenbudate','fenbu_legend','dataZoom');
    }
    //获取头部信息
    public static function getRebateBadge($where){
        $datawhere=['category'=>'now_money','type'=>'brokerage','pm'=>1];
        return [
            [
                'name'=>'返利数(笔)',
                'field'=>'个',
                'count'=>self::getModelTime($where,new self())->where($datawhere)->count(),
                'content'=>'返利总笔数',
                'background_color'=>'layui-bg-blue',
                'sum'=>self::where($datawhere)->count(),
                'class'=>'fa fa-bar-chart',
            ],
            [
                'name'=>'返利金额（元）',
                'field'=>'个',
                'count'=>self::getModelTime($where,new self())->where($datawhere)->sum('number'),
                'content'=>'返利总金额',
                'background_color'=>'layui-bg-cyan',
                'sum'=>self::where($datawhere)->sum('number'),
                'class'=>'fa fa-line-chart',
            ],
        ];
    }
    //获取返佣用户信息列表
    public static function getFanList($where){
        $datawhere=['a.category'=>'now_money','a.type'=>'brokerage','a.pm'=>1];
        $list=self::alias('a')->join('user r','a.uid=r.uid')
            ->where($datawhere)
            ->order('a.number desc')
            ->join('store_order o','o.id=a.link_id')
            ->field(['o.order_id','FROM_UNIXTIME(a.add_time,"%Y-%c-%d") as add_time','a.uid','o.uid as down_uid','r.nickname','r.avatar','r.spread_uid','r.level','a.number'])
            ->page((int)$where['page'],(int)$where['limit'])
            ->select();
        if(count($list)) $list=$list->toArray();
        return $list;
    }
    //获取返佣用户总人数
    public static function getFanCount(){
        $datawhere=['a.category'=>'now_money','a.type'=>'brokerage','a.pm'=>1];
        return self::alias('a')->join('user r','a.uid=r.uid')->join('store_order o','o.id=a.link_id')->where($datawhere)->count();
    }
    //获取用户充值数据
    public static function getEchartsRecharge($where,$limit=15){
        $datawhere=['category'=>'now_money','pm'=>1];
        $list=self::getModelTime($where,self::where($datawhere)->where('type','in',['recharge','system_add']))
            ->field(['sum(number) as sum_money','FROM_UNIXTIME(add_time,"%Y-%c-%d") as un_time','count(id) as count'])
            ->group('un_time')
            ->order('un_time asc')
            ->select();
        if(count($list)) $list=$list->toArray();
        $sum_count=self::getModelTime($where,self::where($datawhere)->where('type','in',['recharge','system_add']))->count();
        $xdata=[];
        $seriesdata=[];
        $data=[];
        $zoom='';
        foreach ($list as $value){
            $xdata[]=$value['un_time'];
            $seriesdata[]=$value['sum_money'];
            $data[]=$value['count'];
        }
        if(count($xdata)>$limit){
            $zoom=$xdata[$limit-5];
        }
        return compact('xdata','seriesdata','data','zoom');
    }
    //获取佣金提现列表
    public static function getExtrctOneList($where,$uid){
        $list=self::setOneWhere($where,$uid)->order('add_time desc')
            ->field(['number','link_id','mark','FROM_UNIXTIME(add_time,"%Y-%m-%d %H:%i:%s") as _add_time','status'])
            ->select();
        count($list) && $list=$list->toArray();
        $count=self::setOneWhere($where,$uid)->count();
        foreach ($list as &$value){
            $value['order_id']=db('store_order')->where(['order_id'=>$value['link_id']])->value('order_id');
        }
        return ['data'=>$list,'count'=>$count];
    }
    //设置单个用户查询
    public static function setOneWhere($where,$uid){
        $model=self::where(['uid'=>$uid,'category'=>'now_money','type'=>'brokerage']);
        $time['data']='';
        if($where['start_time']!='' && $where['end_time']!=''){
            $time['data']=$where['start_time'].' - '.$where['end_time'];
            $model=self::getModelTime($time,$model);
        }
        if($where['nickname']!=''){
            $model=$model->where('link_id|mark','like',"%$where[nickname]%");
        }
        return $model;
    }
    //查询积分个人明细
    public static function getOneIntegralList($where){
        return self::setWhereList(
            $where,
            ['deduction','system_add'],
            ['title','number','balance','mark','FROM_UNIXTIME(add_time,"%Y-%m-%d") as add_time']
        );
    }
    //查询个人签到明细
    public static function getOneSignList($where){
        return self::setWhereList(
            $where,'sign',
            ['title','number','mark','FROM_UNIXTIME(add_time,"%Y-%m-%d") as add_time']
            );
    }
    //查询个人余额变动记录
    public static function getOneBalanceChangList($where){
         $list=self::setWhereList(
            $where,
            ['system_add', 'pay_product', 'extract','extract_fail','pay_goods','pay_sign_up', 'pay_product_refund', 'system_sub'],
            ['FROM_UNIXTIME(add_time,"%Y-%m-%d") as add_time','title','type','mark','number','balance','pm','status'],
            'now_money'
        );
         foreach ($list as &$item){
            switch ($item['type']){
                case 'system_add':
                    $item['_type']='系统添加';
                    break;
                case 'pay_product':
                    $item['_type']='商品购买';
                    break;
                case 'extract':
                    $item['_type']='提现';
                    break;
                case 'extract_fail':
                    $item['_type']='提现失败';
                    break;
                case 'pay_goods':
                    $item['_type']='购买商品';
                    break;
                case 'pay_sign_up':
                    $item['_type']='活动报名';
                    break;
                case 'pay_product_refund':
                    $item['_type']='退款';
                    break;
                case 'system_sub':
                    $item['_type']='系统减少';
                    break;
            }
            $item['_pm']=$item['pm']==1 ? '获得': '支出';
         }
         return $list;
    }
    //设置where条件分页.返回数据
    public static function setWhereList($where,$type='',$field=[],$category='gold_num'){
        $models=self::where('uid',$where['uid'])
            ->where('category',$category)
            ->page((int)$where['page'],(int)$where['limit'])
            ->field($field);
        if(is_array($type)){
            $models=$models->where('type','in',$type);
        }else{
            $models=$models->where('type',$type);
        }
        return ($list=$models->select()) && count($list) ? $list->toArray():[];
    }
    //获取积分统计头部信息
    public static function getScoreBadgeList($where){
        return [
            [
                'name'=>'总积分',
                'field'=>'个',
                'count'=>self::getModelTime($where,new self())->where('category','integral')->where('type','in',['gain','system_sub','deduction','sign'])->sum('number'),
                'background_color'=>'layui-bg-blue',
                'col'=>4,
            ],
            [
                'name'=>'已使用积分',
                'field'=>'个',
                'count'=>self::getModelTime($where,new self())->where('category','integral')->where('type','deduction')->sum('number'),
                'background_color'=>'layui-bg-cyan',
                'col'=>4,
            ],
            [
                'name'=>'未使用积分',
                'field'=>'个',
                'count'=>self::getModelTime($where,db('user'))->sum('integral'),
                'background_color'=>'layui-bg-cyan',
                'col'=>4,
            ],
        ];
    }
    //获取积分统计曲线图和柱状图
    public static function getScoreCurve($where){
        //发放积分趋势图
         $list=self::getModelTime($where,self::where('category','integral')
            ->field(['FROM_UNIXTIME(add_time,"%Y-%m-%d") as _add_time','sum(number) as sum_number'])
            ->group('_add_time')->order('_add_time asc'))->select()->toArray();
         $date=[];
         $zoom='';
         $seriesdata=[];
         foreach ($list as $item){
             $date[]=$item['_add_time'];
             $seriesdata[]=$item['sum_number'];
         }
         unset($item);
         if(count($date)>$where['limit']){
             $zoom=$date[$where['limit']-5];
         }
        //使用积分趋势图
        $deductionlist=self::getModelTime($where,self::where('category','integral')->where('type','deduction')
            ->field(['FROM_UNIXTIME(add_time,"%Y-%m-%d") as _add_time','sum(number) as sum_number'])
            ->group('_add_time')->order('_add_time asc'))->select()->toArray();
         $deduction_date=[];
         $deduction_zoom='';
         $deduction_seriesdata=[];
         foreach ($deductionlist as $item){
             $deduction_date[]=$item['_add_time'];
             $deduction_seriesdata[]=$item['sum_number'];
         }
         if(count($deductionlist)>$where['limit']){
             $deduction_zoom=$deductionlist[$where['limit']-5];
         }
         return compact('date','seriesdata','zoom','deduction_date','deduction_zoom','deduction_seriesdata');
    }


    public static function getGroupList($where){
        $where['data']=$where['start_time'].' - '.$where['end_time'];
        $data=self::getGroupWhere($where)->page((int)$where['page'],(int)$where['limit'])
            ->field(['a.*','u.nickname','u.name','g.phone','g.share_name','g.share_uid'])->select();
        $data=count($data) ? $data->toArray() : [];
        $count=self::getGroupWhere($where)->count();
        foreach ($data as &$item){
            $item['spread_uid']=$item['link_id'];
            $item['spread_nickname']=User::where('uid',$item['link_id'])->value('nickname');
            $item['spread_phone']=Group::where('uid',$item['link_id'])->value('phone');
        }
        return compact('data','count');
    }

    public static function getGroupWhere($where){
        $model=self::getModelTime($where,self::where(['a.category'=>'now_money','a.pm'=>1,'a.status'=>1])
            ->alias('a')->join('__USER__ u','a.uid=u.uid','LEFT')->where('a.type','in',['rake_back','become_partner']),'a.add_time')->join('__GROUP__ g','g.uid=a.uid','LEFT');
        if($where['nickname']!='') $model=$model->where('g.user_name|u.name','LIKE',"%$where[nickname]%");
        return $model;
    }

    public static function setBadgeWhere($model,$where,$alias='',$like='',$key='add_time'){
        $where['data']=$where['start_time'].' - '.$where['end_time'];
        $alias && $alias.='.';
        $key=$alias ? $alias.$key : $key;
        $model=self::getModelTime($where,$model,$key);
        if($where['nickname']) $model=$model->where($like,$where['nickname']);
        return $model;
    }

    public static function getGroupBadge($where){
        $group_count=self::setBadgeWhere(Member::alias('a')->join('__USER__ u','u.uid=a.uid'),$where,'a','u.uid')->count();
        $truePrice=bcmul($group_count-1,500,2);
        $uids=self::setBadgeWhere(User::where(['a.status'=>1,'a.is_partner'=>1])->alias('a')->join('group g','a.uid=g.uid'),$where,'a','a.uid')->column('a.uid');
        $count=Group::where('share_uid','in',$uids)->group('share_uid')->count();
        $GroupCount=self::setBadgeWhere(new Group,$where,'','uid')->count();
        $freezing_amount=SystemConfigService::get('freezing_amount');
        $partner_money=SystemConfigService::get('partner_money');
        return [
            [
                'name'=>'总组数',
                'field'=>'个',
                'count'=>$group_count,
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'总单数',
                'field'=>'单',
                'count'=>$GroupCount,
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'总收入',
                'field'=>'元',
                'count'=>bcmul($GroupCount,$partner_money,2),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'总支出',
                'field'=>'元',
                'count'=>self::setBadgeWhere(UserExtract::where('a.status',1)->alias('a')->join('__USER__ u','u.uid=a.uid'),$where,'a','u.uid')->sum('a.extract_price'),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'实际提现佣金',
                'field'=>'元',
                'count'=>self::setBadgeWhere(UserExtract::where('a.status',1)->alias('a')
                    ->join('__USER__ u','a.uid=u.uid'),$where,'a','u.uid')->sum('a.extract_price'),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'未提现佣金',
                'field'=>'元',
                'count'=>self::setBadgeWhere(User::where('status',1),$where,'','uid')->sum('now_money'),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'冻结佣金',
                'field'=>'元',
                'count'=>bcmul($count,$freezing_amount,2),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
            [
                'name'=>'总盈利',
                'field'=>'元',
                'count'=>bcadd(bcmul($GroupCount,500,2),$truePrice,2),
                'background_color'=>'layui-bg-cyan',
                'col'=>2,
            ],
        ];
    }
}
