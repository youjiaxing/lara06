<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Category
 * @package App\Models
 * @property int             $id
 * @property string          $name
 * @property int             $parent_id
 * @property bool            $is_directory 商品只能归属于叶子节点类目
 * @property int             $level
 * @property string          $path         最顶级节点的 path 是 '-', 次级的是 '-1-' (假设该次级节点的父节点 id 是 1)
 * @property Category        $parent
 *
 * @property-read int[]      $path_ids     所有祖先类目的 ID 只
 * @property-read Collection $ancestors    所有祖先类目
 * @property-read string     $full_name    以 - 为分隔的所有祖先类目名称以及当前类目的名称
 */
class Category extends Model
{
    const PATH_DELIMITER = '-';

    protected $fillable = [
        'name',
        'is_directory',
        'level',
        'path',
    ];

    protected $casts = [
        'is_directory' => 'bool',
    ];

    protected static function boot()
    {
        parent::boot();

        // 方便在创建时不指定 level 和 path
        parent::creating(
            function (Category $category) {
                if (!is_null($category->parent_id)) {
                    $parent = $category->parent;
                    $category->level = $parent->level + 1;
                    $category->path = $parent->path . $parent->id . self::PATH_DELIMITER;
                } else {
                    $category->level = 1;
                    $category->path = self::PATH_DELIMITER;
                }
            }
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * 定义一个访问器，获取所有祖先类目的 ID 值
     * @return int[]
     */
    public function getPathIdsAttribute()
    {
        return array_map(
            'intval',
            array_filter(explode(self::PATH_DELIMITER, trim($this->path, self::PATH_DELIMITER)))
        );
    }

    /**
     * 获取所有祖先类
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAncestorsAttribute()
    {
        return Category::query()->whereIn('id', $this->getPathIdsAttribute())->orderBy('level')->get();
    }

    /**
     * 定义一个访问器，获取以 - 为分隔的所有祖先类目名称以及当前类目的名称
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->ancestors->push($this)->pluck('name')->implode(' - ');
    }
}
