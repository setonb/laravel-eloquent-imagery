<?php

namespace ZiffMedia\Laravel\EloquentImagery\Nova;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemManager;
use Laravel\Nova\Fields\Field;
// use Laravel\Nova\Fields\Image as ImageField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use ZiffMedia\Laravel\EloquentImagery\Eloquent\Image;
use ZiffMedia\Laravel\EloquentImagery\Eloquent\ImageCollection;

class EloquentImageryField extends Field
{
    public $component = 'eloquent-imagery';

    public $showOnIndex = false;

    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            $value = json_decode($request[$requestAttribute], true);

            /** @var Image $image */
            $fieldAttribute = $model->{$attribute};

            if ($fieldAttribute instanceof ImageCollection) {

                $existingImages = $fieldAttribute->exchangeArray([]);

                $existingImages = collect($existingImages)->mapWithKeys(function ($image) {
                    return [$image->path => $image];
                });

                $newImages = collect($value)->map(function ($imageData, $index) use ($fieldAttribute, &$existingImages) {
                    if ($imageData['path']) {
                        $image = $existingImages[$imageData['path']];
                        unset($existingImages[$imageData['path']]);
                    } else {
                        $image = $fieldAttribute->createImage();
                        $image->setData($imageData['fileData']);
                    }

                    $image->metadata->exchangeArray(array_merge($image->metadata->getArrayCopy(), $imageData['metadata']));

                    $fieldAttribute[$index] = $image;
                });

                $currentIndex = $newImages->count();

                foreach ($existingImages->values() as $deletedIndex => $existingImage) {
                    $fieldAttribute[$currentIndex + $deletedIndex] = $existingImage;
                    unset($fieldAttribute[$currentIndex + $deletedIndex]); // wow, what a weird api
                }
            } else {
                if ($value === null) {
                    $image->remove();
                } else {
                    $this->updateImage($fieldAttribute, $value['fileData'] ?? null, $value['metadata']);
                }
            }
        }
    }

    protected function updateImage(Image $image, $imageData, $imageMetadata)
    {
        if ($imageData) {
            $image->setData($imageData);
        }

        $image->setMetadata($imageMetadata);
    }

    public function fillUsing($fillCallback)
    {
        return parent::fillUsing($fillCallback); // TODO: Change the autogenerated stub
    }

    public function jsonSerialize()
    {
        if ($this->value instanceof ImageCollection) {
            $isCollection = true;

            $value = [
                'autoinc' => $this->value->autoinc,
                'images'  => []
            ];

            foreach ($this->value as $image) {
                $value['images'][] = [
                    'previewUrl' => $image->url('v' . $image->timestamp),
                    'path' => $image->path,
                    'metadata' => $image->metadata
                ];
            }
        } else {
            $isCollection = false;

            if ($this->value->exists()) {
                $value = [
                    'previewUrl' => $this->value->url('v' . $this->value->timestamp),
                    'path' => $this->value->path,
                    'metadata' => $this->value->metadata
                ];
            } else {
                $value = null;
            }
        }

        return array_merge([
            'component' => $this->component(),
            'prefixComponent' => true,
            'indexName' => $this->name,
            'name' => $this->name,
            'attribute' => $this->attribute,
            'value' => $value,
            'panel' => $this->panel,
            'sortable' => $this->sortable,
            'nullable' => $this->nullable,
            'readonly' => $this->isReadonly(app(NovaRequest::class)),
            'textAlign' => $this->textAlign,
            'isCollection' => $isCollection
        ], $this->meta());
    }

    // /** @var \Illuminate\Contracts\Filesystem\Filesystem */
    // protected static $eloquentImageryFilesystem;

    // protected $previewUrlModifiers = null;
    // protected $thumbnailUrlModifiers = null;
    //
    // public function __construct($name, $attribute = null, $disk = 'public', $storageCallback = null)
    // {
    //     if (!self::$eloquentImageryFilesystem) {
    //         self::$eloquentImageryFilesystem = app(FilesystemManager::class)
    //             ->disk(config('eloquent-imagery.filesystem', config('filesystems.default')));
    //     }
    //
    //     parent::__construct($name, $attribute, $disk, $storageCallback);
    //
    //     $this->store(function (NovaRequest $request, Model $model) {
    //         if ($request->hasFile('image')) {
    //             $model->{$this->attribute}->fromRequest($request);
    //         }
    //     });
    //
    //     $this->preview(function (Image $eloquentImageryImage) {
    //         if (!$eloquentImageryImage->exists()) {
    //             return null;
    //         }
    //
    //         return $eloquentImageryImage->url($this->previewUrlModifiers);
    //     });
    //
    //     $this->thumbnail(function (Image $eloquentImageryImage) {
    //         if (!$eloquentImageryImage->exists()) {
    //             return null;
    //         }
    //
    //         return $eloquentImageryImage->url($this->thumbnailUrlModifiers);
    //     });
    //
    //     $this->delete(function (NovaRequest $request, Model $model) {
    //         /** @var Image $eloquentImageryImage */
    //         $eloquentImageryImage = $model->{$this->attribute};
    //
    //         if (!$eloquentImageryImage->exists()) {
    //             return null;
    //         }
    //
    //         $eloquentImageryImage->remove();
    //     });
    //
    //     $this->download(function (NovaRequest $request, Model $model) {
    //         /** @var Image $eloquentImageryImage */
    //         $eloquentImageryImage = $model->{$this->attribute};
    //
    //         if (!$eloquentImageryImage->exists()) {
    //             return null;
    //         }
    //
    //         $thing = self::$eloquentImageryFilesystem->download($eloquentImageryImage->getStateProperties()['path']);
    //         return $thing;
    //     });
    // }
    //
    // /**
    //  * @param $previewUrlModifiers
    //  * @return $this
    //  */
    // public function previewUrlModifiers($previewUrlModifiers)
    // {
    //     $this->previewUrlModifiers = $previewUrlModifiers;
    //
    //     return $this;
    // }
    //
    // /**
    //  * @param $thumbnailUrlModifiers
    //  * @return $this
    //  */
    // public function thumbnailUrlModifiers($thumbnailUrlModifiers)
    // {
    //     $this->thumbnailUrlModifiers = $thumbnailUrlModifiers;
    //
    //     return $this;
    // }
}
