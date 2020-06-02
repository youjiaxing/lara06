<?php

namespace App\Admin\Controllers;

use App\Models\Product;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;

class CrowdfundingProductsController extends BaseProductController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '众筹商品管理';

    protected function customGrid(Grid $grid)
    {
        $grid->column('id', 'ID');
        $grid->column('title', '商品名称');
        $grid->column('on_sale', "已上架")->replace([true => '是', false => '否']);
        $grid->column('price', '价格');
        $grid->column('crowdfunding.target_amount', '目标金额');
        $grid->column('crowdfunding.end_at', '结束时间');
        $grid->column('crowdfunding.total_amount', '目前金额');
        $grid->column('crowdfunding.percent', '进度')->display(
            function ($percent) {
                return $percent . "%";
            }
        );
        $grid->column('crowdfunding.status_str', '状态');
    }

    protected function customForm(Form $form)
    {
        $form->currency('crowdfunding.target_amount', "众筹目标金额")->symbol('￥')->rules(['required', 'numeric', 'min:0.01']);
        $form->datetime('crowdfunding.end_at', '众筹截至时间')->rules(['required', 'date']);
    }

    protected function type()
    {
        return Product::TYPE_CROWDFUNDING;
    }
}
