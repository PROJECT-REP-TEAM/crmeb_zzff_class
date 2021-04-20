{extend name="public/container"}
{block name="content"}
<div class="layui-fluid">
	<div class="layui-card">
	    <div class="layui-card-header">
            <div class="layui-btn-group">
                <button type="button" class="layui-btn layui-btn-normal layui-btn-sm" onclick="$eb.createModalFrame('添加数据组','{:Url('create')}')"><i class="layui-icon">&#xe608;</i>添加数据组</button>
            </div>
        </div>
		<div class="layui-card-body">
			<div class="ibox-content">
				<div class="row">
					<div class="col-sm-8 m-b-xs">
						<?php /*  <form action="" class="form-inline">
							  <i class="fa fa-search" style="margin-right: 10px;"></i>
							  <select name="is_show" aria-controls="editable" class="form-control input-sm">
								  <option value="">是否显示</option>
								  <option value="1" {eq name="params.is_show" value="1"}selected="selected"{/eq}>显示</option>
								  <option value="0" {eq name="params.is_show" value="0"}selected="selected"{/eq}>不显示</option>
							  </select>
							  <select name="access" aria-controls="editable" class="form-control input-sm">
								  <option value="">子管理员是否可用</option>
								  <option value="1" {eq name="params.access" value="1"}selected="selected"{/eq}>可用</option>
								  <option value="0" {eq name="params.access" value="0"}selected="selected"{/eq}>不可用</option>
							  </select>
						  <div class="input-group">
							  <input type="text" name="keyword" value="{$params.keyword}" placeholder="请输入关键词" class="input-sm form-control"> <span class="input-group-btn">
									  <button type="submit" class="btn btn-sm btn-primary"> 搜索</button> </span>
						  </div>
						  </form>  */ ?>
					</div>

				</div>
				<div class="table-responsive">
					<table class="table table-striped  table-bordered">
						<thead>
						<tr>
							<th class="text-center" style="width:60px;">编号</th>
							<th class="text-center">KEY</th>
							<th class="text-center">数据组名称</th>
							<th class="text-center">简介</th>
							<th class="text-center">操作</th>
						</tr>
						</thead>
						<tbody class="">
						{volist name="list" id="vo"}
						<tr>
							<td class="text-center">
								{$vo.id}
							</td>
							<td class="text-center">
								{$vo.config_name}
							</td>
							<td class="text-center">
								{$vo.name}
							</td>
							<td class="text-center">
								{$vo.info}
							</td>
							<td class="text-center">
								<a class="layui-btn layui-btn-normal layui-btn-xs" href="{:Url('setting.systemGroupData/index',array('gid'=>$vo['id']))}"><i class="layui-icon">&#xe60a;</i>数据列表</a>
								<button class="layui-btn layui-btn-normal layui-btn-xs"  onclick="$eb.createModalFrame('编辑','{:Url('edit',array('id'=>$vo['id']))}')" ><i class="layui-icon">&#xe642;</i>编辑</button>
								<button class="layui-btn layui-btn-danger layui-btn-xs" data-url="{:Url('delete',array('id'=>$vo['id']))}" type="button"><i class="layui-icon">&#xe640;</i>删除</button>
							</td>
						</tr>
						{/volist}
						</tbody>
					</table>
				</div>
				{include file="public/inner_page"}
			</div>
		</div>
	</div>
</div>
{/block}
{block name="script"}
<script>
    $('.layui-btn-danger').on('click',function(){
        window.t = $(this);
        var _this = $(this),url =_this.data('url');
        $eb.$swal('delete',function(){
            $eb.axios.get(url).then(function(res){
                if(res.status == 200 && res.data.code == 200) {
                    $eb.$swal('success',res.data.msg);
                    _this.parents('tr').remove();
                }else
                    return Promise.reject(res.data.msg || '删除失败')
            }).catch(function(err){
                $eb.$swal('error',err);
            });
        })
    });
</script>
{/block}
