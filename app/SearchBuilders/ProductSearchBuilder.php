<?php
/**
 *
 * @author : 尤嘉兴
 * @version: 2020/6/28 17:40
 */

namespace App\SearchBuilders;

use App\Models\Category;

class ProductSearchBuilder
{
    protected $params = [
        'index' => 'products',
        'body' => [
            'query' => [
                'bool' => [
                    'filter' => [],
                    'must' => [],
                ]
            ]
        ]
    ];

    // 添加分页查询
    public function paginate($size, $page)
    {
        $this->params['size'] = $size;                  // 每次返回文档数量
        $this->params['from'] = ($page - 1) * $size;    // 偏移量
        return $this;
    }

    // 筛选上架状态的商品
    public function onSale()
    {
        $this->params['body']['query']['bool']['filter'][] = [
            'term' => [
                'on_sale' => true,
            ]
        ];
        return $this;
    }

    // 添加搜索词
    public function keywords($keywords)
    {
        $keywords = is_array($keywords) ? $keywords : [$keywords];
        $keywords = array_filter($keywords);

        foreach ($keywords as $keyword) {
            $this->params['body']['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $keyword,
                    "fields" => [
                        "title^3",
                        "long_title^2",
                        'category^2',
                        'description',
                        "skus_title",
                        "skus_description",
                        "properties_value",
                    ]
                ]
            ];
        }
        return $this;
    }

    // 商品类别筛选
    public function category(Category $category)
    {
        $boolParams = &$this->params['body']['query']['bool'];

        if ($category->is_directory) {
            $boolParams['filter'][] = [
                'prefix' => [
                    'category_path' => $category->path . $category->id . Category::PATH_DELIMITER
                ]
            ];
        } else {
            $boolParams['filter'][] = [
                'term' => [
                    'category_id' => $category->id
                ]
            ];
        }
        return $this;
    }

    public function propertyFilter($name, $value, $type = 'filter')
    {
        $this->params['body']['query']['bool'][$type][] = [
            'nested' => [
                'path' => 'properties',
                'query' => [
                    [
                        'term' => [
                            'properties.search_value' => $name . ':' . $value,
                        ]
                    ]
                ],
            ]
        ];

        return $this;
    }

    public function propertyAggregate()
    {
        $this->params['body']['aggs']['properties'] = [
            'nested' => [
                'path' => 'properties',
            ],
            'aggs' => [
                'properties' => [
                    'terms' => [
                        'field' => 'properties.name'
                    ],
                    'aggs' => [
                        'value' => [
                            'terms' => [
                                'field' => 'properties.value'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public function orderBy($field, $direction = "asc")
    {
        $this->params['body']['sort'][] = [
            $field => $direction
        ];
        return $this;
    }

    public function orderByDesc($field)
    {
        return $this->orderBy($field, 'desc');
    }

    public function minShouldMatch(int $count = 1)
    {
        $this->params['body']['query']['bool']['minimum_should_match'] = $count;
        return $this;
    }

    public function excludeProducts($ids)
    {
        $ids = is_array($ids) ? $ids : [$ids];
        foreach ($ids as $id) {
            $this->params['body']['query']['bool']['must_not'][] = [
                'term' => [
                    'id' => $id
                ]
            ];
        }
        return $this;
    }

    public function build()
    {
        return $this->params;
    }
}