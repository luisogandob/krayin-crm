<?php

namespace Webkul\Attribute\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Str;
use Webkul\Core\Eloquent\Repository;

class AttributeRepository extends Repository
{
    /**
     * AttributeOptionRepository object
     *
     * @var \Webkul\Attribute\Repositories\AttributeOptionRepository
     */
    protected $attributeOptionRepository;

    /**
     * Create a new repository instance.
     *
     * @param  \Webkul\Attribute\Repositories\AttributeOptionRepository  $attributeOptionRepository
     * @param  \Illuminate\Container\Container  $container
     * @return void
     */
    public function __construct(
        AttributeOptionRepository $attributeOptionRepository,
        Container $container
    )
    {
        $this->attributeOptionRepository = $attributeOptionRepository;

        parent::__construct($container);
    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Webkul\Attribute\Contracts\Attribute';
    }

    /**
     * @param  array  $data
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function create(array $data)
    {
        $options = isset($data['options']) ? $data['options'] : [];

        $attribute = $this->model->create($data);

        if (in_array($attribute->type, ['select', 'multiselect', 'checkbox']) && count($options)) {
            $sortOrder = 1;

            foreach ($options as $optionInputs) {
                $this->attributeOptionRepository->create(array_merge([
                    'attribute_id' => $attribute->id,
                    'sort_order'  => $sortOrder++,
                ], $optionInputs));
            }
        }

        return $attribute;
    }

    /**
     * @param  array  $data
     * @param  int $id
     * @param  string  $attribute
     * @return \Webkul\Attribute\Contracts\Attribute
     */
    public function update(array $data, $id, $attribute = "id")
    {
        $attribute = $this->find($id);

        $attribute->update($data);

        $previousOptionIds = $attribute->options()->pluck('id');

        if (in_array($attribute->type, ['select', 'multiselect', 'checkbox']) && isset($data['options'])) {
            $sortOrder = 1;

            foreach ($data['options'] as $optionId => $optionInputs) {
                if (Str::contains($optionId, 'option_')) {
                    $this->attributeOptionRepository->create(array_merge([
                        'attribute_id' => $attribute->id,
                        'sort_order'   => $sortOrder++,
                    ], $optionInputs));
                } else {
                    if (is_numeric($index = $previousOptionIds->search($optionId))) {
                        $previousOptionIds->forget($index);
                    }

                    $this->attributeOptionRepository->update(array_merge([
                        'sort_order' => $sortOrder++,
                    ], $optionInputs), $optionId);
                }
            }
        }

        foreach ($previousOptionIds as $optionId) {
            $this->attributeOptionRepository->delete($optionId);
        }

        return $attribute;
    }
}