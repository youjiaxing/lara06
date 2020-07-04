<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use App\Models\ProductSku;
use App\Services\SeckillService;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class SeckillProductsController extends BaseProductController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '秒杀商品管理';

    protected function customGrid(Grid $grid)
    {
        $grid->column('id', 'ID');
        $grid->column('title', '商品名称');
        $grid->column('on_sale', "已上架")->replace([true => '是', false => '否']);
        $grid->column('price', '价格');
        $grid->column('seckill.start_at', '秒杀开始时间');
        $grid->column('seckill.end_at', '秒杀结束时间');
        $grid->column('sold_count', '销量');
    }

    protected function customForm(Form $form)
    {
        $form->datetime('seckill.start_at', "秒杀开始时间")->rules(['required', 'date']);
        $form->datetime('seckill.end_at', '秒杀结束时间')->rules(['required', 'date']);

        $form->saved(function (Form $form) {
            /* @var Product $product */
            $product = $form->model()->refresh();   // 此处需重新加载, 保证是最新数据
            app(SeckillService::class)->cacheStock($product);
        });
    }

    protected function type()
    {
        return Product::TYPE_SECKILL;
    }
}
