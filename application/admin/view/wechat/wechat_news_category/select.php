{extend name="public/container"}
{block name="head_top"}

{/block}
{block name="content"}
<link href="{__ADMIN_PATH}module/wechat/news_category/css/style.css" type="text/css" rel="stylesheet">
<div class="row">
    <div class="col-sm-12">
        <div class="ibox">
            <div class="ibox-title">
                <div class="row">
                    <div class="col-sm-8 m-b-xs">
                        <form action="" class="form-inline">
                            <i class="fa fa-search" style="margin-right: 10px;"></i>
                            <div class="input-group">
                                <input type="text" name="cate_name" value="{$where.cate_name}" placeholder="请输入关键词" class="input-sm form-control"> <span class="input-group-btn">
                                    <button type="submit" class="btn btn-sm btn-primary"> 搜索</button> </span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="ibox-content">
                <?php
                $new_list = array();
                $new_list = $list->toArray();
                $new_list = $new_list['data'];
                foreach ($new_list as $k=>$v){
                    $new_list[$k]['new'] = $v['new']->toArray();
                }
                ?>
                <div id="news_box">
                {volist name="list" id="vo"}
                    <div class="news_item category_one" style="float: left;cursor: pointer" data-id="{$vo.id}">
                        <div class="title"><span>图文名称：{$vo.cate_name}</span></div>
                    {volist name="$vo['new']" id="vvo" key="k"}
                        {if condition="$k eq 1"}
                        <div class="news_articel_item" style="background-image:url({$vvo.image_input})">
                            <p>{$vvo.title}</p>
                        </div>
                        <div class="hr-line-dashed"></div>
                        {else/}
                        <div class="news_articel_item other">
                            <div class="right-text">{$vvo.title}</div>
                            <div class="left-image" style="background-image:url({$vvo.image_input});">
                            </div>
                        </div>
                        <div class="hr-line-dashed"></div>
                        {/if}
                    {/volist}
                    </div>
                {/volist}
                </div>
            </div>
        </div>
    </div>
</div>
<div style="margin-left: 10px">
    {include file="public/inner_page"}
</div>
<?php $new_json = json_encode($new_list)?>
{/block}
{block name="script"}
<script>
    $('.category_one').on('click',function (e) {
        var callback = "{$callback}";
        var id = $(this).data('id');
        var _new = {$new_json};
        $.each(_new,function (key,value) {
            if(value['id'] == id){
                parent[callback](_new[key])
            }
        })

    })
</script>
{/block}