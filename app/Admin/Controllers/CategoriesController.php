<?php

namespace App\Admin\Controllers;

use App\Models\Category;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoriesController extends AdminController
{
    /**
     * Title for current resource.
     *
     * @var string
     */
    protected $title = '商品类目';

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Category);

        $grid->column('id', __('Id'));
        $grid->column('name', '名称');
        $grid->column('level', '层级');
        $grid->column('is_directory', '是否目录')->replace([false => '否', true => '是']);
        $grid->column('path', '类目路径');

        // $grid->column('parent_id', __('Parent id'));
        // $grid->column('created_at', __('Created at'));
        // $grid->column('updated_at', __('Updated at'));

        $grid->actions(
            function (Grid\Displayers\Actions $actions) {
                $actions->disableView();
            }
        );

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $show = new Show(Category::findOrFail($id));

        $show->field('id', __('Id'));
        $show->field('name', __('Name'));
        $show->field('parent_id', __('Parent id'));
        $show->field('is_directory', __('Is directory'));
        $show->field('level', __('Level'));
        $show->field('path', __('Path'));
        $show->field('created_at', __('Created at'));
        $show->field('updated_at', __('Updated at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        $form = new Form(new Category);

        $form->text('name', '类目名称')->rules(['required']);

        // 仅创建时可选择是否目录
        $isDirectoryField = $form->switch('is_directory', '是否目录')->states(
            [
                'on' => ['value' => 1, 'text' => '是', 'color' => 'success'],
                'off' => ['value' => 0, 'text' => '否', 'color' => 'danger'],
            ]
        )->default(0)->rules(['required']);

        if ($form->isEditing()) {
            $isDirectoryField->readonly();

            $parentIdField = $form->display('parent.name', '父类目');
        } else {
            $parentIdField = $form->select('parent_id', '父类目');
            // $parentIdField->ajax('/admin/api/categories');
            $parentIdField->options(Category::query()->where('is_directory', 1)->pluck('name', 'id'));
        }

        $form->saving(
            function (Form $form) {
                $form->model()->updateByParent();
            }
        );

        return $form;
    }

    public function apiIndex(Request $request)
    {
        $query = $request->get('q');
        return Category::query()
            ->where('is_directory', 1)
            ->where('name', 'like', "%{$query}%")
            ->paginate(null, ['id', 'name as text']);
    }
}
