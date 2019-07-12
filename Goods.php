<?php
namespace app\api\controller;
use think\Request;
use think\Db;
use think\Session;
use think\Cookie;
use think\File;
use think\Validate;
use app\classl\Category;
use app\common\user\Users;
use app\classl\Lucky;
class Goods extends Basevip{
    function __construct(){
        parent::__construct(); 
        $this->userinfo=Users::info();
    } 
    
    public function index(){
        echo 'error';
    }
    
    public function listall(){
 
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'提交错误']);
        }
        $page=input('page/d') ? input('page/d') :1;
        $field='B.*,A.title,A.pic,A.money as goodsmoney,A.destination,A.id as gid';
        $where=" A.status = 1 and B.status=1";
        $list=Db::name("goods")->alias('A')->join('goods_round B','A.id=B.goodsid')->where($where)->field($field)->page($page)->limit(10)->order('B.order_bfb desc')->select();
        $newlist=[];
        foreach($list as $k=>$v){
            $newlist[$k]['id']=$v['gid'];
            $newlist[$k]['title']=$v['title'];
            $newlist[$k]['goodsmoney']=$v['goodsmoney'];
            $newlist[$k]['beleft_total']=$v['beleft_total'];
            $newlist[$k]['destination']=$v['destination'];
            $newlist[$k]['total']=$v['total'];
            $newlist[$k]['round']=$v['round'];
            $newlist[$k]['money']=$v['money'];
            $newlist[$k]['time']=date('Y-m-d',$v['time']);
            $newlist[$k]['bfb']= floatval($v['order_bfb']);
            $newlist[$k]['pic']=_encrypt2($v['pic']);
        }

        if(!empty($newlist)){
            $this->ajax_return(['p'=>1,'t'=>'','arr'=>$newlist]);
        }elseif($page==1){
            $this->ajax_return(['p'=>6,'t'=>'没有了']);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'没有了']);
        }
    }
    
    public function buylist(){
        $userinfo=$this->userinfo;
        $id= _strintletter(input('param.id'),2);
        $rid= _strintletter(input('param.rid'),2);
        if(empty($id)){
           $this->ajax_return(['p'=>0,'t'=>'调用错误']); 
        }
        if(empty($rid)){
           $this->ajax_return(['p'=>0,'t'=>'调用错误']); 
        }
        
        
        $page=input('page/d') ? input('page/d') :1;
        $field='*';
        $goods_info=Db::name("goods")->where("id = {$id}")->field($field)->find();
        if(empty($goods_info)){
            $this->ajax_return(['p'=>0,'t'=>'商品不存在']); 
        }
        
        $goods_round_info=Db::name("goods_round")->where("goodsid = {$id} and round = {$rid}")->find();
        if(empty($goods_round_info)){
            $this->ajax_return(['p'=>0,'t'=>'商品期数不存在']);
        }
        
        $list=Db::name("goods_lotter_code")->alias('A')->join('member B','A.cuid=B.cuid')->field('A.*,B.username,B.img')->where("A.goods_round_id = {$goods_round_info['id']}")->limit(20)->page($page)->order("A.time desc,A.id desc")->select();
       
        $newlist=[];
        foreach($list as $k=>$v){
            $newlist[$k]['luckycode']=$v['luckycode'];
            if($goods_round_info['status']==3){
                $newlist[$k]['award']=$v['award'];
            }else{
                $newlist[$k]['award']=0;
            }
            
            $newlist[$k]['time']=date('Y-m-d',$v['time']);
            $newlist[$k]['username']=$v['username'];
            $newlist[$k]['img']=imgthumb($v['img'],'100|100',1);
            $chatime=explode('.',$v['char_time']);
            $newlist[$k]['char_time']=date('Y-m-d H:i:s',$chatime[0]).'.'.$chatime[1];
        }
        if(!empty($newlist)){
            $this->ajax_return(['p'=>1,'t'=>'','arr'=>$newlist]);
        }elseif($page==1){
            $this->ajax_return(['p'=>6,'t'=>'没有了']);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'没有了']);
        }
    }
    
    //商品详情
    public function info(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id= _strintletter(input('id'),2);
        $rid= _strintletter(input('rid'),2);
        if(empty($id)){
           $this->ajax_return(['p'=>0,'t'=>'调用错误']); 
        }
        $where['status']=['eq',1];
        $where['id']=['eq',$id];
        $field=['A.*,B.body'];
        $order_info=Db::name("goods")->alias('A')->join('goods_editor B','A.id=B.bid','LEFT')->field($field)->where($where)->find();
        if(empty($order_info)){
            $this->ajax_return(['p'=>0,'t'=>'提取信息错误']); 
        }
        
        //期数检查
        if(!empty($rid)){
            if($order_info['round']>0 && $order_info['round']<$rid){
                $this->ajax_return(['p'=>0,'t'=>'期数错误']); 
            }
        }else{
            $rid=$order_info['round'];
        }
        $goods_morepic_select=Db::name("goods_morepic")->where("bid={$order_info['id']}")->field("thumb,pic")->select();
        $goods_round_select=Db::name("goods_round")->where("goodsid={$order_info['id']}")->field("id,round,total,lucky_time,beleft_total,order_bfb,luckycuid,money,luckyok,luckyallcodelen,lottery_time,status")->order("round desc")->select();
        
        $qspd=false;
        $thisgoods_round_info=[];
        
        foreach($goods_round_select as $k=>$v){            
            $goods_round_select[$k]['bs']=0;
            $goods_round_select[$k]['order_bfb']=floatval($goods_round_select[$k]['order_bfb']);
            if($v['round']==$rid){
                $qspd=true;
                $goods_round_select[$k]['bs']=1;
                $thisgoods_round_info=$v;
                if($v['status']!==3){
                    $goods_round_select[$k]['luckyok']='';
                    $goods_round_select[$k]['luckyallcodelen']='';
                    $goods_round_select[$k]['luckycuid']='';
                    $goods_round_select[$k]['luckycodejson']=''; 
                    $goods_round_select[$k]['lucky_time']=''; 
                }
            }else{
                $goods_round_select[$k]['luckyok']='';
                $goods_round_select[$k]['luckyallcodelen']='';
                $goods_round_select[$k]['luckycuid']='';
                $goods_round_select[$k]['luckycodejson']=''; 
                $goods_round_select[$k]['lucky_time']=''; 
            }
        }
        
        if(empty($qspd)){
            $grskey=count($goods_round_select)-1;
            $goods_round_select[$grskey]['bs']=1;
            $thisgoods_round_info=$goods_round_select[$grskey];
        }
        $thisgoods_round_info['order_bfb']=floatval($thisgoods_round_info['order_bfb']);
        $thisgoods_round_info['lucky_time']=date('Y-m-d H:i:s',$thisgoods_round_info['lucky_time']);
        $thisgoods_round_info['lottery_time']=date('Y-m-d H:i:s',$thisgoods_round_info['lottery_time']);
        $round_status=$thisgoods_round_info['status'];
        if($round_status==3){
            
            $zjuser=Db::name("member")->where("cuid = {$thisgoods_round_info['luckycuid']}")->field('img,username,mobile')->find();
            $thisgoods_round_info['userinfo']=[
                'img'=>imgthumb($zjuser['img'],'100',1),
                'username'=>$zjuser['username'],
                'mobile'=>mobile_hide($zjuser['mobile'])
            ];
        }else{
           $thisgoods_round_info['luckyok']='';
            $thisgoods_round_info['luckyallcodelen']='';
            $thisgoods_round_info['luckycuid']='';
            $thisgoods_round_info['luckycodejson']='';
            $thisgoods_round_info['lucky_time']='';
        }

        $neworder='';
        $neworder=Db::name("goods_lotter_code")
                    ->alias('A')->join('member B','A.cuid=B.cuid','LEFT')
                    ->where("A.goods_round_id = {$thisgoods_round_info['id']}")
                    ->field("A.luckycode,A.char_time,B.img,B.username")
                    ->limit(5)->order("A.time desc,A.id desc")->select();
        foreach($neworder as $k=>$v){
            $chatime=explode('.',$v['char_time']);
            $neworder[$k]['char_time']=date('Y-m-d H:i:s',$chatime[0]).'.'.$chatime[1];
            $neworder[$k]['img']=imgthumb($v['img'],'100|100',1);
        }
        $neworder=empty($neworder) ? '':$neworder;
        
        $newinfo=[
            'title'=>$order_info['title'],
            'point'=>$order_info['point'],
            'money'=>$order_info['money'],
            'total'=>$order_info['total'],
            'body'=>$order_info['body'],
            'beleft_round'=>$order_info['beleft_round'],
            'round'=>$order_info['total_round'],
            'thisround'=>$thisgoods_round_info,
            'morepic'=>$goods_morepic_select,
            'goods_round'=>$goods_round_select, 
            'neworder'=>$neworder,
            'lottery_time'=>date('Y/m/d H:i:s',strtotime($thisgoods_round_info['lottery_time']))
        ];
        if(!empty($newinfo)){
            $this->ajax_return(['p'=>1,'t'=>'','arr'=>$newinfo]);
        }elseif($page==1){
            $this->ajax_return(['p'=>6,'t'=>'没有了']);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'没有了']);
        }
    }
    
    //幸运购下单
    public function orderlottery(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id=_strintletter(input('id'),2);
        $round=_strintletter(input('round'),2);
        $number=_strintletter(input('number'),2);
        if(empty($id) || empty($round) || empty($number)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $goods_info=Db::name("goods")->where("id = {$id}")->find();
        if(empty($goods_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        if($goods_info['status']==2){
            $this->ajax_return(['p'=>0,'t'=>'商品已被抢空']);
        }
        if(empty($goods_info['status'])){
            $this->ajax_return(['p'=>0,'t'=>'商品暂停购买']);
        }
        $goods_round_info=Db::name("goods_round")->where("goodsid = {$id} and round = {$round}")->find();
        if(empty($goods_round_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        if(empty($goods_round_info['status'])){
            $this->ajax_return(['p'=>0,'t'=>'众筹失败']);
        }
        if($goods_round_info['status']==2){
            $this->ajax_return(['p'=>0,'t'=>'正在开奖中']);
        }
        if($goods_round_info['status']==3){
            $this->ajax_return(['p'=>0,'t'=>'已开奖']);
        }
        if(empty($goods_round_info['beleft_total'])){
            $this->ajax_return(['p'=>0,'t'=>'已被抢空']);
        }
        if($goods_round_info['beleft_total']<$number){
            $this->ajax_return(['p'=>0,'t'=>"还剩余{$goods_round_info['beleft_total']}份,是否继续购买"]);
        }
        $lenmoney=($number*$goods_round_info['money']);//金额
        
        Db::startTrans();
        $goods_info_lock=Db::name("goods")->where("id = {$id}")->lock(true)->find();
        $goods_round_info_lock=Db::name("goods_round")->where("id = {$goods_round_info['id']}")->lock(true)->find();
        if($goods_round_info_lock['beleft_total']<$number){
            Db::rollback();
            $this->ajax_return(['p'=>0,'t'=>"还剩余{$goods_round_info['beleft_total']}份,是否继续购买"]);
        }
        
        $goods_lottery_order_insert_id=Db::name("goods_lottery_order")->insertGetId([
            'cuid'=>$userinfo['cuid'],
            'goods_round_id'=>$goods_round_info_lock['id'],
            'number'=>$number,
            'money'=>$lenmoney,
            'time'=>time(),
        ]);
        
        $ordernumber=_ordernumber(4,$goods_lottery_order_insert_id);
        
        $goods_lottery_order_insert_ordernumberup=Db::name("goods_lottery_order")->where("id = {$goods_lottery_order_insert_id}")->update([
            'ordernumber'=>$ordernumber,
        ]);
        
        $order_insert=Db::name("order")->insert([
            'ordernumber'=>$ordernumber,
            'cuid'=>$userinfo['cuid'],
            'type'=>4,
            'money'=>$lenmoney,
            'add_time'=>time(),
            'add_date'=>date('Ymd'),
            'status'=>0,
            'ip'=>getip(),
        ]);
        
        $checksql=false;
        if($goods_lottery_order_insert_id && $ordernumber && $goods_lottery_order_insert_ordernumberup && $order_insert){
            Db::commit();
            $checksql=true;
        }else{
            Db::rollback();
        }
        
        if($checksql){
            $this->ajax_return(['p'=>1,'t'=>'生成订单成功','ordernumber'=>$ordernumber]);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'生成订单失败']);
        }
    }
    
    //倒计时公布
    public function showlottery(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id=_strintletter(input('id'),2);
        if(empty($id)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $luckyclass=new Lucky;
        $kjret=$luckyclass->show_lucky($id);
        $this->ajax_return($kjret);
        
    }
    
    //计算公式
    public function formula(){
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id=_strintletter(input('id'),2);
        $rid=_strintletter(input('rid'),2);
        if(empty($id)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        if(empty($rid)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        
        $goods_round_info=Db::name("goods_round")->where("goodsid = {$id} and round = {$rid}")->find();
        if(empty($goods_round_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        
        if($goods_round_info['status']!==3){
            $this->ajax_return(['p'=>0,'t'=>'还没开奖的期数']);
        }
        
        $luckycodearr=json_decode($goods_round_info['luckycodejson'],true);
        $counttime=0;
        foreach($luckycodearr as $k=>$v){
            $counttime+=$v['char_time'];
            $char_time= explode('.',$v['char_time']);
            $luckycodearr[$k]['time']=date('Y-m-d H:i:s',$char_time[0]).'.'.$char_time[1];
            $luckycodearr[$k]['img']=imgthumb($v['img'],"100",1);
        }
        $counttime= explode('.',$counttime);
        $counttime=$counttime[0];
        $arr=[
            'luckyok'=>$goods_round_info['luckyok'],
            'total'=>$goods_round_info['total'],
            'counttime'=>$counttime,
            'out_time'=>$goods_round_info['out_time'],
            'luckycodejson'=>$luckycodearr,
        ];
        
        $this->ajax_return(['p'=>1,'arr'=>$arr]);
    }
    
    public function mylucky(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        
        $status=_strintletter(input('param.status'),2);
        if(empty($status)){
            $status=1;
        }
        $statusarr=[1,2,3,4];
        if(!in_array($status,$statusarr)){
           $status=1; 
        }
        
        $page=input('page/d') ? input('page/d') :1;
        $field=['A.id','A.time','A.number','D.title','D.point','D.pic','C.goodsid,C.round,A.award'];
        $field= array_merge($field,['C.status as goods_round_status','C.luckycuid']);
        $field= array_merge($field,['B.ordernumber','B.money','B.status as order_status','B.paybackstep']);
        if($status==1){
            $where="A.cuid = {$userinfo['cuid']}";
        }elseif($status==2){
            $where=" A.cuid = {$userinfo['cuid']} and B.status=0";
        }elseif($status==3){
            $where="A.cuid = {$userinfo['cuid']} and B.status=1";
        }elseif($status==4){
            $where="A.cuid = {$userinfo['cuid']} and A.award=1";
        }

        
        $list=Db::name("goods_lottery_order")
                ->alias('A')
                ->join('order B','A.ordernumber=B.ordernumber','LEFT')
                ->join('goods_round C','A.goods_round_id=C.id','LEFT')
                ->join('goods D','C.goodsid=D.id','LEFT')
                ->where($where)->field($field)->order('A.time desc')->page($page)->limit(10)->select();

        $newlist=[];
        $luckyconfig=\think\config::load(APP_PATH.'config/lucky.php','lucky');
        $goods_round_status=$luckyconfig['goods_round_status'];
        foreach($list as $k=>$v){
            $newlist[$k]['id']=$v['id'];
            $newlist[$k]['title']=$v['title'];
            $newlist[$k]['point']=$v['point'];
            $newlist[$k]['goodsid']=$v['goodsid'];
            $newlist[$k]['round']=$v['round'];
            $newlist[$k]['award']=$v['award'];
            $newlist[$k]['pic']= _encrypt2($v['pic']);
            $newlist[$k]['goods_round_status']=$v['goods_round_status'];
            $newlist[$k]['goods_round_statustxt']=$goods_round_status[$v['goods_round_status']];
            $newlist[$k]['luckycuid']=$v['luckycuid'];
            $newlist[$k]['ordernumber']=$v['ordernumber'];
            $newlist[$k]['money']= floatval($v['money']);
            $newlist[$k]['order_status']=$v['order_status'];
            $newlist[$k]['number']=$v['number'];
            $newlist[$k]['time']=date('Y-m-d H:i:s',$v['time']);
        }
        if(!empty($newlist)){
            $this->ajax_return(['p'=>1,'t'=>'','arr'=>$newlist]);
        }elseif($page==1){
            $this->ajax_return(['p'=>6,'t'=>'没有了']);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'没有了']);
        }
    }
    
    public function myluckyinfo(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id=_strintletter(input('param.id'),2);
        
        if(empty($id)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }

        
        $field=['A.id','A.time','A.pay_time','A.number','D.title','D.point','D.pic','C.goodsid,C.round,A.award'];
        $field= array_merge($field,['C.status as goods_round_status','C.luckycuid','C.luckyok','C.total','C.beleft_total']);
        $field= array_merge($field,['B.ordernumber','B.money','B.status as order_status','B.paybackstep']);
        
        $where="A.id = {$id} and A.cuid = {$userinfo['cuid']}";
        $goods_lottery_order_info=Db::name("goods_lottery_order")
                ->alias('A')
                ->join('order B','A.ordernumber=B.ordernumber','LEFT')
                ->join('goods_round C','A.goods_round_id=C.id','LEFT')
                ->join('goods D','C.goodsid=D.id','LEFT')
                ->where($where)->field($field)->find();
        if(empty($goods_lottery_order_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $luckyconfig=\think\config::load(APP_PATH.'config/lucky.php','lucky');
        $goods_round_status=$luckyconfig['goods_round_status'];
        $info['id']=$goods_lottery_order_info['id'];
        $info['title']=$goods_lottery_order_info['title'];
        $info['point']=$goods_lottery_order_info['point'];
        $info['paybackstep']=$goods_lottery_order_info['paybackstep'];
        $info['total']=$goods_lottery_order_info['total'];
        $info['beleft_total']=$goods_lottery_order_info['beleft_total'];
        $info['goodsid']=$goods_lottery_order_info['goodsid'];
        $info['round']=$goods_lottery_order_info['round'];
        $info['award']=$goods_lottery_order_info['award'];
        $info['pic']= _encrypt2($goods_lottery_order_info['pic']);
        $info['goods_round_status']=$goods_lottery_order_info['goods_round_status'];
        $info['goods_round_statustxt']=$goods_round_status[$goods_lottery_order_info['goods_round_status']];
        //$info['luckycuid']=$goods_lottery_order_info['luckycuid'];
        $info['ordernumber']=$goods_lottery_order_info['ordernumber'];
        $info['money']= floatval($goods_lottery_order_info['money']);
        $info['order_status']=$goods_lottery_order_info['order_status'];
        $info['number']=$goods_lottery_order_info['number'];
        $info['time']=date('Y-m-d H:i:s',$goods_lottery_order_info['time']);
        $info['pay_time']=empty($goods_lottery_order_info['pay_time'])? '' : date('Y-m-d H:i:s',$goods_lottery_order_info['pay_time']);
        $info['luckyok']='';
        if($goods_lottery_order_info['goods_round_status']==3){
            $info['luckyok']=$goods_lottery_order_info['luckyok'];
        }
        
        $this->ajax_return(['p'=>1,'arr'=>$info]);
    }
    
    public function myluckycode(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id= _strintletter(input('param.id'),2);
        if(empty($id)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        
        $goods_lottery_order_info=Db::name("goods_lottery_order")->alias('A')->join('goods_round B','A.goods_round_id=B.id')->field('A.id,B.status,A.goods_round_id')->where("A.cuid = {$userinfo['cuid']} and A.id={$id}")->find();
        if(empty($goods_lottery_order_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        
        $page=input('page/d') ? input('page/d') :1;        
        $goods_lotter_code_list=Db::name("goods_lotter_code")->where("goods_lottery_order_id = {$goods_lottery_order_info['id']} and cuid={$userinfo['cuid']}")->field('luckycode,award')->order('time desc,id desc')->page($page)->limit(50)->select();
        $newarr=[];
        foreach($goods_lotter_code_list as $k=>$v){
            $newarr[$k]['luckycode']=$v['luckycode'];
            if($goods_lottery_order_info['status']==3){
                $newarr[$k]['award']=$v['award'];
            }else{
                 $newarr[$k]['award']=0;
            }
        }
        if(!empty($newarr)){
            $this->ajax_return(['p'=>1,'t'=>'','arr'=>$goods_lotter_code_list]);
        }elseif($page==1){
            $this->ajax_return(['p'=>6,'t'=>'没有了']);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'没有了']);
        }
    }
    
    
    //单买下单
    public function orderalone(){
        $userinfo=$this->userinfo;
        if(!request()->isPost()){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        $id=_strintletter(input('id'),2);
        $number=_strintletter(input('number'),2);
        $addressid=_strintletter(input('addressid'),2);
        if(empty($id)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        if(empty($number)){
            $this->ajax_return(['p'=>0,'t'=>'购买数量不能0']);
        }
        
        $goods_info=Db::name("goods")->where("id = {$id}")->find();
        
        if(empty($goods_info)){
            $this->ajax_return(['p'=>0,'t'=>'调用错误']);
        }
        if($goods_info['total_round']==$goods_info['beleft_round']){
            $this->ajax_return(['p'=>0,'t'=>'库存不足']);
        }
        if($goods_info['total_round']<=$goods_info['beleft_round']){
            $this->ajax_return(['p'=>0,'t'=>'库存不足']);
        }
        if($goods_info['beleft_round']<$number){
            $this->ajax_return(['p'=>0,'t'=>"库存不足,还剩余{$goods_info['beleft_round']}个"]);
        }
        
        if(empty($addressid)){
            $this->ajax_return(['p'=>0,'t'=>'请选择收货地址']);
        }
        
        $member_address=Db::name("member_address")->where("cuid = {$userinfo['cuid']} and id={$addressid}")->find();
        if(empty($member_address)){
            $this->ajax_return(['p'=>0,'t'=>'请选择收货地址']);
        }
        
        Db::startTrans();
        $goods_info_lock=Db::name("goods")->where("id = {$id}")->lock(true)->find();
        if($goods_info_lock['total_round']==$goods_info_lock['beleft_round']){
            Db::rollback();
            $this->ajax_return(['p'=>0,'t'=>'库存不足']);
        }
        if($goods_info_lock['total_round']<=$goods_info_lock['beleft_round']){
            Db::rollback();
            $this->ajax_return(['p'=>0,'t'=>'库存不足']);
        }
        if($goods_info_lock['beleft_round']<$number){
            Db::rollback();
            $this->ajax_return(['p'=>0,'t'=>"库存不足,还剩余{$goods_info_lock['beleft_round']}个"]);
        }
        $money=($number*$goods_info_lock['money']);
        $goods_alone_order_insert_id=Db::name("goods_alone_order")->insertGetId([
            'cuid'=>$userinfo['cuid'],
            'addresid'=>$addressid,
            'goodsid'=>$id,
            'number'=>$number,
            'money'=>$money,
            'time'=>time(),
        ]);
        $ordernumber= _ordernumber(5,$goods_alone_order_insert_id);
        $order_insert=Db::name("order")->insert([
            'ordernumber'=>$ordernumber,
            'cuid'=>$userinfo['cuid'],
            'type'=>5,
            'money'=>$money,
            'add_time'=>time(),
            'add_date'=>date('Ymd'),
            'status'=>0,
            'ip'=>getip(),
        ]);
        $goods_alone_order_update=Db::name("goods_alone_order")->where("id = {$goods_alone_order_insert_id}")->update([
            'ordernumber'=>$ordernumber
        ]);
//        $total_round=($goods_info_lock['beleft_round']-$number);
//        $goods_update=Db::name("goods")->where("id = {$id}")->update([
//            'total_round'=>$total_round
//        ]);
        $checksql=false;
        if($goods_alone_order_insert_id && $ordernumber && $order_insert && $goods_alone_order_update){
            Db::commit();
            $checksql=true;
        }else{
            Db::rollback();
        }
        if($checksql){
            $this->ajax_return(['p'=>1,'t'=>'生成订单成功','ordernumber'=>$ordernumber]);
        }else{
            $this->ajax_return(['p'=>0,'t'=>'生成订单失败']);
        }
        
    }
    
    
}
