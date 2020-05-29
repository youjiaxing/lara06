<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/5/29 12:32
 */

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryService
{
    /**
     * @param null $parentId
     *
     * @return Collection
     */
    public function getCategoryTree($parentId = null)
    {
        $categories = Category::all();
        return $this->buildCategoryTree($parentId, $categories);
    }

    /**
     * @param            $parentId
     * @param Collection $categories
     *
     * @return Collection = [
     *     [
     *          'id' => '类目id',
     *          'name' => '类目名称',
     *          'children' => CategoryService::buildCategoryTree(),
     *     ]
     * ]
     */
    protected function buildCategoryTree($parentId, Collection $categories)
    {
        return $categories->where('parent_id', $parentId)
            ->map(
                function (Category $category) use ($categories) {
                    $node = [
                        'id' => $category->id,
                        'name' => $category->name
                    ];

                    if ($category->is_directory) {
                        $node['children'] = $this->buildCategoryTree($category->id, $categories);
                    }
                    return $node;
                }
            );
    }
}